import sys
import os
import argparse
import traceback
import signal
import json
import threading
import time
import sys
import uuid

from resources.JeedomConnectd import logger

from multiprocessing import Process

from resources.JeedomConnectd.jeedom.jeedom import jeedom_utils, jeedom_com, jeedom_socket, JEEDOM_SOCKET_MESSAGE

from websocket_server import WebsocketServer, WebSocketHandler, CLOSE_STATUS_NORMAL, DEFAULT_CLOSE_REASON
from socketserver import TCPServer


def read_socket():
    global JEEDOM_SOCKET_MESSAGE
    if not JEEDOM_SOCKET_MESSAGE.empty():
        # logger.debug("Msg received in JEEDOM_SOCKET_MESSAGE")
        msg_socket_str = JEEDOM_SOCKET_MESSAGE.get().decode("utf-8")
        msg_socket = json.loads(msg_socket_str)
        # logger.debug("Msg received => " + msg_socket_str)
        try:
            if msg_socket.get("jeedomApiKey", None) != _apikey:
                raise Exception("Invalid apikey from socket : " + str(msg_socket))

            method = msg_socket.get("type", None)
            payload = msg_socket.get("payload", None)
            eqApiKey = msg_socket.get("eqApiKey", None)

            if eqApiKey:
                toClient = server.apiKey_to_client(eqApiKey)
            else:
                # raise Exception("no apiKey found ! ")
                logger.warning("no apiKey found ! -- skip msg " + method)
                return

            if not toClient:
                # raise Exception("no client found ! ")
                logger.warning("no client found ! -- skip msg " + method)
                return
            else:
                if method == "SET_EVENTS":
                    for elt in msg_socket.get("payload", None):
                        # logger.debug("checking elt =>" + str(elt))
                        if elt.get("type", None) == "DATETIME":
                            toClient["lastReadTimestamp"] = elt.get("payload", None)

                        elif elt.get("type", None) == "HIST_DATETIME":
                            toClient["lastHistoricReadTimestamp"] = elt.get(
                                "payload", None
                            )

                        else:
                            if (
                                "payload" in elt
                                and hasattr(elt, "__len__")
                                and len(elt["payload"]) > 0
                            ):
                                logger.debug(
                                    f"Broadcast to {toClient['id']} : " + str(elt)
                                )
                                server.send_message(toClient, json.dumps(elt))
                else:

                    # if WELCOME or CONFIG_AND_INFOS save data before sending msg
                    if method == "WELCOME":
                        toClient["configVersion"] = payload.get("configVersion", None)
                        toClient["lastReadTimestamp"] = time.time()
                        toClient["lastHistoricReadTimestamp"] = time.time()
                        # logger.debug("all data client =>" + str(toClient))

                    if method == "CONFIG_AND_INFOS":
                        toClient["configVersion"] = payload["config"]["payload"][
                            "configVersion"
                        ]

                    # in all cases, send the msg
                    server.send_message(toClient, msg_socket_str)

                    # if it's a "wrong msg", then close the connection after sending the msg
                    if method in [
                        "BAD_DEVICE",
                        "EQUIPMENT_DISABLE",
                        "APP_VERSION_ERROR",
                        "PLUGIN_VERSION_ERROR",
                        "EMPTY_CONFIG_FILE",
                        "FORMAT_VERSION_ERROR",
                        "BAD_TYPE_VERSION",
                    ]:
                        logger.debug("Bad configuration closing connexion, " + method)
                        server.close_client(toClient)

        except Exception as e:
            logger.exception(e)


# ----------------------------------------------------------------------------
# ----------------------------------------------------------------------------


def listen():
    logger.debug("Start listening")
    jeedomSocket.open()
    try:
        while 1:
            time.sleep(0.01)
            read_socket()
    except KeyboardInterrupt:
        shutdown()


def handler(signum=None, frame=None):
    logger.debug("Signal %i caught, exiting..." % int(signum))
    shutdown()


def shutdown():
    logger.debug("Shutdown")
    try:
        logger.debug("Shutdown websocket server")
        server.shutdown_gracefully
    except Exception as err:
        # logger.exception("shutdown ws server exception " + str(err))
        pass

    try:
        logger.debug("Removing PID file " + str(_pidfile))
        os.remove(_pidfile)
    except:
        pass

    # if error below, closing the connexion, and exit
    try:
        logger.debug("Closing Jeedom Socket connexion")
        jeedomSocket.close()
    except:
        pass

    logger.debug("Exit 0")
    sys.stdout.flush()
    os._exit(0)


# ----------------------------------------------------------------------------
# ----------------------------------------------------------------------------
def client_left(client, server):
    if client and client["apiKey"]:
        logger.info(
            f"Connection #{client['id']} ({client['apiKey']}) has disconnected"
        )
        result = dict()
        result["jsonrpc"] = "2.0"
        result["method"] = "DISCONNECT"
        result["id"] = str(uuid.uuid4())

        params = dict()
        params["apiKey"] = client["apiKey"]
        params["connexionFrom"] = "WS"

        result["params"] = params

        jeedomCom.send_change_immediate(result, client["realIpAdd"])


def new_client(client, server):
    logger.info(f"New connection: #{client['id']} from IP: {client['address']}")
    if client["realIpAdd"]:
        logger.info(f"Connection coming from real IP Address >{client['realIpAdd']}<")
    logger.info(f"Number of client connected #{len(server.clients)}")
    client["openTimestamp"] = time.time()
    # logger.debug(f"All Clients {str(server.clients)}")
    nbNotAuthenticate = server.client_not_authenticated()
    if nbNotAuthenticate > 0:
        logger.warning(f"Clients not authenticated #{nbNotAuthenticate}")
        # server.close_unauthenticated(client)
    # else:
    #     logger.debug(f"All clients are authenticated")


def onMessageReceived(client, server, message):
    # logger.info("[WEBSOCKET RECEIVED] message: " + str(message))
    try:
        original = json.loads(message)
        method = original.get("method", None)
        params = original.get("params", None)

        if method == "SET_EVENTS":
            logger.debug("[WEBSOCKET RECEIVE] message: " + str(message))
            logDebug = True
        else:
            logger.debug("[WEBSOCKET RECEIVE] message: " + str(message))
            # logger.info("[WEBSOCKET RECEIVE] message: " + str(message))
            logDebug = False

        if not method:
            logger.warning("no method received - skip")
            return

        if method == "CONNECT":
            apiKey = params.get("apiKey", None)
            server.close_client_existing(apiKey)
            client["apiKey"] = apiKey

        if not params:
            original["params"] = dict()

        if "apiKey" not in original["params"]:
            original["params"]["apiKey"] = client.get("apiKey", None)

        original["params"]["connexionFrom"] = "WS"

        jeedomCom.send_change_immediate(original, client["realIpAdd"])
        # jeedomCom.send_change_immediate(original, logDebug)
    except Exception as err:
        logger.exception("Exception onMessageReceived : " + str(err))


def async_worker():
    logger.debug("Starting loop to retrieve all events")
    try:
        while True:
            # Check is there is unauthenticated clients for too long
            if server.client_not_authenticated() > 0:
                server.close_unauthenticated(time.time(), 3)

            # Get last event for every connected client
            for client in server.clients:
                if client["apiKey"]:
                    result = dict()
                    result["jsonrpc"] = "2.0"
                    result["method"] = "GET_EVENTS"
                    result["id"] = str(uuid.uuid4())

                    params = dict()
                    params["apiKey"] = client["apiKey"]
                    params["configVersion"] = client["configVersion"]
                    params["lastReadTimestamp"] = client["lastReadTimestamp"]
                    params["lastHistoricReadTimestamp"] = client[
                        "lastHistoricReadTimestamp"
                    ]
                    params["connexionFrom"] = "WS"

                    result["params"] = params

                    # jeedomCom.send_change_immediate(result)
                    jeedomCom.send_change_immediate(result, client["realIpAdd"], True)
                else:
                    logger.warning(f"no api key found for client ${str(client)}")
            time.sleep(1)
    except Exception as err:
        logger.exception(err)

class JCWebsocketServer(WebsocketServer):
    def __init__(self, host='127.0.0.1', port=0, key=None, cert=None):
        
        TCPServer.__init__(self, (host, port), JCWebSocketHandler)
        self.host = host
        self.port = self.socket.getsockname()[1]

        self.key = key
        self.cert = cert

        self.clients = []
        self.id_counter = 0
        self.thread = None

        self._deny_clients = False


    def _new_client_(self, handler, real_ip_add):
        if self._deny_clients:
            status = self._deny_clients["status"]
            reason = self._deny_clients["reason"]
            handler.send_close(status, reason)
            self._terminate_client_handler(handler)
            return

        self.id_counter += 1
        client = {
            "id": self.id_counter,
            "handler": handler,
            "address": handler.client_address,
            "realIpAdd": real_ip_add,
            "apiKey": None,
            "configVersion": None,
            "lastReadTimestamp": None,
            "lastHistoricReadTimestamp": None,
        }
        self.clients.append(client)
        self.new_client(client, self)

    def _unicast(self, receiver_client, msg):
        logger.debug(
            f"[WEBSOCKET SEND] message to client #{receiver_client['id']}: " + str(msg)
        )
        receiver_client["handler"].send_message(msg)

    def apiKey_to_client(self, apiKey):
        for client in self.clients:
            if client["apiKey"] == apiKey:
                return client
        return None

    def close_client(self, client):
        logger.debug(
            f"Closing connection client #{client['id']} from address: {client['address']}"
        )
        client["handler"].send_close(CLOSE_STATUS_NORMAL, DEFAULT_CLOSE_REASON)
        self._terminate_client_handler(client["handler"])

    def close_client_existing(self, eqApiKey):
        for client in self.clients:
            if client["apiKey"] and client["apiKey"] == eqApiKey:
                self.close_client(client)

    def close_unauthenticated(self, currentTime, maxTime):
        logger.debug(
            f"trying to close unauthenticate client. current time {currentTime} with max time {maxTime}"
        )
        for client in self.clients:
            if "openTimestamp" in client and (
                (currentTime - client["openTimestamp"]) > maxTime
            ):
                logger.warning(
                    f"Over time unauthenticate closing connexion for {str(client)}"
                )
                self.close_client(client)

    def client_not_authenticated(self):
        count = 0
        for client in self.clients:
            if not client["apiKey"]:
                count += 1
        return count

class JCWebSocketHandler(WebSocketHandler):
    def handshake(self):
        try:
            headers = self.read_http_headers()
        except Exception:
            logger.warning(
                "[E-00] Client tried to connect but encountered header issue - connexion aborted "
                + str(self.client_address)
            )
            self.keep_alive = False
            return

        try:
            assert headers['upgrade'].lower() == 'websocket'
        except KeyError:
            logger.warning(
                "[E-01] Client tried to connect but not with a websocket protocol - connexion aborted "
                + str(self.client_address)
            )
            self.keep_alive = False
            return
        except AssertionError:
            logger.warning(
                "[E-02] Client tried to connect but not with a websocket protocol - connexion aborted"
                + str(self.client_address)
            )
            self.keep_alive = False
            return

        try:
            key = headers['sec-websocket-key']
        except KeyError:
            logger.warning("Client tried to connect but was missing a key")
            self.keep_alive = False
            return

        try:
            real_ip_add = headers['x-real-ip']
        except KeyError:
            # logger.warning("no real add")
            real_ip_add = None
            pass

        response = self.make_handshake_response(key)
        with self._send_lock:
            self.handshake_done = self.request.send(response.encode())
        self.valid_client = True
        self.server._new_client_(self, real_ip_add)

# ----------------------------------------------------------------------------
# ----------------------------------------------------------------------------



_log_level = "debug"
_socket_port = 58090
_websocket_port = 8090
_socket_host = "localhost"
_pidfile = "/tmp/JeedomConnectd.pid"
_apikey = ""
_callback = ""

parser = argparse.ArgumentParser(description="Daemon for Jeedom plugin")
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--user", help="username", type=str)
parser.add_argument("--pwd", help="password", type=str)
parser.add_argument("--socketport", help="Socket Port", type=int)
parser.add_argument("--websocketport", help="Socket Port", type=int)
parser.add_argument("--callback", help="Value to write", type=str)
parser.add_argument("--apikey", help="Value to write", type=str)
parser.add_argument("--pid", help="Value to write", type=str)
args, unknown = parser.parse_known_args()

_log_level = args.loglevel
_socket_port = args.socketport
_websocket_port = args.websocketport
_pidfile = args.pid
_apikey = args.apikey
_callback = args.callback

logger.setLevel(jeedom_utils.convert_log_level(_log_level)) 

logger.info("Start daemon")
logger.info("Log level : " + str(_log_level))
logger.debug("Socket port : " + str(_socket_port))
logger.debug("PID file : " + str(_pidfile))


signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)

try:
    logger.debug("** Starting Jeedom daemon **")
    jeedom_utils.write_pid(str(_pidfile))
    # Socket to connect daemon <=> jeedom
    jeedomSocket = jeedom_socket(port=_socket_port, address=_socket_host)
    jeedomCom = jeedom_com(apikey=_apikey, url=_callback, cycle=0)
    logger.info("** Jeedom daemon started **")

    # Websocket to connect to JC app
    logger.debug("** Starting JC Websocket daemon **")
    
    server = JCWebsocketServer(host="0.0.0.0", port=_websocket_port)
    server.set_fn_message_received(onMessageReceived)
    server.set_fn_new_client(new_client)
    server.set_fn_client_left(client_left)
    server.run_forever(True)
    logger.info("** JC Websocket daemon started **")

    async_GET_EVENTS = threading.Thread(target=async_worker, daemon=True)
    async_GET_EVENTS.start()

    listen()


except Exception as e:
    logger.exception("Fatal error : " + str(e))
    # logger.info(traceback.format_exc())
    shutdown()
