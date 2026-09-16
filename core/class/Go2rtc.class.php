<?php

/* * ***************************Includes********************************* */

require_once __DIR__ . '/JeedomConnectWidget.class.php';
require_once __DIR__ . '/JeedomConnectLogs.class.php';
require_once __DIR__ . '/JeedomConnectLock.class.php';

/**
 * Pont go2rtc (https://github.com/AlexxIT/go2rtc) -> WebRTC pour les widgets
 * caméra. Daemon géré par le plugin, comme JeedomConnectd.py, mais AVEC SES
 * PROPRES méthodes start()/stop()/info() : les noms deamon_start/stop/info
 * sont réservés par Jeedom pour le démon principal du plugin (JeedomConnect
 * n'en a qu'un via cette convention), donc un second démon ne peut pas s'y
 * accrocher automatiquement. Pour cette v1 (POC), le démon est démarré
 * paresseusement dès qu'un widget caméra avec webrtcEnabled est enregistré
 * (registerStream()) plutôt que via un toggle dédié sur la page de config.
 *
 * En LAN, l'app se connecte en direct à go2rtc (WebSocket vers /api/ws) ;
 * go2rtc lui-même n'est pas exposé hors LAN, aucune authentification n'est
 * configurée sur son API (même niveau d'exposition que le flux RTSP direct
 * utilisé aujourd'hui par VLCPlayer).
 *
 * Hors LAN, le signaling passe par un second petit processus géré par cette
 * classe : webrtcBridge.py (localhost uniquement). Il traduit une série
 * d'appels HTTP courts (offer/candidate/poll, voir apiHelper::webrtcOffer
 * et consorts) en une connexion WebSocket vers go2rtc/api/ws, ce qui permet
 * un vrai trickle ICE (candidats envoyés au fil de l'eau, comme le client de
 * référence de go2rtc) sans exiger de canal WebSocket bout-en-bout côté app
 * - une première version utilisait le canal WS du démon JeedomConnectd.py,
 * abandonnée car elle ne fonctionne que si l'utilisateur a activé l'option
 * optionnelle useWs (minoritaire), alors que le HTTP fonctionne pour tous
 * les utilisateurs, avec ou sans reverse proxy personnalisé (voir plan
 * "Passage en signaling WebSocket + trickle ICE" pour l'historique complet).
 *
 * Côté app : le lecteur WebRTC s'exécute dans une WebView (moteur Chromium),
 * pas via react-native-webrtc - voir webrtcPlayer.js pour le pourquoi
 * (limitation documentée et non résolue de la pile ICE native embarquée par
 * react-native-webrtc sur réseau cellulaire, cf. historique de session).
 */
class Go2rtc {

	// Version épinglée (asset filenames confirmés via l'API GitHub au moment
	// de l'écriture). Vérifier https://github.com/AlexxIT/go2rtc/releases si
	// une mise à jour est nécessaire un jour.
	const GO2RTC_TAG = 'v1.9.14';

	// POC : durée de vie (secondes) du credential TURN Cloudflare miné pour
	// go2rtc lui-même (config statique, pas de rafraîchissement à chaud -
	// voir writeConfig()). 24h.
	const GO2RTC_ICE_SERVERS_TTL = 86400;

	public static $_bin_dir = __DIR__ . '/../../resources/go2rtc/';

	public static function getBinaryPath() {
		return self::$_bin_dir . 'go2rtc';
	}

	public static function getConfigPath() {
		return self::$_bin_dir . 'go2rtc.yaml';
	}

	public static function getPidFile() {
		return jeedom::getTmpFolder(__CLASS__) . '/go2rtc.pid';
	}

	public static function getPort() {
		return intval(config::byKey('go2rtcPort', 'JeedomConnect', 1984));
	}

	public static function getLocalApiUrl() {
		return 'http://127.0.0.1:' . self::getPort();
	}

	public static function streamName($widgetId) {
		return 'jc_' . $widgetId;
	}

	/*     * ********************** TURN (Cloudflare - fournisseur par défaut, offre gratuite BYO) ****** */
	// Cloudflare est plus rapide en conditions normales qu'un relais TURN
	// auto-hébergé, avec un taux de dégradation comparable ou meilleur -
	// d'où son choix comme fournisseur par défaut. Turn Key ID + API Token
	// saisis sur la page de configuration du plugin (offre gratuite "BYO",
	// chaque utilisateur son propre compte Cloudflare) - jamais transmis à
	// l'app (seuls les credentials courte durée générés à partir d'eux le
	// sont). Le VPS auto-hébergé (ci-dessous) reste disponible en dormant.

	public static function getTurnKeyId() {
		return config::byKey('cloudflareTurnKeyId', 'JeedomConnect', '');
	}

	public static function getTurnApiToken() {
		return config::byKey('cloudflareTurnApiToken', 'JeedomConnect', '');
	}

	public static function isTurnConfigured() {
		return self::getTurnKeyId() != '' && self::getTurnApiToken() != '';
	}

	/**
	 * Chaîne YAML entre guillemets simples (pas de séquences d'échappement à
	 * gérer comme en JSON/double guillemets - seul un guillemet simple
	 * littéral doit être doublé). À privilégier sur json_encode() pour toute
	 * valeur pouvant contenir un "/" (URLs, credentials...) : json_encode()
	 * échappe les "/" en "\/", une séquence que le parseur YAML de go2rtc ne
	 * reconnaît pas ("unknown escape character").
	 */
	private static function yamlSingleQuote($s) {
		return "'" . str_replace("'", "''", $s) . "'";
	}

	/**
	 * Génère des credentials TURN Cloudflare via leur API de mint
	 * (https://developers.cloudflare.com/realtime/turn/), Turn Key ID +
	 * API Token restant côté serveur (Bearer, jamais transmis à l'app).
	 * Endpoint "generate-ice-servers" (pas juste "generate") : renvoie une
	 * LISTE de descripteurs {urls, [username, credential]} directement
	 * utilisable comme RTCPeerConnection({iceServers: ...}) - vérifié en
	 * conditions réelles (curl direct) : 2 entrées, un STUN sans credentials
	 * puis un TURN avec username/credential.
	 *
	 * @param int $ttlSeconds durée de vie du credential généré
	 * @return array la liste d'objets {urls, username?, credential?} telle
	 *                 que retournée par Cloudflare sous 'iceServers'
	 * @throws Exception si Cloudflare est injoignable, mal configuré, ou
	 *                     répond une erreur
	 */
	private static function mintTurnCredentials($ttlSeconds) {
		if (!self::isTurnConfigured()) {
			throw new Exception(__("TURN Cloudflare non configuré (Turn Key ID / API Token manquants)", __FILE__));
		}

		$url = 'https://rtc.live.cloudflare.com/v1/turn/keys/' . rawurlencode(self::getTurnKeyId()) . '/credentials/generate-ice-servers';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . self::getTurnApiToken(),
		));
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('ttl' => $ttlSeconds)));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$body = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($error) {
			throw new Exception('Cloudflare TURN injoignable : ' . $error);
		}
		$decoded = json_decode($body, true);
		// Cet endpoint répond 201 (Created), pas seulement 200 : il crée bel
		// et bien une ressource (les credentials courte durée). On se base
		// surtout sur la présence d'iceServers.
		if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['iceServers'])) {
			throw new Exception('Cloudflare TURN : réponse invalide (' . $httpCode . ') : ' . $body);
		}
		return $decoded['iceServers'];
	}

	/**
	 * Credential courte durée pour l'app (webrtcPlayer.js, mode
	 * 'webrtc-remote') - voir apiHelper::cameraTurnCredentials.
	 * mintTurnCredentials()/mintSelfHostedTurnCredentials() renvoient déjà la
	 * liste iceServers telle quelle (STUN + TURN) - rien à envelopper ici.
	 *
	 * Cloudflare est le fournisseur par défaut : campagne de mesure terrain
	 * concluante en sa faveur face au VPS auto-hébergé (connexion plus
	 * rapide, taux de dégradation comparable ou meilleur). Le VPS reste
	 * disponible en dormant (`$provider = 'selfhosted'`) pour un usage
	 * avancé futur, pas exposé côté app pour l'instant.
	 *
	 * @param string $provider 'cloudflare' (défaut), 'managed' ou 'selfhosted'.
	 * @return array un tableau iceServers directement utilisable comme
	 *                 RTCPeerConnection({iceServers: ...})
	 */
	public static function mintClientTurnCredentials($provider = 'cloudflare', $ttlSeconds = 300) {
		if ($provider === 'selfhosted') {
			return self::mintSelfHostedTurnCredentials($ttlSeconds);
		}
		if ($provider === 'managed') {
			return self::mintManagedTurnCredentials($ttlSeconds);
		}
		return self::mintTurnCredentials($ttlSeconds);
	}

	/*     * ********************** TURN (auto-hébergé, coturn sur VPS) ********** */
	// Remplace le TURN Cloudflare ci-dessus comme option par défaut : évite
	// d'imposer à chaque utilisateur du plugin la création d'un compte
	// Cloudflare (carte bancaire requise pour le palier gratuit malgré le
	// discours marketing - confirmé via un fil de la communauté Cloudflare).
	// VPS "Always Free" (Oracle Cloud) faisant tourner coturn + un petit
	// service de mint HTTP dédié (turn_mint_service.py, non versionné,
	// tourne uniquement sur ce VPS) : le secret partagé coturn (schéma REST
	// API, HMAC-SHA1) ne quitte JAMAIS ce serveur et n'est donc jamais
	// exposé dans ce dépôt public - seule l'URL du service de mint est en
	// dur ici, ce qui est sans risque (aucune authentification à connaître
	// pour l'appeler, protégé côté VPS par un rate-limit par IP + plafond
	// global/jour, pas par un secret partagé).

	const SELF_HOSTED_TURN_MINT_URL = 'http://141.145.201.141:8089/mint';

	/**
	 * @param int $ttlSeconds durée de vie du credential généré
	 * @return array un tableau iceServers (même forme que
	 *                 mintTurnCredentials()) directement utilisable comme
	 *                 RTCPeerConnection({iceServers: ...})
	 * @throws Exception si le service de mint est injoignable ou répond une
	 *                     erreur
	 */
	public static function mintSelfHostedTurnCredentials($ttlSeconds) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, self::SELF_HOSTED_TURN_MINT_URL . '?ttl=' . intval($ttlSeconds));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$body = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($error) {
			throw new Exception('TURN auto-hébergé injoignable : ' . $error);
		}
		$decoded = json_decode($body, true);
		if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['iceServers'])) {
			throw new Exception('TURN auto-hébergé : réponse invalide (' . $httpCode . ') : ' . $body);
		}
		return $decoded['iceServers'];
	}

	/*     * ********************** TURN (managé, abonnement Apple/Google) ******** */
	// Offre payante : l'utilisateur n'a pas besoin de compte Cloudflare - il
	// s'abonne directement depuis l'app (achat intégré App Store/Google
	// Play, écran Préférences > Caméras hors LAN), qui lie l'achat au service de
	// mint sur le VPS via jeedom::getHardwareKey() (voir
	// src/services/iap.js côté app). Ce plugin PHP n'a donc aucune donnée
	// d'abonnement à stocker/vérifier lui-même - il tente simplement un
	// mint avec ce hardware_key, et le VPS répond selon l'état réel de
	// l'abonnement (validé côté serveur auprès d'Apple/Google). Le service
	// mine ensuite des credentials depuis UNE Turn Key Cloudflare dédiée à
	// cette offre (compte du développeur, jamais celle de l'utilisateur),
	// pour ne pas mélanger la conso "test perso" et la conso agrégée des
	// abonnés dans la facturation Cloudflare.
	//
	// Quota 10 Go/mois suivi en octets réels côté service de mint, via
	// l'API Analytics GraphQL de Cloudflare (chaque credential est tagué
	// d'un customIdentifier au mint) ; le nombre de sessions par abonnement
	// ne sert plus que de filet de sécurité anti-abus si l'Analytics est
	// indisponible.

	const MANAGED_TURN_MINT_URL = 'https://turn.vento.ovh/mint';
	const MANAGED_TURN_TRIAL_URL = 'https://turn.vento.ovh/mint-trial';
	const MANAGED_TURN_STATUS_URL = 'https://turn.vento.ovh/status';

	public static function isManagedTrialStarted() {
		return config::byKey('managedTurnTrialStarted', 'JeedomConnect', '') != '';
	}

	/**
	 * Pose le jalon de départ de l'essai gratuit - déclenché explicitement
	 * par un bouton dédié dans la config du plugin, JAMAIS automatiquement
	 * à la première ouverture d'une caméra (l'utilisateur doit savoir
	 * consciemment que le délai de 7 jours démarre à cet instant).
	 */
	public static function startManagedTrial() {
		config::save('managedTurnTrialStarted', '1', 'JeedomConnect');
		// Enregistre immédiatement l'essai côté service de mint (sinon le
		// statut afficherait "non démarré" jusqu'à la première ouverture
		// réelle d'une caméra hors LAN) - consomme 1 session sur les 240
		// allouées, coût négligeable pour garantir un statut exact dès le
		// clic. Non bloquant : si le service est injoignable, le jalon
		// local reste posé et le premier essai réel réessaiera.
		try {
			self::mintManagedTurnCredentials(self::GO2RTC_ICE_SERVERS_TTL);
		} catch (Exception $e) {
			log::add('JeedomConnect', 'debug', 'startManagedTrial : pré-enregistrement essai échoué (non bloquant) : ' . $e->getMessage());
		}
	}

	/**
	 * Appel GET générique vers le service de mint managé - factorisé, utilisé
	 * par les trois chemins (statut, abonnement store, essai gratuit).
	 *
	 * @return array [decoded (array|null), httpCode (int), curlError (string), rawBody (string)]
	 */
	private static function curlManagedTurn($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$body = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return array(json_decode($body, true), $httpCode, $error, $body);
	}

	/**
	 * Transforme une réponse de mint en tableau iceServers, ou lève une
	 * Exception (en posant au passage l'alerte centre de messages
	 * correspondante si le service a renvoyé un code connu).
	 *
	 * @throws Exception
	 */
	private static function handleMintResponse($decoded, $httpCode, $body) {
		if ($httpCode >= 200 && $httpCode < 300 && !empty($decoded['iceServers'])) {
			// Mint reussi : l'abonnement/essai est valide et dans les clous -
			// on leve toute alerte precedente du centre de messages Jeedom
			// (l'utilisateur a corrige la situation - renouvellement,
			// nouvel abonnement, nouveau mois qui reinitialise le quota...).
			self::clearManagedTurnAlerts();
			return $decoded['iceServers'];
		}
		$msg = (is_array($decoded) ? ($decoded['error'] ?? null) : null) ?? $body;
		$code = is_array($decoded) ? ($decoded['code'] ?? '') : '';
		if ($code != '') {
			self::notifyManagedTurnIssue($code, $msg);
		}
		throw new Exception('Service TURN managé : ' . $msg);
	}

	/**
	 * Tente le mint via un abonnement Apple/Google (achat intégré) - lié
	 * directement par l'app au service de mint via hardware_key (voir
	 * /link-subscription côté app, src/services/iap.js) : ce plugin PHP n'a
	 * donc aucun état local à vérifier avant de tenter, la réponse du
	 * service fait foi.
	 *
	 * @return array|null iceServers si un abonnement actif existe, null si
	 *                      l'utilisateur n'a simplement aucun abonnement
	 *                      store lié (l'appelant retombe alors sur l'essai
	 *                      gratuit) - toute AUTRE erreur (abonnement
	 *                      résilié/expiré, quota dépassé...) est levée
	 *                      comme une vraie Exception : il ne faut jamais
	 *                      masquer un abonnement cassé derrière un message
	 *                      d'essai qui n'a rien à voir.
	 * @throws Exception
	 */
	private static function tryMintSubscription($ttlSeconds) {
		$url = self::MANAGED_TURN_MINT_URL . '?' . http_build_query(array(
			'hardware_key' => jeedom::getHardwareKey(),
			'ttl' => intval($ttlSeconds),
		));
		list($decoded, $httpCode, $error, $body) = self::curlManagedTurn($url);
		if ($error) {
			throw new Exception('Service TURN managé injoignable : ' . $error);
		}
		$code = is_array($decoded) ? ($decoded['code'] ?? '') : '';
		if ($code == 'no_subscription') {
			return null;
		}
		return self::handleMintResponse($decoded, $httpCode, $body);
	}

	/**
	 * Mine des credentials TURN pour l'offre managée - priorité à un
	 * abonnement Apple/Google actif, repli sur l'essai gratuit (7 jours /
	 * 4 Go, sans carte bancaire) à condition que startManagedTrial() ait
	 * déjà été appelé (bouton dédié). L'identifiant utilisé dans les deux
	 * cas est jeedom::getHardwareKey() - la "clé d'installation" du CORE
	 * Jeedom (pas générée par ce plugin), qui survit à une réinstallation
	 * du plugin contrairement à un identifiant que ce plugin aurait généré
	 * lui-même dans sa propre config.
	 *
	 * @param int $ttlSeconds durée de vie du credential généré
	 * @return array un tableau iceServers (même forme que
	 *                 mintTurnCredentials()) directement utilisable comme
	 *                 RTCPeerConnection({iceServers: ...})
	 * @throws Exception si ni abonnement ni essai démarré, si l'un des
	 *                     deux est invalide/expiré, le quota est dépassé,
	 *                     ou le service de mint est injoignable
	 */
	public static function mintManagedTurnCredentials($ttlSeconds) {
		$iceServers = self::tryMintSubscription($ttlSeconds);
		if ($iceServers !== null) {
			return $iceServers;
		}

		if (!self::isManagedTrialStarted()) {
			throw new Exception(__("Essai gratuit non démarré - cliquez sur \"Démarrer mon essai gratuit\" dans la configuration du plugin", __FILE__));
		}
		$url = self::MANAGED_TURN_TRIAL_URL . '?' . http_build_query(array(
			'hardware_key' => jeedom::getHardwareKey(),
			'ttl' => intval($ttlSeconds),
		));
		list($decoded, $httpCode, $error, $body) = self::curlManagedTurn($url);
		if ($error) {
			throw new Exception('Service TURN managé injoignable : ' . $error);
		}
		return self::handleMintResponse($decoded, $httpCode, $body);
	}

	// logicalId utilises pour le centre de messages Jeedom (message::add) -
	// un logicalId stable par motif permet a Jeedom de dedoublonner
	// automatiquement (occurrences++ / date mise a jour) plutot que de
	// spammer un nouveau message a chaque camera ouverte tant que le
	// probleme n'est pas corrige.
	const MANAGED_TURN_ALERT_LOGICAL_IDS = array(
		'subscription_inactive' => 'managedTurnSubscriptionInactive',
		'quota_exceeded' => 'managedTurnQuotaExceeded',
		'trial_expired' => 'managedTurnTrialExpired',
		'trial_quota_exceeded' => 'managedTurnTrialQuotaExceeded',
	);

	/**
	 * Pose une alerte dans le centre de messages Jeedom (bloque de facto le
	 * mode caméra hors LAN payant, puisque mintManagedTurnCredentials() a
	 * de toute facon leve une Exception au moment de cet appel - ceci n'est
	 * qu'un avertissement lisible pour l'utilisateur, pas un mecanisme de
	 * blocage supplementaire).
	 *
	 * @param string $code un des motifs connus (voir
	 *                       MANAGED_TURN_ALERT_LOGICAL_IDS), sinon message
	 *                       generique
	 * @param string $fallbackMsg message brut du service, utilise si $code
	 *                              n'est pas reconnu
	 */
	private static function notifyManagedTurnIssue($code, $fallbackMsg) {
		$messages = array(
			'subscription_inactive' => __("Votre abonnement JeedomConnect Cloud TURN est inactif, résilié ou expiré. Le mode caméra hors LAN payant est bloqué - abonnez-vous depuis l'application (Préférences > Caméras hors LAN).", __FILE__),
			'quota_exceeded' => __("Le quota mensuel de 10 Go de votre abonnement JeedomConnect Cloud TURN est atteint. Le mode caméra hors LAN payant est bloqué jusqu'au mois prochain.", __FILE__),
			'trial_expired' => __("Votre essai gratuit de 7 jours pour le mode caméra hors LAN payant est terminé. Abonnez-vous depuis la configuration du plugin pour continuer à l'utiliser.", __FILE__),
			'trial_quota_exceeded' => __("Le quota de 4 Go de votre essai gratuit pour le mode caméra hors LAN payant est atteint. Abonnez-vous depuis la configuration du plugin pour continuer à l'utiliser.", __FILE__),
		);
		$logicalId = self::MANAGED_TURN_ALERT_LOGICAL_IDS[$code] ?? 'managedTurnError';
		$message = $messages[$code] ?? $fallbackMsg;
		message::add('JeedomConnect', $message, '', $logicalId);
	}

	/**
	 * Leve toutes les alertes managed-turn posees precedemment - appele au
	 * premier mint reussi apres un incident (l'utilisateur a corrige la
	 * situation).
	 */
	private static function clearManagedTurnAlerts() {
		foreach (self::MANAGED_TURN_ALERT_LOGICAL_IDS as $logicalId) {
			message::removeByPluginLogicalId('JeedomConnect', $logicalId);
		}
	}

	/**
	 * Statut lisible par l'utilisateur (abonnement store ou essai) pour
	 * affichage dans la page de config et dans l'écran Abonnement de
	 * l'app - lecture seule côté service de mint, ne consomme aucun
	 * quota. Le service de mint priorise lui-même l'abonnement store sur
	 * l'essai s'il existe (voir _status_subscription côté VPS).
	 *
	 * @return array toujours un tableau associatif normalisé pour le JS :
	 *                {mode:'subscription', status:'active'|..., ...} ou
	 *                {mode:'trial', started:bool, ...}
	 * @throws Exception si le service de mint est injoignable
	 */
	public static function getManagedTurnStatus() {
		$url = self::MANAGED_TURN_STATUS_URL . '?' . http_build_query(array('hardware_key' => jeedom::getHardwareKey()));
		list($decoded, $httpCode, $error) = self::curlManagedTurn($url);
		if ($error || $httpCode < 200 || $httpCode >= 300) {
			throw new Exception('Service TURN managé injoignable : ' . ($error ?: ('HTTP ' . $httpCode)));
		}
		return $decoded;
	}

	/*     * ********************** WEBRTC BRIDGE (HTTP -> go2rtc/api/ws) ******** */

	// Même venv Python que le démon principal (resources/requirements.txt y
	// inclut websocket-client, nécessaire à webrtcBridge.py) - installé via le
	// même flux "réinstaller les dépendances" que JeedomConnectd.py, pas de
	// nouvelle étape d'installation pour l'utilisateur.
	private static function getPythonPath() {
		return __DIR__ . '/../../resources/venv/bin/python3';
	}

	private static function getBridgeScriptPath() {
		return __DIR__ . '/../../resources/webrtcBridge.py';
	}

	public static function getBridgePort() {
		return intval(config::byKey('go2rtcBridgePort', 'JeedomConnect', 1985));
	}

	private static function getBridgeLocalUrl() {
		return 'http://127.0.0.1:' . self::getBridgePort();
	}

	private static function getBridgePidFile() {
		return jeedom::getTmpFolder(__CLASS__) . '/webrtc_bridge.pid';
	}

	/*     * ********************** INSTALL / BINARY *************************** */

	public static function isInstalled() {
		$bin = self::getBinaryPath();
		if (!file_exists($bin)) {
			return false;
		}
		if (!is_executable($bin)) {
			// Le bit exécutable peut être perdu après coup (ex. une opération
			// externe qui réinitialise les droits du dossier plugin) sans que le
			// binaire lui-même soit corrompu - se contenter d'un chmod plutôt que
			// de re-télécharger 5+ Mo inutilement.
			@chmod($bin, 0755);
			clearstatcache(true, $bin);
		}
		return is_executable($bin);
	}

	private static function getBinaryAsset() {
		switch (php_uname('m')) {
			case 'x86_64':
				return 'go2rtc_linux_amd64';
			case 'aarch64':
				return 'go2rtc_linux_arm64';
			case 'armv7l':
				return 'go2rtc_linux_arm';
			default:
				throw new Exception(__('Architecture non supportée pour go2rtc : ', __FILE__) . php_uname('m'));
		}
	}

	public static function install() {
		if (!is_dir(self::$_bin_dir)) {
			mkdir(self::$_bin_dir, 0755, true);
		}

		$filename = self::getBinaryAsset();
		JCLog::debug('go2rtc install - asset : ' . $filename);

		$sh_path = realpath(__DIR__ . '/../../resources/installGo2rtc.sh');
		// Invoqué via `sh <script>` plutôt qu'en exécution directe : ne
		// nécessite que la permission de lecture sur le script, pas le bit
		// exécutable - évite un "Permission denied" selon comment/par qui le
		// fichier a été déposé sur le serveur (chmod échoue silencieusement si
		// le process PHP n'est pas propriétaire du fichier).
		$cmd = 'sh ' . escapeshellarg($sh_path) . ' ' . escapeshellarg(self::GO2RTC_TAG)
			. ' ' . escapeshellarg($filename) . ' ' . escapeshellarg(self::getBinaryPath())
			. ' >> ' . log::getPathToLog('JeedomConnect_go2rtc') . ' 2>&1';
		JCLog::debug('go2rtc install cmd : ' . $cmd);
		shell_exec($cmd);

		if (!self::isInstalled()) {
			throw new Exception(__("Impossible de télécharger le binaire go2rtc, vérifiez le log", __FILE__));
		}
	}

	/*     * ********************** DAEMON MANAGEMENT *************************** */

	public static function info() {
		$return = array();
		$return['log'] = 'JeedomConnect_go2rtc';
		$return['state'] = 'nok';

		$pid_file = self::getPidFile();
		if (file_exists($pid_file)) {
			if (@posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				@unlink($pid_file);
			}
		}
		return $return;
	}

	private static function writeConfig() {
		if (!is_dir(self::$_bin_dir)) {
			mkdir(self::$_bin_dir, 0755, true);
		}
		// streams vide au démarrage : les caméras sont enregistrées
		// dynamiquement via l'API HTTP de go2rtc (registerStream()), qui les
		// persiste elle-même dans ce fichier (PUT /api/streams écrit sa
		// propre config) - pas besoin de les lister ici.
		//
		// allow_paths restreint l'API exposée au strict nécessaire :
		// /api/streams (utilisé uniquement en local par ce plugin PHP pour
		// enregistrer les flux) et /api/ws (signaling WebRTC en trickle ICE -
		// accès direct en LAN depuis la WebView, ou via webrtcBridge.py en
		// local hors LAN, voir Go2rtc::openWebrtcSession). Ferme /api/webrtc
		// (échange SDP figé, plus utilisé), /api/config (lecture/écriture de
		// toute la config sans authentification), /api/restart, /api/exit et
		// l'UI web statique.
		// Attention : allow_paths ne distingue pas l'appelant (local vs LAN)
		// - /api/streams reste donc atteignable depuis le LAN et expose les
		// URLs sources (identifiants RTSP inclus le cas échéant) ; fermer
		// complètement ce point nécessiterait une authentification
		// (volontairement hors scope de cette passe, voir plan go2rtc).
		// webrtc.candidates: "stun:8555" - fixe le port UDP/TCP média sur 8555
		// (déjà la valeur par défaut de go2rtc) et annonce l'adresse publique
		// découverte par STUN SUR CE PORT PRÉCIS, plutôt que de laisser go2rtc
		// annoncer le port éphémère attribué à chaque nouvelle requête STUN.
		// Utile seulement si l'utilisateur redirige un jour ce port sur sa box
		// (non requis pour l'usage courant LAN + pont HTTP hors LAN).
		//
		// webrtc.ice_servers (POC 'webrtc-remote') : go2rtc tourne sur la box
		// Jeedom, elle-même derrière NAT sans port ouvert (cf. investigation MSE dans
		// webrtcPlayer.js) - il a donc besoin de sa PROPRE entrée TURN pour
		// relayer sa moitié du média, pas seulement l'app - et c'est cette
		// moitié qui porte le gros du volume réel (go2rtc relaie le flux
		// caméra complet, pas juste de la signalisation). Doit donc utiliser
		// le MÊME fournisseur que turnMode, sans quoi le trafic réel passerait
		// par la mauvaise Turn Key (perso au lieu de managée, ou inversement),
		// sans passer par la validation abonnement/quota/essai côté managé.
		// Credential longue durée (pas celle, courte, de
		// mintClientTurnCredentials côté app) car cette config est statique -
		// lue au démarrage du démon, jamais rafraîchie à chaud. Minée une
		// seule fois ici : si le token expire avant le prochain redémarrage
		// du démon, régénérer ce fichier (le supprimer puis Go2rtc::start())
		// le renouvelle - pas de rotation automatique dans ce POC. Côté
		// managé, ce mint consomme 1 session du quota/essai à chaque
		// (re)démarrage du démon (voir MAX_TTL côté service VPS pour que ce
		// credential 24h ne soit pas tronqué à 10 min).
		$webrtcYaml = "webrtc:\n"
			. "  candidates:\n"
			. "    - stun:8555\n";
		try {
			$turnMode = config::byKey('turnMode', 'JeedomConnect', 'cloudflare');
			// mintClientTurnCredentials()/mintTurnCredentials() renvoie une
			// LISTE de descripteurs (vérifié en conditions réelles : un STUN
			// sans username/credential, un TURN avec) - UNE SEULE clé
			// "ice_servers:", avec un item de liste par descripteur. La
			// répéter à chaque itération produirait un YAML invalide (clé de
			// mapping dupliquée sous webrtc:), silencieusement écrasée/mal
			// interprétée par le parseur de go2rtc.
			$iceServers = self::mintClientTurnCredentials($turnMode, self::GO2RTC_ICE_SERVERS_TTL);
			$webrtcYaml .= "  ice_servers:\n";
			foreach ($iceServers as $iceServer) {
				$urls = array_map(function ($u) {
					return self::yamlSingleQuote($u);
				}, (array) ($iceServer['urls'] ?? array()));
				$webrtcYaml .= "    - urls: [ " . implode(', ', $urls) . " ]\n";
				// username/credential absents pour l'entrée STUN (pas
				// d'authentification requise) - ne les écrire que s'ils
				// existent réellement.
				if (!empty($iceServer['username'])) {
					$webrtcYaml .= "      username: " . self::yamlSingleQuote($iceServer['username']) . "\n"
						. "      credential: " . self::yamlSingleQuote($iceServer['credential'] ?? '') . "\n";
				}
			}
		} catch (Exception $e) {
			JCLog::warning('go2rtc: échec du mint TURN pour sa propre config - ' . $e->getMessage());
		}

		$yaml = "api:\n"
			. "  listen: \":" . self::getPort() . "\"\n"
			. "  allow_paths:\n"
			. "    - /api/streams\n"
			. "    - /api/ws\n"
			. $webrtcYaml
			. "streams:\n";
		file_put_contents(self::getConfigPath(), $yaml);
	}

	public static function start() {
		if (!self::isInstalled()) {
			throw new Exception(__("go2rtc n'est pas installé. Veuillez réinstaller les dépendances", __FILE__));
		}

		self::stop();
		JCLog::info('Starting go2rtc daemon');

		// Ne réécrit le fichier de config que s'il n'existe pas déjà : un
		// redémarrage (ex. après un simple restart du plugin) ne doit pas
		// effacer les streams que go2rtc y a lui-même persistés via ses PUT
		// précédents (voir writeConfig()).
		if (!file_exists(self::getConfigPath())) {
			self::writeConfig();
		}

		$cmd = escapeshellarg(self::getBinaryPath()) . ' -config ' . escapeshellarg(self::getConfigPath());
		$pidFile = self::getPidFile();
		exec($cmd . ' >> ' . log::getPathToLog('JeedomConnect_go2rtc') . ' 2>&1 & echo $! > ' . escapeshellarg($pidFile));

		$i = 0;
		while ($i < 10) {
			$info = self::info();
			if ($info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 10) {
			log::add('JeedomConnect', 'error', __('Impossible de démarrer go2rtc, vérifiez le log', __FILE__), 'unableStartGo2rtc');
			return false;
		}
		message::removeAll('JeedomConnect', 'unableStartGo2rtc');
		return true;
	}

	public static function stop() {
		JCLog::info('Stopping go2rtc daemon');
		$pid_file = self::getPidFile();
		if (file_exists($pid_file)) {
			$pid = intval(trim(file_get_contents($pid_file)));
			system::kill($pid);
			@unlink($pid_file);
		}
		system::kill('go2rtc -config');
		self::stopBridge();
		sleep(1);
	}

	private static function ensureStarted() {
		// Deux widgets caméra enregistrés à quelques instants d'intervalle
		// peuvent chacun déclencher leur propre saveConfig() -> registerStream()
		// -> ensureStarted() dans des requêtes PHP concurrentes : sans verrou,
		// les deux peuvent voir isInstalled()==false en même temps et lancer
		// chacun leur propre téléchargement/installation en parallèle (écritures
		// concurrentes sur le même fichier binaire, risque d'échec transitoire).
		$lock = new JeedomConnectLock('Go2rtc_ensureStarted');
		try {
			if (!$lock->Lock()) {
				JCLog::warning('go2rtc: verrou ensureStarted non obtenu - une autre requête le démarre probablement déjà');
				return;
			}
			if (!self::isInstalled()) {
				self::install();
			}
			$info = self::info();
			if ($info['state'] != 'ok') {
				self::start();
			}
		} finally {
			unset($lock);
		}
	}

	/*     * ********************** STREAM REGISTRATION *************************** */

	private static function curlRequest($url, $method) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		$body = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($error) {
			JCLog::warning('go2rtc API error (' . $method . ' ' . $url . ') => ' . $error);
			return;
		}
		// Un curl_error() vide ne veut dire que "requête HTTP terminée" -
		// sans ça, un rejet applicatif de go2rtc sur un code non-2xx (ex.
		// source refusée) passe complètement inaperçu (ni log, ni exception).
		if ($httpCode < 200 || $httpCode >= 300) {
			JCLog::warning('go2rtc API rejet (' . $method . ' ' . $url . ') => HTTP ' . $httpCode . ' : ' . $body);
		}
	}

	private static function bridgeInfo() {
		$return = array();
		$return['state'] = 'nok';
		$pid_file = self::getBridgePidFile();
		if (file_exists($pid_file)) {
			if (@posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				@unlink($pid_file);
			}
		}
		return $return;
	}

	private static function startBridge() {
		if (!file_exists(self::getPythonPath())) {
			throw new Exception(__("VENV n'est pas disponible. Veuillez réinstaller les dépendances", __FILE__));
		}

		self::stopBridge();
		JCLog::info('Starting webrtc bridge');

		$cmd = escapeshellarg(self::getPythonPath()) . ' ' . escapeshellarg(self::getBridgeScriptPath())
			. ' --port ' . self::getBridgePort()
			. ' --go2rtcport ' . self::getPort()
			. ' --pid ' . escapeshellarg(self::getBridgePidFile());
		exec($cmd . ' >> ' . log::getPathToLog('JeedomConnect_webrtc_bridge') . ' 2>&1 &');

		$i = 0;
		while ($i < 10) {
			if (self::bridgeInfo()['state'] == 'ok') {
				break;
			}
			usleep(200000);
			$i++;
		}
		if ($i >= 10) {
			log::add('JeedomConnect', 'error', __('Impossible de démarrer le pont WebRTC, vérifiez le log', __FILE__), 'unableStartWebrtcBridge');
			return false;
		}
		message::removeAll('JeedomConnect', 'unableStartWebrtcBridge');
		return true;
	}

	private static function stopBridge() {
		$pid_file = self::getBridgePidFile();
		if (file_exists($pid_file)) {
			$pid = intval(trim(file_get_contents($pid_file)));
			system::kill($pid);
			@unlink($pid_file);
		}
		system::kill('webrtcBridge.py');
	}

	/**
	 * Un process Python déjà lancé ne recharge jamais son propre fichier -
	 * si webrtcBridge.py a été modifié depuis (mise à jour du plugin, ou
	 * itération de dev) après le démarrage du process actuel, il tourne
	 * avec l'ancien code sans qu'aucun signe extérieur ne le montre (jusqu'à
	 * ce qu'une route ait changé et réponde 404, par ex.). Comparer la date
	 * du script à celle du pidfile (écrit par le script à son démarrage)
	 * permet de détecter ce décalage et de forcer un redémarrage tout seul,
	 * sans action manuelle.
	 */
	private static function isBridgeStale() {
		$pidFile = self::getBridgePidFile();
		$script = self::getBridgeScriptPath();
		if (!file_exists($pidFile) || !file_exists($script)) {
			return false;
		}
		return filemtime($script) > filemtime($pidFile);
	}

	/**
	 * Démarre go2rtc ET le pont HTTP, nécessaires tous les deux pour le
	 * signaling hors LAN (voir apiHelper::cameraStreamOpen et consorts).
	 */
	private static function ensureBridgeStarted() {
		self::ensureStarted();
		if (self::bridgeInfo()['state'] == 'ok' && self::isBridgeStale()) {
			JCLog::info('webrtc bridge: script modifié depuis le dernier démarrage - redémarrage');
			self::stopBridge();
		}
		if (self::bridgeInfo()['state'] != 'ok') {
			if (!self::startBridge()) {
				throw new Exception(__("Impossible de démarrer le pont WebRTC", __FILE__));
			}
		}
	}

	private static function bridgeCurl($path, $query = '') {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, self::getBridgeLocalUrl() . $path . ($query ? '?' . $query : ''));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		// Généreux : /open attend que le pont ait fini de se connecter à
		// go2rtc (jusqu'à 10s côté Python, cf. webrtcBridge.py) - laisser de
		// la marge pour ne pas couper juste avant que ça réponde.
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		return $ch;
	}

	/**
	 * Ouvre une session vers go2rtc/api/ws pour un widget caméra, pour le
	 * compte de l'app quand elle n'est pas sur le LAN (voir
	 * apiHelper::cameraStreamOpen - méthode JSON-RPC "CAMERA_STREAM_OPEN").
	 * En LAN, l'app se connecte en direct à go2rtc (voir webrtcPlayer.js).
	 * Générique : $message est le premier message envoyé à go2rtc une fois
	 * la session ouverte - {type:"webrtc/offer",...} pour le signaling
	 * WebRTC, {type:"mse",...} pour démarrer un flux vidéo MSE (voir
	 * webrtcPlayer.js pour le détail des deux usages).
	 *
	 * @return string l'identifiant de session à réutiliser pour
	 *                 sendToSession()/pollSession()/closeSession()
	 * @throws Exception si go2rtc ou le pont sont injoignables
	 */
	public static function openSession($widgetId, $message) {
		self::ensureBridgeStarted();

		$ch = self::bridgeCurl('/open', 'widgetId=' . rawurlencode($widgetId));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('message' => $message)));
		$body = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($error) {
			throw new Exception('Erreur réseau vers le pont WebRTC : ' . $error);
		}
		$decoded = json_decode($body, true);
		if ($httpCode != 200 || !is_array($decoded) || empty($decoded['sessionId'])) {
			throw new Exception('Pont WebRTC : réponse invalide (' . $httpCode . ') : ' . $body);
		}
		return $decoded['sessionId'];
	}

	public static function sendToSession($sessionId, $message) {
		$ch = self::bridgeCurl('/send', 'sessionId=' . rawurlencode($sessionId));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('message' => $message)));
		curl_exec($ch);
		curl_close($ch);
	}

	/**
	 * @return array la liste des messages reçus de go2rtc depuis le dernier
	 *                appel, chacun sous la forme {kind:"json", data:{...}}
	 *                ou {kind:"binary", data:"<base64>"} (fragments MSE).
	 */
	public static function pollSession($sessionId) {
		$ch = self::bridgeCurl('/poll', 'sessionId=' . rawurlencode($sessionId));
		$body = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$decoded = json_decode($body, true);
		if ($httpCode != 200 || !is_array($decoded)) {
			throw new Exception('Pont WebRTC : session introuvable ou expirée');
		}
		return $decoded['messages'] ?? array();
	}

	public static function closeSession($sessionId) {
		$ch = self::bridgeCurl('/close', 'sessionId=' . rawurlencode($sessionId));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_exec($ch);
		curl_close($ch);
	}

	/**
	 * Enregistre (ou désenregistre) le flux d'un widget caméra auprès de
	 * go2rtc, à appeler depuis JeedomConnectWidget::updateWidgetConfig()
	 * chaque fois qu'un widget de type "camera" est sauvegardé.
	 *
	 * @param string|int $widgetId
	 * @param array $conf configuration complète du widget (type, streamUrl,
	 *                     streamUrlInfo, username, password, webrtcEnabled...)
	 */
	public static function registerStream($widgetId, $conf) {
		if (empty($conf['webrtcEnabled'])) {
			self::unregisterStream($widgetId);
			return;
		}

		$url = JeedomConnectWidget::resolveConfUrl($conf, 'streamUrl', 'streamUrlInfo');
		if (!is_string($url) || $url == '') {
			JCLog::warning('go2rtc: pas de streamUrl pour le widget ' . $widgetId . ' - webrtcEnabled ignoré');
			return;
		}

		$replaceArr = array(
			'#username#' => rawurlencode($conf['username'] ?? ''),
			'#password#' => rawurlencode($conf['password'] ?? ''),
		);
		$url = str_replace(array_keys($replaceArr), $replaceArr, $url);

		try {
			self::ensureStarted();
		} catch (Exception $e) {
			JCLog::error('go2rtc: impossible de démarrer le démon - ' . $e->getMessage());
			return;
		}

		$apiUrl = self::getLocalApiUrl() . '/api/streams?name=' . rawurlencode(self::streamName($widgetId))
			. '&src=' . rawurlencode($url);
		self::curlRequest($apiUrl, 'PUT');
	}

	public static function unregisterStream($widgetId) {
		if (!self::isInstalled()) {
			return;
		}
		$info = self::info();
		if ($info['state'] != 'ok') {
			return;
		}
		$apiUrl = self::getLocalApiUrl() . '/api/streams?src=' . rawurlencode(self::streamName($widgetId));
		self::curlRequest($apiUrl, 'DELETE');
	}
}
