<?php

/* * ***************************Includes********************************* */

require_once __DIR__ . '/JeedomConnectWidget.class.php';
require_once __DIR__ . '/JeedomConnectLogs.class.php';
require_once __DIR__ . '/JeedomConnectLock.class.php';

/**
 * Accès hors-LAN aux widgets webview via un tunnel Cloudflare - remplace
 * l'ancien proxy PHP à réécriture de texte (approche abandonnée).
 *
 * Topologie : cloudflared DOIT tourner sur CETTE box Jeedom (seule machine
 * qui voit son propre LAN) - démon géré par cette classe, comme go2rtc
 * (Go2rtc.class.php), dont elle reprend délibérément la structure
 * (install()/start()/stop()/info()/ensureStarted() avec le même verrou
 * JeedomConnectLock contre l'installation concurrente).
 *
 * Contrairement à go2rtc, pas de fichier de config local à écrire : un
 * tunnel "remotely-managed" ne prend qu'un jeton en argument
 * (`cloudflared tunnel run --token ...`) - toute la logique dynamique
 * (règles d'ingress par widget) est gérée à distance, via l'API Cloudflare,
 * par le service VPS géré (voir MANAGED_TUNNEL_*_URL) : ce plugin n'appelle
 * JAMAIS l'API Cloudflare directement (le token API Cloudflare du palier
 * géré ne doit jamais vivre dans du code lisible par tout admin Jeedom).
 *
 * Un hostname public dédié par WIDGET (pas par installation - un ingress
 * Cloudflare Tunnel ne route que par hostname, jamais par chemin). Réservé
 * au palier géré (abonnement Store IAP) : contrairement au TURN "BYO"
 * (compte Cloudflare personnel de l'utilisateur), il n'existe pas de mode
 * auto-hébergé pour cette fonctionnalité - créer/gérer soi-même un tunnel
 * Cloudflare par widget serait bien trop lourd pour un utilisateur non
 * technique.
 */
class CloudflareTunnel {

	const MANAGED_TUNNEL_ROUTE_URL = 'https://turn.jeedomconnect.stream/tunnel/route';
	const MANAGED_TUNNEL_STATUS_URL = 'https://turn.jeedomconnect.stream/tunnel/status';

	const CLOUDFLARED_TAG = '2026.8.2';

	public static $_bin_dir = __DIR__ . '/../../resources/cloudflared/';

	public static function getBinaryPath() {
		return self::$_bin_dir . 'cloudflared';
	}

	public static function getPidFile() {
		return jeedom::getTmpFolder(__CLASS__) . '/cloudflared.pid';
	}

	/*     * ********************** SERVICE VPS GÉRÉ (provisionnement) ******** */

	private static function curlManagedTunnel($method, $url, $payload = null) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		if ($method != 'GET') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}
		if ($payload !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		}
		$body = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return array(json_decode($body, true), $httpCode, $error, $body);
	}

	/**
	 * Enregistre (ou met à jour) la route tunnel d'un widget webview -
	 * appelée depuis JeedomConnectWidget::saveConfig(), au même chokepoint
	 * que Go2rtc::registerStream() pour les caméras. Provisionne le tunnel
	 * de l'installation au tout premier appel (voir _provision_tunnel côté
	 * service VPS), réutilisé pour tous les widgets webview suivants -
	 * SEUL le hostname est spécifique à CE widget (un ingress Cloudflare
	 * Tunnel ne retire jamais un préfixe de chemin avant de transmettre à
	 * la cible, un hostname/widget est donc obligatoire, pas un chemin
	 * partagé - voir la note détaillée côté service VPS).
	 *
	 * @param int $widgetId
	 * @param string $target URL locale complète de la cible (localUrl du widget)
	 * @return string le hostname public tunnelé pour CE widget précis
	 * @throws Exception
	 */
	public static function registerRoute($widgetId, $target) {
		$payload = array(
			'hardwareKey' => jeedom::getHardwareKey(),
			'widgetId' => strval($widgetId),
			'target' => $target,
		);
		list($decoded, $httpCode, $error, $body) = self::curlManagedTunnel('POST', self::MANAGED_TUNNEL_ROUTE_URL, $payload);
		if ($error) {
			throw new Exception('Service tunnel injoignable : ' . $error);
		}
		if ($httpCode < 200 || $httpCode >= 300) {
			$msg = (is_array($decoded) ? ($decoded['error'] ?? null) : null) ?? $body;
			throw new Exception('Service tunnel : ' . $msg);
		}

		// Le jeton cloudflared ne change que lors du tout premier
		// provisionnement de l'installation (ou d'une reprovisionnement
		// manuelle côté VPS) - ne redémarrer le démon local que s'il a
		// réellement changé, pas à chaque simple sauvegarde de widget.
		$previousToken = config::byKey('cloudflareTunnelToken', 'JeedomConnect', '');
		if (!empty($decoded['tunnelToken']) && $decoded['tunnelToken'] != $previousToken) {
			config::save('cloudflareTunnelToken', $decoded['tunnelToken'], 'JeedomConnect');
			self::ensureStarted(true);
		} else {
			self::ensureStarted(false);
		}

		// Le hostname est SPÉCIFIQUE à ce widget : à l'appelant
		// (saveConfig()) de le persister sur la conf DU WIDGET, pas ici
		// (cette classe ne manipule pas directement la conf des widgets).
		// Plus de Cloudflare Access/service token (abandonné le
		// 2026-09-17 - voir la note détaillée côté service VPS,
		// _provision_tunnel) : le hostname lui-même (96 bits d'entropie)
		// fait office de seul secret.
		return $decoded['hostname'] ?? '';
	}

	/**
	 * Retire la route tunnel d'un widget - ne touche jamais au
	 * tunnel/hostname de l'installation eux-mêmes (partagés avec
	 * d'éventuels autres widgets webview), juste la règle d'ingress de CE
	 * widget précis (le CNAME DNS, lui, dépend de $permanent). Non
	 * bloquant : un échec ici laisse au pire une règle orpheline côté
	 * Cloudflare (nettoyable manuellement plus tard côté /admin), jamais
	 * une sauvegarde/suppression de widget en échec pour cette seule
	 * raison - même principe que Go2rtc::unregisterStream.
	 *
	 * @param bool $permanent true SEULEMENT quand le widget est
	 *   réellement supprimé (JeedomConnectWidget::removeWidget) - le CNAME
	 *   DNS est alors définitivement retiré, sans quoi il resterait
	 *   orphelin indéfiniment (rien d'autre ne le nettoie). false (défaut)
	 *   pour le cas localUrl vidée/remplacée par une url publique : le
	 *   widget existe toujours et sera très probablement réenregistré
	 *   bientôt avec une nouvelle cible, inutile de payer un appel API
	 *   DNS supplémentaire pour ça.
	 */
	public static function unregisterRoute($widgetId, $permanent = false) {
		$url = self::MANAGED_TUNNEL_ROUTE_URL . '?' . http_build_query(array(
			'hardware_key' => jeedom::getHardwareKey(),
			'widget_id' => strval($widgetId),
			'permanent' => $permanent ? '1' : '0',
		));
		list(, $httpCode, $error) = self::curlManagedTunnel('DELETE', $url);
		if ($error || $httpCode < 200 || $httpCode >= 300) {
			JCLog::warning('CloudflareTunnel::unregisterRoute échec (non bloquant) pour le widget ' . $widgetId . ' : ' . ($error ?: ('HTTP ' . $httpCode)));
		}
	}

	public static function getStatus() {
		$url = self::MANAGED_TUNNEL_STATUS_URL . '?' . http_build_query(array('hardware_key' => jeedom::getHardwareKey()));
		list($decoded, $httpCode, $error) = self::curlManagedTunnel('GET', $url);
		if ($error || $httpCode < 200 || $httpCode >= 300) {
			throw new Exception('Service tunnel injoignable : ' . ($error ?: ('HTTP ' . $httpCode)));
		}
		return $decoded;
	}

	/**
	 * Vue d'ensemble pour l'UI (modale "Services de streaming") : statut
	 * VPS (quota, widgets tunnelés avec leur hostname) enrichi du nom de
	 * chaque widget - le service VPS ne connaît que l'id (jamais le nom,
	 * il ne stocke que ce qui lui est nécessaire pour router le trafic).
	 *
	 * @return array{provisioned: bool, quota: int, widgets: array<array{id:string,name:string,hostname:string}>}
	 */
	public static function getWidgetsOverview() {
		$status = self::getStatus();
		$widgets = array();
		foreach (($status['widgets'] ?? array()) as $widgetId => $hostname) {
			$conf = JeedomConnectWidget::getConfiguration($widgetId, '', null);
			$name = (!empty($conf['nameDisplayed']) ? $conf['nameDisplayed'] : ($conf['name'] ?? null)) ?? ('#' . $widgetId);
			$widgets[] = array(
				'id' => $widgetId,
				'name' => $name,
				'hostname' => $hostname,
			);
		}
		return array(
			'provisioned' => $status['provisioned'] ?? false,
			'quota' => $status['quota'] ?? 0,
			'widgets' => $widgets,
		);
	}

	/*     * ********************** INSTALL / BINARY *************************** */

	public static function isInstalled() {
		$bin = self::getBinaryPath();
		if (!file_exists($bin)) {
			return false;
		}
		if (!is_executable($bin)) {
			@chmod($bin, 0755);
			clearstatcache(true, $bin);
		}
		return is_executable($bin);
	}

	private static function getBinaryAsset() {
		switch (php_uname('m')) {
			case 'x86_64':
				return 'cloudflared-linux-amd64';
			case 'aarch64':
				return 'cloudflared-linux-arm64';
			case 'armv7l':
				return 'cloudflared-linux-arm';
			default:
				throw new Exception(__('Architecture non supportée pour cloudflared : ', __FILE__) . php_uname('m'));
		}
	}

	public static function install() {
		if (!is_dir(self::$_bin_dir)) {
			mkdir(self::$_bin_dir, 0755, true);
		}

		$filename = self::getBinaryAsset();
		JCLog::debug('cloudflared install - asset : ' . $filename);

		$sh_path = realpath(__DIR__ . '/../../resources/installCloudflaredTunnel.sh');
		$cmd = 'sh ' . escapeshellarg($sh_path) . ' ' . escapeshellarg(self::CLOUDFLARED_TAG)
			. ' ' . escapeshellarg($filename) . ' ' . escapeshellarg(self::getBinaryPath())
			. ' >> ' . log::getPathToLog('JeedomConnect_cloudflared') . ' 2>&1';
		JCLog::debug('cloudflared install cmd : ' . $cmd);
		shell_exec($cmd);

		if (!self::isInstalled()) {
			throw new Exception(__("Impossible de télécharger le binaire cloudflared, vérifiez le log", __FILE__));
		}
	}

	/*     * ********************** DAEMON MANAGEMENT *************************** */

	public static function info() {
		$return = array();
		$return['log'] = 'JeedomConnect_cloudflared';
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

	public static function start() {
		if (!self::isInstalled()) {
			throw new Exception(__("cloudflared n'est pas installé. Veuillez réinstaller les dépendances", __FILE__));
		}
		$token = config::byKey('cloudflareTunnelToken', 'JeedomConnect', '');
		if ($token == '') {
			throw new Exception(__("Aucun tunnel provisionné pour cette installation", __FILE__));
		}

		self::stop();
		JCLog::info('Starting cloudflared daemon');

		// "remotely-managed" : aucun fichier de config local, le jeton
		// suffit - toute la logique dynamique (règles d'ingress par widget)
		// vit côté API Cloudflare, mise à jour par le service VPS géré à
		// chaque registerRoute()/unregisterRoute(), sans jamais avoir à
		// redémarrer ce démon pour une simple modification de routage.
		$cmd = escapeshellarg(self::getBinaryPath()) . ' tunnel run --token ' . escapeshellarg($token);
		$pidFile = self::getPidFile();
		exec($cmd . ' >> ' . log::getPathToLog('JeedomConnect_cloudflared') . ' 2>&1 & echo $! > ' . escapeshellarg($pidFile));

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
			log::add('JeedomConnect', 'error', __('Impossible de démarrer cloudflared, vérifiez le log', __FILE__), 'unableStartCloudflared');
			return false;
		}
		message::removeAll('JeedomConnect', 'unableStartCloudflared');
		return true;
	}

	public static function stop() {
		JCLog::info('Stopping cloudflared daemon');
		$pid_file = self::getPidFile();
		if (file_exists($pid_file)) {
			$pid = intval(trim(file_get_contents($pid_file)));
			system::kill($pid);
			@unlink($pid_file);
		}
		system::kill('cloudflared tunnel run');
		sleep(1);
	}

	/**
	 * @param bool $forceRestart redémarre même si le démon tourne déjà
	 *                             (utilisé quand le jeton vient de changer -
	 *                             cloudflared ne relit pas un nouveau jeton
	 *                             à chaud).
	 */
	public static function ensureStarted($forceRestart = false) {
		// Même schéma que Go2rtc::ensureStarted : deux widgets webview
		// enregistrés à quelques instants d'intervalle peuvent chacun
		// déclencher leur propre saveConfig() -> registerRoute() ->
		// ensureStarted() dans des requêtes PHP concurrentes.
		$lock = new JeedomConnectLock('CloudflareTunnel_ensureStarted');
		try {
			if (!$lock->Lock()) {
				JCLog::warning('cloudflared: verrou ensureStarted non obtenu - une autre requête le démarre probablement déjà');
				return;
			}
			if (!self::isInstalled()) {
				self::install();
			}
			$info = self::info();
			if ($forceRestart || $info['state'] != 'ok') {
				self::start();
			}
		} finally {
			unset($lock);
		}
	}
}
