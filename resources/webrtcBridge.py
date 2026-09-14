import sys
import os
import json
import time
import uuid
import base64
import argparse
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs

import websocket as ws_client

# Bridge HTTP <-> go2rtc/api/ws, localhost uniquement (jamais exposé hors de
# cette machine - seul Go2rtc.class.php l'appelle, en local). Permet à l'app
# de parler à go2rtc/api/ws via de simples appels HTTP courts, sans dépendre
# d'un canal WebSocket persistant côté app - la variante WebSocket
# bout-en-bout a été abandonnée : elle ne fonctionne que pour les
# utilisateurs ayant activé useWs, minoritaires, alors que le HTTP
# fonctionne pour tous, avec ou sans reverse proxy personnalisé).
#
# Générique : sert aussi bien le signaling WebRTC (offer/candidate/answer,
# JSON uniquement) que le flux vidéo MSE (un message JSON initial puis des
# trames BINAIRES en continu, cf. www/video-rtc.js de go2rtc) - voir
# apiHelper::cameraStreamOpen et consorts. Les trames binaires sont
# encodées en base64 pour transiter dans les réponses JSON du /poll.
#
# Une "session" = une connexion WS ouverte vers go2rtc. Le client (app) crée
# la session avec un premier message (POST /open), peut en envoyer d'autres
# au fil de l'eau (POST /send - candidats ICE pour webrtc, rien pour mse),
# et récupère tout ce que go2rtc renvoie en pollant (GET /poll).
#
# /poll est en long-polling plutôt qu'en aller-retour instantané : sans
# donnée en attente, la requête reste ouverte jusqu'à POLL_WAIT_TIMEOUT au
# lieu de répondre "rien" immédiatement - évite de bombarder l'API JC (donc
# tout le pipeline PHP) d'une requête toutes les 300ms en continu tant qu'une
# caméra est affichée, ce qui pouvait peser sur une petite installation
# Jeedom. Une fois des données disponibles, un court délai supplémentaire
# (POLL_BATCH_WINDOW) laisse le temps d'en accumuler plusieurs avant de
# répondre, pour grouper les fragments plutôt qu'en renvoyer un par requête.
# ThreadingHTTPServer est nécessaire ici : plusieurs sessions (plusieurs
# caméras affichées en même temps) doivent pouvoir chacune avoir une requête
# /poll bloquée en parallèle sans se bloquer les unes les autres.
SESSION_TIMEOUT = 30  # secondes d'inactivité (dernier poll) avant nettoyage
POLL_WAIT_TIMEOUT = 5.0  # attente max si aucune donnée n'est encore disponible
# Le délai de regroupement (avant de répondre à /poll) démarre bas puis monte
# progressivement jusqu'au régime de croisière sur BATCH_RAMP_SECONDS -
# affichage rapide dès l'ouverture (peu de regroupement, donc peu de retard),
# puis de moins en moins de requêtes une fois le flux établi.
POLL_BATCH_WINDOW_START = 0.05
POLL_BATCH_WINDOW_STEADY = 1.5
BATCH_RAMP_SECONDS = 3.0

GO2RTC_PORT = 1984

sessions = {}
sessions_lock = threading.Lock()


class Session:
    def __init__(self, ws):
        self.ws = ws
        self.messages = []
        self.msg_lock = threading.Lock()
        self.new_data = threading.Event()
        self.created_ts = time.time()
        self.last_seen = time.time()

    def current_batch_window(self):
        elapsed = time.time() - self.created_ts
        if elapsed >= BATCH_RAMP_SECONDS:
            return POLL_BATCH_WINDOW_STEADY
        frac = elapsed / BATCH_RAMP_SECONDS
        return POLL_BATCH_WINDOW_START + frac * (POLL_BATCH_WINDOW_STEADY - POLL_BATCH_WINDOW_START)

    def push(self, msg):
        with self.msg_lock:
            self.messages.append(msg)
        self.new_data.set()

    def pop_all(self):
        with self.msg_lock:
            msgs = self.messages
            self.messages = []
            self.new_data.clear()
        self.last_seen = time.time()
        return msgs


def close_session(session_id):
    with sessions_lock:
        session = sessions.pop(session_id, None)
    if session:
        try:
            session.ws.close()
        except Exception:
            pass


def reader_loop(session_id, session):
    try:
        while True:
            raw = session.ws.recv()
            if raw is None:
                break
            if isinstance(raw, (bytes, bytearray)):
                if len(raw) == 0:
                    break
                session.push({'kind': 'binary', 'data': base64.b64encode(raw).decode('ascii')})
            else:
                if raw == '':
                    break
                try:
                    parsed = json.loads(raw)
                except Exception:
                    continue
                session.push({'kind': 'json', 'data': parsed})
    except Exception:
        pass
    finally:
        close_session(session_id)


def reaper_loop():
    while True:
        time.sleep(10)
        now = time.time()
        with sessions_lock:
            stale = [sid for sid, s in sessions.items() if now - s.last_seen > SESSION_TIMEOUT]
        for sid in stale:
            close_session(sid)


class Handler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        pass

    def _json(self, code, payload):
        body = json.dumps(payload).encode('utf-8')
        self.send_response(code)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _body(self):
        try:
            length = int(self.headers.get('Content-Length', 0))
        except (TypeError, ValueError):
            length = 0
        if length <= 0:
            return {}
        try:
            return json.loads(self.rfile.read(length))
        except Exception:
            return {}

    def do_POST(self):
        parsed = urlparse(self.path)
        qs = parse_qs(parsed.query)

        if parsed.path == '/open':
            widget_id = (qs.get('widgetId') or [None])[0]
            message = self._body().get('message')
            if not widget_id or not message:
                self._json(400, {'error': 'widgetId/message manquant'})
                return
            try:
                go2rtc_ws = ws_client.create_connection(
                    'ws://127.0.0.1:%d/api/ws?src=%s' % (GO2RTC_PORT, 'jc_' + widget_id),
                    timeout=10,
                )
            except Exception as err:
                self._json(502, {'error': 'go2rtc injoignable : %s' % err})
                return
            go2rtc_ws.settimeout(None)
            session_id = uuid.uuid4().hex
            session = Session(go2rtc_ws)
            with sessions_lock:
                sessions[session_id] = session
            threading.Thread(target=reader_loop, args=(session_id, session), daemon=True).start()
            try:
                go2rtc_ws.send(json.dumps(message))
            except Exception as err:
                close_session(session_id)
                self._json(502, {'error': 'envoi message échoué : %s' % err})
                return
            self._json(200, {'sessionId': session_id})
            return

        if parsed.path == '/send':
            session_id = (qs.get('sessionId') or [None])[0]
            message = self._body().get('message')
            with sessions_lock:
                session = sessions.get(session_id)
            if not session:
                self._json(404, {'error': 'session inconnue'})
                return
            try:
                session.ws.send(json.dumps(message))
            except Exception as err:
                self._json(502, {'error': 'envoi échoué : %s' % err})
                return
            self._json(200, {})
            return

        if parsed.path == '/close':
            session_id = (qs.get('sessionId') or [None])[0]
            if session_id:
                close_session(session_id)
            self._json(200, {})
            return

        self._json(404, {'error': 'not found'})

    def do_GET(self):
        parsed = urlparse(self.path)
        qs = parse_qs(parsed.query)

        if parsed.path == '/poll':
            session_id = (qs.get('sessionId') or [None])[0]
            with sessions_lock:
                session = sessions.get(session_id)
            if not session:
                self._json(404, {'error': 'session inconnue'})
                return
            # POLL_BATCH_WINDOW s'applique TOUJOURS avant de répondre, pas
            # seulement quand il a fallu attendre la première donnée : en
            # streaming actif (~25-30 fragments/s), il y a quasiment toujours
            # déjà quelque chose en attente au moment où la requête arrive,
            # donc sans ce délai systématique le regroupement ne se produit
            # jamais et on retombe sur un aller-retour par fragment.
            if not session.new_data.is_set():
                session.new_data.wait(POLL_WAIT_TIMEOUT)
            time.sleep(session.current_batch_window())
            self._json(200, {'messages': session.pop_all()})
            return

        self._json(404, {'error': 'not found'})


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Bridge HTTP <-> go2rtc/api/ws (signaling WebRTC + flux MSE)')
    parser.add_argument('--port', type=int, required=True)
    parser.add_argument('--go2rtcport', type=int, required=True)
    parser.add_argument('--pid', type=str, required=True)
    args = parser.parse_args()

    GO2RTC_PORT = args.go2rtcport

    with open(args.pid, 'w') as f:
        f.write(str(os.getpid()))

    threading.Thread(target=reaper_loop, daemon=True).start()

    server = ThreadingHTTPServer(('127.0.0.1', args.port), Handler)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        sys.exit(0)
