<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/JeedomConnectWidget.class.php';
require_once dirname(__FILE__) . '/JeedomConnectActions.class.php';
require_once dirname(__FILE__) . '/JeedomConnectUtils.class.php';
require_once dirname(__FILE__) . '/JeedomConnectLogs.class.php';
require_once dirname(__FILE__) . '/JeedomConnectDeviceControl.class.php';
require_once dirname(__FILE__) . '/JeedomConnectAutomations.class.php';
require_once dirname(__FILE__) . '/apiHelper.class.php';

class JeedomConnect extends eqLogic {

	public static $_widgetPossibility = array('custom' => true);
	/*     * *************************Attributs****************************** */

	private const PYTHON_PATH = __DIR__ . '/../../resources/venv/bin/python3';
	public static $_initialConfig = array(
		'type' => 'JEEDOM_CONFIG',
		'formatVersion' => '1.0',
		'idCounter' => 0,
		'payload' => array(
			'configVersion' => 0,
			'tabs' => array(),
			'sections' => array(),
			'rooms' => array(),
			'groups' => array(),
			'summaries' => array(),
			'widgets' => array()
		)
	);

	public static $_notifConfig = array(
		'idCounter' => 0,
		'channels' => array(
			array(
				'id' => 'default',
				'name' => 'Défaut'
			)
		),
		'notifs' => array(
			array(
				'id' => 'defaultNotif',
				'name' => 'Notification',
				'channel' => 'default',
				'index' => 0
			)
		)
	);


	public static function pluginGenericTypes() {
		$generics = array(
			'GEOLOCATION' => array(
				'name' => __('Géolocalisation', __FILE__),
				'familyid' => 'tracking',
				'family' => __('Géolocalisation', __FILE__),
				'type' => 'Info',
				'subtype' => array('other')
			),
			'AC_ON' => array(
				'name' => __('Climatiseur ON', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Action',
				'subtype' => array('other')
			),
			'AC_OFF' => array(
				'name' => __('Climatiseur OFF', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Action',
				'subtype' => array('other')
			),
			'AC_STATE' => array(
				'name' => __('Climatiseur Etat', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Info',
				'subtype' => array('binary')
			),
			'AC_TEMPERATURE' => array(
				'name' => __('Climatiseur Température', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Info',
				'subtype' => array('numeric')
			),
			'AC_SET_TEMPERATURE' => array(
				'name' => __('Climatiseur Consigne Température', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Action',
				'subtype' => array('slider')
			),
			'AC_SET_MODE' => array(
				'name' => __('Climatiseur Mode', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Action',
				'subtype' => array('select')
			),
			'AC_MODE' => array(
				'name' => __('Climatiseur Mode', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Info',
				'subtype' => array('other')
			),
			'AC_SET_FAN_MODE' => array(
				'name' => __('Climatiseur Ventillation Mode', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Action',
				'subtype' => array('select')
			),
			'AC_FAN_MODE' => array(
				'name' => __('Climatiseur Ventillation Mode', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Info',
				'subtype' => array('other')
			),
			'AC_INDOOR_TEMPERATURE' => array(
				'name' => __('Climatiseur Température Intérieur', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Info',
				'subtype' => array('numeric')
			),
			'AC_OUTDOOR_TEMPERATURE' => array(
				'name' => __('Climatiseur Température Extérieur', __FILE__),
				'familyid' => 'ac',
				'family' => __('Climatisation', __FILE__),
				'type' => 'Info',
				'subtype' => array('numeric')
			),
		);
		return $generics;
	}

	public static $_plugin_config_dir = __DIR__ . '/../config/';
	public static $_plugin_info_dir = __DIR__ . '/../../plugin_info/';
	public static $_data_dir = __DIR__ . '/../../data/';
	public static $_config_dir = __DIR__ . '/../../data/configs/';
	public static $_qr_dir = __DIR__ . '/../../data/qrcodes/';
	public static $_notif_dir = __DIR__ . '/../../data/notifs/';
	public static $_backup_dir = __DIR__ . '/../../data/backups/';
	public static $_notif_bin_dir = __DIR__ . "/../../resources/sendNotif/";

	public static $_volumeType = array(
		"alarm" => "Alarme",
		"call" => "Appel",
		"music" => "Musique",
		"notification" => "Notification",
		"ring" => "Sonnerie",
		"system" => "Système",
	);

	/*     * ***********************Methode static*************************** */

	/*     * ********************** DAEMON MANAGEMENT *************************** */

	public static function deamon_info() {
		$return = array();
		$return['log'] = __CLASS__;
		$return['state'] = 'nok';

		$pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';

		// $serverExist = count(system::ps('core/php/server.php')) > 0;
		if (file_exists($pid_file)) {
			if (@posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
			}
		}

		if (!self::useWebsocket()) {
			if ($return['state'] == 'ok') self::deamon_stop();
			$return['state'] == 'nok';
			$return['launchable'] = 'nok';
			$return['launchable_message'] = 'Aucun équipement utilise le websocket';
			return $return;
		}

		$return['launchable'] = 'ok';
		$return['last_launch'] = config::byKey('lastDeamonLaunchTime', __CLASS__, __('Inconnue', __FILE__));
		return $return;
	}


	public static function deamon_start() {
		self::deamon_stop();
		JCLog::info('Starting daemon');

		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		if (!file_exists(self::PYTHON_PATH)) {
			throw new Exception(__("VENV n'est pas disponible. Veuillez réinstaller les dépendances", __FILE__));
		}

		$daemonLogConfig = config::byKey('daemonLog', __CLASS__, 'parent');
		$daemonLog = ($daemonLogConfig == 'parent') ? log::getLogLevel(__CLASS__) : $daemonLogConfig;

		$JCapiKey = jeedom::getApiKey(__CLASS__);
		$JCapiKeySize = strlen($JCapiKey);


		$parent_path = realpath(dirname(__FILE__) . '/../..'); // répertoire parent du démon
		$cmd = 'cd ' . escapeshellarg($parent_path) . ' && ' . self::PYTHON_PATH . ' -m resources.JeedomConnectd.JeedomConnectd'; // nom du démon à modifier
		$cmd .= ' --loglevel ' . log::convertLogLevel($daemonLog); // log::convertLogLevel(log::getLogLevel(__CLASS__));
		$cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__, '58090'); // port socket - échange entre le démon en PY et l'api jeedom
		$cmd .= ' --websocketport ' . config::byKey('port', __CLASS__, '8090'); // port d'écoute du démon pour échange avec l'application JC
		$cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/JeedomConnect/core/api/JeedomConnect.api.php'; // chemin de la callback url à modifier (voir ci-dessous)
		$cmd .= ' --apikey ' . $JCapiKey; // l'apikey pour authentifier les échanges suivants
		$cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid'; // et on précise le chemin vers le pid file (ne pas modifier)
		$cmd .= ' --trace ' . config::byKey('traceLog', 'JeedomConnect', 0); // verbose ou non
		JCLog::debug('Starting daemon with cmd >>' . str_replace($JCapiKey, str_repeat('*', $JCapiKeySize), $cmd) . '<<');
		exec($cmd . ' >> ' . log::getPathToLog('JeedomConnect_daemon') . ' 2>&1 &'); // 'template_daemon' est le nom du log pour votre démon, vous devez nommer votre log en commençant par le pluginid pour que le fichier apparaisse dans la page de config

		$i = 0;
		while ($i < 10) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 10) {
			log::add(__CLASS__, 'error', __('Impossible de lancer le démon, vérifiez le log', __FILE__), 'unableStartDeamon');
			return false;
		}
		message::removeAll(__CLASS__, 'unableStartDeamon');
		return true;
	}

	public static function deamon_stop() {
		JCLog::info('Stopping daemon');
		$pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid'; // ne pas modifier
		if (file_exists($pid_file)) {
			$pid = intval(trim(file_get_contents($pid_file)));
			system::kill($pid);
		}
		system::kill('JeedomConnectd.py');
		sleep(1);
	}

	public static function sendToDaemon($params) {
		$deamon_info = self::deamon_info();
		if ($deamon_info['state'] != 'ok') {
			throw new Exception("Le démon n'est pas démarré");
		}
		$params['jeedomApiKey'] = jeedom::getApiKey(__CLASS__);
		$payLoad = json_encode($params);
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_connect($socket, '127.0.0.1', config::byKey('socketport', __CLASS__, '58090')); //port par défaut de votre plugin à modifier
		socket_write($socket, $payLoad, strlen($payLoad));
		socket_close($socket);
	}

	/*     * -------------------------------- END DAEMON -------------------------------- */

	/*     * ********************** DEPENDANCIES MANAGEMENT *************************** */

	public static function dependancy_install() {
		// exec("sudo dos2unix " . dirname(__FILE__) . '/../../resources/install_apt.sh ');
		log::remove(__CLASS__ . '_dep');
		return array(
			'script' => dirname(__FILE__) . '/../../resources/install_apt.sh ',
			'log' => log::getPathToLog(__CLASS__ . '_dep')
		);
	}

	public static function dependancy_info() {
		$return = array();
		$return['log'] = log::getPathToLog(__CLASS__ . '_dependance');
		$return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependance';
		$return['state'] = 'ok';
		if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependance')) {
			$return['state'] = 'in_progress';
		} elseif (!self::pythonRequirementsInstalled(self::PYTHON_PATH, __DIR__ . '/../../resources/requirements.txt')) {
			$return['state'] = 'nok';
		}
		return $return;
	}

	/**
	 * copyrigth : Mips2648
	 *
	 * @param string $pythonPath
	 * @param string $requirementsPath
	 * @return void
	 */
	private static function pythonRequirementsInstalled(string $pythonPath, string $requirementsPath) {
		if (!file_exists($pythonPath) || !file_exists($requirementsPath)) {
			return false;
		}
		exec("{$pythonPath} -m pip freeze", $packages_installed);
		$packages = join("||", $packages_installed);
		exec("cat {$requirementsPath}", $packages_needed);
		foreach ($packages_needed as $line) {
			if (preg_match('/([^\s]+)[\s]*([>=~]=)[\s]*([\d+\.?]+)$/', $line, $need) === 1) {
				if (preg_match('/' . $need[1] . '==([\d+\.?]+)/', $packages, $install) === 1) {
					if ($need[2] == '==' && $need[3] != $install[1]) {
						return false;
					} elseif (version_compare($need[3], $install[1], '>')) {
						return false;
					}
				} else {
					return false;
				}
			}
		}
		return true;
	}

	/*     * ---------------------- END DEPENDANCIES -------------------------------- */


	/*     * ********************** NOTIF INSTALL MANAGEMENT *************************** */

	public static function install_notif() {
		array_map('unlink', glob(__DIR__ . '/../../resources/sendNotif/vNotif*'));

		$filename = self::getSendNotifBin();
		JCLog::debug('installation notif bin type >> ' . $filename);

		$pluginInfo = self::getPluginInfo();
		JCLog::debug('plugin info => ' . json_encode($pluginInfo));

		$version = 'tag_notifBin_' . JeedomConnectUtils::isBeta(true);
		JCLog::debug('   looking for version => ' . json_encode($version));
		$tag = $pluginInfo[$version] ?? 'unknown';
		if ($tag == 'unknown') throw new Exception("Version notification non disponible => " . $version);

		@mkdir(self::$_notif_bin_dir);
		$destination_dir = self::getSendNotifBinPath();
		JCLog::debug('   destination_dir : ' . $destination_dir);

		$sh_path = realpath(self::$_notif_bin_dir . "/../../resources/installNotifBin.sh");
		if (!is_executable($sh_path)) {
			chmod($sh_path, 0755);
		}

		$cmd = $sh_path . " $tag $filename $destination_dir >> " . log::getPathToLog(__CLASS__);
		JCLog::debug('   cmd : ' . $cmd);
		shell_exec($cmd);
	}

	public static function install_notif_info() {

		$sendNotifBin = self::getSendNotifBinPath();

		if (!file_exists($sendNotifBin)) {
			JCLog::trace($sendNotifBin . ' not found ... ');
			return 'nok';
		}

		return 'ok';
	}

	public static function backupExclude() {
		return [
			'resources/sendNotif/'
		];
	}

	/*     * ---------------------- END NOTIF INSTALL -------------------------------- */

	public static function useWebsocket() {
		/** @param JeedomConnect $eqLogic */
		$daemonRequired = false;
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			if ($eqLogic->getConfiguration('useWs', false)) {
				$daemonRequired = true;
				break;
			}
		}
		return $daemonRequired;
	}

	/**
	 * Check if websocket is acitvated at least on one equipment, if not and if daemon is started
	 * then displays a message in jeedom console to let the user know that the daemon seems not required
	 * for his current need, and that this functionnality can be disabled. 
	 *
	 * @return void
	 */
	public static function checkDaemon() {
		/**
		 * @param JeedomConnect $eqLogic
		 */
		$daemonRequired = self::useWebsocket();

		if (!$daemonRequired) {
			JCLog::warning("le démon n'est pas nécessaire !");
			$plugin = plugin::byId('JeedomConnect');
			$daemon_info = $plugin->deamon_info();
			$msg = ($daemon_info['state'] == 'ok') ?
				"Il semblerait que le démon du plugin JC soit actif alors que tu n'en as pas besoin puisqu'aucun de tes équipements n'utilise la connexion par websocket.
			 Tu peux donc le stopper." : '';
			$msg .= $daemon_info['auto'] ? ' Tu peux également désactiver la gestion automatique du démon.' : '';

			if ($msg != '') message::add('JeedomConnect',  $msg);
		}

		return;
	}

	public static function removeBackupFiles() {
		return JeedomConnectUtils::removeBackupFiles();
	}

	public static function backup() {
		JeedomConnectWidget::exportWidgetConf();
		JeedomConnectWidget::exportWidgetCustomConf();

		foreach (self::getAllJCequipment() as $eqLogic) {
			$apiKey = $eqLogic->getLogicalId();

			$bkpDir = self::$_backup_dir . $apiKey;
			if (!is_dir($bkpDir))  @mkdir($bkpDir, 0755, true);

			$configFile = realpath(self::$_config_dir) . '/' . $apiKey . '.json';
			if (file_exists($configFile)) {
				$configFileContent = JeedomConnectUtils::getFileContent($configFile);

				if (array_key_exists('formatVersion', $configFileContent)) {
					$content = JeedomConnectUtils::addTypeInPayload($configFileContent, 'JC_EXPORT_EQLOGIC_CONFIG');
					file_put_contents($bkpDir . '/config-' . $apiKey . '.json', json_encode($content, JSON_PRETTY_PRINT));
				} else {
					JCLog::warning("no backup up of config file, because it's a bad one => " . json_encode($configFileContent));
				}
			}

			$notifFile = realpath(self::$_notif_dir) . '/' . $apiKey . '.json';
			if (file_exists($notifFile)) copy($notifFile, $bkpDir . '/notif-' . $apiKey . '.json');
		}
	}

	public static function copyNotifConfig($oldApiKey, $newApiKey) {
		$notif_file = self::$_notif_dir . $oldApiKey . ".json";
		$notif_file_new = self::$_notif_dir . $newApiKey . ".json";

		if (file_exists($notif_file)) {
			JCLog::debug('Copying notification config file');
			copy($notif_file, $notif_file_new);
		}
	}

	public static function copyBackupConfig($oldApiKey, $newApiKey) {
		$backupDir = JeedomConnect::$_backup_dir . $oldApiKey;
		$backupDir_new = JeedomConnect::$_backup_dir . $newApiKey;

		if (is_dir($backupDir)) {
			JCLog::debug('Copying backup config folder');
			JeedomConnectUtils::recurse_copy($backupDir, $backupDir_new);
		}
	}

	public static function copyConfig($from, $to) {

		$config_file_model = self::$_config_dir . $from . ".json";
		if (!file_exists($config_file_model)) {
			JCLog::warning('file ' . $config_file_model . ' does not exist');
			return null;
		}
		$configFile = file_get_contents($config_file_model);

		foreach ($to as $item) {

			if ($item != $from) {

				$config_file_destination = self::$_config_dir . $item . ".json";
				try {
					JCLog::debug('Copying config file from ' . $from . ' to ' . $item);
					file_put_contents($config_file_destination, $configFile);

					/** @var JeedomConnect $eqLogic */
					$eqLogic = eqLogic::byLogicalId($item, 'JeedomConnect');
					if (!is_object($eqLogic)) {
						JCLog::debug('no object found');
						continue;
					}
					$eqLogic->generateNewConfigVersion();
				} catch (Exception $e) {
					JCLog::error('Unable to write file : ' . $e->getMessage());
				}
			}
		}

		return true;
	}

	public function cleanCustomData() {
		$apiKey = $this->getConfiguration('apiKey');
		$customData = config::searchKey('customData::' . $apiKey . '::', 'JeedomConnect');

		if (!empty($customData)) {
			$config = $this->getConfig(true);

			foreach ($customData as $item) {
				$search = array_search($item['value']['widgetId'], array_column($config['payload']['widgets'], 'widgetId'));
				if ($search === false) {
					JCLog::debug('removing custom data (not used anymore) : ' . $item['value']['widgetId']);
					config::remove('customData::' . $apiKey . '::' . $item['value']['widgetId'], 'JeedomConnect');
					$this->removeCustomConf($item['value']['widgetId']);
				}
			}
		}
	}


	/*     * *********************Méthodes d'instance************************* */

	public function saveConfig($config) {
		if (!is_dir(self::$_config_dir)) {
			mkdir(self::$_config_dir);
		}
		$config_file = self::$_config_dir . $this->getConfiguration('apiKey') . ".json";
		try {
			JCLog::debug('Saving conf in file : ' . $config_file);
			file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
		} catch (Exception $e) {
			JCLog::error('Unable to write file : ' . $e->getMessage());
		}
	}

	public function getConfig($replace = false, $saveGenerated = false) {

		if ($this->isWidgetMap()) return null;

		if ($this->getConfiguration('apiKey') == null || $this->getConfiguration('apiKey') == '') {
			JCLog::error('¤¤¤¤¤ getConfig for ApiKey EMPTY ! [' . $this->getName() . ']');
			return null;
		}

		$config_file_path = self::$_config_dir . $this->getConfiguration('apiKey') . ".json";
		if (!file_exists($config_file_path)) {
			JCLog::warning('file ' . $config_file_path . ' does not exist');
			return null;
		}
		$configFile = file_get_contents($config_file_path);
		$jsonConfig = json_decode($configFile, true);

		if (!$replace) {
			JCLog::trace('¤¤¤¤¤ only send the config file without enrichment for apikey ' . $this->getConfiguration('apiKey'));
			return $jsonConfig;
		}

		$roomIdList = array();
		$widgetList = array();
		$widgetIdInGroup = array();
		$maxIndex = 0;
		foreach ($jsonConfig['payload']['widgets'] as $key => $widget) {
			$widgetData = JeedomConnectWidget::getWidgets($widget['id']);
			$widgetId = $widget['widgetId'] ?? '';
			if (empty($widgetData)) {
				// ajax::error('Erreur - pas d\'équipement trouvé');
				JCLog::debug('Erreur - pas de widget trouvé avec l\'id ' . $widget['id']);
			} else {
				$widgetConf = $widgetData[0]['widgetJC'] ?? '';

				foreach ($widgetConf as $key2 => $value2) {
					$widget[$key2] = $value2;
				}
				$widget['id'] = intval($widget['id']);
				$widget['widgetId'] = intval($widgetId);
				array_push($widgetList, $widget['id']);

				if (isset($widget['widgets'])) {
					foreach ($widget['widgets'] as $itemGroup) {
						array_push($widgetIdInGroup, array('id' => $itemGroup['id'], 'parentId' =>  $widget['widgetId']));
					}
				}

				if (isset($widget['moreWidgets'])) {
					foreach ($widget['moreWidgets'] as $itemGroup) {
						array_push($widgetIdInGroup, array('id' => $itemGroup['id'], 'parentId' =>  $widget['widgetId']));
					}
				}

				if (isset($widget['room'])) {
					array_push($roomIdList, $widget['room']);
				}

				foreach ($widget as $item => $value) {
					if (is_array($value) && array_key_exists('subType', $value) && $value['subType'] == 'select') {
						$choices = self::getChoiceData($value['id']);
						$widget[$item]['choices'] = $choices;
					}
				}

				$jsonConfig['payload']['widgets'][$key] = $widget;
				$maxIndex = $key;
			}
		}

		$customData = $this->getCustomWidget();

		// moreWidgets in customData
		if (!empty($customData)) {
			if (array_key_exists('widgets', $customData)) {
				foreach ($customData['widgets'] as $widgetId => $customWidget) {
					if (isset($customWidget['moreWidgets'])) {
						foreach ($customWidget['moreWidgets'] as $itemGroup) {
							array_push($widgetIdInGroup, array('id' => $itemGroup['id'], 'parentId' =>  $widgetId));
						}
					}
				}
			}
		}

		while (count($widgetIdInGroup) > 0) {
			$moreWidget = array();
			// remove duplicate id
			//$widgetIdInGroup = array_unique($widgetIdInGroup);
			// check if for each widgetId found in a group, the widget itself has his configuration
			// already detailed in the config file, if not, then add it
			foreach ($widgetIdInGroup as $item) {
				if (!in_array($item['id'], $widgetList)) {
					// JCLog::debug( 'the widget ['. $item . '] does not exist in the config file. Adding it.');
					$newWidgetData = JeedomConnectWidget::getWidgets($item['id']);

					if (empty($newWidgetData)) {
						// ajax::error('Erreur - pas d\'équipement trouvé');
					} else {
						$newWidgetConf = $newWidgetData[0]['widgetJC'] ?? '';

						$newWidgetConf['id'] = intval($newWidgetConf['id']);
						$newWidgetConf['index'] = 999999999;
						$newWidgetConf['widgetId'] = (intval($item['parentId']) + 1) * 100000 + $newWidgetConf['id'];

						if (isset($newWidgetConf['room'])) {
							array_push($roomIdList, $newWidgetConf['room']);
						}

						if (isset($newWidgetConf['widgets'])) {
							foreach ($newWidgetConf['widgets'] as $itemWidget) {
								array_push($moreWidget, array('id' => $itemWidget['id'], 'parentId' =>  $newWidgetConf['widgetId']));
							}
						}

						if (isset($newWidgetConf['moreWidgets'])) {
							foreach ($newWidgetConf['moreWidgets'] as $itemWidget) {
								array_push($moreWidget, array('id' => $itemWidget['id'], 'parentId' =>  $newWidgetConf['widgetId']));
							}
						}

						if ($newWidgetConf['type'] == 'choices-list') {
							foreach ($newWidgetConf as $key => $value) {
								if (is_array($value) && key_exists('subType', $value) && $value['subType'] == 'select') {
									$choices = self::getChoiceData($newWidgetConf[$key]['id']);
									$newWidgetConf[$key]['choices'] = $choices;
								}
							}
						}

						$maxIndex = $maxIndex + 1;
						$jsonConfig['payload']['widgets'][$maxIndex] = $newWidgetConf;

						array_push($widgetList, $newWidgetConf['id']);
					}
				}
			}

			if (count($moreWidget) > 0) JCLog::debug('more widgets children to add -- ' . json_encode($moreWidget));
			$widgetIdInGroup = $moreWidget;
		}


		//add equipement password
		$pwd = $this->getConfiguration('pwdAction', null);
		$jsonConfig['payload']['password'] = $pwd;

		//add summary details
		/** @var ?array $objSummary */
		$objSummary = config::byKey('object:summary');
		$allSummaries = $jsonConfig['payload']['summaries'] ?? [];
		foreach ($allSummaries as $index => $summary) {
			if (array_key_exists($summary['key'], $objSummary)) {
				$newSummary = $summary;
				$newSummary['calcul'] = $objSummary[$summary['key']]['calcul'];
				$newSummary['unit'] = $objSummary[$summary['key']]['unit'];
				$newSummary['count'] = $objSummary[$summary['key']]['count'];
				$newSummary['allowDisplayZero'] =  $objSummary[$summary['key']]['allowDisplayZero'];
				$newSummary['ignoreIfCmdOlderThan'] =  $objSummary[$summary['key']]['ignoreIfCmdOlderThan'];

				if (array_key_exists('image', $newSummary) && array_key_exists('name', $newSummary['image'])) {
					$newSummary['image']['name'] =  trim($newSummary['image']['name']);
				}

				$jsonConfig['payload']['summaries'][$index] = $newSummary;
			}
		}

		//add customData
		$jsonConfig['payload']['customData'] = $customData == "" ? array('widgets' => array()) : $customData;

		if ($saveGenerated) {
			cache::set('jcConfig' . $this->getConfiguration('apiKey'), json_encode($jsonConfig));
			file_put_contents($config_file_path . '.generated', json_encode($jsonConfig, JSON_PRETTY_PRINT));
		}

		// $jsonConfig = json_decode($widgetStringFinal, true);
		return $jsonConfig;
	}

	/**
	 * @param string $apiKey
	 * @return array
	 */
	public static function getWidgetConfigContent($apiKey = '') {

		$filePath = self::$_config_dir . $apiKey . '.json';
		JCLog::debug('will check for config file : ' . $filePath);

		if (!file_exists($filePath) || $apiKey == '') {
			throw new Exception("No config file found");
		}

		return json_decode(file_get_contents($filePath), true);
	}

	public function getCustomConf($widgetId, $apiKey = null) {
		if (is_null($apiKey)) $apiKey = $this->getLogicalId();

		return config::byKey('customData::' . $apiKey . '::' . $widgetId, __CLASS__);
	}

	public function removeCustomConf($widgetId, $apiKey = null) {
		if (is_null($apiKey)) $apiKey = $this->getLogicalId();

		return config::remove('customData::' . $apiKey . '::' . $widgetId, __CLASS__);
	}

	public function saveCustomConf($widgetId, $customData, $apiKey = null) {
		if (is_null($apiKey)) $apiKey = $this->getLogicalId();

		return config::save('customData::' . $apiKey . '::' . $widgetId, json_encode($customData), __CLASS__);
	}

	public function updateCustomConf($widgetId, $key, $data, $apiKey = null) {
		if (is_null($apiKey)) $apiKey = $this->getLogicalId();

		$customConf = $this->getCustomConf($widgetId);
		// JCLog::debug(' == custom data ' . json_encode($customConf));

		$customConf[$key] = $data;

		config::save('customData::' . $apiKey . '::' . $widgetId, json_encode($customConf), __CLASS__);
	}


	public function getCustomWidget() {

		$myKey = 'customData::' . $this->getConfiguration('apiKey') . '::';
		// JCLog::debug( 'looking for config key : ' .  myKey);

		$allCustomData = config::searchKey($myKey, 'JeedomConnect');

		$final = array();
		foreach ($allCustomData as $item) {
			if (!key_exists('widgetId', $item['value'])) {
				JCLog::warning('no widgetId found - skip - ' . json_encode($item));
				continue;
			}
			$final[$item['value']['widgetId']] = $item['value'];
		}

		$result = array("widgets" => $final);
		// JCLog::debug( 'resultat : ' .  json_encode($result));
		return $result;
	}

	public function getGeneratedConfigFile($forceReload = false) {

		if ($this->getConfiguration('apiKey') == null || $this->getConfiguration('apiKey') == '') {
			JCLog::error('¤¤¤¤¤ getGeneratedConfigFile for ApiKey EMPTY ! [' . $this->getName() . ']');
			return null;
		}

		$config_file_path = self::$_config_dir . $this->getConfiguration('apiKey') . ".json.generated";
		if ($forceReload) {
			JCLog::debug('force new config generation');
			$this->getConfig(true, true);
		} else {
			$cacheConf = cache::byKey('jcConfig' . $this->getConfiguration('apiKey'))->getValue();
			if ($cacheConf != '') {
				return json_decode($cacheConf, true);
			}

			if (!file_exists($config_file_path)) {
				JCLog::warning('file ' . $config_file_path . ' does not exist  -- try to generate new one');
				$this->getConfig(true, true);
			}
		}

		try {
			$configFile = file_get_contents($config_file_path);
			$jsonConfig = json_decode($configFile, true);
			return $jsonConfig;
		} catch (Exception $e) {
			JCLog::error('Unable to generate configuration setting : ' . $e->getMessage());
			return null;
		}
	}

	public function restoreConfigFile() {
		$newConfig = null;
		$apiKey = $this->getConfiguration('apiKey');
		if ($apiKey == '') {
			JCLog::warning('restoreConfigFile - apiKey empty !');
			return $newConfig;
		}

		$bkpFile = self::$_backup_dir . $apiKey . '/config-' . $apiKey . '.json';
		if (file_exists($bkpFile)) {
			$fileConfigRestore = self::$_config_dir . $apiKey . '.json';

			JCLog::debug('trying to restore bkp file from ' . $bkpFile . ' to ' . $fileConfigRestore);
			$configFile = file_get_contents($bkpFile);
			$jsonConfig = json_decode($configFile, true);

			file_put_contents($fileConfigRestore, json_encode($jsonConfig['payload']));

			$newConfig = $this->getGeneratedConfigFile(true);
		}
		return $newConfig;
	}


	public function getGeneratedWidget($widgetId = null, $_force = false) {

		$conf = $this->getGeneratedConfigFile($_force);

		if (!key_exists('payload', $conf) || !key_exists('widgets', $conf['payload'])) {
			JCLog::warning('bad configuration file, no payload nor widgets');
			return null;
		}

		$widgetsAll = $conf['payload']['widgets'];

		$widgets = array();
		foreach ($widgetsAll as $widget) {

			if ($widgetId != null && $widget['id'] == $widgetId) {
				return $widget;
			}

			$widgets[] =  $widget;
		}

		return $widgets;
	}


	public static function getChoiceData($cmdId) {
		$choice = array();

		$cmd = cmd::byId($cmdId);

		if (!is_object($cmd)) {
			JCLog::warning($cmdId . ' is not a valid cmd Id');
			return $choice;
		}

		$cmdConfig = $cmd->getConfiguration('listValue');

		if ($cmdConfig !=  '') {
			JCLog::trace('value of listValue ' . json_encode($cmdConfig));

			foreach (explode(';', $cmdConfig) as $list) {
				$selectData = explode('|', $list);

				if (count($selectData) == 1) {
					$id = $value = $selectData[0];
				} else {
					$id = $selectData[0];
					$value = $selectData[1];
				}

				$choice_info = array(
					'id' => $id,
					'value' => $value
				);
				array_push($choice, $choice_info);
			}
		}

		JCLog::trace('final choices list => ' . json_encode($choice));
		return $choice;
	}

	public function getWidgetId() {
		$ids = array();

		$conf = $this->getConfig(true);
		if (is_null($conf)) return $ids;

		foreach ($conf['payload']['widgets'] as $item) {
			$ids[] = $item['id'];
		}

		JCLog::debug(' fx  getWidgetId -- result final ' . json_encode($ids));
		return $ids;
	}

	/**
	 * Return the array of the widget specific widgetId (and not the id)
	 *
	 * @return array
	 */
	public function getWidgetWidgetId($from_id = null) {
		$ids = array();
		JCLog::debug(' getWidgetWidgetId looking for id  ' . $from_id);

		$conf = $this->getConfig();
		if (is_null($conf)) return $ids;

		foreach ($conf['payload']['widgets'] as $item) {
			if ($from_id == null || $from_id == $item['id']) {
				$ids[] = $item['widgetId'];
			}
		}

		JCLog::debug(' fx  getWidgetWidgetId -- result final ' . json_encode($ids));
		return $ids;
	}
	public function isWidgetIncluded($widgetId) {

		$conf = $this->getGeneratedConfigFile();
		if (is_null($conf)) return false;

		foreach ($conf['payload']['widgets'] ?? array() as $item) {
			if ($item['id'] == $widgetId) return true;
		}
		return false;
	}

	public static function getWidgetCountByEq($widgetId) {

		$nb = 0;
		$names = array();

		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			if ($eqLogic->isWidgetIncluded($widgetId)) {
				$nb++;
				$names[] = $eqLogic->getName();
			}
		}

		return array($nb, $names);
	}

	public function updateConfig() {
		$jsonConfig = $this->getConfig();
		$changed = false;
		$idCounter = $jsonConfig['idCounter'];
		//unique widget id
		foreach ($jsonConfig['payload']['widgets'] as $index => $widget) {
			if (!array_key_exists("widgetId", $widget)) {
				$jsonConfig['payload']['widgets'][$index]['widgetId'] = $idCounter;
				$idCounter++;
				$changed = true;
			}
		}
		//move to new background format
		if (array_key_exists("condImages", $jsonConfig['payload']['background'] ?? array())) {
			$cond = array();
			foreach ($jsonConfig['payload']['background']['condImages'] as $i => $bgCond) {
				array_push($cond, array(
					'index' => $bgCond['index'],
					'condition' => $bgCond['condition'],
					'background' => array(
						'type' => 'image',
						'options' => $bgCond['image']
					)
				));
			}
			$jsonConfig['payload']['background']["condBackgrounds"] = $cond;
			unset($jsonConfig['payload']['background']['condImages']);
			$changed = true;
		}
		if (array_key_exists("image", $jsonConfig['payload']['background'] ?? array())) {
			$jsonConfig['payload']['background']["background"] = array(
				'type' => 'image',
				'options' => $jsonConfig['payload']['background']['image']
			);
			unset($jsonConfig['payload']['background']['image']);
			$changed = true;
		}

		if ($changed) {
			$jsonConfig['idCounter'] = $idCounter;
			$jsonConfig['payload']['widgets'] = array_values($jsonConfig['payload']['widgets']);
			JCLog::info('Config file updated for ' . $this->getName() . ':' . json_encode($jsonConfig));
			$this->saveConfig($jsonConfig);
			$this->generateNewConfigVersion();
		}
	}

	public function saveNotifs($config, $sendToApp = true) {
		//update channels
		$data = array(
			"type" => "SET_NOTIFS_CONFIG",
			"payload" => $config
		);


		if (!is_dir(self::$_notif_dir)) {
			mkdir(self::$_notif_dir);
		}
		$config_file = self::$_notif_dir . $this->getConfiguration('apiKey') . ".json";
		file_put_contents($config_file, json_encode($config));
		if ($sendToApp) {
			$this->sendNotif('defaultNotif', $data);
		}

		//Update cmds
		foreach ($config['notifs'] as $notif) {
			$this->addCmd($notif);
		}
		//Remove unused cmds
		foreach (cmd::byEqLogicId($this->getId()) as $cmd) {
			if (strpos(strtolower($cmd->getLogicalId()), 'notif') !== false) {
				$remove = true;
				foreach ($config['notifs'] as $notif) {
					if ($cmd->getLogicalId() == $notif['id'] || strpos(strtolower($cmd->getLogicalId()), 'notifall') !== false) {
						$remove = false;
						break;
					}
				}
				if ($remove) {
					JCLog::debug('remove cmd ' . $cmd->getName());
					$cmd->remove();
				}
			}
		}
	}

	public function addCmd($notif) {
		try {
			$cmdNotif = $this->getCmd(null, $notif['id']);
			if (!is_object($cmdNotif)) {
				JCLog::debug('add new cmd ' . $notif['name']);
				$cmdNotif = new JeedomConnectCmd();
			}
			$cmdNotif->setLogicalId($notif['id']);
			$cmdNotif->setName(__($notif['name'], __FILE__));
			$cmdNotif->setOrder(0);
			$cmdNotif->setEqLogic_id($this->getId());
			$cmdNotif->setDisplay('generic_type', 'GENERIC_ACTION');
			$cmdNotif->setType('action');
			$cmdNotif->setSubType('message');
			$cmdNotif->setIsVisible(1);
			$cmdNotif->setDisplay('title_placeholder', __('Titre/Options', __FILE__));

			$cmdNotif->save();
		} catch (Exception $e) {
			JCLog::error('Notif creation error ' . $e->getMessage());
		}
	}

	public static function fixNotif() {
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$change = false;
			$config_file = self::$_notif_dir . $eqLogic->getConfiguration('apiKey') . ".json";
			if (!file_exists($config_file)) continue;

			$config = json_decode(file_get_contents($config_file), true);
			// JCLog::debug('FIX NOTIF // config ' . json_encode($config));
			$idCounter = $config['idCounter'];

			//Update cmds
			foreach ($config['notifs'] as $key => $notif) {
				if (!key_exists('id', $notif) || $notif['id'] == '') {
					$change = true;

					JCLog::debug('Notif Id not found : ' . json_encode($notif));
					$newId = 'notif-' . $idCounter;
					$config['notifs'][$key]['id'] = $newId;
					$idCounter++;

					/** @var cmd $cmdNotif */
					$cmdNotif = cmd::byEqLogicIdCmdName($eqLogic->getId(), $notif['name']);
					// if cmd is an object as an action/msg and logicalId is null, then it's the same cmd 
					// --> update the logicalId with the new id set
					if (
						is_object($cmdNotif) &&
						$cmdNotif->getType() == 'action' &&
						$cmdNotif->getSubType() == 'message' &&
						is_null($cmdNotif->getLogicalId())
					) {
						try {
							JCLog::debug('updating cmd with empty logicalId => ' . json_encode(utils::o2a($cmdNotif)));
							$cmdNotif->setLogicalId($newId);
							$cmdNotif->save();
						} catch (Exception $e) {
							JCLog::error('Notif error ' . $e->getMessage());
						}
					} else {
						JCLog::debug('cmd already OK');
					}
				}
			}

			if ($change) {
				$config['idCounter'] = $idCounter;
				file_put_contents($config_file, json_encode($config));

				$data = array(
					"type" => "SET_NOTIFS_CONFIG",
					"payload" => $config
				);

				$eqLogic->sendNotif('defaultNotif', $data);
			}
		}
		config::save('fix::notifID', 'done', 'JeedomConnect');
		JCLog::debug('END fixNotif');
	}

	public static function fixNotifCmdDummy() {
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {

			// remove bad cmd named '{'
			/** @var cmd $cmdDummy */
			$cmdDummy = cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), '{');
			if (is_object($cmdDummy)) $cmdDummy->remove();
		}
		config::save('fix::notifCmdDummy', 'done', 'JeedomConnect');
		JCLog::debug('END fixNotifCmdDummy');
	}

	public function getNotifs() {
		$config_file = self::$_notif_dir . $this->getConfiguration('apiKey') . ".json";
		if (!file_exists($config_file)) {
			$this->saveNotifs(self::$_notifConfig);
		}
		$config = file_get_contents($config_file);
		return json_decode($config, true);
	}

	public function generateQRCode() {
		if (!is_dir(self::$_qr_dir)) {
			mkdir(self::$_qr_dir);
		}
		$user = user::byId($this->getConfiguration('userId'));
		if ($user == null) {
			// $user = user::all()[0];
			// $this->setConfiguration('userId', $user->getId());
			// $this->save(true);  //save manquant ! info non persistée
			JCLog::error('Aucun utilisateur sélectionné sur [' . $this->getName() . ']');
		}

		$connectData = array(
			'useWs' => $this->getConfiguration('useWs', 0),
			'polling' => $this->getConfiguration('polling', 0),
			'eqName' => $this->getName(),
			'userName' => $user ? $user->getLogin() : null,
			'httpUrl' => config::byKey('httpUrl', 'JeedomConnect', network::getNetworkAccess('external')),
			'internalHttpUrl' => config::byKey('internHttpUrl', 'JeedomConnect', network::getNetworkAccess('internal')),
			'wsAddress' => config::byKey('wsAddress', 'JeedomConnect', 'ws://' . config::byKey('externalAddr') . ':8090'),
			'internalWsAddress' => config::byKey('internWsAddress', 'JeedomConnect', 'ws://' . config::byKey('internalAddr', 'core', 'localhost') . ':8090'),
			'apiKey' => $this->getConfiguration('apiKey'),
			'userHash' => $user ? $user->getHash() : null,
		);

		$dataFree = JeedomConnectUtils::hideSensitiveData(json_encode($connectData), 'send');
		JCLog::debug('Generate qrcode with data ' . $dataFree);

		require_once dirname(__FILE__) . '/../php/phpqrcode.php';
		try {
			$filepath = self::$_qr_dir . $this->getConfiguration('apiKey') . '.png';
			QRcode::png(json_encode($connectData), $filepath);

			if (config::byKey('withQrCode', 'JeedomConnect', true)) {
				// Start DRAWING LOGO IN QRCODE
				$QR = imagecreatefrompng($filepath);

				// START TO DRAW THE IMAGE ON THE QR CODE
				$logopath = __DIR__ . '/../../data/img/JeedomConnect_icon2.webp';
				$logo = imagecreatefromstring(file_get_contents($logopath));
				$QR_width = imagesx($QR);
				$QR_height = imagesy($QR);

				$logo_width = imagesx($logo);
				$logo_height = imagesy($logo);

				// Scale logo to fit in the QR Code
				$logo_qr_width = $QR_width / 5;
				$scale = $logo_width / $logo_qr_width;
				$logo_qr_height = $logo_height / $scale;

				imagecopyresampled($QR, $logo, $QR_width / 2.5, $QR_height / 2.5, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);

				// Save QR code again, but with logo on it
				imagepng($QR, $filepath);

				//------------------------------------
				////// ADD EQUIPMENT NAME TO THE QR CODE
				//------------------------------------

				// Create Image From Existing File
				$jpg_image = imagecreatefrompng($filepath);
				$orig_width = imagesx($jpg_image);
				$orig_height = imagesy($jpg_image);

				// Create your canvas containing both image and text
				$canvas = imagecreatetruecolor($orig_width, ($orig_height + 40));
				// Allocate A Color For The background
				$bcolor = imagecolorallocate($canvas, 255, 255, 255);
				// Add background colour into the canvas
				imagefilledrectangle($canvas, 0, 0, $orig_width, ($orig_height + 40), $bcolor);

				// Save image to the new canvas
				imagecopyresampled($canvas, $jpg_image, 0, 0, 0, 0, $orig_width, $orig_height, $orig_width, $orig_height);

				// Set Path to Font File
				$font_path = realpath(dirname(__FILE__)) . '/../../resources/arial.ttf';

				// Set Text to Be Printed On Image
				$text = $this->getName();

				// Allocate A Color For The Text
				$color = imagecolorallocate($canvas, 0, 0, 0);

				// Print Text On Image
				imagettftext($canvas,  16, 0, 10, $orig_height + 15, $color, $font_path, $text);

				imagepng($canvas, $filepath);
			}
		} catch (Exception $e) {
			JCLog::error('Unable to generate a QR code : ' . $e->getMessage());
		}
	}

	public function registerDevice($id, $name) {
		$this->setConfiguration('deviceId', $id);
		$this->setConfiguration('deviceName', $name);
		$this->save(true);
	}

	public function removeDevice() {
		$this->setConfiguration('deviceId', '');
		$this->setConfiguration('deviceName', '');
		$this->setConfiguration('platformOs', '');
		$this->save(true);
	}

	public function registerToken($token) {
		$this->setConfiguration('token', $token);
		$this->save(true);
	}

	public function sendNotif($notifId, $data, $cmdId = null) {
		if ($this->getConfiguration('token') == null) {
			JCLog::info("No token defined. Please connect your device first");
			return;
		}

		$binPath = self::getSendNotifBinPath();
		if (!file_exists($binPath)) {
			throw new Exception("Impossible d'envoyer des notifications - bin introuvable");
		}

		$postData = array(
			'to' => $this->getConfiguration('token')
		);
		if ($this->getConfiguration('platformOs') == 'android') {
			$postData['priority'] = 'high';
		}

		foreach ($this->getNotifs()['notifs'] as $notif) {
			if ($notif['id'] == $notifId) {
				unset($notif['name']);
				// JCLog::info(" add notif setup data // BEFORE ==> " . json_encode($notif));
				if (key_exists('actions', $notif)) {
					foreach ($notif['actions'] as $key => $value) {
						$value['name'] = str_replace("'", "&#039;", $value['name']);
						$notif['actions'][$key] = $value;
					}
				}
				// JCLog::info(" add notif setup data // AFTER ==> " . json_encode($notif));
				$data["payload"] = array_merge($data["payload"], $notif);
			}
		}
		$data["payload"]["time"] = time();
		if ($data["type"] == "DISPLAY_NOTIF") {
			$data = JeedomConnectUtils::getNotifData($data, $this);
		}
		$postData["data"] = $data;

		if ($this->getConfiguration('platformOs') == 'ios' && $data["type"] == "DISPLAY_NOTIF") {
			// JCLog::info("on passe chez ios");
			$postData["data"]["payload"]["display_options"] = JeedomConnectUtils::getIosPostData($data);
		}

		if (!is_executable($binPath)) {
			chmod($binPath, 0555);
		}

		$cmd = $binPath . " -token='" . $postData['to'] . "' -os='" . $this->getConfiguration('platformOs') .  "' -type='" . $data["type"] . "' -payload='" . json_encode($postData["data"]["payload"], JSON_HEX_APOS) . "' 2>&1";
		$cmdIdInfo = is_null($cmdId) ? '' : "to [" . $cmdId . "] ";
		// JCLog::info("Send notification " . $cmdIdInfo . "with data " . json_encode($postData));
		JCLog::info("Send notification " . $cmdIdInfo . "with data " . json_encode($postData["data"]));

		$output = shell_exec($cmd);
		$outputJson = preg_replace('/.*response:( )/', '', $output);

		if (is_json($outputJson)) {
			JCLog::debug("JSON OUTPUT : " . json_encode($outputJson));
			$outputJson = json_decode($outputJson, true);
			$SuccessCount = $outputJson['SuccessCount'] ?? null;
			JCLog::debug("   -- SuccessCount : " . json_encode($SuccessCount));
			$FailureCount = $outputJson['FailureCount'] ?? null;
			JCLog::debug("   -- FailureCount : " . json_encode($FailureCount));
			$Responses = $outputJson['Responses'] ?? array();
			JCLog::debug("   -- Responses : ");
			foreach ($Responses as $item) {
				JCLog::debug("       " . json_encode($item));
			}
			if ($SuccessCount != 1 || $FailureCount != 0) {
				JCLog::error("Erreur détectée sur le dernier envoi de notification => " . json_encode($outputJson));
			}
		} else {
			JCLog::warning("L'envoi de la notification ne peut pas être vérifiée : >" . $output . "<");
		}

		if (is_null($output) || empty($output)) {
			JCLog::error("Error while sending notification");
		}
	}

	public static function getNotifBinVersion() {

		$pluginInfo = self::getPluginInfo();

		$version = 'tag_notifBin_' . JeedomConnectUtils::isBeta(true);
		$tag = $pluginInfo[$version] ?? 'unknown';


		return array($tag, $version);
	}
	public static function getSendNotifBinPath() {
		$filename = self::getSendNotifBin();
		JCLog::trace('notif bin type >> ' . $filename);

		list($tag, $version) = self::getNotifBinVersion();
		if ($tag == 'unknown') throw new Exception("Version notification non disponible => " . $version);

		$destination_dir = realpath(self::$_notif_bin_dir) . '/' . $tag . '_' . $filename;
		return $destination_dir;
	}

	public static function getSendNotifBin() {

		switch (php_uname("m")) {
			case "x86_64":
				$sendBin = "sendNotif_x64";
				break;
			case "armv7l":
				$sendBin = "sendNotif_arm";
				break;
			case "aarch64":
				$sendBin = "sendNotif_arm64";
				break;
			case "i686":
				$sendBin = "sendNotif_x32";
				break;
			default:
				JCLog::error("Error while detecting system architecture. " . php_uname("m") . " detected");
				throw new Exception("Error while detecting system architecture. " . php_uname("m") . " detected");
		}
		return $sendBin;
	}

	public function addGeofenceCmd($geofence, $coordinates) {
		JCLog::debug("Add or update geofence cmd : " . json_encode($geofence));

		$geofenceCmd = cmd::byEqLogicIdAndLogicalId($this->getId(), 'geofence_' . $geofence['identifier']);
		if (!is_object($geofenceCmd)) {
			$geofenceCmd = new JeedomConnectCmd();
			$geofenceCmd->setLogicalId('geofence_' . $geofence['identifier']);
			$geofenceCmd->setEqLogic_id($this->getId());
			$geofenceCmd->setType('info');
			$geofenceCmd->setSubType('binary');
			$geofenceCmd->setIsVisible(1);
		}
		$geofenceCmd->setName(__($geofence['extras']['name'], __FILE__));
		$geofenceCmd->setConfiguration('latitude', $geofence['latitude']);
		$geofenceCmd->setConfiguration('longitude', $geofence['longitude']);
		$geofenceCmd->setConfiguration('radius', $geofence['radius']);
		$geofenceCmd->save();

		$this->setCoordinates($coordinates['latitude'], $coordinates['longitude'], $coordinates['altitude'], '', '', time());
	}

	public function removeGeofenceCmd($geofence) {
		JCLog::debug("Remove geofence cmd : " . json_encode($geofence));

		$geofenceCmd = cmd::byEqLogicIdAndLogicalId($this->getId(), 'geofence_' . $geofence['identifier']);
		if (is_object($geofenceCmd)) {
			$geofenceCmd->remove();
		}
	}

	public function setCoordinates($lat, $lgt, $alt, $activity, $batteryLevel, $timestamp) {
		$positionCmd = $this->getCmd(null, 'position');
		$info = $lat . "," . $lgt;
		if ($this->getConfiguration('addAltitude', false)) {
			$info .= "," . $alt;
			$info .= "," . $activity;
			$info .= "," . $batteryLevel;
		}
		$positionCmd->event($info, date('Y-m-d H:i:s', $timestamp));
		$this->setGeofencesByCoordinates($lat, $lgt, $timestamp);
	}

	public function setGeofencesByCoordinates($lat, $lgt, $timestamp) {
		JCLog::debug("[setGeofencesByCoordinates] " . $lat . ' -- ' . $lgt);
		// foreach (cmd::byEqLogicId($this->getId()) as $cmd) {
		foreach ($this->getCmd('info') as $cmd) {
			if (strpos(strtolower($cmd->getLogicalId()), 'geofence') !== false) {
				$dist = JeedomConnectUtils::getDistance($lat, $lgt, $cmd->getConfiguration('latitude'), $cmd->getConfiguration('longitude'));
				$radius = $cmd->getConfiguration('radius');
				JCLog::trace("  -- testing " . $cmd->getName() . ' --> distance = ' . $dist . ' // radius : ' . $radius);
				if ($dist < $radius) {
					JCLog::trace("  ---- dist lower than radius - entering the area");
					if ($cmd->execCmd() != 1) {
						JCLog::debug("Set 1 for geofence " . $cmd->getName());
						$cmd->event(1, date('Y-m-d H:i:s', $timestamp));
					}
				} else {
					JCLog::trace("  ---- dist greater than radius - not in this area");
					if ($cmd->execCmd() !== 0) {
						JCLog::debug("Set 0 for geofence " . $cmd->getName());
						$cmd->event(0, date('Y-m-d H:i:s', $timestamp));
					}
				}
			}
		}

		$distToDefault = JeedomConnectUtils::getDistance($lat, $lgt);
		$cmdDistance = $this->getCmd(null, 'distance');
		if (is_object($cmdDistance)) $cmdDistance->event($distToDefault);
	}

	public function preInsert() {
		if ($this->isWidgetMap()) return;

		if ($this->getConfiguration('apiKey') == '') {
			$this->setConfiguration('apiKey', JeedomConnectUtils::generateApiKey());
			$this->setLogicalId($this->getConfiguration('apiKey'));
			$this->generateQRCode();
		}

		$this->setConfiguration('volume', 'all');
		$this->setIsEnable(1);
	}

	public function postInsert() {
		if ($this->isWidgetMap()) return;

		$this->setIsEnable(1);
		if ($this->getConfiguration('configVersion') == '') {
			$this->setConfiguration('configVersion', 0);
		}
		$this->save(true);

		$this->saveConfig(self::$_initialConfig);
		$this->saveNotifs(self::$_notifConfig);
	}

	public function preSave() {
	}

	public function postSave() {
		if ($this->isWidgetMap()) return;

		if ($this->getConfiguration('qrRefresh')) {
			$this->generateQRCode();
			$this->setConfiguration('qrRefresh',  0);
			$this->save(true);
		}

		if ($this->getConfiguration('pwdChanged') == 'true') {
			$confStd = $this->getConfig();
			$configVersion = $confStd['payload']['configVersion'] + 1;
			JCLog::debug(' saving new conf after password changed -- updating configVersion to ' . $configVersion);

			//update configVersion in the file
			$confStd['payload']['configVersion'] =  $configVersion;
			$this->saveConfig($confStd);

			//update configVersion in the equipment configuration
			$this->setConfiguration('configVersion', $configVersion);
			$this->setConfiguration('pwdChanged',  'false');
			$this->save(true);

			$this->getConfig(true, true);
		}

		if ($this->getConfiguration('hideBattery')) {
			// JCLog::debug( 'hiding battery : -2');
			$this->batteryStatus(100); //force 100 to remove any ongoing warning or danger notification
			$this->setStatus("battery"); //and for status to null to remove the info

		}
	}

	public function preUpdate() {
		if ($this->isWidgetMap()) return;

		$save = false;

		if ($this->getConfiguration('scenariosEnabled') == '') {
			$this->setConfiguration('scenariosEnabled', '1');
			$save = true;
		}
		if ($this->getConfiguration('webviewEnabled') == '') {
			$this->setConfiguration('webviewEnabled', '1');
			$save = true;
		}
		if ($this->getConfiguration('automationsEnabled') == '') {
			$this->setConfiguration('automationsEnabled', '1');
			$save = true;
		}
		if ($this->getConfiguration('editEnabled') == '') {
			$this->setConfiguration('editEnabled', '1');
			$save = true;
		}

		if ($save) $this->save(true);
	}

	public function postUpdate() {
		if ($this->isWidgetMap()) return;

		$this->createCommands('all');
		if ($this->getConfiguration('platformOs') != '') {
			$this->createCommands($this->getConfiguration('platformOs'));
		}

		$confCmd = $this->getConfiguration('cmdInShortcut');
		$cmd_ids = ($confCmd != '') ? explode(",", $confCmd) : array();
		$this->setListener($cmd_ids);

		$confControls = $this->getConfiguration('activeControlIds');
		JCLog::debug('------ confControls ' . $confControls);
		$cmdControls_ids = ($confControls != '') ? JeedomConnectDeviceControl::getInfoCmdIdsFromControls($this, explode(",", $confControls))  : array();

		JCLog::debug('------ cmdControls_ids ' . json_encode($cmdControls_ids));
		$this->setListener($cmdControls_ids, 'sendActiveControl');
	}

	public function preRemove() {
		if ($this->isWidgetMap()) return;

		$apiKey = $this->getConfiguration('apiKey');
		self::removeAllData($apiKey);
	}

	public function isWidgetMap() {
		return ($this->getConfiguration('jceqtype') == "map");
	}


	public static function getAllJCequipment() {
		$allEq = array();
		/** @var JeedomConnect $eqLogic */
		foreach (eqLogic::byType('JeedomConnect') as $eqLogic) {
			if ($eqLogic->isWidgetMap()) continue;
			$allEq[] = $eqLogic;
		}
		return $allEq;
	}

	public static function preConfig_latitude($value) {
		return preg_replace('/[^0-9.,]/', '', $value);
	}
	public static function preConfig_longitude($value) {
		return preg_replace('/[^0-9.,]/', '', $value);
	}
	/**
	 * ensure userImgPath doesn't start with / and ends with /
	 */
	public static function preConfig_userImgPath($value) {

		$userImgPath = ltrim($value, "/");
		if (substr($userImgPath, -1) != "/") {
			$userImgPath .= "/";
		}

		$realPath = __DIR__ . '/../../../../' . $userImgPath;
		if (!is_dir($realPath)) {
			if (!mkdir($realPath)) JCLog::error("mkdir FAILED for => " . $realPath);
		}

		return $userImgPath;
	}

	public static function removeAllData($apiKey) {
		@unlink(self::$_qr_dir . $apiKey . '.png');
		@unlink(self::$_config_dir . $apiKey . ".json");
		@unlink(self::$_config_dir . $apiKey . ".json.generated");
		@unlink(self::$_notif_dir . $apiKey . ".json");
		JeedomConnectUtils::delTree(self::$_backup_dir . $apiKey);

		$allKey = config::searchKey('customData::' . $apiKey, 'JeedomConnect');
		foreach ($allKey as $item) {
			config::remove($item['key'], 'JeedomConnect');
		}
	}

	public function postRemove() {
	}

	public function createCommands(string $type) {
		$configFile = JeedomConnectUtils::getFileContent(self::$_plugin_config_dir . '/params.json');
		$dict = $configFile['dictionary'];
		try {
			if (isset($configFile['cmds'][$type])) {
				$this->createCommandsFromConfigFile($configFile['cmds'][$type], $dict);
			} else {
				JCLog::error($type . ' not found in config');
			}
		} catch (Exception $e) {
			JCLog::error('Cannot save Cmd for this EqLogic -- ' . $e->getMessage());
		}
	}

	public function createCommandsFromConfigFile($commands, $dict) {
		$cmd_updated_by = array();
		foreach ($commands as $cmdData) {
			$cmd = $this->getCmd(null, $cmdData["logicalId"]);

			if (!is_object($cmd)) {
				JCLog::debug('cmd creation  => ' . $cmdData["name"] . ' [' . $cmdData["logicalId"] . ']');

				$cmd = new cmd();
				$cmd->setLogicalId($cmdData["logicalId"]);
				$cmd->setEqLogic_id($this->getId());

				if (isset($cmdData["isVisible"])) {
					$cmd->setIsVisible($cmdData["isVisible"]);
				}

				if (isset($cmdData["isHistorized"])) {
					$cmd->setIsHistorized($cmdData["isHistorized"]);
				}

				if (isset($cmdData["generic_type"])) {
					$cmd->setGeneric_type($cmdData["generic_type"]);
				}

				if (isset($cmdData["unite"])) {
					$cmd->setUnite($cmdData["unite"]);
				}

				if (isset($cmdData["order"])) {
					$cmd->setOrder($cmdData["order"]);
				}
			}

			$cmd->setName(__($cmdData["name"], __FILE__));

			$cmd->setType($cmdData["type"]);
			$cmd->setSubType($cmdData["subtype"]);

			if (isset($cmdData['configuration'])) {
				foreach ($cmdData['configuration'] as $key => $value) {
					if ($key == 'listValueToCreate') {
						$key = 'listValue';
						$value = JeedomConnectUtils::createListOption(explode(";", $value), $dict);
					}
					$cmd->setConfiguration($key, $value);
				}
			}

			if (isset($cmdData['display'])) {
				foreach ($cmdData['display'] as $key => $value) {
					$cmd->setDisplay($key, $value);
				}
			}

			if (isset($cmdData['template'])) {
				foreach ($cmdData['template'] as $key => $value) {
					$cmd->setTemplate($key, $value);
				}
			}

			if (isset($cmdData['updateCmd'])) {
				$cmd_updated_by[$cmdData["logicalId"]] = $cmdData['updateCmd'];
			}

			$cmd->save();
		}

		foreach ($cmd_updated_by as $cmdAction_logicalId => $cmdInfo_logicalId) {
			$cmdAction = $this->getCmd(null, $cmdAction_logicalId);
			$cmdInfo = $this->getCmd(null, $cmdInfo_logicalId);

			if (is_object($cmdAction) && is_object($cmdInfo)) {
				$cmdAction->setValue($cmdInfo->getId());
				$cmdAction->save();
			}
		}
	}

	public static function checkAllEquimentsAndUpdateConfig($widgetId) {
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$eqLogic->checkEqAndUpdateConfig($widgetId);
		}
	}

	public function checkEqAndUpdateConfig($widgetId) {

		JCLog::debug('Checking if widget ' . $widgetId . ' exist on equipment "' . $this->getName() . '" [' . $this->getConfiguration('apiKey') . ']');

		$conf = $this->getGeneratedConfigFile();
		$exist = false;

		if (!$conf) {
			JCLog::debug('No config content retrieved');
			return;
		}


		foreach ($conf['payload']['widgets'] as $widget) {
			if ($widget['id']  == $widgetId) {
				$exist = true;
				break;
			}
		}

		if ($exist) {
			$this->generateNewConfigVersion($widgetId);
		} else {
			JCLog::debug($widgetId . ' NOT found in the current equipment');
		}
	}

	public function generateNewConfigVersion($widgetId = 'widgets') {
		$confStd = $this->getConfig();
		$configVersion = $confStd['payload']['configVersion'] + 1;
		JCLog::debug($widgetId . ' found in the current equipment -- updating configVersion to ' . $configVersion);

		//update configVersion in the file
		$confStd['payload']['configVersion'] =  $configVersion;
		$this->saveConfig($confStd);

		//update configVersion in the equipment configuration
		$this->setConfiguration('configVersion', $configVersion);
		$this->save(true);

		JCLog::debug('Renewing the version of the widgets configuration');
		$this->getConfig(true, true);
		return true;
	}

	public static function getWidgetParam($only_name = true, $widget_types = array()) {
		$widgetsConfigJonFile = json_decode(file_get_contents(self::$_plugin_config_dir . 'widgetsConfig.json'), true);
		$count_widget_types = count($widget_types);

		$result = array();
		foreach (array_merge($widgetsConfigJonFile['widgets'], $widgetsConfigJonFile['components']) as $config) {
			if ($count_widget_types > 0 && !in_array($config['type'], $widget_types)) continue;

			$result[$config['type']] = $only_name ? $config['name'] : $config;
		}
		return $result;
	}

	public function moveWidgetIndex($widgetId, $parentId, $currentIndex, $newIndex) {
		try {
			JCLog::debug('moveWidgetIndex data : ' . $widgetId . ' - ' . $parentId . ' - ' . $currentIndex . ' - ' . $newIndex);
			$conf = $this->getConfig();
			if ($conf) {
				foreach (array('widgets', 'groups') as $type) {
					JCLog::debug('moveWidgetIndex -- dealing with type : ' . $type);
					foreach ($conf['payload'][$type] as $key => $value) {
						// JCLog::debug( 'moveWidgetIndex -- checking widget  : ' . json_encode($conf['payload']['widgets'][$key]) ) ;
						// JCLog::debug( 'moveWidgetIndex -- parentId  : ' . $value['parentId'] ) ;
						// JCLog::debug( 'moveWidgetIndex -- index : ' . $value['index'] ) ;

						if ($value['parentId']  != $parentId) continue;
						if ($value['index']  == $currentIndex) {
							$conf['payload'][$type][$key]['index'] = $newIndex;
							continue;
						}

						if ($currentIndex < $newIndex) {
							if ($value['index']  < $currentIndex || $value['index'] > $newIndex) continue;

							$conf['payload'][$type][$key]['index'] = intval($conf['payload'][$type][$key]['index']) - 1;
						} else {
							if ($value['index']  > $currentIndex || $value['index'] < $newIndex) continue;

							$conf['payload'][$type][$key]['index'] = intval($conf['payload'][$type][$key]['index']) + 1;
						}
					}
				}

				$this->saveConfig($conf);
			}
			$this->generateNewConfigVersion();
			return true;
		} catch (Exception $e) {
			JCLog::error('Unable to move index : ' . $e->getMessage());
			return false;
		}
	}

	public function removeWidgetConf($idToRemoveList) {

		$remove = false;

		$conf = $this->getConfig();

		JCLog::debug('Removing widget in equipement config file -- ' . json_encode($conf));
		if ($conf) {
			foreach ($conf['payload']['widgets'] as $key => $value) {
				if (in_array($value['id'], $idToRemoveList)) {
					JCLog::debug('Removing that widget item config -- ' . json_encode($value));
					unset($conf['payload']['widgets'][$key]);
					$remove = true;
				}
			}

			if ($remove) {
				$conf['payload']['widgets'] = array_values($conf['payload']['widgets']);
				JCLog::info('Widget ID ' . json_encode($idToRemoveList) . ' has been removed on equipement ' . $this->getName());
				$this->saveConfig($conf);
				$this->cleanCustomData();
				$this->generateNewConfigVersion();
				return;
			} else {
				JCLog::info('Widget ID ' . json_encode($idToRemoveList) . ' not found in equipement ' . $this->getName());
			}
		} else {
			JCLog::warning('No config content retrieved');
		}
		return;
	}

	public function resetConfigFile() {
		JCLog::debug('reseting configuration for equipment "' . $this->getName() . '" [' . $this->getConfiguration('apiKey') . ']');
		$this->saveConfig(self::$_initialConfig);
		$this->cleanCustomData();
	}

	public static function getPluginInfo() {

		$pluginInfo = json_decode(file_get_contents(self::$_plugin_info_dir . 'version.json'), true);
		$branchInfo = array(
			"typeVersion" => JeedomConnectUtils::isBeta(true),
			"enrollment" => "https://jeedomconnect-team.github.io/jc-doc/docs/documentation/faq#qBeta"
		);

		$result = array_merge($pluginInfo, $branchInfo);

		return $result;
	}

	public static function displayMessageInfo() {

		$pluginInfo = self::getPluginInfo();

		$apkVersionRequired = $pluginInfo['require'];
		message::add('JeedomConnect',  'Ce plugin nécessite d\'utiliser l\'application en version minimum : ' . $apkVersionRequired . '. Si nécessaire, pensez à mettre à jour votre application depuis votre Store');

		if ($pluginInfo['typeVersion'] == 'beta') {
			$enrollmentLink = htmlentities('<a href="' . $pluginInfo['enrollment'] . '" target="_blank">en cliquant ici</a>');
			message::add('JeedomConnect',  'Si ça n\'est pas déjà fait, pensez à vous inscrire dans le programme beta-testeur de l\'application sur le Store : ' . $enrollmentLink);
		}
	}


	public function toHtml($_version = 'dashboard') {
		$type = $this->getConfiguration('jceqtype', 'none');
		if ($type != 'map') return parent::toHtml($_version);

		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}

		$version = jeedom::versionAlias($_version);

		return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'map', 'JeedomConnect')));
	}

	public static function createMapEquipment() {
		$eqMap = eqLogic::byLogicalId('jcmapwidget', 'JeedomConnect');
		if (is_object($eqMap)) return;

		$eqMap = new eqLogic();
		$eqMap->setName('Localisation');
		$eqMap->setLogicalId('jcmapwidget');
		$eqMap->setConfiguration('jceqtype', 'map');
		$eqMap->setIsEnable(1);
		$eqMap->setEqType_name(__CLASS__);
		$eqMap->save();
	}

	public function addInEqConfiguration($key, $value, $separator = ',') {

		if (is_array($value)) {
			$arr = array();
			foreach ($value as $val) {
				JCLog::debug('Adding ' . $val . ' in configuration ' . $key);
				$arr[] = $val;
			}
			$str = implode($separator, array_filter($arr));
		} else {
			$str = $value;
		}

		$this->setConfiguration($key, $str);
		$this->save();

		return null;
	}


	/**
	 * @return listener
	 */
	private function getListener($fx = 'sendCmdInfoToShortcut') {
		return listener::byClassAndFunction(__CLASS__, $fx, array('id' => $this->getId()));
	}

	private function removeListener($fx) {
		$listener = $this->getListener($fx);
		if (is_object($listener)) {
			$listener->remove();
		}
	}

	private function setListener(array $cmd_ids = array(), string $fx = 'sendCmdInfoToShortcut') {
		JCLog::debug('------ setListener started -- adding listener for fx ' . $fx);
		JCLog::trace('------ setListener started -- ids ' . json_encode($cmd_ids));
		if ($this->getIsEnable() == 0 || count($cmd_ids) == 0) {
			JCLog::trace('remove listener');
			$this->removeListener($fx);
			return;
		}

		/** @var listener $listener */
		$listener = $this->getListener($fx);
		if (!is_object($listener)) {
			$listener = new listener();
			$listener->setClass(__CLASS__);
			$listener->setFunction($fx);
			$listener->setOption(array('id' => $this->getId()));
		}
		$listener->emptyEvent();

		foreach ($cmd_ids as $cmd_id) {
			if (!is_numeric($cmd_id)) continue;

			$cmd = cmd::byId($cmd_id);
			if (!is_object($cmd)) continue;
			JCLog::debug(' -- add listener for cmd ' . $cmd_id);
			$listener->addEvent($cmd_id);
		}
		$listener->save();
		JCLog::debug('------ setListener end for fx ' . $fx);
	}

	public static function sendCmdInfoToShortcut($_option) {
		JCLog::debug('sendCmdInfoToShortcut started -->>> ' . json_encode($_option));

		$result = JeedomConnectUtils::addTypeInPayload(JeedomConnectUtils::getCmdInfoDataIds(array($_option['event_id']), false), 'SET_QSTILES_INFO');;

		/** @var JeedomConnect $eqLogic */
		$eqLogic = eqLogic::byId($_option['id']);
		$eqLogic->sendNotif($eqLogic->getLogicalId(), $result);
		JCLog::debug('---- sendCmdInfoToShortcut end -->>> ' . json_encode($result));
	}

	public static function sendActiveControl($_option) {
		JCLog::debug('sendActiveControl started -->>> ' . json_encode($_option));

		/** @var JeedomConnect $eqLogic */
		$eqLogic = eqLogic::byId($_option['id']);

		if (!is_object($eqLogic)) {
			JCLog::warning('sendActiveControl - No eqLogic with id  [' . $_option['id'] . ']');
			return;
		}

		// CHECK if control page has been displayed since the last 5 min (300sec)
		$lastTime = $eqLogic->getConfiguration('activeControlTime', 0);
		$diff = time() - $lastTime;
		// JCLog::debug('diff : ' . $diff);
		if ($diff > 300) {
			JCLog::debug('Not sending sendActiveControl result - time exceed');
			return;
		}

		$confControls = $eqLogic->getConfiguration('activeControlIds');

		$result = array();
		foreach (explode(",", $confControls) as $widgetId) {
			// JCLog::debug('checking control ID : ' . $widgetId);

			// get the widget conf from generated file of the current eqlogic
			$widget = $eqLogic->getGeneratedWidget($widgetId);
			// JCLog::debug('getting widget : ' . json_encode($widget));

			// if there is no result -> continue
			if (empty($widget)) continue;

			// retrieve the IDs used in this widget
			$cmdIds = JeedomConnectUtils::getInfosCmdIds($widget);
			// JCLog::debug('cmdIds : ' . json_encode($cmdIds));

			// if the event is coming from a cmdId that is used in the widget, 
			// then get the device info on that widget otherwise do nothing (next)
			if (in_array($_option['event_id'], $cmdIds)) {
				// JCLog::debug('  ----  cmd found in widget ! ');
				$cmdIds = array_unique(array_filter($cmdIds, 'strlen'));
				$cmdData = JeedomConnectUtils::getCmdValues($cmdIds);

				$deviceConfig = JeedomConnectDeviceControl::getDeviceConfig($widget, $cmdData['data']);
				if ($deviceConfig != null) {
					$result[] =  $deviceConfig;
				}
			}
		}

		if (!empty($result)) {
			$payload = JeedomConnectUtils::addTypeInPayload(array("devices" => $result), 'SET_CONTROLS_INFO');
			$eqLogic->sendNotif($eqLogic->getLogicalId(), $payload);
		}
		JCLog::debug('---- sendActiveControl end -->>> ' . json_encode($payload));
	}

	public static function getConfigForCommunity($str = true) {

		$pluginType = JeedomConnectUtils::isBeta(true);

		$infoPlugin = '<b>Version JC</b> : ' . config::byKey('version', 'JeedomConnect', '#NA#') . ' ' . $pluginType  . '<br/>';

		$infoPlugin .= '<b>Version OS</b> : ' .  system::getDistrib() . ' ' . system::getOsVersion() . '<br/>';

		$infoPlugin .= '<b>Version PHP</b> : ' . phpversion() . '<br/>';

		list($tag,) = self::getNotifBinVersion();
		$infoPlugin .= '<b>Bin</b> : ' . self::install_notif_info() . ' / ' . $tag . '<br/>';
		$dep = self::dependancy_info();
		$infoPlugin .= '<b>Dépendances</b> : ' . ($dep['state'] ?? 'N/A') . '<br/><br/>';

		$infoPlugin .= '<b>Equipements</b> : <br/>';

		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$platformOs = $eqLogic->getConfiguration('platformOs');
			$platform = $platformOs != '' ? 'sur ' . $platformOs : $platformOs;

			$versionAppConfig = $eqLogic->getConfiguration('appVersion');
			$versionAppTypeConfig = $eqLogic->getConfiguration('appTypeVersion');
			$buildVersion = $eqLogic->getConfiguration('buildVersion');
			$warn = ($versionAppTypeConfig != '' && $versionAppTypeConfig != $pluginType) ? ' <i class="fas fa-exclamation-triangle" style="color:red"></i> ' : '';
			$buildVersionApp = ($pluginType == 'beta' && $buildVersion != '') ? ' (' . $buildVersion . ')' : '';
			$versionApp = $versionAppConfig != '' ? 'v' . $versionAppConfig . $buildVersionApp . ' ' . $versionAppTypeConfig . $warn  : $versionAppConfig;

			$connexionType = $eqLogic->getConfiguration('useWs') == '1' ? 'ws'  : '';
			$withPolling = $eqLogic->getConfiguration('polling') == '1' ? 'polling'  : '';

			$osVersionConfig = $eqLogic->getConfiguration('osVersion');
			$osVersion = $osVersionConfig != '' ? ' [os : ' . $osVersionConfig . ']'  : '';

			$cpl =  (($connexionType . $withPolling) == '')  ? '' : ' (' . ((($connexionType != '' && $withPolling != '')) ? ($connexionType . '/' . $withPolling) : (($connexionType ?: '')  . ($withPolling ?: ''))) . ')';

			$infoPlugin .= '&nbsp;&nbsp;' .  $eqLogic->getName();
			if ($platform == '' && $versionApp == '') {
				$infoPlugin .= ' : non enregistré';
			} else {
				$infoPlugin .=  ' : ' . $versionApp . ' ' . $platform . $osVersion . $cpl;
			}

			$infoPlugin .= ' - ' . JeedomConnectUtils::getUserInfo($eqLogic->getConfiguration('userId'));
			$infoPlugin .=  '<br/>';
		}

		/*
		$nbWar = 0;
		$arrWar = array();
		$nbErr = 0;
		$arrErr = array();
		foreach ((jeedom::health()) as $datas) {
			if ($datas['state'] === 2) {
				$nbWar++;
				$arrWar[] = $datas['name'];
			} else if (!$datas['state']) {
				$nbErr++;
				$arrErr[] = $datas['name'];
			}
		}
		
		$info = 'Infos Santé : <br/>';
		if ($nbWar > 0) $info .= ' ' . $nbWar . ' warning (' . implode(', ', $arrWar) . ') <br/>';
		if ($nbErr > 0) $info .= ' ' . $nbErr . ' erreur (' . implode(', ', $arrErr) . ') <br/>';

		$infoPlugin .= $info;
		*/


		if ($str) {
			$infoPlugin = '<br/>```<br/>' . str_replace(array('<b>', '</b>', '&nbsp;'), array('', '', ' '), $infoPlugin) . '<br/>```<br/>';
		}

		return $infoPlugin;
	}


	public static function execCmdFomAsync($_options) {
		JCLog::debug("Lancement d'une cmd plannifiée -- " . json_encode($_options));
		apiHelper::execCmd($_options['cmdId'], $_options);
	}

	public static function jobExecutor($options) {
		JeedomConnectAutomations::jobExecutor($options);
	}



	/**
	 * Allow to perform a // task exec : now or later
	 * from @Mips2648
	 *
	 * @param string $_method
	 * @param array|null $_option
	 * @param string $_date : 'Y-m-d H:i:s'
	 * @return void
	 */
	public static function executeAsync(string $_method, $_option = null, $_date = 'now') {
		if (!method_exists(__CLASS__, $_method)) {
			throw new InvalidArgumentException("Method provided for executeAsync does not exist: {$_method}");
		}

		$cron = new cron();
		$cron->setClass(__CLASS__);
		$cron->setFunction($_method);
		if (isset($_option)) {
			$cron->setOption($_option);
		}
		$cron->setOnce(1);
		$scheduleTime = strtotime($_date);
		$cron->setSchedule(cron::convertDateToCron($scheduleTime));
		$cron->save();
		if ($scheduleTime <= strtotime('now')) {
			$cron->run();
			JCLog::debug("Task '{$_method}' executed now");
		} else {
			$details = $_option['txtdetail'] ?? '';
			if ($details == '') {
				JCLog::debug("Task '{$_method}' scheduled at {$_date}");
			} else {
				JCLog::debug("Exécution de {$details} programmée à {$_date}");
			}
		}
	}


	/*
	 ************************************************************************
	 ****************** FUNCTION TO UPDATE CONF FILE FORMAT *****************
	 ******************    AND CREATE WIDGET ACCORDINGLY    *****************
	 ************************************************************************
	 */

	public static function migrationAllNotif() {
		$result = array();
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			foreach ($eqLogic->getCmd() as $cmd) {
				// JCLog::debug( '    | checking cmd : ' . $cmd->getName());
				if ($cmd->getLogicalId() != 'notifall' && strpos(strtolower($cmd->getLogicalId()), 'notif') !== false) {
					if ($cmd->getConfiguration('notifAll', false)) {
						// JCLog::debug( '    ++ adding cmd : ' . $cmd->getId());
						$cmd->setConfiguration('notifAll', '');
						$cmd->save();
						$result[] = $cmd->getId();
					}
				}
			}
		}

		config::save('notifAll', json_encode(array("name" => "Notifier les appareils JC", "cmd" => $result)), 'JeedomConnect');
		config::save('migration::notifAll', 'done', 'JeedomConnect');
	}

	public static function migrationAllNotif2() {
		$cmdNotif = config::byKey('notifAll', 'JeedomConnect', array());

		if (!isset($cmdNotif['name'])) {
			config::save('notifAll', json_encode(array("name" => "Notifier les appareils JC", "cmd" => $cmdNotif)), 'JeedomConnect');
		}
		config::save('migration::notifAll2', 'done', 'JeedomConnect');
	}


	public static function migrateAppPref() {
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$apiKey = $eqLogic->getLogicalId();

			$bkpDir = self::$_backup_dir . $apiKey;
			if (!is_dir($bkpDir)) continue;

			$files = JeedomConnectUtils::getFiles(realpath($bkpDir), false, false);
			foreach ($files as $item) {
				$fileInfo = pathinfo($item['path']);
				rename($item['path'], $bkpDir . '/appPref-' . $fileInfo['basename']);
			}
		}

		config::save('migration::appPref', 'done', 'JeedomConnect');
	}

	public static function migrateCustomData() {
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$apiKey = $eqLogic->getConfiguration('apiKey');
			// JCLog::debug('checking ' . $eqLogic->getName . ' [' . $apiKey . ']', '_mig')

			$customDataOriginal = config::byKey('customData::' . $apiKey, 'JeedomConnect');

			// JCLog::debug('data  ' . json_encode($customDataOriginal), '_mig')

			/** @var ?array $customDataOriginal */
			if (array_key_exists('widgets', $customDataOriginal)) {
				// JCLog::debug('widgets exist ! ', '_mig')
				foreach ($customDataOriginal['widgets'] as $key => $value) {
					// JCLog::debug('key : ' . $key . ' -- value :' . json_encode($value), '_mig')
					config::save('customData::' . $apiKey . '::' . $key, json_encode($value), 'JeedomConnect');
				}
			}
			config::remove('customData::' . $apiKey, 'JeedomConnect');
		}

		config::save('migration::customData', 'done', 'JeedomConnect');
	}

	public static function migrateCondImg() {
		try {
			$hasChangesConf = false;
			//****** UPDATE ALL WIDGETS CONFIG  ******
			foreach (JeedomConnectWidget::getWidgets() as $widget) {
				$currentChange = false;
				$widgetId = $widget['id'];
				$widgetJC = $widget['widgetJC'];

				if (isset($widgetJC['statusImages']) && count($widgetJC['statusImages']) > 0) {

					foreach ($widgetJC['statusImages'] as $key => $value) {
						if (isset($value['operator']) && isset($value['info']) && isset($value['value'])) {
							$hasChangesConf = true; //to make the new generation action [last one]
							$currentChange = true;

							$operator = $value['operator'] == '=' ? '==' : $value['operator'];

							$cond = '#' . $value['info']['id'] . '# ' . $operator . ' ' . $value['value'];
							$value['condition'] = $cond;
							unset($value['info']);
							unset($value['operator']);
							unset($value['value']);
							$widgetJC['statusImages'][$key] = $value;
						}
					}

					if ($currentChange) JeedomConnectWidget::setConfiguration($widgetId, 'widgetJC', json_encode($widgetJC));
				}
			}


			//****** UPDATE ALL EQUIPMENT JSON CONFIG (summary)  ******
			/** @var JeedomConnect $eqLogic */
			foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
				$hasChangesEq = false;
				$jsonConfig = $eqLogic->getConfig();

				foreach ($jsonConfig['payload']['summaries']  as $main => $summary) {

					if (isset($summary['statusImages']) && count($summary['statusImages']) > 0) {

						foreach ($summary['statusImages'] as $key => $value) {
							$currentChange = false;
							if (isset($value['operator']) && isset($value['info']) && isset($value['value'])) {
								$hasChangesEq = true;  // for the global save action [one before the last]

								$operator = $value['operator'] == '=' ? '==' : $value['operator'];

								$cond = '#' . $value['info']['id'] . '# ' . $operator . ' ' . $value['value'];
								$value['condition'] = $cond;
								unset($value['info']);
								unset($value['operator']);
								unset($value['value']);
								$summary['statusImages'][$key] = $value;
								$currentChange = true;
							}
						}

						if ($currentChange) $jsonConfig['payload']['summaries'][$main] = $summary;
					}
				}

				if ($hasChangesEq || $hasChangesConf) {
					$eqLogic->saveConfig($jsonConfig);
					$eqLogic->generateNewConfigVersion();
				}
			}

			config::save('migration::imgCond', 'done', 'JeedomConnect');
		} catch (Exception $e) {
			JCLog::error('Unable to migrate Img Condition : ' . $e->getMessage());
		}
	}

	public function isConnected() {
		if ($this->getConfiguration('useWs', 0) == 0 && $this->getConfiguration('polling', 0) == 1) {
			return time() - $this->getConfiguration('lastSeen', 0) < 3;
		} else {
			return $this->getConfiguration('appState', 0) == 'active';
		}
	}
}

class JeedomConnectCmd extends cmd {

	public static $_widgetPossibility = array(
		'custom' => true
	);

	public function dontRemoveCmd() {
		return true;
	}

	public function cancelAsk($notificationId, $answer, $eqNameAnswered, $dateAnswer) {
		/** @var JeedomConnect $eqLogic */
		$eqLogic = $this->getEqLogic();

		$data = array(
			'type' => 'ASK_ALREADY_ANSWERED',
			'payload' => array(
				'notificationId' => $notificationId,
				'answer' => $answer,
				'fromEquipement' => $eqNameAnswered,
				'dateAnswer' => $dateAnswer
			)
		);

		// JCLog::debug( ' sending notif data ==> ' . json_encode($data));
		$eqLogic->sendNotif($this->getLogicalId(), $data, $this->getId());
	}

	public function execute($_options = array()) {
		if ($this->getType() != 'action') {
			return;
		}

		/** @var JeedomConnect $eqLogic */
		$eqLogic = $this->getEqLogic();

		// JCLog::debug( 'start for : ' . $this->getLogicalId());

		$logicalId = (strpos(strtolower($this->getLogicalId()), 'notifall') !== false) ? 'notifAll' : ((strpos(strtolower($this->getLogicalId()), 'notif') !== false) ? 'notif' : $this->getLogicalId());

		// JCLog::debug( 'will execute action : ' . $logicalId . ' -- with option ' . json_encode($_options));

		switch ($logicalId) {
			case 'notifAll':
				$cmdNotif = config::byKey($this->getLogicalId(), 'JeedomConnect', array());
				$orignalCmdId = $this->getId();
				$timestamp = round(microtime(true) * 10000);
				// JCLog::debug( ' all cmd notif all : ' . json_encode($cmdNotif));

				/** @var ?array $cmdNotif */
				foreach ($cmdNotif['cmd'] as $cmdId) {
					$cmd = cmd::byId($cmdId);
					$_options['orignalCmdId'] = $orignalCmdId;
					$_options['notificationId'] = $timestamp;

					$cmdNotifCopy = array_values(array_diff($cmdNotif['cmd'], array($cmdId)));
					$_options['otherAskCmdId'] = count($cmdNotifCopy) > 0 ? $cmdNotifCopy : null;
					$cmd->execute($_options);
				}
				break;

			case 'notif':

				// JCLog::debug( ' ----- running exec notif ! ---------');
				$myData = self::getTitleAndArgs($_options);

				if (key_exists('answer', $_options) && $myData['title'] == $_options['message']) $myData['title'] = null;

				$data = array(
					'type' => 'DISPLAY_NOTIF',
					'payload' => array(
						'cmdId' => $_options['orignalCmdId'] ?? $this->getId(),
						'title' => $myData['title'],
						'message' => $myData['args']['message'] ?? $_options['message'],
						'answer' => $_options['answer'] ?? null,
						'timeout' => $_options['timeout'] ?? null,
						'notificationId' => $_options['notificationId'] ?? round(microtime(true) * 10000),
						'otherAskCmdId' => $_options['otherAskCmdId'] ?? null,
						'options' => $myData['args'] ?? null
					)
				);
				if (isset($_options["files"]) || isset($myData["files"])) {
					$files = array();
					$arrayMerge = array_merge($_options["files"] ?? array(), $myData["files"] ?? array());
					foreach ($arrayMerge as $file) {
						if (realpath($file)) array_push($files, realpath($file));
					}
					$data['payload']['files'] = $files;
				}
				// JCLog::debug(' notif payload ==> ' . json_encode($data), '_test')
				$eqLogic->sendNotif($this->getLogicalId(), $data, $this->getId());
				break;

			case 'goToPage':
				if ($_options['message'] == '') {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				$payload = array(
					'action' => 'goToPage',
					'pageId' => $_options['message']
				);
				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				}
				break;

			case 'toaster':
				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				$payload = array(
					'action' => 'toaster',
					'message' => $_options['message']
				);
				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'launchApp':
				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				$payload = array(
					'action' => 'launchApp',
					'packageName' => $_options['message']
				);
				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'shellExec':
				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				$payload = array(
					'action' => 'shellExec',
					'cmd' => $_options['message']
				);
				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'screenOn':
				$payload = array(
					'action' => 'screenOn'
				);
				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'screenOff':
				$payload = array(
					'action' => 'screenOff'
				);
				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'getDeviceInfos':
				$payload = array(
					'action' => 'getDeviceInfos'
				);
				if ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'eraseData':
				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				} elseif (strtolower($_options['message']) != 'erase') {
					JCLog::error('To use the erase data command, message field has to be filled with "erase"');
					return;
				}

				$payload = array(
					'action' => 'eraseData'
				);
				if ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'play_sound':
				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				$payload = array(
					'action' => 'playSound',
					'sound' => $_options['message']
				);
				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'ringerMode':
				if (empty($_options['title'])) {
					JCLog::error('Empty field "' . $this->getDisplay('title_placeholder', 'Titre') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}

				$payload = array(
					'action' => 'ringerMode',
					'mode' => $_options['title']
				);

				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}

				break;

			case 'dndMode':
				if (empty($_options['title'])) {
					JCLog::error('Empty field "' . $this->getDisplay('title_placeholder', 'Titre') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}

				$payload = array(
					'action' => 'dndMode',
					'mode' => $_options['title']
				);

				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}

				break;

			case 'setVolume':
				if (empty($_options['title']) && $eqLogic->getConfiguration('platformOs') == 'android') {
					JCLog::error('Empty field "' . $this->getDisplay('title_placeholder', 'Titre') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}

				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}

				$payload = array(
					'action' => 'setVolume',
					'volume' => intval($_options['message']),
					'type' => $_options['title']
				);

				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'tts':
				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				if (!empty($_options['title'])) {
					if (((string)(int)$_options['title'] !== $_options['title']) || (intval($_options['title']) < 0 || intval($_options['title']) > 100)) {
						JCLog::error('Field "' . $this->getDisplay('title_placeholder', 'Titre') . '" has to contain integer between 0 to 100 [cmdId : ' . $this->getId() . ']');
						return;
					}
				}
				$payload = array(
					'action' => 'tts',
					'message' => $_options['message'],
					'volume' => $_options['title']
				);
				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'unlink':
				$eqLogic->removeDevice();
				break;


			case 'display_menu':
				if (empty($_options['title'])) {
					JCLog::error('Empty field "' . $this->getDisplay('title_placeholder', 'Titre') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}

				$pageIds = explode(',', $_options['message']);
				$setVisible = $_options['title'] == 'show' ? true : false;

				$hasChange = false;

				$conf = $eqLogic->getConfig();

				// check "menu haut"
				if (key_exists('payload', $conf) && key_exists('tabs', $conf['payload'])) {
					foreach ($conf['payload']['tabs'] as $key => $menu) {
						if (key_exists('id', $menu) && in_array($menu['id'], $pageIds)) {
							$menu['enable'] = $setVisible;
							$conf['payload']['tabs'][$key] = $menu;
							$hasChange = true;
						}
					}
				}

				// check "menu bas"
				if (key_exists('payload', $conf) && key_exists('sections', $conf['payload'])) {
					foreach ($conf['payload']['sections'] as $key => $menu) {
						if (key_exists('id', $menu) && in_array($menu['id'], $pageIds)) {
							$menu['enable'] = $setVisible;
							$conf['payload']['sections'][$key] = $menu;
							$hasChange = true;
						}
					}
				}

				if ($hasChange) {
					$configVersion = $conf['payload']['configVersion'] + 1;
					$conf['payload']['configVersion'] =  $configVersion;
					JCLog::debug("saving new conf => " . json_encode($conf));
					$eqLogic->saveConfig($conf);
					$eqLogic->getConfig(true, true);
					$eqLogic->setConfiguration('configVersion', $configVersion);
					$eqLogic->save();
				} else {
					JCLog::debug("nothing to change...");
				}

				break;

			case 'display_widget':
				if (empty($_options['title'])) {
					JCLog::error('Empty field "' . $this->getDisplay('title_placeholder', 'Titre') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}

				$widgetIds = explode(',', $_options['message']);
				$setVisible = $_options['title'] == 'show' ? true : false;

				$cond = "'enable' == #auto#";
				$hasChange = false;

				foreach ($widgetIds as $widgetId) {

					$customConf = $eqLogic->getCustomConf($widgetId);
					if (!$setVisible) {
						// JCLog::debug('Masquer // position =>' .  strpos(json_encode($customConf), $cond));
						if (strpos(json_encode($customConf), $cond) === false) {
							if ($customConf == "") $customConf = array();
							// if ($customConf == "") $customConf = array('widgetId' => $widgetId);

							$initialValue = (($customConf['visibilityCond'] ?? '') != '') ? ($customConf['visibilityCond'] . ' && ') : '';
							$customConf['visibilityCond'] = $initialValue . $cond;
							$hasChange = true;
						}
					} else {
						// JCLog::debug('Afficher // position =>' .  strpos(json_encode($customConf), $cond));
						if (strpos(json_encode($customConf), $cond) !== false) {
							$customConf['visibilityCond'] = trim(str_replace(array('&& ' . $cond, $cond), array('', ''), $customConf['visibilityCond']));

							if ($customConf['visibilityCond'] == '') unset($customConf['visibilityCond']);

							$hasChange = true;
						}
					}

					if (count($customConf) == 0) {
						$eqLogic->removeCustomConf($widgetId);
					} else {
						$eqLogic->saveCustomConf($widgetId, $customConf);
					}
				}

				if ($hasChange) {
					JCLog::trace(' ===>> has change !');
					$eqLogic->generateNewConfigVersion();
				} else {
					JCLog::trace("nothing to change...");
				}

				break;

			case 'update_pref_app':
				if (empty($_options['title'])) {
					JCLog::error('Empty field "' . $this->getDisplay('title_placeholder', 'Titre') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				if ($_options['title'] == 'none') {
					JCLog::error('Please select an action for cmd "' . $this->getName() . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				if (empty($_options['message']) && $_options['message'] != '0') {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}

				switch (strtoupper($_options['message'])) {
					case 'START':
					case 'RUN':
					case 'MARCHE':
					case 'ON':
					case '1':
						$finalValue = 'ON';
						break;

					case 'STOP':
					case 'ARRET':
					case 'OFF':
					case '0':
						$finalValue = 'OFF';
						break;

					default:
						$finalValue = $_options['message'];
						break;
				}

				$payload = array(
					'action' => $_options['title'],
					'arg' => $finalValue
				);

				// JCLog::debug( 'payload sent ' . json_encode($payload));
				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			case 'remove_custo':
				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}

				$exist = $eqLogic->getCustomConf($_options['message']);
				JCLog::debug('custom exist => ' . json_encode($exist));
				$result = $eqLogic->removeCustomConf($_options['message']);
				if ($result) $eqLogic->generateNewConfigVersion();

				break;
			case 'send_sms':
				if (empty($_options['title'])) {
					JCLog::error('Empty field "' . $this->getDisplay('title_placeholder', 'Titre') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}
				if (empty($_options['message'])) {
					JCLog::error('Empty field "' . $this->getDisplay('message_placeholder', 'Message') . '" [cmdId : ' . $this->getId() . ']');
					return;
				}

				$args = self::arg2arrayCustom($_options['title']);
				if (empty($args)) {
					$num = $_options['title'];
					$sim = null;
					$files = null;
				} elseif (!empty($args['numero'])) {
					$num = $args['numero'];
					$sim = $args['sim'] ?? null;
					$files = isset($args['files']) ? explode(',', $args['files']) :  null;
				} else {
					JCLog::error('No numero found');
					return;
				}

				if (!is_null($files)) {
					$filesTemp = array();
					foreach ($files as $file) {
						if (realpath($file)) {
							array_push($filesTemp, realpath($file));
						}
					}
					$files = !empty($filesTemp) ? $filesTemp :  null;
				}

				$payload = array(
					'action' => 'send_sms',
					'numero' => $num,
					'message' => $_options['message'],
					'simId' => $sim,
					'files' => $files
				);

				// JCLog::debug('payload sent ' . json_encode($payload), '_test')
				if ($eqLogic->isConnected()) {
					JeedomConnectActions::addAction($payload, $eqLogic->getLogicalId());
				} elseif ($eqLogic->getConfiguration('platformOs') == 'android') {
					$eqLogic->sendNotif($this->getLogicalId(), array('type' => 'ACTIONS', 'payload' => $payload), $this->getId());
				}
				break;

			default:
				JCLog::error('unknow action [' . $logicalId . '] - options :' . json_encode($_options));
				break;
		}
	}


	public static function getTitleAndArgs($_options) {

		$optionsSupp = array();
		$args = array();
		$files = array();
		if (isset($_options['title'])) {
			$optionsSupp = self::arg2arrayCustom($_options['title']);
			//if no use of '|', then will try to use the standard fx
			// if (count($optionsSupp) == 0) $optionsSupp = arg2array($_options['title']);
		}

		if (empty($optionsSupp)) {
			$titre = $_options['title'] ?: '';
		} else {
			foreach ($optionsSupp as $key => $value) {
				$optionsSupp[$key] =  trim($value, '"');
			}

			$titre = isset($optionsSupp['title']) ? $optionsSupp['title'] : '';
			$args = $optionsSupp;
			if (isset($optionsSupp['title'])) unset($args['title']);

			if (isset($optionsSupp['files'])) {
				$files = explode(',', $optionsSupp['files']);
				unset($args['files']);
			}
		}
		return array('title' => $titre, 'args' => $args, 'files' => $files);
	}

	public static function arg2arrayCustom($data) {
		$dataArray = explode('|', $data);
		$result = array();
		foreach ($dataArray as $item) {
			$arg = explode('=', trim($item), 2);
			if (count($arg) == 2) {
				$result[trim($arg[0])] = trim($arg[1]);
			}
		}
		return $result;
	}

	public function getWidgetTemplateCode($_version = 'dashboard', $_clean = true, $_widgetName = '') {

		// if ($_version != 'scenario') return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);

		if ($this->getDisplay('title_with_list', '') != 1) return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);

		// $template = getTemplate('core', 'scenario', 'cmd.action.message_with_choice', 'JeedomConnect');
		$template = getTemplate('core', $_version, 'cmd.action.message_with_choice', 'JeedomConnect');

		if (!empty($template)) {
			if (version_compare(jeedom::version(), '4.2.0', '>=')) {
				if (!is_array($template)) return array('template' => $template, 'isCoreWidget' => false);
			} else {
				$replace = array();
				$replace['#listValue#'] = $this->getListOption();
				$html = template_replace($replace, $template);

				return $html;
			}
		}
		return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
	}

	public function getListOption() {
		$listOption = '';
		if ($this->getConfiguration('listValue', '') != '') {
			$elements = explode(';', $this->getConfiguration('listValue', ''));
			$foundSelect = false;
			foreach ($elements as $element) {
				$coupleArray = explode('|', $element);
				$cmdValue = $this->getCmdValue();
				if (is_object($cmdValue) && $cmdValue->getType() == 'info') {
					if ($cmdValue->execCmd() == $coupleArray[0]) {
						$listOption .= '<option value="' . $coupleArray[0] . '" selected>' . $coupleArray[1] . '</option>';
						$foundSelect = true;
					} else {
						$listOption .= '<option value="' . $coupleArray[0] . '">' . $coupleArray[1] . '</option>';
					}
				} else {
					$listOption .= '<option value="' . $coupleArray[0] . '">' . $coupleArray[1] . '</option>';
				}
			}
			if (!$foundSelect) {
				$listOption = '<option value="none" selected>-- A SELECTIONNER --</option>' . $listOption;
			}
		}

		return $listOption;
	}
}
