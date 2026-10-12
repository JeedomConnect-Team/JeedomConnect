<?php

/* * ***************************Includes********************************* */

require_once __DIR__ . '/JeedomConnectWidget.class.php';
require_once __DIR__ . '/JeedomConnectLogs.class.php';

/**
 * Client HTTP pour l'API Frigate (https://frigate.video), utilisé par le
 * widget "frigate" (voir JeedomConnectWidget::saveConfig() pour la
 * dérivation de streamUrl côté go2rtc - cette classe ne gère PAS le live,
 * seulement l'historique/événements et le proxy média, l'authentification
 * JWT étant propre à l'API HTTP de Frigate, jamais au restream RTSP).
 *
 * Authentification (https://docs.frigate.video/configuration/authentication) :
 * JWT obtenu via POST /api/login ({"user":..., "password":...}), renvoyé en
 * cookie mais également utilisable en "Authorization: Bearer <token>" sur
 * les appels suivants - c'est cette seconde forme qui est utilisée ici (plus
 * simple qu'un cookie-jar curl pour du code stateless). Peut être désactivée
 * entièrement côté Frigate (authEnabled=false côté widget dans ce cas).
 */
class FrigateClient {

	// Marge de sécurité avant expiration du JWT (secondes) - déclenche un
	// relogin proactif plutôt que d'attendre un 401 en plein milieu d'un
	// appel (notamment le flux clip.mp4, où un retry à mi-chemin n'est pas
	// géré - voir proxyMedia()).
	const TOKEN_EXPIRY_MARGIN = 30;

	/*     * ********************** AUTHENTIFICATION (JWT) ************************ */

	/**
	 * @param string $frigateUrl URL de base Frigate (sans slash final)
	 * @return string le JWT brut
	 * @throws Exception si le login échoue
	 */
	private static function login($frigateUrl, $username, $password) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, rtrim($frigateUrl, '/') . '/api/login');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('user' => $username, 'password' => $password)));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);

		// Le JWT est renvoyé en cookie (Set-Cookie: <nom>=<jwt>; ...) - pas de
		// nom de cookie en dur (non documenté précisément), on extrait la
		// première paire "...token=<valeur>" rencontrée dans les en-têtes
		// (couvre "frigate_token" et toute variante future du même schéma).
		$token = null;
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$token) {
			if (preg_match('/^Set-Cookie:.*?[a-zA-Z0-9_]*token=([^;]+)/i', $header, $m)) {
				$token = $m[1];
			}
			return strlen($header);
		});

		$body = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($error) {
			throw new Exception('Frigate injoignable (login) : ' . $error);
		}
		if ($httpCode < 200 || $httpCode >= 300) {
			throw new Exception('Frigate : échec de connexion (' . $httpCode . ') - vérifiez le nom d\'utilisateur/mot de passe : ' . $body);
		}
		if (empty($token)) {
			throw new Exception('Frigate : connexion réussie mais jeton introuvable dans la réponse');
		}
		return $token;
	}

	/**
	 * Décode le claim "exp" d'un JWT sans vérifier sa signature (on ne fait
	 * que lire une info déjà émise par Frigate pour savoir quand relogin -
	 * la validité réelle du jeton est de toute façon vérifiée par Frigate
	 * lui-même à chaque appel).
	 *
	 * @return int|null timestamp unix d'expiration, ou null si indécodable
	 */
	private static function decodeJwtExpiry($token) {
		$parts = explode('.', $token);
		if (count($parts) != 3) {
			return null;
		}
		$payloadB64 = strtr($parts[1], '-_', '+/');
		$remainder = strlen($payloadB64) % 4;
		if ($remainder) {
			$payloadB64 .= str_repeat('=', 4 - $remainder);
		}
		$payload = json_decode(base64_decode($payloadB64), true);
		return is_array($payload) ? ($payload['exp'] ?? null) : null;
	}

	/**
	 * Jeton mis en cache par widget (persisté via config::save, même
	 * convention que le reste du plugin pour un petit état persisté - ex.
	 * Go2rtc::isManagedTrialStarted()). Relogin si absent/expiré (avec marge
	 * de sécurité).
	 *
	 * @throws Exception si le login échoue
	 */
	private static function getToken($widgetId, $conf) {
		$cacheKey = 'frigateToken::' . $widgetId;
		$cached = config::byKey($cacheKey, 'JeedomConnect', null);
		$cached = is_string($cached) ? json_decode($cached, true) : null;

		if (is_array($cached) && !empty($cached['token']) && !empty($cached['exp'])
			&& $cached['exp'] > (time() + self::TOKEN_EXPIRY_MARGIN)) {
			return $cached['token'];
		}

		$token = self::login($conf['frigateUrl'], $conf['username'] ?? '', $conf['password'] ?? '');
		$exp = self::decodeJwtExpiry($token) ?? (time() + 3600);
		config::save($cacheKey, json_encode(array('token' => $token, 'exp' => $exp)), 'JeedomConnect');
		return $token;
	}

	/*     * ********************** REQUÊTES HTTP (bufferisées) ******************** */

	/**
	 * Requête HTTP bufferisée vers Frigate, avec authentification Bearer si
	 * activée côté widget et un retry après relogin forcé sur 401 (couvre le
	 * cas d'un jeton invalidé côté serveur avant son expiration annoncée,
	 * ex. redémarrage de Frigate).
	 *
	 * @param array $conf configuration du widget frigate (frigateUrl,
	 *                      authEnabled, username, password...)
	 * @param string $path chemin d'API Frigate, ex. "/api/events?..."
	 * @param string $method verbe HTTP (GET par défaut - deleteEvent() est le
	 *                         seul appelant à passer autre chose, DELETE)
	 * @return string le corps de la réponse
	 * @throws Exception sur échec réseau ou HTTP non-2xx
	 */
	private static function request($widgetId, $conf, $path, $forceRelogin = false, $method = 'GET') {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, rtrim($conf['frigateUrl'], '/') . $path);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		if ($method !== 'GET') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		if (!empty($conf['authEnabled'])) {
			if ($forceRelogin) {
				config::remove('frigateToken::' . $widgetId, 'JeedomConnect');
			}
			$token = self::getToken($widgetId, $conf);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token));
		}

		$body = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($error) {
			throw new Exception('Frigate injoignable : ' . $error);
		}
		if ($httpCode == 401 && !empty($conf['authEnabled']) && !$forceRelogin) {
			return self::request($widgetId, $conf, $path, true, $method);
		}
		if ($httpCode < 200 || $httpCode >= 300) {
			throw new Exception('Frigate : erreur ' . $httpCode . ' sur ' . $path);
		}
		return $body;
	}

	/*     * ********************** ÉVÉNEMENTS (historique) ************************ */

	/**
	 * @param int|string $widgetId
	 * @param array $params before/after (timestamps unix)/limit optionnels,
	 *                        transmis par le client (voir apiHelper::frigateEvents)
	 * @return array liste d'événements bruts tels que renvoyés par Frigate
	 *                (/api/events)
	 * @throws Exception
	 */
	public static function getEvents($widgetId, $params = array()) {
		$conf = JeedomConnectWidget::getConfiguration($widgetId, '', null);
		if (empty($conf) || ($conf['type'] ?? '') != 'frigate') {
			throw new Exception(__("Widget Frigate introuvable", __FILE__));
		}
		if (empty($conf['frigateCameraName'])) {
			throw new Exception(__("Caméra Frigate non configurée sur ce widget", __FILE__));
		}

		// La caméra ciblée est TOUJOURS celle de la config du widget, jamais
		// transmise par le client - même principe que apiHelper::cameraStreamOpen
		// (pas de proxy vers une caméra non prévue pour ce widget).
		// Défaut à 500 (pas 50) : /api/events est trié 'date_desc' (le plus
		// récent d'abord), donc un `limit` trop bas tronque silencieusement
		// aux N événements les plus récents AVANT même d'appliquer before/
		// after côté affichage - une caméra assez active pour dépasser 50
		// événements en une seule journée retournait alors uniquement des
		// événements du jour même pour un filtre "7 jours" (ou "Tout"),
		// laissant croire que le filtre de date lui-même était cassé alors
		// que c'était cette limite qui l'était. Le client (app) n'envoie
		// jamais explicitement `limit` aujourd'hui - ce défaut s'applique
		// donc systématiquement.
		$query = array(
			'cameras' => $conf['frigateCameraName'],
			'sort' => 'date_desc',
			'limit' => !empty($params['limit']) ? intval($params['limit']) : 500,
		);
		if (!empty($params['before'])) {
			$query['before'] = intval($params['before']);
		}
		if (!empty($params['after'])) {
			$query['after'] = intval($params['after']);
		}

		$body = self::request($widgetId, $conf, '/api/events?' . http_build_query($query));
		$events = json_decode($body, true);
		if (!is_array($events)) {
			throw new Exception('Frigate : réponse /api/events invalide');
		}

		// Pas de fusion côté serveur ici : mqttEventsInfo (si configuré) ne
		// sert plus qu'à déclencher un rafraîchissement côté app (voir
		// apiHelper::frigateEvents, qui renvoie sa référence telle quelle -
		// l'app s'y abonne via useCmd et rappelle simplement cette méthode
		// dès que sa valeur change). Le topic MQTT recommandé pour l'y
		// brancher, frigate/<camera_name>/review_status, ne contient que
		// l'état courant ("NONE"/"DETECTION"/"ALERT"), pas le détail d'un
		// event - rien à fusionner. (Abandonné : frigate/events, dont le
		// payload JSON {type, before, after} aurait permis un affichage
		// instantané sans ce second aller-retour REST, mais qui n'est ni
		// spécifique à une caméra ni assez sobre - il republie plusieurs
		// fois pour un même event tant qu'il reste actif.)
		return $events;
	}

	/*     * ********************** SUPPRESSION D'ÉVÉNEMENT ************************* */

	/**
	 * Supprime un event Frigate (DELETE /api/events/<id> - vérifié dans le
	 * code source de Frigate, frigate/api/event.py : supprime l'entrée en
	 * base ET les fichiers média associés, clip/snapshot compris).
	 *
	 * eventId est opaque (généré par Frigate, pas dérivé de la config du
	 * widget) : sans vérification, un widget pourrait faire supprimer un
	 * event d'une AUTRE caméra du même serveur Frigate - même principe de
	 * cloisonnement par caméra que getEvents() (query "cameras" jamais
	 * transmise par le client), mais devant être revérifié ici au cas par
	 * cas puisque DELETE /api/events/<id> ne prend pas de filtre caméra.
	 * Un aller-retour GET supplémentaire (/api/events/<id>, route confirmée
	 * dans frigate/api/event.py) avant le DELETE reste un coût raisonnable
	 * pour une opération destructrice/irréversible.
	 *
	 * @throws Exception
	 */
	public static function deleteEvent($widgetId, $eventId) {
		$conf = JeedomConnectWidget::getConfiguration($widgetId, '', null);
		if (empty($conf) || ($conf['type'] ?? '') != 'frigate') {
			throw new Exception(__("Widget Frigate introuvable", __FILE__));
		}
		if (empty($eventId)) {
			throw new Exception(__("Identifiant d'événement manquant", __FILE__));
		}
		$body = self::request($widgetId, $conf, '/api/events/' . rawurlencode($eventId));
		$event = json_decode($body, true);
		if (!is_array($event) || ($event['camera'] ?? null) !== $conf['frigateCameraName']) {
			throw new Exception(__("Événement introuvable pour cette caméra", __FILE__));
		}
		self::request($widgetId, $conf, '/api/events/' . rawurlencode($eventId), false, 'DELETE');
	}

	/**
	 * Bascule retain_indefinitely d'un event Frigate - POST /api/events/<id>/
	 * retain pour le protéger indéfiniment (ne sera jamais nettoyé
	 * automatiquement par ancienneté), DELETE /api/events/<id>/retain pour
	 * retirer cette protection (routes confirmées dans frigate/api/event.py,
	 * toutes deux sans corps de requête). Même vérification de cloisonnement
	 * par caméra que deleteEvent() ci-dessus, pour la même raison.
	 *
	 * @throws Exception
	 */
	public static function setEventRetain($widgetId, $eventId, $retain) {
		$conf = JeedomConnectWidget::getConfiguration($widgetId, '', null);
		if (empty($conf) || ($conf['type'] ?? '') != 'frigate') {
			throw new Exception(__("Widget Frigate introuvable", __FILE__));
		}
		if (empty($eventId)) {
			throw new Exception(__("Identifiant d'événement manquant", __FILE__));
		}
		$body = self::request($widgetId, $conf, '/api/events/' . rawurlencode($eventId));
		$event = json_decode($body, true);
		if (!is_array($event) || ($event['camera'] ?? null) !== $conf['frigateCameraName']) {
			throw new Exception(__("Événement introuvable pour cette caméra", __FILE__));
		}
		self::request($widgetId, $conf, '/api/events/' . rawurlencode($eventId) . '/retain', false, $retain ? 'POST' : 'DELETE');
	}

	/**
	 * Extrait vidéo sur une plage horaire arbitraire (pas liée à un event
	 * détecté, contrairement à proxyMedia media=clip qui prend un eventId) -
	 * route directe/synchrone confirmée dans frigate/api/media.py :
	 * GET /api/<camera>/start/<start_ts>/end/<end_ts>/clip.mp4 (à ne pas
	 * confondre avec /api/export/... qui est un mécanisme de job
	 * asynchrone, inadapté ici). $start/$duration en secondes unix, $end
	 * calculé ici pour matcher l'appel déjà écrit côté frigateMedia.php.
	 * Passthrough chunké/Range via streamMedia (comme proxyVod pour les
	 * segments HLS) car le fichier peut être volumineux.
	 */
	public static function exportClip($widgetId, $start, $duration, $rangeHeader = null) {
		$conf = JeedomConnectWidget::getConfiguration($widgetId, '', null);
		if (empty($conf) || ($conf['type'] ?? '') != 'frigate' || empty($conf['frigateCameraName'])) {
			http_response_code(404);
			return;
		}
		if (empty($start) || empty($duration) || $duration <= 0) {
			http_response_code(400);
			return;
		}
		$end = $start + $duration;
		$path = '/api/' . rawurlencode($conf['frigateCameraName']) . "/start/$start/end/$end/clip.mp4";
		self::streamMedia($widgetId, $conf, $path, $rangeHeader);
	}

	/*     * ********************** TEST DE CONNEXION ****************************** */

	/**
	 * Utilisable avant la sauvegarde d'un widget (prend les paramètres bruts,
	 * pas un widgetId) - vérifie la connexion ET que la caméra indiquée existe
	 * bien côté Frigate, pour un message d'erreur actionnable.
	 *
	 * @throws Exception
	 */
	public static function testConnection($frigateUrl, $cameraName, $authEnabled, $username, $password) {
		$conf = array(
			'frigateUrl' => $frigateUrl,
			'authEnabled' => $authEnabled,
			'username' => $username,
			'password' => $password,
		);
		// widgetId 'test' : dédié au cache de jeton de ce contrôle ponctuel,
		// sans widget réel associé - collision sans risque (juste un cache
		// écrasé/réutilisé d'un test à l'autre).
		$body = self::request('test', $conf, '/api/config');
		$config = json_decode($body, true);
		$cameras = is_array($config['cameras'] ?? null) ? array_keys($config['cameras']) : array();

		if (!in_array($cameraName, $cameras, true)) {
			throw new Exception(sprintf(
				__("Caméra '%s' introuvable. Caméras disponibles : %s", __FILE__),
				$cameraName,
				implode(', ', $cameras)
			));
		}
		return true;
	}

	/*     * ********************** RESTREAM (nom du flux go2rtc) ****************** */

	/**
	 * Nom du flux go2rtc de Frigate (restream rtsp://<hôte>:8554/<nom>) à
	 * utiliser pour une caméra Frigate - qui n'est PAS forcément le nom de la
	 * caméra elle-même : rien n'oblige à nommer ses flux go2rtc comme ses
	 * caméras (cas réel : caméra "reolink_avant" alimentée par les flux
	 * "rtsp_avant"/"rtsp_avant_sub" - avec le nom de caméra seul, le restream
	 * répondait 404 et la vidéo ne démarrait jamais ; avec le nom du flux, la
	 * vidéo marchait mais l'API Frigate, elle, ne connaissait pas cette
	 * "caméra" - snapshot/événements en 404).
	 *
	 * Lu dans /api/config, premier candidat existant réellement dans
	 * go2rtc.streams, par ordre de préférence :
	 *  1. le(s) flux déclaré(s) par Frigate lui-même pour l'affichage en direct
	 *     de la caméra (live.streams depuis Frigate 0.16 - premier de la liste -,
	 *     live.stream_name avant) : c'est la correspondance qu'utilise sa propre
	 *     interface ;
	 *  2. le nom de la caméra (convention recommandée par la doc Frigate) ;
	 *  3. les flux référencés par les entrées ffmpeg de la caméra
	 *     (rtsp://127.0.0.1:8554/<flux>, montage restream classique), celle de
	 *     rôle "record" (flux principal) en premier.
	 *
	 * @return string|null le nom du flux, ou null si rien de concluant (config
	 *                      sans section go2rtc, caméra introuvable...) - à
	 *                      l'appelant de retomber sur le nom de la caméra.
	 * @throws Exception si Frigate est injoignable
	 */
	public static function resolveRestreamName($widgetId, $conf) {
		$config = json_decode(self::request($widgetId, $conf, '/api/config'), true);
		$cameraName = $conf['frigateCameraName'] ?? '';
		$camera = $config['cameras'][$cameraName] ?? null;
		if (!is_array($camera)) {
			return null;
		}

		$candidates = array();
		$liveStreams = $camera['live']['streams'] ?? null;
		if (is_array($liveStreams)) {
			foreach ($liveStreams as $streamName) {
				if (is_string($streamName) && $streamName !== '') {
					$candidates[] = $streamName;
				}
			}
		}
		if (!empty($camera['live']['stream_name']) && is_string($camera['live']['stream_name'])) {
			$candidates[] = $camera['live']['stream_name'];
		}
		$candidates[] = $cameraName;

		$inputCandidates = array();
		foreach ($camera['ffmpeg']['inputs'] ?? array() as $input) {
			$path = is_array($input) ? ($input['path'] ?? '') : '';
			// rtsp://[user:pass@]hôte:8554/<flux>[?...] - hôte quelconque
			// (127.0.0.1, localhost, nom du conteneur...) : seul le port du
			// restream go2rtc de Frigate et le nom de flux comptent ici.
			if (is_string($path) && preg_match('#^rtsp://(?:[^@/]*@)?[^:/]+:8554/([^/?\#]+)#', $path, $m)) {
				$roles = is_array($input['roles'] ?? null) ? $input['roles'] : array();
				if (in_array('record', $roles, true)) {
					array_unshift($inputCandidates, rawurldecode($m[1]));
				} else {
					$inputCandidates[] = rawurldecode($m[1]);
				}
			}
		}
		$candidates = array_values(array_unique(array_merge($candidates, $inputCandidates)));

		$go2rtcStreams = $config['go2rtc']['streams'] ?? null;
		if (is_array($go2rtcStreams) && !empty($go2rtcStreams)) {
			foreach ($candidates as $candidate) {
				if (array_key_exists($candidate, $go2rtcStreams)) {
					return $candidate;
				}
			}
			return null;
		}
		// Pas de liste go2rtc exploitable dans la réponse (version de Frigate
		// qui ne l'expose pas) : seules les entrées ffmpeg pointant sur le
		// restream prouvent l'existence d'un flux.
		return $inputCandidates[0] ?? null;
	}

	/*     * ********************** PROXY MÉDIA (vignette/snapshot/clip) *********** */

	/**
	 * Sert (en écrivant directement la réponse HTTP courante) le média d'un
	 * événement Frigate, pour le compte de core/php/frigateMedia.php.
	 * thumbnail/snapshot sont bufferisés (petites images déjà dimensionnées
	 * par Frigate) ; clip est streamé au fil de l'eau avec support des
	 * requêtes Range (nécessaire pour un scrubbing correct côté lecteur
	 * mobile - un clip peut faire plusieurs dizaines de Mo, le bufferiser
	 * serait lent et coûteux en mémoire sur du matériel Jeedom modeste).
	 * 'live' est à part : pas un média d'événement (eventId vide/ignoré),
	 * sert le dernier cliché de la caméra elle-même (/api/<camera>/latest.jpg,
	 * doc Frigate) - utilisé côté client pour déduire le ratio d'affichage de
	 * la vue "Carte" du widget une seule fois (voir setAspectRatio côté app).
	 *
	 * Ne retente pas de relogin en cas de 401 en cours de stream (voir
	 * TOKEN_EXPIRY_MARGIN - le relogin proactif avant expiration couvre déjà
	 * l'essentiel des cas, un 401 en plein streaming resterait une erreur
	 * affichée au lecteur plutôt qu'un retry transparent).
	 */
	public static function proxyMedia($widgetId, $eventId, $media, $rangeHeader = null, $liveHeight = 0, $liveQuality = 0) {
		$conf = JeedomConnectWidget::getConfiguration($widgetId, '', null);
		if (empty($conf) || ($conf['type'] ?? '') != 'frigate') {
			http_response_code(404);
			return;
		}
		if (!in_array($media, array('thumbnail', 'snapshot', 'clip', 'live'), true)) {
			http_response_code(400);
			return;
		}

		if ($media == 'live') {
			if (empty($conf['frigateCameraName'])) {
				http_response_code(404);
				return;
			}
			$path = '/api/' . rawurlencode($conf['frigateCameraName']) . '/latest.jpg';
			// Aperçu basse qualité affiché derrière le spinner pendant le
			// chargement du flux (voir snapshotBgUrl côté app) : h/quality de
			// l'API Frigate, bornés ici plutôt que relayés tels quels.
			// "height" et non "h" : vérifié sur Frigate 0.18, "h" est ignoré
			// (même taille d'image qu'sans paramètre).
			$query = array();
			if ($liveHeight > 0) {
				$query['height'] = min(intval($liveHeight), 1080);
			}
			if ($liveQuality > 0) {
				$query['quality'] = min(intval($liveQuality), 100);
			}
			if (!empty($query)) {
				$path .= '?' . http_build_query($query);
			}
		} else {
			if (empty($eventId)) {
				http_response_code(404);
				return;
			}
			$ext = $media == 'clip' ? 'mp4' : 'jpg';
			$path = '/api/events/' . rawurlencode($eventId) . '/' . $media . '.' . $ext;
		}

		if ($media == 'clip') {
			self::streamMedia($widgetId, $conf, $path, $rangeHeader);
			return;
		}

		try {
			$body = self::request($widgetId, $conf, $path);
		} catch (Exception $e) {
			JCLog::warning('FrigateClient::proxyMedia (' . $media . ') : ' . $e->getMessage());
			http_response_code(502);
			return;
		}
		header('Content-Type: image/jpeg');
		echo $body;
	}

	/*     * ********************** PROXY VOD HLS (seek réel) *********************** */

	/**
	 * Frigate expose, en plus de clip.mp4 (non-seekable - voir proxyMedia,
	 * pas de Content-Length/Range, servi en chunked), une playlist HLS par
	 * event via son propre nginx interne : /vod/event/<id>/master.m3u8 (doc
	 * officielle Frigate). Vérifié en pratique sur cette installation :
	 * master.m3u8 référence une sous-playlist ("index-v1-a1.m3u8"), qui
	 * référence à son tour un segment init fMP4 (#EXT-X-MAP) et un ou
	 * plusieurs segments .m4s, TOUJOURS par simple nom de fichier relatif
	 * (jamais d'URL absolue) - c'est ce qui rend cette réécriture tractable
	 * (contrairement au proxy webview à réécriture de texte, abandonné pour
	 * complexité - voir le plan correspondant) : on n'a qu'à réécrire une
	 * ligne "nue" par référence, format HLS bien défini et prévisible.
	 *
	 * @param string $apiKey l'apiKey déjà validée par frigateMedia.php -
	 *                         réinjectée dans les URLs réécrites pour que les
	 *                         requêtes suivantes (sous-playlist, segments)
	 *                         passent la même vérification.
	 */
	public static function proxyVod($widgetId, $eventId, $file, $apiKey, $rangeHeader = null) {
		$conf = JeedomConnectWidget::getConfiguration($widgetId, '', null);
		if (empty($conf) || ($conf['type'] ?? '') != 'frigate' || empty($eventId)) {
			http_response_code(404);
			return;
		}
		$file = $file !== '' ? $file : 'master.m3u8';
		$path = '/vod/event/' . rawurlencode($eventId) . '/' . $file;

		if (substr($file, -5) === '.m3u8') {
			try {
				$body = self::request($widgetId, $conf, $path);
			} catch (Exception $e) {
				JCLog::warning('FrigateClient::proxyVod (' . $file . ') : ' . $e->getMessage());
				http_response_code(502);
				return;
			}
			header('Content-Type: application/vnd.apple.mpegurl');
			echo self::rewriteVodPlaylist($body, $widgetId, $eventId, $apiKey);
			return;
		}

		// Segments/init (.mp4, .m4s...) : même streaming binaire avec Range
		// que clip.mp4 - important ici aussi, un lecteur HLS s'appuie
		// couramment sur des requêtes Range par segment.
		self::streamMedia($widgetId, $conf, $path, $rangeHeader);
	}

	/**
	 * Réécrit chaque référence de fichier "nue" (sous-playlist ou segment)
	 * pour qu'elle repasse par ce même proxy, plus l'URI de la balise
	 * #EXT-X-MAP (segment d'initialisation, syntaxe différente - attribut
	 * entre guillemets, pas une ligne à part).
	 */
	private static function rewriteVodPlaylist($body, $widgetId, $eventId, $apiKey) {
		$lines = explode("\n", $body);
		foreach ($lines as &$line) {
			$trimmed = rtrim($line, "\r");
			if ($trimmed === '' || $trimmed[0] === '#') {
				if (preg_match('/^(#EXT-X-MAP:.*URI=")([^"]+)(".*)$/', $trimmed, $m)) {
					$line = $m[1] . self::vodProxyUrl($widgetId, $eventId, $m[2], $apiKey) . $m[3];
				}
				continue;
			}
			$line = self::vodProxyUrl($widgetId, $eventId, $trimmed, $apiKey);
		}
		return implode("\n", $lines);
	}

	/**
	 * URL relative (résolue par le lecteur par rapport à l'URL de la
	 * playlist elle-même, donc frigateMedia.php - pas besoin de host/scheme).
	 */
	private static function vodProxyUrl($widgetId, $eventId, $file, $apiKey) {
		return 'frigateMedia.php?apiKey=' . rawurlencode($apiKey) . '&id=' . rawurlencode($widgetId)
			. '&eventId=' . rawurlencode($eventId) . '&media=vod&file=' . rawurlencode($file);
	}

	/**
	 * Streaming avec passthrough du Range - relaie le statut/les en-têtes de
	 * la réponse Frigate AVANT tout octet de corps (via CURLOPT_HEADERFUNCTION),
	 * puis écrit chaque paquet reçu directement en sortie au fur et à mesure
	 * (CURLOPT_WRITEFUNCTION), sans jamais accumuler le clip entier en mémoire.
	 */
	private static function streamMedia($widgetId, $conf, $path, $rangeHeader) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, rtrim($conf['frigateUrl'], '/') . $path);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);

		$headers = array();
		if (!empty($rangeHeader)) {
			$headers[] = 'Range: ' . $rangeHeader;
		}
		if (!empty($conf['authEnabled'])) {
			try {
				$headers[] = 'Authorization: Bearer ' . self::getToken($widgetId, $conf);
			} catch (Exception $e) {
				JCLog::warning('FrigateClient::streamMedia - échec login : ' . $e->getMessage());
				http_response_code(502);
				curl_close($ch);
				return;
			}
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$headersSent = false;
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headersSent) {
			$trimmed = trim($header);
			if ($trimmed == '') {
				return strlen($header);
			}
			if (preg_match('#^HTTP/\S+\s+(\d{3})#', $trimmed, $m)) {
				http_response_code(intval($m[1]));
			} elseif (preg_match('/^(Content-Type|Content-Length|Content-Range|Accept-Ranges):/i', $trimmed)) {
				header($trimmed);
			}
			return strlen($header);
		});
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) {
			echo $chunk;
			@ob_flush();
			@flush();
			return strlen($chunk);
		});

		curl_exec($ch);
		if ($error = curl_error($ch)) {
			JCLog::warning('FrigateClient::streamMedia (' . $path . ') : ' . $error);
		}
		curl_close($ch);
	}
}
