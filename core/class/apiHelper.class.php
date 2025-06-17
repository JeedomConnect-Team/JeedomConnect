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
 *
 */

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

class apiHelper {
  public static $_skipLog = array('GET_EVENTS', 'GET_LOG');

  /**
   * Dispatch API call
   * @param string $type 'API' | 'WS'
   * @param string $method one of the API methods
   * @param JeedomConnect|null $eqLogic a JeedomConnect eqLogic or null
   * @param array<string>|null $param
   * @param string $apiKey
   * @return null|array
   */
  public static function dispatch($type, $method, $eqLogic, $param, $apiKey) {

    try {
      if (is_object($eqLogic) && !$eqLogic->getIsEnable()) {
        return array('type' => 'EQUIPMENT_DISABLE');
      }

      switch ($method) {
        case 'PING':
          if (is_object($eqLogic)) {
            $eqLogic->setConfiguration('appState', 'active');
            $eqLogic->save(true);
          }
          return null;
          break;

        case 'CHECK_AUTHENT':
          return self::checkAuthentication($param['login'] ?? '', $param['password'] ?? '');
          break;

        case 'CHECK_USER':
          return self::checkUser($param['userHash'] ?? '');
          break;

        case 'VERIF_2FA':
          return self::verifyTwoFactorAuthentification($param['userHash'] ?? '', $param['password2FA'] ?? '');
          break;

        case 'REGISTER_DEVICE':
          $rdk = self::registerUser($eqLogic, $param['userHash'], $param['rdk']);
          return $rdk;
          break;

        case 'GET_AVAILABLE_EQUIPEMENT':
          $result = self::getAvailableEquipement($param['userHash']);
          return $result;
          break;

        case 'DETACH_EQUIPEMENT':
          $result = self::detachEquipement($param['eqId']);
          return $result;
          break;

        case 'CMD_EXEC':
          $result = self::execCmd($param['id'], $param['options'] ?? null);
          return $result;
          break;

        case 'CMDLIST_EXEC':
          // example : used for a light group
          $result = self::execMultipleCmd($param['cmdList']);
          return $result;
          break;

        case 'SC_EXEC':
          $param['options']['tags'] = ($param['options']['tags'] ?? '') . ' eqId=' . $eqLogic->getId();
          $result = self::execSc($param['id'], $param['options'], $eqLogic->getId());
          return $result;
          break;

        case 'SC_STOP':
          self::stopSc($param['id']);
          return null;
          break;

        case 'SC_SET_ACTIVE':
          self::setActiveSc($param['id'], $param['active']);
          return null;
          break;

        case 'QUERY_INTERACT':
          $result = self::queryInteract($param['query'], $param['options'], $param['keywordIndex']);
          return $result;
          break;

        case 'GET_PLUGIN_CONFIG':
          $conf = self::getPluginConfig($eqLogic);
          return $conf;
          break;

        case 'GET_CONFIG':
          $config = JeedomConnectUtils::addTypeInPayload($eqLogic->getGeneratedConfigFile(), 'SET_CONFIG');
          return $config;
          break;

        case 'GET_PWD':
          return JeedomConnectUtils::addTypeInPayload($eqLogic->getConfiguration('pwdAction', null), 'SET_PWD');
          break;

        case 'GET_BATTERIES':
          $config = self::getBatteries();
          return $config;
          break;

        case 'GET_CMD_INFO':
          $result = self::getCmdInfoData($eqLogic->getGeneratedConfigFile());
          return $result;
          break;

        case 'GET_OBJ_INFO':
          $result = self::getObjectData($eqLogic->getGeneratedConfigFile());
          return $result;
          break;

        case 'GET_SC_INFO':
          $result = self::getScenarioData($eqLogic->getGeneratedConfigFile());
          return $result;
          break;

        case 'GET_ALL_SC':
          $eqLogic->setConfiguration('scAll', 1);
          $eqLogic->save(true);
          $result = self::getScenarioData($eqLogic->getGeneratedConfigFile(), true);
          return $result;
          break;

        case 'GET_INFO':
          $result = self::getAllInformations($eqLogic);
          return $result;
          break;

        case 'SET_APPSTATE':
          $eqLogic->setConfiguration('appState', $param['state']);
          $eqLogic->save(true);
          return null;
          break;

        case 'GET_JEEDOM_DATA':
          $result = self::getFullJeedomData();
          return $result;
          break;

        case 'GET_WIDGET_DATA':
          $result = self::getWidgetData();
          return $result;
          break;

        case 'GET_WIDGET_WITH_GEN_TYPE':
          $result = self::getWidgetFromGenType($param['widget_type'], $param['eqId'] ?? null);
          return $result;
          break;

        case 'GET_PLUGINS_UPDATE':
          return self::getPluginsUpdate();
          break;

        case 'DO_PLUGIN_UPDATE':
          $result = self::doUpdate($param['pluginId']);
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_PLUGIN_UPDATE');
          break;

        case 'GET_JEEDOM_GLOBAL_HEALTH':
          return self::getJeedomHealthDetails();
          break;

        case 'DAEMON_PLUGIN_RESTART':
          $result = self::restartDaemon($param['userId'], $param['pluginId']);
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_DAEMON_PLUGIN_RESTART');
          break;

        case 'DAEMON_PLUGIN_STOP':
          $result = self::stopDaemon($param['userId'], $param['pluginId']);
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_DAEMON_PLUGIN_STOP');
          break;

        case 'UNSUBSCRIBE_SC':
          $eqLogic->setConfiguration('scAll', 0);
          $eqLogic->save(true);
          return null;
          break;

        case 'GET_HISTORY':
          return self::getHistory($param['id'], $param['options']);
          break;
        case 'GET_HISTORIES':
          return self::getHistories($param['ids'], $param['options']);
          break;
        case 'GET_FILES':
          return self::getFiles($param['folder'], $param['recursive'], true, $param['prefixe'] ?? null);
          break;

        case 'REMOVE_FILE':
          return self::removeFile($param['file']);
          break;

        case 'REMOVE_FILES':
          return self::removeFiles($param['files'], $param['path']);
          break;

        case 'SET_BATTERY':
          self::saveBatteryEquipment($eqLogic, $param['level']);
          return null;
          break;

        case 'SET_WIDGET':
          self::setWidget($param['widget']);
          return null;
          break;

        case 'ADD_WIDGETS':
          self::addWidgets($eqLogic, $param['widgets'], $param['parentId'], $param['index']);
          return null;
          break;

        case 'REMOVE_WIDGET':
          self::removeWidget($eqLogic, $param['widgetId']);
          return null;
          break;

        case 'MOVE_WIDGET':
          self::moveWidget($eqLogic, $param['widgetId'], $param['destinationId'], $param['destinationIndex']);
          return null;
          break;

        case 'SET_CUSTOM_WIDGETS':
          self::setCustomWidgetList($eqLogic, $param['customWidgetList']);
          return null;
          break;

        case 'SET_GROUP':
          self::setGroup($eqLogic, $param['group']);
          return null;
          break;

        case 'REMOVE_GROUP':
          self::removeGroup($eqLogic, $param['id']);
          return null;
          break;

        case 'ADD_GROUP':
          self::addGroup($eqLogic, $param['group']);
          return null;
          break;

        case 'MOVE_GROUP':
          self::moveGroup($eqLogic, $param['groupId'], $param['destinationId'], $param['destinationIndex']);
          return null;
          break;

        case 'REMOVE_GLOBAL_WIDGET':
          self::removeGlobalWidget($param['id']);
          return null;
          break;

        case 'ADD_GLOBAL_WIDGETS':
          $result = self::addGlobalWidgets($param['widgets']);
          return $result;
          break;

        case 'SET_BOTTOM_TABS':
          self::setBottomTabList($eqLogic, $param['tabs'], $param['migrate'], $param['idCounter']);
          return null;
          break;

        case 'REMOVE_BOTTOM_TAB':
          self::removeBottomTab($eqLogic, $param['id']);
          return null;
          break;

        case 'SET_TOP_TABS':
          self::setTopTabList($eqLogic, $param['tabs'], $param['migrate'], $param['idCounter']);
          return null;
          break;

        case 'REMOVE_TOP_TAB':
          self::removeTopTab($eqLogic, $param['id']);
          return null;
          break;

        case 'MOVE_TOP_TAB':
          self::moveTopTab($eqLogic, $param['sectionId'], $param['destinationId']);
          return null;
          break;

        case 'SET_PAGE_DATA':
          self::setPageData($eqLogic, $param['rootData'], $param['idCounter']);
          return null;
          break;

        case 'SET_ROOMS':
          self::setRooms($eqLogic, $param['rooms']);
          return null;
          break;

        case 'SET_SUMMARIES':
          self::setSummaries($eqLogic, $param['summaries']);
          return null;
          break;

        case 'SET_BACKGROUNDS':
          self::setBackgrounds($eqLogic, $param['backgrounds']);
          return null;
          break;

        case 'SET_APP_CONFIG':
          $result = self::setAppConfig($apiKey, $param['name'], $param['config']);
          return $result;
          break;

        case 'GET_APP_CONFIG':
          return self::getAppConfig($apiKey, $param['configId']);
          break;

        case 'ADD_GEOFENCE':
          self::addGeofence($eqLogic, $param['geofence'], $param['coordinates']);
          return null;
          break;

        case 'REMOVE_GEOFENCE':
          self::removeGeofence($eqLogic, $param['geofence']);
          return null;
          break;

        case 'GET_GEOFENCES':
          $result = self::getGeofencesData($eqLogic);
          return $result;
          break;

        case 'GET_NOTIFS_CONFIG':
          $result = self::getNotifConfig($eqLogic);
          return $result;
          break;

        case 'SET_NOTIFS_CONFIG':
          self::setNotifConfig($eqLogic, $param['notifsConfig']);
          return null;
          break;

        case 'TEST_NOTIF':
          self::testNotif($eqLogic, $param['notifId']);
          return null;
          break;

        case 'GEOLOC':
          self::setGeofence($eqLogic, $param);
          return null;
          break;

        case 'ASK_REPLY':
          self::setAskReply($eqLogic, $param);
          return null;
          break;

        case 'GET_EVENTS':
          $eqLogic->setConfiguration('lastSeen', time());
          $eqLogic->save(true);

          if ($eqLogic->getConfiguration('appState', '') != 'active') {
            return null;
          }

          if (!is_null($param['configVersion'])) {
            $newConfig = self::lookForNewConfig($eqLogic, $param['configVersion']);
            if ($newConfig != false) {
              JCLog::debug("Send new config : " . json_encode($newConfig));
              $infos = self::getAllInformations($eqLogic, false);
              // return array('type' => 'CONFIG_AND_INFOS', 'payload' => array('config' => $newConfig, 'infos' => $infos));
              $ConfigAndInfos = array('type' => 'CONFIG_AND_INFOS', 'payload' => array('config' => $newConfig, 'infos' => $infos));
              // JCLog::debug("config and info : " . json_encode($allR));
              return $ConfigAndInfos;
            }
          }

          $actions = self::getJCActions($apiKey);
          if (count($actions['payload']) > 0) {
            return $actions;
          }

          $result = self::getEventsFull($eqLogic, $param['lastReadTimestamp'], $param['lastHistoricReadTimestamp']);
          return array('type' => 'SET_EVENTS', 'payload' => $result);

          // TODO : target solution
          /*
          $newConfig = array(
            'type' => 'SET_CONFIG',
            'payload' => apiHelper::lookForNewConfig(eqLogic::byLogicalId($apiKey, 'JeedomConnect'), $param['configVersion']) ?: array()
          );


          $actions = self::getJCActions($apiKey);

          $allEvents = JeedomConnectUtils::addTypeInPayload(self::getEventsFull($eqLogic, $param['lastReadTimestamp']), 'ALL_EVENTS');

          $payload = array($newConfig, $actions, $allEvents);
          $result = JeedomConnectUtils::addTypeInPayload($payload, 'SET_EVENTS');

          return $result;
          */
          break;


        case 'CONNECT':
          $result = self::checkConnexion($eqLogic, $param);
          return $result;
          break;

        case 'DISCONNECT':
          $result = self::disconnect($eqLogic);
          return null;
          break;

        case 'GET_LOG':
          $result = self::getLog($param['type'], $param['id']);
          return $result;
          break;

        case 'SET_DEVICE_INFOS':
          self::setDeviceInfos($eqLogic, $param);
          return null;
          break;

        case 'SET_FACE_DETECTED':
          self::setFaceDetected($eqLogic, $param);
          return null;
          break;

        case 'GET_INSTALL_DETAILS':
          $result = JeedomConnectUtils::getCommunityUrl();
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_INSTALL_DETAILS');
          break;

        case 'SET_LOG':
          $action = $param['level'] ?? 'debug';
          JCLog::$action($param['text'] ?? '');
          return null;
          break;

        case 'GET_TIMELINE_FOLDERS':
          $result = JeedomConnectUtils::getTimelineFolders();
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_TIMELINE_FOLDERS');
          break;

        case 'GET_TIMELINE_EVENTS':
          $result = JeedomConnectUtils::getTimelineEvents($param['folder'] ?? 'main', $param['user_id'] ?? null);
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_TIMELINE_EVENTS');
          break;

        case 'GET_JEEDOM_MESSAGES':
          $result = JeedomConnectUtils::getJeedomMessages($param['plugin'] ?? '');
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_JEEDOM_MESSAGES');
          break;

        case 'REMOVE_JEEDOM_MESSAGE':
          JeedomConnectUtils::removeJeedomMessage($param['messageId'] ?? null);
          $result = JeedomConnectUtils::getJeedomMessages();
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_JEEDOM_MESSAGES');
          break;

        case 'GET_NEW_APIKEY':
          $result = self::getApiKeyRegenerated($apiKey);
          return $result;
          break;

        case 'SET_EVENT':
          self::setEvent($param['commandId'], $param['message']);
          return null;
          break;

        case 'SET_WEBSOCKET':
          $result = self::setWebsocket($eqLogic, $param['value']);
          return $result;
          break;

        case 'SET_POLLING':
          $result = self::setPolling($eqLogic, $param['value']);
          return $result;
          break;

        case 'SET_PICOKEY':
          self::setPicoKey($eqLogic, $param['value']);
          return null;
          break;

        case 'GET_PICOKEY':
          return self::getPicoKey($eqLogic);
          break;

        case 'COPY_CONFIG':
          JeedomConnectUtils::copyConfig($param['from'], $param['to'], $param['withCustom']);
          return null;
          break;


        case 'SET_CMD_SHORTCUT':
          $eqLogic->addInEqConfiguration('cmdInShortcut', $param['cmdId']);
          return
            JeedomConnectUtils::addTypeInPayload(JeedomConnectUtils::getCmdInfoDataIds($param['cmdId'], false), 'SET_QSTILES_INFO');
          break;

        case 'GET_CONTROL_DEVICES':
          $result = JeedomConnectDeviceControl::getDevices($eqLogic, $param['activeControlIds'] ?? null);
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_CONTROL_DEVICES');
          break;

        case 'ADD_AUTOMATION':
          $result = JeedomConnectAutomations::addAutomation($eqLogic, $param['automation'] ?? null);
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_AUTOMATIONS');
          break;

        case 'REMOVE_AUTOMATION':
          $result = JeedomConnectAutomations::removeAutomation($eqLogic, $param['automation'] ?? null);
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_AUTOMATIONS');
          break;

        case 'REMOVE_ALL_AUTOMATIONS':
          $result = JeedomConnectAutomations::removeAllAutomation($eqLogic);
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_AUTOMATIONS');
          break;

        case 'GET_AUTOMATIONS':
          $result = JeedomConnectAutomations::getAutomations($eqLogic);
          return JeedomConnectUtils::addTypeInPayload($result, 'SET_AUTOMATIONS');
          break;

        default:
          return self::raiseException('[' . $type . '] - method not defined', $method);
          break;
      }
    } catch (Exception $e) {
      return self::raiseException('[' . $type . '] - ' . $e->getMessage(), $method);
    }
  }

  // GENERIC FUNCTIONS
  public static function getAllInformations($eqLogic, $withType = true) {
    $returnType = 'SET_INFO';

    if (!is_object($eqLogic)) {
      throw new Exception('No equipment found');
    }

    $config = $eqLogic->getGeneratedConfigFile();
    $payload =  array(
      'cmdInfo' => self::getCmdInfoData($config, false),
      'scInfo' => self::getScenarioData($config, false, false),
      'objInfo' => self::getObjectData($config, false)
    );

    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  // CONNEXION FUNCTIONS

  /**
   * Check if the login and password provided are correct, and match an existing user
   *
   * @param string $login
   * @param string $password
   * @return array
   */
  private static function checkAuthentication($login = '', $password = '') {
    $returnType = 'SET_AUTHENT';

    $payload = array(
      'userHash' => null,
      'userProfil' => null
    );

    if ($login == '' || $password == '') {
      return self::raiseException(__('L\'identifiant ou le mot de passe ne peuvent pas être vide', __FILE__));
    }

    $user = user::connect($login, $password);

    if (!is_object($user)) {
      return self::raiseException(__('Echec lors de l\'authentification', __FILE__));
    }

    $payload['userHash'] = $user->getHash();
    $payload['userProfil'] = $user->getProfils();

    return JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  /**
   * Check if the user linked to the userHash provided is :
   *   - enabled
   *   - allowed for external connection (if it is)
   *   - required a two factor authentication
   *
   * @param string $userHash
   * @return array
   */
  private static function checkUser($userHash) {
    $returnType = 'SET_CHECK_USER';

    $payload = array(
      'twoFactorAuthentificationRequired' => false
    );

    $user = user::byHash($userHash);

    if (!is_object($user)) {
      return self::raiseException(__('Echec lors de l\'authentification', __FILE__));
    }

    if ($user->getEnable() != 1) {
      return self::raiseException(__('L\'utilisateur n\'est pas actif', __FILE__));
    }

    JCLog::debug('ClientIp => ' . network::getClientIp());
    JCLog::debug('network => ' . network::getUserLocation());
    JCLog::debug('localOnly => ' . $user->getOptions('localOnly', 0));

    if (network::getUserLocation() != 'internal' &&  $user->getOptions('localOnly', 0) == 1) {
      return self::raiseException(__('Connexion distante interdite', __FILE__));
    }

    $payload['twoFactorAuthentificationRequired'] = self::hasTwoFactorAuthentification($user);

    return JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  /**
   * Define if a two factor authentication is required
   *
   * @param user $user
   * @return boolean
   */
  private static function hasTwoFactorAuthentification($user) {

    return network::getUserLocation() != 'internal' &&
      $user->getOptions('twoFactorAuthentification', 0) == 1 &&
      $user->getOptions('twoFactorAuthentificationSecret') != '';
  }

  /**
   * Verify if the 2FA code is available for a specific user
   *
   * @param string $userHash
   * @param string $password2FA
   * @return array
   */
  private static function verifyTwoFactorAuthentification($userHash, $password2FA = '') {
    $returnType = 'SET_2FA';

    $payload = array(
      'authorized' => false
    );

    $user = user::byHash($userHash);
    $payload['authorized'] =  (trim($password2FA) != '' && is_object($user) && $user->validateTwoFactorCode($password2FA));

    return JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  private static function disconnect($eqLogic) {
    $eqLogic->setConfiguration('connected', 0);
    $eqLogic->setConfiguration('appState', 'background');
    $eqLogic->save(true);
  }


  /**
   * Make all the primaries checks to control if the connection can be done
   *
   * @param JeedomConnect $eqLogic a JeedomConnect eqLogic
   * @param array $param
   * @param boolean $withType
   * @return array
   */
  private static function checkConnexion($eqLogic, $param, $withType = true) {

    $versionJson = JeedomConnect::getPluginInfo();

    //check registered device
    if ($eqLogic->getConfiguration('deviceId') != $param['deviceId'] && $eqLogic->getConfiguration('deviceId') != '') {
      JCLog::warning("Try to connect to an invalid device (eq already used)");
      return array('type' => 'BAD_DEVICE');
    }

    //check if eqLogic is enable
    if (!$eqLogic->getIsEnable()) {
      JCLog::warning("Equipment " . $eqLogic->getName() . " is disabled");
      return array('type' => 'EQUIPMENT_DISABLE');
    }

    //check if plugin=app type version => stable/stable or beta/beta
    $pluginType = JeedomConnectUtils::isBeta() ? 'beta' : 'stable';
    if (config::byKey('ctrl::appTypeVersion', 'JeedomConnect', true) && key_exists('appTypeVersion', $param) &&  $param['appTypeVersion'] != $pluginType) {
      JCLog::warning("App and Plugin not aligned : beta/beta or stable/stable. Here app=" . $param['appTypeVersion'] . "/plugin=" . $pluginType);
      return array('type' => 'BAD_TYPE_VERSION');
    }

    //check version requirement
    if (version_compare($param['appVersion'], $versionJson['require'], "<")) {
      JCLog::warning("Failed to connect : bad version requirement");
      return array(
        'type' => 'APP_VERSION_ERROR',
        'payload' => JeedomConnect::getPluginInfo()
      );
    }

    if (version_compare($versionJson['version'], $param['pluginRequire'], "<")) {
      JCLog::warning("Failed to connect : bad plugin requirement");
      return array('type' => 'PLUGIN_VERSION_ERROR');
    }

    if ($eqLogic->getConfiguration('deviceId') != $param['deviceId'] || $eqLogic->getConfiguration('deviceName') != $param['deviceName']) {
      JCLog::info("Register new device {$param['deviceName']}");
      $eqLogic->registerDevice($param['deviceId'], $param['deviceName']);
    }
    $eqLogic->registerToken($param['token']);

    $user = user::byId($eqLogic->getConfiguration('userId'));
    if ($user == null) {
      return self::raiseException(__('Aucun utilisateur configuré sur l\'appareil', __FILE__));
    }

    $userConnected = user::byHash($param['userHash']);
    if (!is_object($userConnected)) $userConnected = $user;

    $testConnection = self::checkUser($param['userHash']);
    // JCLog::debug("test connexion ==> " . json_encode($testConnection));
    if ($testConnection['type'] == 'EXCEPTION') {
      return $testConnection;
    }

    $config = $eqLogic->getGeneratedConfigFile();

    //check config content
    if (is_null($config)) {
      $wrongFile = true;
      $newConfig = $eqLogic->restoreConfigFile();
      if (!is_null($newConfig)) {
        $wrongFile = false;
      }

      if ($wrongFile) {
        JCLog::warning("Failed to connect : empty config file");
        return array('type' => 'EMPTY_CONFIG_FILE');
      }
    }

    //check config format version
    if (!array_key_exists('formatVersion', $config)) {
      $wrongFile = true;
      $newConfig = $eqLogic->restoreConfigFile();
      if (array_key_exists('formatVersion', $newConfig)) {
        $wrongFile = false;
      }

      if ($wrongFile) {
        JCLog::warning("Failed to connect : bad format version");
        return array('type' => 'FORMAT_VERSION_ERROR');
      }
    }

    if ($eqLogic->getConfiguration('platformOs') == '') $eqLogic->createCommands($param['platformOs']);
    $eqLogic->setConfiguration('platformOs', $param['platformOs']);
    $eqLogic->setConfiguration('appVersion', $param['appVersion'] ?? '#NA#');
    $eqLogic->setConfiguration('appTypeVersion', $param['appTypeVersion'] ?? '');
    $eqLogic->setConfiguration('buildVersion', $param['buildVersion'] ?? '');
    $eqLogic->setConfiguration('connected', 1);
    $eqLogic->setConfiguration('scAll', 0);
    $eqLogic->setConfiguration('appState', 'active');
    $eqLogic->setConfiguration('osVersion', $param['osVersion'] ?? '');
    $eqLogic->save(true);

    return self::getWelcomeMsg($eqLogic, $userConnected, $versionJson['version'], $withType);
  }

  /**
   * getWelcomeMsg
   *
   * @param JeedomConnect $eqLogic
   * @param user $userConnected
   * @param string $pluginVersion
   * @param boolean $withType
   * @return array
   */
  private static function getWelcomeMsg($eqLogic, $userConnected, $pluginVersion, $withType = true) {
    $returnType = 'WELCOME';

    $config = $eqLogic->getGeneratedConfigFile();
    $notifsConfig =  $eqLogic->getNotifs();

    $payload = array(
      'pluginVersion' => $pluginVersion,
      'jeedomName' => config::byKey('name'),
      'serverId' => config::byKey('register::id'),
      'eqName' => $eqLogic->getName(),
      'useWs' => $eqLogic->getConfiguration('useWs', 0),
      'polling' => $eqLogic->getConfiguration('polling', 0),
      'userHash' => $userConnected->getHash(),
      'userId' => (string) $userConnected->getId(),
      'userName' => $userConnected->getLogin(),
      'userProfil' => $userConnected->getProfils(),
      'userImgPath' => config::byKey('userImgPath',   'JeedomConnect'),
      'configVersion' => $eqLogic->getConfiguration('configVersion'),
      'notifsVersion' => $notifsConfig['idCounter'],
      'scenariosEnabled' => $eqLogic->getConfiguration('scenariosEnabled') == '1',
      'webviewEnabled' => $eqLogic->getConfiguration('webviewEnabled') == '1',
      'automationsEnabled' => $eqLogic->getConfiguration('automationsEnabled') == '1',
      'editEnabled' => $userConnected->getProfils() == 'admin', //$eqLogic->getConfiguration('editEnabled') == '1',
      'getLogAllowed' => $userConnected->getProfils() == "admin",
      'pluginConfig' => self::getPluginConfig($eqLogic, false),
      'cmdInfo' => self::getCmdInfoData($config, false),
      'scInfo' => self::getScenarioData($config, false, false),
      'objInfo' => self::getObjectData($config, false),
      'geofenceInfo' => self::getGeofencesData($eqLogic, false),
      'links' => JeedomConnectUtils::getLinks(),
      'timezone' => date_default_timezone_get(),
      // check timelineclass for old jeedom core
      'timelineFolders' => (class_exists('timeline') && $eqLogic->getConfiguration('timelineEnabled', 1) == '1') ?  JeedomConnectUtils::getTimelineFolders() : null,
    );

    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  /**
   * setAskReply
   *
   * @param JeedomConnect $eqLogic
   * @param array $param
   * @return void
   */
  private static function setAskReply($eqLogic, $param) {
    $answer = $param['answer'];
    $cmd = cmd::byId($param['cmdId']);
    if (!is_object($cmd)) {
      throw new Exception("Can't find command [id=" . $param['cmdId'] . "]");
    }
    if (!$cmd->askResponse($answer)) {
      JCLog::warning('issue while reply to ask');
    }

    // if ASK was sent to other equipment, then we will let them know that an answer was already given
    if (!empty($param['otherAskCmdId']) && !is_null($param['otherAskCmdId'])) {
      $eqName = $eqLogic->getName();

      foreach ($param['otherAskCmdId'] as $cmdId) {
        /** @var JeedomConnectCmd $cmd */
        $cmd = JeedomConnectCmd::byId($cmdId);
        if (is_object($cmd)) {
          $cmd->cancelAsk($param['notificationId'], $answer, $eqName, $param['dateAnswer']);
        }
      }
    }

    return;
  }

  // EQUIPMENT FUNCTIONS
  private static function getAvailableEquipement($userHash, $withType = true) {
    $returnType = 'AVAILABLE_EQUIPEMENT';

    /** @var array<JeedomConnect> $eqLogics */
    $eqLogics = JeedomConnect::getAllJCequipment();

    if (is_null($eqLogics)) {
      throw new Exception(__("No equipment available", __FILE__), -32699);
    }

    $payload = array();
    $userConnected = user::byHash($userHash);
    $userConnectedProfil = is_object($userConnected) ? $userConnected->getProfils() : null;
    foreach ($eqLogics as $eqLogic) {
      $userOnEquipment = user::byId($eqLogic->getConfiguration('userId'));
      if (is_object($userOnEquipment)) {
        $userOnEquipmentHash = $userOnEquipment->getHash();
      } else {
        JCLog::warning('No user found on ' . $eqLogic->getName());
        $userOnEquipmentHash = null;
      }

      if (strtolower($userConnectedProfil) == 'admin' || $userOnEquipmentHash == $userHash) {
        array_push($payload, array(
          'logicalId' => $eqLogic->getLogicalId(),
          'name' => $eqLogic->getName(),
          'enable' => (string) $eqLogic->getIsEnable(),
          'useWs' => $eqLogic->getConfiguration('useWs', 0),
          'polling' => $eqLogic->getConfiguration('polling', 0),
          'deviceName' => $eqLogic->getConfiguration('deviceName', null),
          'deviceId' => $eqLogic->getConfiguration('deviceId', null)
        ));
      }
    }

    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  private static function detachEquipement($eqId) {
    /** @var JeedomConnect $eq */
    $eq = eqLogic::byLogicalId($eqId, 'JeedomConnect');
    if (is_object($eq)) {
      $eq->removeDevice();
    }
    return null;
  }


  // CMD FUNCTIONS
  private static function getInfoCmdList($config) {
    $return = array();
    $conditionsArr = array();
    foreach ($config['payload']['widgets'] as $widget) {
      foreach ($widget as $item => $value) {
        if (is_array($value)) {
          if (array_key_exists('type', $value)) {
            if ($value['type'] == 'info') {
              array_push($return, $value['id']);
            }
          }
          if ($item == 'moreInfos') {
            foreach ($value as $i => $info) {
              array_push($return, $info['id']);
            }
          }
        }
        if ($item == 'visibilityCond') {
          array_push($conditionsArr, $value);
        }
      }
    }

    //** CHECK FIELDS WITH CONDITIONS */
    if (array_key_exists('background', $config['payload'])) {
      foreach ($config['payload']['background']['condBackgrounds'] as $cond) {
        if (isset($cond['condition'])) array_push($conditionsArr, $cond['condition']);
      }
    }
    if (array_key_exists('tabs', $config['payload'])) {
      foreach ($config['payload']['tabs'] as $menu) {
        if (isset($menu['visibilityCond'])) array_push($conditionsArr, $menu['visibilityCond']);
      }
    }
    if (array_key_exists('sections', $config['payload'])) {
      foreach ($config['payload']['sections'] as $menu) {
        if (isset($menu['visibilityCond'])) array_push($conditionsArr, $menu['visibilityCond']);
      }
    }
    // JCLog::trace("conditionsArr " . json_encode($conditionsArr));
    foreach ($conditionsArr as $cond) {
      preg_match_all("/#([a-zA-Z0-9]*)#/", $cond, $matches);
      if (count($matches) > 0) {
        $matches = array_unique($matches[0]);
        foreach ($matches as $match) {
          $cmd = cmd::byId(str_replace('#', '', $match));
          if (is_object($cmd)) {
            array_push($return, str_replace('#', '', $match));
          }
        }
      }
    }
    //**-------- END CHECK CONDITIONS -------*/

    if (array_key_exists('weather', $config['payload']) && count($config['payload']['weather']) > 0) {
      $return = array_merge($return, array(
        $config['payload']['weather']['condition'],
        $config['payload']['weather']['condition_id'],
        $config['payload']['weather']['sunrise'],
        $config['payload']['weather']['sunset'],
        $config['payload']['weather']['temperature'],
        $config['payload']['weather']['temperature_min'],
        $config['payload']['weather']['temperature_max']
      ));
    }

    foreach ($config['payload']['customData']['widgets'] as $widgetId => $widget) {
      foreach ($widget as $item => $value) {
        if ($item == 'moreInfos') {
          foreach ($value as $i => $info) {
            array_push($return, $info['id']);
          }
        }
      }
    }

    return array_unique($return);
  }

  /**
   * return detail of cmd type info from the JC config file
   *
   * @param string $config
   * @param boolean $withType
   * @return void
   */
  public static function getCmdInfoData($config, $withType = true) {
    $cmdsIds = self::getInfoCmdList($config);
    return JeedomConnectUtils::getCmdInfoDataIds($cmdsIds, $withType);
  }

  // SCENARIO FUNCTIONS

  private static function getScenarioList($config) {
    $return = array();
    foreach ($config['payload']['widgets'] as $widget) {
      if (array_key_exists('type', $widget)) {
        if ($widget['type'] == 'scenario') {
          array_push($return, $widget['scenarioId']);
        }
      }
    }
    return array_unique($return);
  }

  public static function getScenarioData($config, $all = false, $withType = true) {
    $returnType = $all ? 'SET_ALL_SC' : 'SET_SC_INFO';

    $scIds = self::getScenarioList($config);
    $payload = array();

    foreach (scenario::all() as $sc) {
      if (in_array($sc->getId(), $scIds) || $all) {
        $state = $sc->getCache(array('state', 'lastLaunch'));
        $sc_info = array(
          'id' => (string) $sc->getId(),
          'name' => $sc->getName(),
          'object' => $sc->getObject() == null ? 'Aucun' : $sc->getObject()->getName(),
          'group' => $sc->getGroup() == '' ? 'Aucun' : $sc->getGroup(),
          'status' => $state['state'],
          'lastLaunch' => strtotime($state['lastLaunch']),
          'active' => $sc->getIsActive() ? 1 : 0,
          'icon' => self::getScenarioIcon($sc)
        );
        array_push($payload, $sc_info);
      }
    }

    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  private static function getScenarioIcon($sc) {
    if (strpos($sc->getDisplay('icon'), '<i') === 0) {
      return trim(str_replace(array('<i', 'class=', '"', '/>', '></i>'), '', $sc->getDisplay('icon')));
    }
    return null;
  }

  // OBJECT FUNCTIONS
  private static function getObjectList($config) {
    $return = array();
    foreach ($config['payload']['rooms'] as $room) {
      if (array_key_exists("id", $room)) {
        array_push($return, $room['id']);
      }
    }
    return array_unique($return);
  }

  public static function getObjectData($config, $withType = true) {
    $returnType = 'SET_OBJ_INFO';

    $objIds = self::getObjectList($config);
    $payload = array();

    foreach (jeeObject::all() as $object) {
      if (in_array($object->getId(), $objIds)) {
        $object_info = array(
          'object_id' => (string) $object->getId() ?: null,
          'keys' => array()
        );
        foreach ($object->getConfiguration('summary') as $key => $value) {
          $sum = $object->getSummary($key);
          array_push($object_info['keys'], array(
            $key => array('value' => $sum, 'cmds' => $value)
          ));
        }
        array_push($payload, $object_info);
      }
    }
    $global_info = array(
      'object_id' => 'global',
      'keys' => array()
    );
    /**
     * @var array
     */
    $globalSum = config::byKey('object:summary');
    foreach ($globalSum as $key => $value) {
      array_push($global_info['keys'], array(
        $key => array('value' => jeeObject::getGlobalSummary($key))
      ));
    }
    array_push($payload, $global_info);

    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  // NOTIFICATION FUNCTIONS
  /**
   * @param JeedomConnect $eqLogic
   * @param boolean $withType
   * @return array
   */
  private static function getNotifConfig($eqLogic, $withType = true) {
    $returnType = 'SET_NOTIFS_CONFIG';

    $payload =  $eqLogic->getNotifs();

    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  private static function setNotifConfig($eqLogic, $notifsConfig) {
    $eqLogic->saveNotifs($notifsConfig, false);
  }

  private static function testNotif($eqLogic, $notifId) {
    $cmd = $eqLogic->getCmd(null, $notifId);
    if (!is_object($cmd)) {
      return self::raiseException("Can't find command [logicalId=" . $notifId . "]");
    }
    try {
      $cmd->execCmd(array());
    } catch (Exception $e) {
      return self::raiseException($e->getMessage());
    }
  }

  // GEOFENCE FUNCTIONS
  /**
   * @param JeedomConnect $eqLogic
   * @param array $geo
   * @param array $coordinates
   * @return void
   */
  private static function addGeofence($eqLogic, $geo, $coordinates) {
    $eqLogic->addGeofenceCmd($geo, $coordinates);
  }

  /**
   * Undocumented function
   *
   * @param JeedomConnect $eqLogic
   * @param array $geo
   * @return void
   */
  private static function removeGeofence($eqLogic, $geo) {
    $eqLogic->removeGeofenceCmd($geo);
  }

  /**
   * Undocumented function
   *
   * @param JeedomConnect $eqLogic
   * @param array $param
   * @return void
   */
  private static function setGeofence($eqLogic, $param) {
    $ts = array_key_exists('timestampMeta', $param) ? floor($param['timestampMeta']['systemTime'] / 1000) : strtotime($param['timestamp']);

    $activity = $param['activity']['type'];
    $accuracy = $param['coords']['accuracy'];
    if ($accuracy < 50 || ($activity == 'in_vehicle' && $accuracy && $accuracy < 400)) {
      $eqLogic->setCoordinates($param['coords']['latitude'], $param['coords']['longitude'], $param['coords']['altitude'], $param['activity']['type'], $param['battery']['level'] * 100, $ts);
    } else {
      JCLog::debug("[GeoLoc] data not saved, not accurate enough");
    }

    $activityCmd = $eqLogic->getCmd(null, 'activity');
    if (is_object($activityCmd)) {
      $activityCmd->event($param['activity']['type']);
    }

    if (array_key_exists('battery', $param)) {
      if ($param['battery']['level'] > -1) {
        $batteryCmd = $eqLogic->getCmd(null, 'battery');
        if (is_object($batteryCmd)) {
          $batteryCmd->event($param['battery']['level'] * 100, date('Y-m-d H:i:s', $ts));
        }
      }
    }
    return;
  }
  /**
   * Undocumented function
   *
   * @param JeedomConnect $eqLogic
   * @return (string|array)[]|null
   */
  private static function getGeofencesData($eqLogic, $withType = true) {
    $returnType = 'SET_GEOFENCES';

    $result = array();
    foreach ($eqLogic->getCmd('info') as $cmd) {
      if (substr($cmd->getLogicalId(), 0, 8) === "geofence") {
        array_push($result, array(
          'identifier' => substr($cmd->getLogicalId(), 9),
          'extras' => array(
            'name' => $cmd->getName()
          ),
          'radius' => doubleval($cmd->getConfiguration('radius')),
          'latitude' => doubleval($cmd->getConfiguration('latitude')),
          'longitude' => doubleval($cmd->getConfiguration('longitude')),
          'notifyOnEntry' => true,
          'notifyOnExit' => true
        ));
      }
    }

    $payload = array(
      'geofences' => $result
    );
    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  //PLUGIN CONF FUNCTIONS
  /**
   * Undocumented function
   *
   * @param JeedomConnect $eqLogic
   * @param boolean $withType
   * @return array
   */
  private static function getPluginConfig($eqLogic, $withType = true) {
    $returnType = 'PLUGIN_CONFIG';

    $versionJson = JeedomConnect::getPluginInfo();
    $beta = JeedomConnectUtils::isBeta() ? " (beta)" : "";

    $payload =  array(
      'useWs' => is_object($eqLogic) ?  $eqLogic->getConfiguration('useWs', 0) : 0,
      'polling' => is_object($eqLogic) ?  $eqLogic->getConfiguration('polling', 0) : 0,
      'httpUrl' => config::byKey('httpUrl', 'JeedomConnect', network::getNetworkAccess('external')),
      'internalHttpUrl' => config::byKey('internHttpUrl', 'JeedomConnect', network::getNetworkAccess('internal')),
      'wsAddress' => config::byKey('wsAddress', 'JeedomConnect', 'ws://' . config::byKey('externalAddr') . ':8090'),
      'internalWsAddress' => config::byKey('internWsAddress', 'JeedomConnect', 'ws://' . config::byKey('internalAddr', 'core', 'localhost') . ':8090'),
      'pluginJeedomVersion' => "v" . $versionJson['version'] . $beta
    );

    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  // REGISTER FUNCTION
  /**
   * Undocumented function
   *
   * @param JeedomConnect $eqLogic
   * @param string $userHash
   * @param string $rdk
   * @param user|null $user
   * @param boolean $withType
   * @return array
   */
  private static function registerUser($eqLogic, $userHash, $rdk, $user = null, $withType = true) {
    $returnType = 'REGISTERED';

    if ($user == null) {
      $user = user::byHash($userHash);
    }
    if (!isset($user)) {
      throw new Exception(__("User not valid", __FILE__), -32699);
    }
    $eqLogic->setConfiguration('userHash', $userHash);
    $eqLogic->save(true);

    $registerDevice = $user->getOptions('registerDevice', array());
    if (!is_array($registerDevice)) {
      $registerDevice = array();
    }
    if (!isset($rdk) || !isset($registerDevice[sha512($rdk)])) {
      $rdk = config::genKey();
    }
    $registerDevice[sha512($rdk)] = array();
    $registerDevice[sha512($rdk)]['datetime'] = date('Y-m-d H:i:s');
    $registerDevice[sha512($rdk)]['ip'] = getClientIp();
    $registerDevice[sha512($rdk)]['session_id'] = session_id();
    if (version_compare(PHP_VERSION, '7.3') >= 0) {
      setcookie('registerDevice', $userHash . '-' . $rdk, ['expires' => time() + 365 * 24 * 3600, 'samesite' => 'Strict', 'httponly' => true, 'path' => '/', 'secure' => (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')]);
    } else {
      setcookie('registerDevice', $userHash . '-' . $rdk, time() + 365 * 24 * 3600, "/; samesite=strict", '', (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'), true);
    }
    @session_start();
    $user->refresh();
    $user->setOptions('registerDevice', $registerDevice);
    $user->save();
    @session_write_close();

    return (!$withType) ? $rdk : JeedomConnectUtils::addTypeInPayload(array('rdk' => $rdk), $returnType);
  }

  // Config Watcher
  /**
   * Undocumented function
   *
   * @param JeedomConnect $eqLogic
   * @param string $prevConfig
   */
  public static function lookForNewConfig($eqLogic, $prevConfig) {
    $configVersion = $eqLogic->getConfiguration('configVersion');
    // JCLog::debug("apiHelper : Look for new config, compare " . $configVersion . " and " . $prevConfig);
    if ($configVersion != $prevConfig) {
      // JCLog::debug("apiHelper : New configuration");
      return $eqLogic->getGeneratedConfigFile();
    }
    return false;
  }

  // JEEDOM FULL DATA
  private static function getFullJeedomData() {
    $result = array(
      'type' => 'SET_JEEDOM_DATA',
      'payload' => array(
        'cmds' => array(),
        'eqLogics' => array(),
        'objects' => array(),
        'scenarios' => array(),
        'summariesConfig' => array()
      )
    );

    foreach (cmd::all() as $item) {
      $array = utils::o2a($item);
      $cmd = array(
        'id' => (string) $array['id'],
        'name' => $array['name'],
        'type' => $array['type'],
        'subType' => $array['subType'],
        'eqLogic_id' => (string) $array['eqLogic_id'],
        'unite' => $array['unite'],
        'isHistorized' => $array['isHistorized'],
        'configuration' => $array['configuration'],
        'shortcutAllowed' => ($array['configuration']['actionConfirm'] ?? "0") === "0",
      );
      array_push($result['payload']['cmds'], $cmd);
    }
    foreach (eqLogic::all() as $item) {
      $array = utils::o2a($item);
      $eqLogic = array(
        'id' => (string) $array['id'],
        'name' => $array['name'],
        'object_id' => (string) $array['object_id'] ?: null,
        'isEnable' => (string) $array['isEnable']
      );
      array_push($result['payload']['eqLogics'], $eqLogic);
    }
    foreach (jeeObject::all() as $item) {
      $array = utils::o2a($item);
      $jeeObject = array(
        'id' => (string) $array['id'],
        'name' => $array['name'],
        'display' => JeedomConnectUtils::getIconAndColor($array['display']['icon'] ?? '')
      );
      array_push($result['payload']['objects'], $jeeObject);
    }
    foreach (scenario::all() as $item) {
      $array = utils::o2a($item);
      $scenario = array(
        'id' => (string) $array['id'],
        'name' => $array['name'],
        'group' => $array['group'],
        'object_id' => (string) $array['object_id'] ?: null,
      );
      array_push($result['payload']['scenarios'], $scenario);
    }
    /**
     * @var array
     */
    $globalSum = config::byKey('object:summary');
    foreach ($globalSum as $item) {
      $item['display'] = JeedomConnectUtils::getIconAndColor($item['icon']);
      $item['icon'] = trim(preg_replace('/ icon_(red|yellow|blue|green|orange)/', '', $item['icon']));
      array_push($result['payload']['summariesConfig'], $item);
    }

    return $result;
  }


  //WIDGET DATA
  private static function getWidgetData() {
    $result = array(
      'type' => 'SET_WIDGET_DATA',
      'payload' => array(
        'widgets' => JeedomConnectWidget::getWidgets()
      )
    );

    return $result;
  }

  // EDIT FUNCTIONS
  private static function setWidget($widget) {
    // JCLog::debug('save widget data');
    JeedomConnectWidget::updateWidgetConfig($widget);
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function addWidgets($eqLogic, $widgets, $parentId, $index) {
    $curConfig = $eqLogic->getConfig();

    foreach ($curConfig['payload']['widgets'] as $i => $widget) {
      if ($widget['parentId'] == $parentId && $widget['index'] > $index) {
        $curConfig['payload']['widgets'][$i]['index'] += count($widgets);
      }
    }
    foreach ($curConfig['payload']['groups'] as $i => $group) {
      if ($group['parentId'] == $parentId && $group['index'] > $index) {
        $curConfig['payload']['groups'][$i]['index'] += count($widgets);
      }
    }

    $curIndex = $index + 1;
    foreach ($widgets as $widget) {
      $newWidget = array(
        'index' => $curIndex,
        'widgetId' => $curConfig['idCounter'],
        'id' => $widget['id']
      );
      if (!is_null($parentId)) {
        $newWidget['parentId'] = $parentId;
      }
      array_push($curConfig['payload']['widgets'], $newWidget);
      $curIndex++;
      $curConfig['idCounter'] = $curConfig['idCounter'] + 1;
    }

    $eqLogic->saveConfig($curConfig);
    $eqLogic->generateNewConfigVersion();
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function removeWidget($eqLogic, $widgetId) {
    $curConfig = $eqLogic->getConfig();
    $toRemove = array_search($widgetId, array_column($curConfig['payload']['widgets'], 'widgetId'));
    if ($toRemove !== false) {
      $parentId = $curConfig['payload']['widgets'][$toRemove]['parentId'];
      $index = $curConfig['payload']['widgets'][$toRemove]['index'];
      unset($curConfig['payload']['widgets'][$toRemove]);
      $curConfig['payload']['widgets'] = array_values($curConfig['payload']['widgets']);

      foreach ($curConfig['payload']['widgets'] as $i => $widget) {
        if ($widget['parentId'] == $parentId && $widget['index'] > $index) {
          $curConfig['payload']['widgets'][$i]['index'] -= 1;
        }
      }
      foreach ($curConfig['payload']['groups'] as $i => $group) {
        if ($group['parentId'] == $parentId && $group['index'] > $index) {
          $curConfig['payload']['groups'][$i]['index'] -= 1;
        }
      }

      $eqLogic->saveConfig($curConfig);
      $eqLogic->cleanCustomData();
      $eqLogic->generateNewConfigVersion();
    }
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function moveWidget($eqLogic, $widgetId, $destinationId, $destinationIndex) {
    $curConfig = $eqLogic->getConfig();
    $widgetIndex = array_search($widgetId, array_column($curConfig['payload']['widgets'], 'widgetId'));
    if ($widgetIndex === false) {
      return;
    }

    $moved = false;

    $oldIndex = $curConfig['payload']['widgets'][$widgetIndex]['index'];
    $oldParentId = $curConfig['payload']['widgets'][$widgetIndex]['parentId'];
    if (isset($destinationIndex)) {
      $newIndex = $destinationIndex;
    } else {
      $newIndex = 0;
      foreach ($curConfig['payload']['widgets'] as $i => $item) {
        if ($item['parentId'] == $destinationId) {
          $newIndex++;
        }
      }
      foreach ($curConfig['payload']['groups'] as $i => $item) {
        if ($item['parentId'] == $destinationId) {
          $newIndex++;
        }
      }
    }


    $destinationParentIndex = array_search($destinationId, array_column($curConfig['payload']['tabs'], 'id'));
    if ($destinationParentIndex !== false) { //destination is a bottom tab
      $moved = true;
    } else {
      $destinationParentIndex = array_search($destinationId, array_column($curConfig['payload']['sections'], 'id'));
      if ($destinationParentIndex !== false) { //destination is a top tab
        $moved = true;
      } else {
        $destinationParentIndex = array_search($destinationId, array_column($curConfig['payload']['groups'], 'id'));
        if ($destinationParentIndex !== false) { //destination is a group
          $moved = true;
        }
      }
    }

    if ($moved) {
      //re-index pages
      foreach ($curConfig['payload']['widgets'] as $i => $item) {
        if ($item['parentId'] == $oldParentId && $item['index'] > $oldIndex) {
          $curConfig['payload']['widgets'][$i]['index'] -= 1;
        }
        if ($item['parentId'] == $destinationId && $item['index'] >= $newIndex) {
          $curConfig['payload']['widgets'][$i]['index'] += 1;
        }
      }
      foreach ($curConfig['payload']['groups'] as $i => $item) {
        if ($item['parentId'] == $oldParentId && $item['index'] > $oldIndex) {
          $curConfig['payload']['groups'][$i]['index'] -= 1;
        }
        if ($item['parentId'] == $destinationId && $item['index'] >= $newIndex) {
          $curConfig['payload']['groups'][$i]['index'] += 1;
        }
      }

      $curConfig['payload']['widgets'][$widgetIndex]['parentId'] = $destinationId;
      $curConfig['payload']['widgets'][$widgetIndex]['index'] = $newIndex;


      $curConfig['payload']['tabs'] = array_values($curConfig['payload']['tabs']);
      $curConfig['payload']['sections'] = array_values($curConfig['payload']['sections']);
      $curConfig['payload']['groups'] = array_values($curConfig['payload']['groups']);
      $curConfig['payload']['widgets'] = array_values($curConfig['payload']['widgets']);

      $eqLogic->saveConfig($curConfig);
      $eqLogic->generateNewConfigVersion();
    }
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function setCustomWidgetList($eqLogic, $customWidgetList) {
    $apiKey = $eqLogic->getConfiguration('apiKey');
    foreach ($customWidgetList as $customWidget) {
      if (!key_exists('widgetId', $customWidget)) {
        JCLog::warning('no widgetId found - skip - ' . json_encode($customWidget));
        continue;
      }
      JCLog::debug('save custom data for widget [' . $customWidget['widgetId'] . '] : ' . json_encode($customWidget));
      config::save('customData::' . $apiKey . '::' . $customWidget['widgetId'], json_encode($customWidget), 'JeedomConnect');
    }
    $eqLogic->generateNewConfigVersion();
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function setGroup($eqLogic, $group) {
    $curConfig = $eqLogic->getConfig();
    $toEdit = array_search($group['id'], array_column($curConfig['payload']['groups'], 'id'));
    if ($toEdit !== false) {
      $curConfig['payload']['groups'][$toEdit] = $group;
      $eqLogic->saveConfig($curConfig);
      $eqLogic->generateNewConfigVersion();
    }
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function removeGroup($eqLogic, $id) {
    $curConfig = $eqLogic->getConfig();
    $toRemove = array_search($id, array_column($curConfig['payload']['groups'], 'id'));
    if ($toRemove !== false) {
      $parentId = $curConfig['payload']['groups'][$toRemove]['parentId'];
      $index = $curConfig['payload']['groups'][$toRemove]['index'];
      unset($curConfig['payload']['groups'][$toRemove]);
      $curConfig['payload']['groups'] = array_values($curConfig['payload']['groups']);

      foreach ($curConfig['payload']['widgets'] as $i => $widget) {
        if ($widget['parentId'] == $parentId && $widget['index'] > $index) {
          $curConfig['payload']['widgets'][$i]['index'] -= 1;
        }
        if ($widget['parentId'] == $id) {
          unset($curConfig['payload']['widgets'][$i]);
        }
      }
      foreach ($curConfig['payload']['groups'] as $i => $group) {
        if ($group['parentId'] == $parentId && $group['index'] > $index) {
          $curConfig['payload']['groups'][$i]['index'] -= 1;
        }
      }
      $curConfig['payload']['widgets'] = array_values($curConfig['payload']['widgets']);

      $eqLogic->saveConfig($curConfig);
      $eqLogic->generateNewConfigVersion();
    }
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function addGroup($eqLogic, $curGroup) {
    $curConfig = $eqLogic->getConfig();
    $parentId = $curGroup['parentId'];
    $index = $curGroup['index'];
    $maxId = 999000;

    foreach ($curConfig['payload']['widgets'] as $i => $widget) {
      if ($widget['parentId'] == $parentId && $widget['index'] > $index) {
        $curConfig['payload']['widgets'][$i]['index'] += 1;
      }
    }
    foreach ($curConfig['payload']['groups'] as $i => $group) {
      if ($group['parentId'] == $parentId && $group['index'] > $index) {
        $curConfig['payload']['groups'][$i]['index'] += 1;
      }
      if ($group['id'] >= $maxId) {
        $maxId = $group['id'] + 1;
      }
    }

    $curGroup['id'] = $maxId;
    array_push($curConfig['payload']['groups'], $curGroup);
    $eqLogic->saveConfig($curConfig);
    $eqLogic->generateNewConfigVersion();
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function moveGroup($eqLogic, $groupId, $destinationId, $destinationIndex) {
    $curConfig = $eqLogic->getConfig();
    $groupIndex = array_search($groupId, array_column($curConfig['payload']['groups'], 'id'));
    if ($groupIndex === false) {
      return;
    }

    $moved = false;

    $oldIndex = $curConfig['payload']['groups'][$groupIndex]['index'];
    $oldParentId = $curConfig['payload']['groups'][$groupIndex]['parentId'];

    if (isset($destinationIndex)) {
      $newIndex = $destinationIndex;
    } else {
      $newIndex = 0;
      foreach ($curConfig['payload']['widgets'] as $i => $item) {
        if ($item['parentId'] == $destinationId) {
          $newIndex++;
        }
      }
      foreach ($curConfig['payload']['groups'] as $i => $item) {
        if ($item['parentId'] == $destinationId) {
          $newIndex++;
        }
      }
    }

    $destinationParentIndex = array_search($destinationId, array_column($curConfig['payload']['tabs'], 'id'));
    if ($destinationParentIndex !== false) { //destination is a bottom tab
      $moved = true;
    } else {
      $destinationParentIndex = array_search($destinationId, array_column($curConfig['payload']['sections'], 'id'));
      if ($destinationParentIndex !== false) { //destination is a top tab
        $moved = true;
      }
    }

    if ($moved) {
      //re-index pages
      foreach ($curConfig['payload']['widgets'] as $i => $item) {
        if ($item['parentId'] == $oldParentId && $item['index'] > $oldIndex) {
          $curConfig['payload']['widgets'][$i]['index'] -= 1;
        }
        if ($item['parentId'] == $destinationId && $item['index'] >= $newIndex) {
          $curConfig['payload']['widgets'][$i]['index'] += 1;
        }
      }
      foreach ($curConfig['payload']['groups'] as $i => $item) {
        if ($item['parentId'] == $oldParentId && $item['index'] > $oldIndex) {
          $curConfig['payload']['groups'][$i]['index'] -= 1;
        }
        if ($item['parentId'] == $destinationId && $item['index'] >= $newIndex) {
          $curConfig['payload']['groups'][$i]['index'] += 1;
        }
      }

      $curConfig['payload']['groups'][$groupIndex]['parentId'] = $destinationId;
      $curConfig['payload']['groups'][$groupIndex]['index'] = $newIndex;

      $curConfig['payload']['tabs'] = array_values($curConfig['payload']['tabs']);
      $curConfig['payload']['sections'] = array_values($curConfig['payload']['sections']);
      $curConfig['payload']['groups'] = array_values($curConfig['payload']['groups']);
      $curConfig['payload']['widgets'] = array_values($curConfig['payload']['widgets']);

      $eqLogic->saveConfig($curConfig);
      $eqLogic->generateNewConfigVersion();
    }
  }

  private static function removeGlobalWidget($id) {
    JeedomConnectWidget::removeWidget($id);
  }

  private static function addGlobalWidgets($widgets) {

    foreach ($widgets as $i => $widget) {
      $widgetId = JeedomConnectWidget::incrementIndex();
      $widget['id'] = intval($widgetId);
      $widgets[$i]['id'] = $widgetId;
      JeedomConnectWidget::saveConfig($widget, $widgetId);
    }

    $result = array(
      'type' => 'SET_MULTIPLE_WIDGET_DATA',
      'payload' => array_values($widgets)
    );
    return $result;
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function setBottomTabList($eqLogic, $tabs, $migrate = false, $idCounter) {
    $curConfig = $eqLogic->getConfig();
    $curConfig['idCounter'] = $idCounter;

    //remove children
    foreach ($tabs as $tab) {
      $oldTabIndex = array_search($tab['id'], array_column($curConfig['payload']['tabs'], 'id'));
      if ($tab['index'] < 0) { //tabs with negative index have to be removed
        if ($oldTabIndex !== false) {
          unset($curConfig['payload']['tabs'][$oldTabIndex]);
        }
        $sectionsToRemove = array();
        foreach ($curConfig['payload']['sections'] as $i => $section) {
          if ($section['parentId'] == $tab['id']) {
            array_push($sectionsToRemove, $section['id']);
            unset($curConfig['payload']['sections'][$i]);
          }
        }
        $groupsToRemove = array();
        foreach ($curConfig['payload']['groups'] as $i => $group) {
          if ($group['parentId'] == $tab['id'] || in_array($group['parentId'], $sectionsToRemove)) {
            array_push($groupsToRemove, $group['id']);
            unset($curConfig['payload']['groups'][$i]);
          }
        }
        foreach ($curConfig['payload']['widgets'] as $i => $widget) {
          if ($widget['parentId'] == $tab['id'] || in_array($widget['parentId'], $groupsToRemove) || in_array($widget['parentId'], $sectionsToRemove)) {
            unset($curConfig['payload']['widgets'][$i]);
          }
        }
      } else {
        if ($oldTabIndex !== false) {
          $curConfig['payload']['tabs'][$oldTabIndex] = $tab;
        } else {
          array_push($curConfig['payload']['tabs'], $tab);
        }
      }
      $curConfig['payload']['tabs'] = array_values($curConfig['payload']['tabs']);
    }

    if ($migrate) {
      $firstTab = array_search(0, array_column($tabs, 'index'));
      $id = $tabs[$firstTab]['id'];
      if (count($curConfig['payload']['sections']) > 0) {
        foreach ($curConfig['payload']['sections'] as $i => $section) {
          $curConfig['payload']['sections'][$i]['parentId'] = $id;
        }
      } else {
        foreach ($curConfig['payload']['groups'] as $i => $group) {
          $curConfig['payload']['groups'][$i]['parentId'] = $id;
        }
        foreach ($curConfig['payload']['widgets'] as $i => $widget) {
          if ($widget['parentId'] < 999000) {
            $curConfig['payload']['widgets'][$i]['parentId'] = $id;
          }
        }
      }
    }

    $curConfig['payload']['tabs'] = array_values($curConfig['payload']['tabs']);
    $curConfig['payload']['sections'] = array_values($curConfig['payload']['sections']);
    $curConfig['payload']['groups'] = array_values($curConfig['payload']['groups']);
    $curConfig['payload']['widgets'] = array_values($curConfig['payload']['widgets']);

    $eqLogic->saveConfig($curConfig);
    $eqLogic->generateNewConfigVersion();
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function removeBottomTab($eqLogic, $id) {
    $curConfig = $eqLogic->getConfig();
    $toRemove = array_search($id, array_column($curConfig['payload']['tabs'], 'id'));
    if ($toRemove !== false) {
      $index = $curConfig['payload']['tabs'][$toRemove]['index'];
      $parentId = $curConfig['payload']['tabs'][$toRemove]['parentId'];
      unset($curConfig['payload']['tabs'][$toRemove]);

      // remove children
      $sectionsToRemove = array();
      foreach ($curConfig['payload']['sections'] as $i => $section) {
        if ($section['parentId'] == $id) {
          array_push($sectionsToRemove, $section['id']);
          unset($curConfig['payload']['sections'][$i]);
        }
      }
      $groupsToRemove = array();
      foreach ($curConfig['payload']['groups'] as $i => $group) {
        if ($group['parentId'] == $id || in_array($group['parentId'], $sectionsToRemove)) {
          array_push($groupsToRemove, $group['id']);
          unset($curConfig['payload']['groups'][$i]);
        }
      }
      foreach ($curConfig['payload']['widgets'] as $i => $widget) {
        if ($widget['parentId'] == $id || in_array($widget['parentId'], $groupsToRemove) || in_array($widget['parentId'], $sectionsToRemove)) {
          unset($curConfig['payload']['widgets'][$i]);
        }
      }
      //re-index tabs
      foreach ($curConfig['payload']['tabs'] as $i => $tab) {
        if ($tab['parentId'] == $parentId && $tab['index'] > $index) {
          $curConfig['payload']['tabs'][$i]['index'] -= 1;
        }
      }

      $curConfig['payload']['tabs'] = array_values($curConfig['payload']['tabs']);
      $curConfig['payload']['sections'] = array_values($curConfig['payload']['sections']);
      $curConfig['payload']['groups'] = array_values($curConfig['payload']['groups']);
      $curConfig['payload']['widgets'] = array_values($curConfig['payload']['widgets']);

      $eqLogic->saveConfig($curConfig);
      $eqLogic->cleanCustomData();
      $eqLogic->generateNewConfigVersion();
    }
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function setTopTabList($eqLogic, $tabs, $migrate = false, $idCounter) {
    if (count($tabs) == 0) {
      return;
    }
    $curConfig = $eqLogic->getConfig();
    $curConfig['idCounter'] = $idCounter;

    //remove children
    foreach ($tabs as $tab) {
      $oldTabIndex = array_search($tab['id'], array_column($curConfig['payload']['sections'], 'id'));
      if ($tab['index'] < 0) { //tabs with negative index have to be removed
        if ($oldTabIndex !== false) {
          unset($curConfig['payload']['sections'][$oldTabIndex]);
        }
        $groupsToRemove = array();
        foreach ($curConfig['payload']['groups'] as $i => $group) {
          if ($group['parentId'] == $tab['id']) {
            array_push($groupsToRemove, $group['id']);
            unset($curConfig['payload']['groups'][$i]);
          }
        }
        foreach ($curConfig['payload']['widgets'] as $i => $widget) {
          if ($widget['parentId'] == $tab['id'] || in_array($widget['parentId'], $groupsToRemove)) {
            unset($curConfig['payload']['widgets'][$i]);
          }
        }
      } else {
        if ($oldTabIndex !== false) {
          $curConfig['payload']['sections'][$oldTabIndex] = $tab;
        } else {
          array_push($curConfig['payload']['sections'], $tab);
        }
      }
      $curConfig['payload']['sections'] = array_values($curConfig['payload']['sections']);
    }

    if ($migrate) {
      $firstTab = array_search(0, array_column($tabs, 'index'));
      $id = $tabs[$firstTab]['id'];
      $parentId = $tabs[$firstTab]['parentId'];
      foreach ($curConfig['payload']['groups'] as $i => $group) {
        if (!isset($group['parentId']) || $group['parentId'] == $parentId) {
          $curConfig['payload']['groups'][$i]['parentId'] = $id;
        }
      }
      foreach ($curConfig['payload']['widgets'] as $i => $widget) {
        if (!isset($widget['parentId']) || $widget['parentId'] == $parentId) {
          $curConfig['payload']['widgets'][$i]['parentId'] = $id;
        }
      }
    }

    $curConfig['payload']['sections'] = array_values($curConfig['payload']['sections']);
    $curConfig['payload']['groups'] = array_values($curConfig['payload']['groups']);
    $curConfig['payload']['widgets'] = array_values($curConfig['payload']['widgets']);

    $eqLogic->saveConfig($curConfig);
    $eqLogic->cleanCustomData();
    $eqLogic->generateNewConfigVersion();
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function removeTopTab($eqLogic, $id) {
    $curConfig = $eqLogic->getConfig();
    $toRemove = array_search($id, array_column($curConfig['payload']['sections'], 'id'));
    if ($toRemove !== false) {
      $parentId = $curConfig['payload']['sections'][$toRemove]['parentId'];
      $index = $curConfig['payload']['sections'][$toRemove]['index'];
      unset($curConfig['payload']['sections'][$toRemove]);

      // remove children
      $groupsToRemove = array();
      foreach ($curConfig['payload']['groups'] as $i => $group) {
        if ($group['parentId'] == $id) {
          array_push($groupsToRemove, $group['id']);
          unset($curConfig['payload']['groups'][$i]);
        }
      }
      foreach ($curConfig['payload']['widgets'] as $i => $widget) {
        if ($widget['parentId'] == $id || in_array($widget['parentId'], $groupsToRemove)) {
          unset($curConfig['payload']['widgets'][$i]);
        }
      }
      //re-index sections
      foreach ($curConfig['payload']['sections'] as $i => $section) {
        if ($section['parentId'] == $parentId && $section['index'] > $index) {
          $curConfig['payload']['sections'][$i]['index'] -= 1;
        }
      }

      $curConfig['payload']['sections'] = array_values($curConfig['payload']['sections']);
      $curConfig['payload']['groups'] = array_values($curConfig['payload']['groups']);
      $curConfig['payload']['widgets'] = array_values($curConfig['payload']['widgets']);

      $eqLogic->saveConfig($curConfig);
      $eqLogic->cleanCustomData();
      $eqLogic->generateNewConfigVersion();
    }
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function moveTopTab($eqLogic, $sectionId, $destinationId) {
    $curConfig = $eqLogic->getConfig();
    $sectionIndex = array_search($sectionId, array_column($curConfig['payload']['sections'], 'id'));
    if ($sectionIndex === false) {
      return;
    }

    $moved = false;

    $oldIndex = $curConfig['payload']['sections'][$sectionIndex]['index'];
    $oldParentId = $curConfig['payload']['sections'][$sectionIndex]['parentId'];

    $newIndex = 0;
    foreach ($curConfig['payload']['sections'] as $i => $item) {
      if ($item['parentId'] == $destinationId) {
        $newIndex++;
      }
    }

    $destinationIndex = array_search($destinationId, array_column($curConfig['payload']['tabs'], 'id'));
    if ($destinationIndex !== false) { //destination is a bottom tab
      $moved = true;
    }

    if ($moved) {
      $curConfig['payload']['sections'][$sectionIndex]['parentId'] = $destinationId;
      $curConfig['payload']['sections'][$sectionIndex]['index'] = $newIndex;
      //re-index initial tab
      foreach ($curConfig['payload']['sections'] as $i => $item) {
        if ($item['parentId'] == $oldParentId && $item['index'] > $oldIndex) {
          $curConfig['payload']['sections'][$i]['index'] -= 1;
        }
      }

      $curConfig['payload']['tabs'] = array_values($curConfig['payload']['tabs']);
      $curConfig['payload']['sections'] = array_values($curConfig['payload']['sections']);
      $curConfig['payload']['groups'] = array_values($curConfig['payload']['groups']);
      $curConfig['payload']['widgets'] = array_values($curConfig['payload']['widgets']);

      $eqLogic->saveConfig($curConfig);
      $eqLogic->generateNewConfigVersion();
    }
  }

  /**
   * Receive root data (already ordered) (widgets, groups) for a page view (index < 0 ==> remove)
   *
   * @param JeedomConnect $eqLogic
   * @param array $rootData
   */
  private static function setPageData($eqLogic, $rootData, $idCounter) {
    if (count($rootData) == 0) {
      return;
    }
    $curConfig = $eqLogic->getConfig();
    $curConfig['idCounter'] = $idCounter;

    foreach ($rootData as $element) {
      $type = 'groups';
      $id = 'id';
      if (isset($element['widgetId'])) { //this is a widget
        $type = 'widgets';
        $id = 'widgetId';
      }
      $oldWidgetIndex = array_search($element[$id], array_column($curConfig['payload'][$type], $id));
      if ($element['index'] < 0) {
        if ($oldWidgetIndex !== false) {
          unset($curConfig['payload'][$type][$oldWidgetIndex]);
        }
      } else {
        if ($oldWidgetIndex !== false) { //update element
          $curConfig['payload'][$type][$oldWidgetIndex] = $element;
        } else { //add element
          array_push($curConfig['payload'][$type], $element);
        }
      }
      $curConfig['payload'][$type] = array_values($curConfig['payload'][$type]);
    }

    $eqLogic->saveConfig($curConfig);
    $eqLogic->cleanCustomData();
    $eqLogic->generateNewConfigVersion();
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function setRooms($eqLogic, $rooms) {
    $curConfig = $eqLogic->getConfig();
    $curConfig['payload']['rooms'] = $rooms;

    $eqLogic->saveConfig($curConfig);
    $eqLogic->generateNewConfigVersion();
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function setSummaries($eqLogic, $summaries) {
    $curConfig = $eqLogic->getConfig();
    $curConfig['payload']['summaries'] = $summaries;

    $eqLogic->saveConfig($curConfig);
    $eqLogic->generateNewConfigVersion();
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function setBackgrounds($eqLogic, $backgrounds) {
    $curConfig = $eqLogic->getConfig();
    $curConfig['payload']['background'] = $backgrounds;

    $eqLogic->saveConfig($curConfig);
    $eqLogic->generateNewConfigVersion();
  }

  private static function getWidgetFromGenType($_widget_type, $_eqLogicId) {
    $result = array(
      'type' => 'SET_WIDGET_WITH_GEN_TYPE',
      'payload' => JeedomConnectUtils::generateWidgetWithGenType($_widget_type, $_eqLogicId)
    );

    return $result;
  }


  // EVENTS FUNCTION
  /**
   * @param JeedomConnect $eqLogic
   */
  public static function getEventsFull($eqLogic, $lastReadTimestamp, $lastHistoricReadTimestamp) {

    $config = $eqLogic->getGeneratedConfigFile();

    $events = event::changes($lastReadTimestamp);

    // Refresh cmds that need historic data every 5 mins - custumizable param ?
    if (time() - $lastHistoricReadTimestamp > 5 * 60) {
      $lastHistoricReadTimestamp = time();
      $cmdsToAdd = self::getHistoricEvents($config);
      if (count($cmdsToAdd) > 0) {
        $events['result'] = array_merge($events['result'], $cmdsToAdd);
      }
    }

    $eventCount = count($events['result']);
    if ($eventCount == 0) {
      // JCLog::debug('--- no change - skipped (' . $eventCount . ')');
      $data = array(
        array(
          'type' => 'DATETIME',
          'payload' => $events['datetime']
        ),
        array(
          'type' => 'HIST_DATETIME',
          'payload' => $lastHistoricReadTimestamp
        )
      );
    } elseif ($eventCount < 249) {
      // JCLog::debug('--- using cache (' . $eventCount . ')');
      $data = self::getEventsFromCache($events, $config, $eqLogic->getConfiguration('scAll', 0) == 1, $lastHistoricReadTimestamp);
    } else {
      // JCLog::debug('*****  too many items, refresh all (' . $eventCount . ')');
      $data = self::getEventsGlobalRefresh($events, $config, $eqLogic->getConfiguration('scAll', 0) == 1, $lastHistoricReadTimestamp);
    }

    return $data;
  }

  private static function getHistoricEvents($config) {
    $result = array();

    foreach ($config['payload']['widgets'] as $widget) {
      $name = $widget['name'];
      $subtitle = $widget['subtitle'] ?? '';
      foreach ($widget as $item => $value) {
        if (is_array($value)) {
          if (array_key_exists('type', $value)) {
            if ($value['type'] == 'info') {
              if (self::hasHistoricFunction($value['id'], $name) || self::hasHistoricFunction($value['id'], $subtitle)) {
                $cmd = cmd::byId($value['id']);
                $state = $cmd->getCache(array('valueDate', 'collectDate', 'value'));
                array_push($result, array(
                  'name' => 'cmd::update',
                  'option' => array_merge(array(
                    'cmd_id' => $value['id']
                  ), $state)
                ));
              }
            }
          }
          if ($item == 'moreInfos') {
            foreach ($value as $i => $info) {
              if (self::hasHistoricFunction($info['id'], $name) || self::hasHistoricFunction($info['id'], $subtitle)) {
                $cmd = cmd::byId($info['id']);
                $state = $cmd->getCache(array('valueDate', 'collectDate', 'value'));
                array_push($result, array(
                  'name' => 'cmd::update',
                  'option' => array_merge(array(
                    'cmd_id' => $info['id']
                  ), $state)
                ));
              }
            }
          }
        }
      }
    }
    return $result;
  }

  private static function hasHistoricFunction($id, $string) {
    $match = array("average(#$id#)", "min(#$id#)", "max(#$id#)", "collect(#$id#)", "tendance(#$id#)");
    foreach ($match as $key) {
      if (strpos($string, $key) !== FALSE) {
        return true;
      }
    }
    return false;
  }


  private static function getEventsGlobalRefresh($events, $config, $scAll = false, $histDatetime) {
    $result = array(
      array(
        'type' => 'DATETIME',
        'payload' => $events['datetime']
      ),
      array(
        'type' => 'HIST_DATETIME',
        'payload' => $histDatetime
      ),
      array(
        'type' => 'CMD_INFO',
        'payload' => apiHelper::getCmdInfoData($config, false)
      ),
      array(
        'type' => 'SC_INFO',
        'payload' => apiHelper::getScenarioData($config, $scAll, false)
      ),
      array(
        'type' => 'OBJ_INFO',
        'payload' => apiHelper::getObjectData($config, false)
      )
    );
    return $result;
  }

  private static function getEventsFromCache($events, $config, $scAll = false, $histDatetime) {
    $result_datetime = array(
      'type' => 'DATETIME',
      'payload' => $events['datetime']
    );
    $result_histDatetime = array(
      'type' => 'HIST_DATETIME',
      'payload' => $histDatetime
    );
    $result_cmd = array(
      'type' => 'CMD_INFO',
      'payload' => array()
    );
    $infoIds = self::getInfoCmdList($config);
    $result_sc = array(
      'type' => 'SC_INFO',
      'payload' => array()
    );
    $scIds = self::getScenarioList($config);
    $result_obj = array(
      'type' => 'OBJ_INFO',
      'payload' => array()
    );

    foreach ($events['result'] as $event) {
      if ($event['name'] == 'jeeObject::summary::update') {
        $item = $event['option'];
        $item['object_id'] = (string)$item['object_id'];
        array_push($result_obj['payload'], $item);
      }
      if ($event['name'] == 'scenario::update') {
        if (in_array($event['option']['scenario_id'], $scIds) || $scAll) {
          $sc_info = array(
            'id' => (string) $event['option']['scenario_id'],
            'status' => $event['option']['state'],
            'lastLaunch' => strtotime($event['option']['lastLaunch'])
          );
          if (array_key_exists('isActive', $event['option'])) {
            $sc_info['active'] = $event['option']['isActive'];
          }
          array_push($result_sc['payload'], $sc_info);
        }
      }
      if ($event['name'] == 'cmd::update') {
        if (in_array($event['option']['cmd_id'], $infoIds)) {
          $cmd_info = array(
            'id' => (string)$event['option']['cmd_id'],
            'value' => $event['option']['value'],
            'modified' => strtotime($event['option']['valueDate']),
            'collectDate' => strtotime($event['option']['collectDate']),
            'history' => JeedomConnectUtils::getHistoryValueInfo($event['option']['cmd_id'])
          );
          array_push($result_cmd['payload'], $cmd_info);
        }
      }
    }
    return array($result_datetime, $result_histDatetime, $result_cmd, $result_sc, $result_obj);
  }

  //HISTORY
  private static function getHistory($id, $options = null) {
    $history = array();
    if ($options == null) {
      $history = history::all($id);
    } else {
      $startTime = date('Y-m-d H:i:s', $options['startTime']);
      $endTime = date('Y-m-d H:i:s', $options['endTime']);
      JCLog::debug('Get history from: ' . $startTime . ' to ' . $endTime);
      $history = history::all($id, $startTime, $endTime);
    }

    $result = array(
      'type' => 'SET_HISTORY',
      'payload' => array(
        'id' => $id,
        'data' => array()
      )
    );

    foreach ($history as $h) {
      array_push($result['payload']['data'], array(
        'time' => strtotime($h->getDateTime()),
        'value' => $h->getValue()
      ));
    }
    JCLog::debug('Send history (' . count($result['payload']['data']) . ' points)');
    return $result;
  }

  private static function getHistories($ids, $options = null) {
    $historyList = array(
      'type' => 'SET_HISTORIES',
      'payload' => array()
    );
    foreach ($ids as $id) {
      $history = array();
      if ($options == null) {
        $history = history::all($id);
      } else {
        $startTime = date('Y-m-d H:i:s', $options['startTime']);
        $endTime = date('Y-m-d H:i:s', $options['endTime']);
        JCLog::debug('Get history for cmd id: ' . $id . ' from: ' . $startTime . ' to ' . $endTime);
        $history = history::all($id, $startTime, $endTime);
      }

      $result =  array(
        'id' => $id,
        'data' => array()
      );

      foreach ($history as $h) {
        array_push($result['data'], array(
          'time' => strtotime($h->getDateTime()),
          'value' => $h->getValue()
        ));
      }

      array_push($historyList['payload'], $result);
    }
    JCLog::debug('Send histories');
    return $historyList;
  }

  // BATTERIES
  private static function getBatteries() {
    $list = array();
    /** @var eqLogic $eqLogic */
    foreach (eqLogic::all() as $eqLogic) {
      if ($eqLogic->getIsEnable() && $eqLogic->getStatus('battery', -2) != -2) {
        array_push($list, self::getBatteryDetails($eqLogic));
      }
    }

    $result = array(
      'type' => 'SET_BATTERIES',
      'payload' => $list
    );
    return $result;
  }

  /**
   * @param eqLogic $eqLogic
   */
  private static function getBatteryDetails(eqLogic $eqLogic) {
    $result = array();
    $level = 'good';
    $batteryType = $eqLogic->getConfiguration('battery_type', '');
    $batteryTime = $eqLogic->getConfiguration('batterytime', 'NA');
    $batterySince = '';
    if ($batteryTime != 'NA') {
      $batterySince = round((strtotime(date("Y-m-d")) - strtotime(date("Y-m-d", strtotime($batteryTime)))) / 86400, 1);
    }

    $plugin = ucfirst($eqLogic->getEqType_name());

    $object_name = 'Aucun';
    $object_id = null;
    if (is_object($eqLogic->getObject())) {
      $object_name = $eqLogic->getObject()->getName();
      $object_id = $eqLogic->getObject()->getId();
    }

    if ($eqLogic->getStatus('battery') <= $eqLogic->getConfiguration('battery_danger_threshold', config::byKey('battery::danger'))) {
      $level = 'critical';
    } else if ($eqLogic->getStatus('battery') <= $eqLogic->getConfiguration('battery_warning_threshold', config::byKey('battery::warning'))) {
      $level = 'warning';
    }

    $result['eqName'] = $eqLogic->getName();
    $result['roomName'] = $object_name;
    $result['roomId'] = strval($object_id) ?: null;
    $result['plugin'] = $plugin;

    $result['level'] = $level;

    $result['battery'] = $eqLogic->getStatus('battery', -2);
    $result['batteryType'] = $batteryType;
    $result['lastUpdate'] =  date("d/m/Y H:i:s", strtotime($eqLogic->getStatus('batteryDatetime', 'inconnue')));

    $result['lastReplace'] = ($batteryTime != 'NA') ? $batterySince : '';

    return $result;
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function saveBatteryEquipment($eqLogic, $level) {
    $eqLogic->checkAndUpdateCmd('battery', $level);

    if (!$eqLogic->getConfiguration('hideBattery') || $eqLogic->getConfiguration('hideBattery', -2) == -2) {
      $eqLogic->batteryStatus($level);
      //  JCLog::warning('saveBatteryEquipment | SAVING battery saved on equipment page ');
    }
  }


  // PLUGINS UPDATE
  /**
   * @param string $pluginId
   */
  private static function doUpdate($pluginId) {
    /** @var update $update */
    $update = update::byLogicalId($pluginId);

    if (!is_object($update)) {
      JCLog::warning('doUpdate -- cannot update plugin ' . $pluginId);
      return false;
    }

    $update->doUpdate();
    return true;
  }

  private static function getPluginsUpdate() {
    update::checkAllUpdate();
    $nbNeedUpdate = update::nbNeedUpdate();

    $updateArr = array();
    $pluginUpdateId = array();
    if ($nbNeedUpdate != 0) {
      foreach (update::all() as $update) {

        if (strtolower($update->getStatus()) != 'update') continue;

        $item = array();
        try {

          if ($update->getType() == 'core') {
            $item['pluginId'] =  $update->getLogicalId();
            $item['message'] = 'La mise à jour du core n\'est pas possible depuis l\'application';
            $item['doNotUpdate'] = true;
            $item['name'] =  'Jeedom Core';

            $version = substr(jeedom::version(), 0, 3);
            $item['changelogLink'] =  'https://doc.jeedom.com/' . config::byKey('language', 'core', 'fr_FR') . '/core/' . $version . '/changelog';
            $item['currentVersion'] =  $update->getLocalVersion();
            $item['updateVersion'] = $update->getRemoteVersion();
          } else {

            $plugin = plugin::byId($update->getLogicalId());
            $item = JeedomConnectUtils::getPluginDetails($plugin);
            array_push($pluginUpdateId, $item['pluginId']);
          }
        } catch (Exception $e) {
          JCLog::warning('PLUGIN UPDATE -- exception : ' . $e->getMessage());
          $item['message'] = 'Une erreur est survenue. Merci de regarder les logs.';
        }
        array_push($updateArr, $item);
      }
    }

    $otherPlugins = array();
    foreach (plugin::listPlugin() as $plugin) {
      if (in_array($plugin->getId(), $pluginUpdateId)) continue;
      array_push($otherPlugins, JeedomConnectUtils::getPluginDetails($plugin));
    }

    $result = array(
      'type' => 'SET_PLUGINS_UPDATE',
      'payload' => array(
        'nbUpdate' => $nbNeedUpdate,
        'pluginsToUpdate' => $updateArr,
        'otherPlugins' => $otherPlugins
      )
    );
    return $result;
  }

  // BACKUPS
  private static function setAppConfig($apiKey, $name, $config) {
    $_backup_dir = JeedomConnect::$_backup_dir;
    $prefix = 'appPref';

    if (!is_dir($_backup_dir)) {
      mkdir($_backup_dir);
    }

    $eqDir = $_backup_dir . $apiKey . '/';
    if (!is_dir($eqDir)) {
      mkdir($eqDir);
    }

    $files = JeedomConnectUtils::scan_dir($eqDir, $prefix);

    $newestMD5 = null;
    if (is_array($files) && count($files) > 0) {
      $newest_file = $files[0];
      $newestMD5 = md5_file($eqDir . $newest_file);
      // JCLog::debug('newest file => ' . json_encode($newest_file) . ' md5 => ' . $newestMD5);
    }

    $md5Config = md5(json_encode($config, JSON_PRETTY_PRINT));
    // JCLog::debug('md5 received => ' . $md5Config);

    //if no name set, then put today info
    $name = $name ?: date('d-m-y_H-i');
    $config_file = $eqDir . $prefix . '-' . $name . '-' . time() . '.json';
    // $config_file = $eqDir . urlencode($prefix . '-' . $name) . '-' . time() . '.json';
    try {
      JCLog::trace('Saving backup in file : ' . $config_file);
      $createFile = file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));

      if ($createFile !== false && ($md5Config == $newestMD5)) {
        JCLog::trace('same AppPref as last one, removing previous one');
        unlink($eqDir . $newest_file);
      }

      return array(
        'type' => 'GET_APP_CONFIG'
      );
    } catch (Exception $e) {
      JCLog::error('Unable to write file : ' . $e->getMessage());
      return self::raiseException('Unable to write file : ' . $e->getMessage());
    }
  }

  private static function getAppConfig($apiKey, $configId) {

    $searchFor = realpath(JeedomConnect::$_backup_dir) . '/' . $apiKey . '/appPref-*-' . $configId . '.json';

    $matches = glob($searchFor);
    if (is_array($matches) && count($matches) > 0) {
      $config_file = file_get_contents($matches[0]);
      return array(
        'type' => 'SET_APP_CONFIG',
        'payload' => array('config' => json_decode($config_file))
      );
    }

    return self::raiseException('Le fichier n\'existe plus.');
  }

  // JEEDOM & PLUGINS HEALTH

  private static function restartDaemon($userId, $pluginId) {
    $_plugin = \plugin::byId($pluginId);
    if (is_object($_plugin)) {
      JCLog::info('DAEMON restart by [' . $userId . '] =>' . $pluginId);
      $_plugin->deamon_start(true);
      return true;
    }
    return false;
  }

  private static function stopDaemon($userId, $pluginId) {
    $_plugin = \plugin::byId($pluginId);
    if (is_object($_plugin)) {
      JCLog::info('DAEMON stopped by [' . $userId . '] =>' . $pluginId);
      $_plugin->deamon_stop();
      return true;
    }
    return false;
  }

  private static function getJeedomHealthDetails() {
    $allPluginsData = array();
    $jeedomData = array();

    // get the number of update availables
    $nb = update::nbNeedUpdate();

    // CUSTOM FX to disable health details -- not available now on screen -- personal use :) -- tomtom
    $sendHealth = config::byKey('sendHealth', 'JeedomConnect', 'true');

    if ($sendHealth == 'true') {

      foreach (plugin::listPlugin(true) as $plugin) {

        if ($plugin->getHasDependency() == 1 || $plugin->getHasOwnDeamon() == 1 || method_exists($plugin->getId(), 'health')) {
          $plugin_id = $plugin->getId();

          $asNok = 0;
          $asPending = 0;

          $portInfo = null;
          if (config::byKey('port', $plugin->getId()) != '') {
            $portInfo = ucfirst(config::byKey('port', $plugin->getId()));
          }

          $dependencyInfo = null;
          if ($plugin->getHasDependency() == 1) {
            try {
              $dependancy_info = $plugin->dependancy_info();
              switch ($dependancy_info['state']) {
                case 'ok':
                  $dependencyInfo = 'OK';
                  break;
                case 'in_progress':
                  $dependencyInfo = 'En cours';
                  $asPending += 1;
                  break;
                default:
                  $dependencyInfo = 'KO';
                  $asNok += 1;
                  break;
              }
            } catch (Exception $e) {
              JCLog::warning('HEALTH -- issue while getting dependancy_info -- ' . $e->getMessage());
            }
          }

          $daemonData = array();
          if ($plugin->getHasOwnDeamon() == 1) {
            try {

              $daemonData['setup'] = array();
              $daemon_info = $plugin->deamon_info();

              $daemonData['setup']['mode'] = $daemon_info['auto'] ? 'auto' : 'manuel';

              $daemonData['setup']['message'] = null;

              switch ($daemon_info['launchable']) {
                case 'ok':
                  $daemonData['setup']['status'] = 'OK';
                  break;
                case 'nok':
                  if ($daemon_info['auto'] != 1) {
                    $daemonData['setup']['status'] = 'Désactivé';
                  } else {
                    $daemonData['setup']['status'] = 'KO';
                    $daemonData['setup']['message'] =  $daemon_info['launchable_message'];
                    $asNok += 1;
                  }
                  break;
              }

              $daemonData['last_launch'] = $daemon_info['last_launch'];
              switch ($daemon_info['state']) {
                case 'ok':
                  $daemonData['state'] = 'OK';
                  break;
                case 'nok':
                  if ($daemon_info['auto'] != 1) {
                    $daemonData['state'] = 'Désactivé';
                  } else {
                    $daemonData['state'] = 'KO';
                    $asNok += 1;
                  }
                  break;
              }
            } catch (Exception $e) {
              JCLog::warning('HEALTH -- issue while getting daemon_info -- ' . $e->getMessage());
            }
          }

          $healthData = array();
          if (method_exists($plugin->getId(), 'health')) {

            try {
              foreach ($plugin_id::health() as $result) {
                $item = array();
                $item['test'] = $result['test'];
                $item['advice'] = $result['advice'];

                if (!$result['state']) {
                  $asNok += 1;
                }
                $item['result'] = $result['result'];

                array_push($healthData, $item);
              }
            } catch (Exception $e) {
              JCLog::warning('HEALTH -- issue while getting health info -- ' . $e->getMessage());
            }
          }


          $update = $plugin->getUpdate();
          $versionType = $versionDate = null;
          if (is_object($update)) {
            $versionType = $update->getConfiguration('version');
            $versionDate = $update->getLocalVersion();
          }

          $pluginData = array();

          $pluginData['id'] = $plugin_id;
          $pluginData['name'] = $plugin->getName();
          $pluginData['img'] = $plugin->getPathImgIcon();
          $pluginData['versionDate'] = $versionDate;
          $pluginData['versionType'] = $versionType;
          $pluginData['error'] = $asNok;
          $pluginData['pending'] = $asPending;
          $pluginData['portInfo'] = $portInfo;
          $pluginData['dependencyInfo'] = $dependencyInfo;
          $pluginData['daemonData'] = $daemonData;
          $pluginData['healthData'] = $healthData;

          array_push($allPluginsData, $pluginData);
        }
      }

      // get jeedom health page
      foreach ((jeedom::health()) as $datas) {
        $item = array();
        $item['name'] = $datas['name'];
        $item['comment'] = $datas['comment'];

        if ($datas['state'] === 2) {
          $item['state'] = 'warning';
        } else if ($datas['state']) {
          $item['state'] = 'OK';
        } else {
          $item['state'] = 'KO';
        }

        $item['result'] = $datas['result'];

        array_push($jeedomData, $item);
      }
    } else {
      JCLog::debug('HEALTH -- skip');
    }

    $result = array(
      'type' => 'SET_JEEDOM_GLOBAL_HEALTH',
      'payload' => array(
        'plugins' => $allPluginsData,
        'jeedom' => $jeedomData,
        'nbUpdate' => $nb
      )
    );
    return $result;
  }


  //EXEC ACTIONS
  public static function execCmd($id, $options = null) {
    $cmd = cmd::byId($id);
    if (!is_object($cmd)) {
      return self::raiseException("Can't find command [id=" . $id . "]");
    }

    $options = array_merge($options ?? array(), array('comingFrom' => 'JeedomConnect'));
    try {

      if (key_exists('user_id', $options)) {
        /** @var user $user */
        $user = user::byId($options['user_id']);
        if (!$cmd->hasRight($user)) {
          if ($options['withLog'] ?? true) JCLog::warning('/!\ commande ' . $cmd->getHumanName() . ' interdite pour l\'utilisateur "' . $user->getLogin() . '" - droit limité');
          if ($options['withLog'] ?? true) {
            return self::raiseException('Vous n\'avez pas le droit d\'exécuter cette commande ' . $cmd->getHumanName());
          } else {
            return "unauthorized";
          }
        }
      }

      $txtUser = '';
      if (key_exists('user_login', $options)) {
        $txtUser = ' par l\'utilisateur ' . $options['user_login'];
        // $options['user_login'] = $options['user_login'] . ' via JC';
      }
      JCLog::info('Exécution de la commande ' . $cmd->getHumanName() . ' (' . $id . ')' . $txtUser);

      $cmd->execCmd($options);
    } catch (Exception $e) {
      JCLog::error($e->getMessage());
      return self::raiseException($e->getMessage());
    }
    return null;
  }

  private static function execMultipleCmd($cmdList) {
    $unauthorized = array();
    $error = array();
    foreach ($cmdList as $cmd) {
      $temp = self::execCmd($cmd['id'], array_merge($cmd['options'] ?? array(), array('withLog' => false)));
      if ($temp == 'unauthorized') {
        array_push($unauthorized, $cmd['id']);
        continue;
      }
      if (!is_null($temp)) array_push($error, $cmd['id']);
    }

    $exception = '';
    $errorIds = array();
    if (count($unauthorized) > 0) {
      $cmdUnauthorizedName = JeedomConnectUtils::getCmdName($unauthorized, true);
      $command = (count($unauthorized) == 1) ? 'la commande' : 'les commandes';
      $exception .= 'Vous n\'avez pas le droit d\'exécuter ' . $command . ' ' . implode(", ", $cmdUnauthorizedName) . '. ';
      $errorIds = array_merge($errorIds, $unauthorized);
    }

    if (count($error) > 0) {
      $cmdErrorName = JeedomConnectUtils::getCmdName($error, true);
      $cmdName = implode(", ", $cmdErrorName);
      $exception .= (count($error) == 1) ? "La commande $cmdName n'a pas pu être exécutée. " : "Les commandes $cmdName n'ont pas pu être exécutées. ";
      $errorIds = array_merge($errorIds, $error);
    }

    if ($exception != '') {
      return self::raiseException($exception, '', array("cmd_ids" => $errorIds));
    }

    return null;
  }

  public static function getJCActions($apiKey, $withType = true) {
    $returnType = 'ACTIONS';

    $actions = JeedomConnectActions::getAllActions($apiKey);
    $payload = array();
    if (count($actions) > 0) {
      foreach ($actions as $action) {
        array_push($payload, $action['value']['payload']);
      }
      JeedomConnectActions::removeActions($actions);
      JCLog::debug("send action " . json_encode($payload));
    }


    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  // MANAGE SC
  public static function execSc($id, $options = null, $eqLogicId = null) {
    if ($options == null) $options = array();

    try {
      $scenario = scenario::byId($id);
      if (is_object($scenario)) {
        if (key_exists('user_id', $options)) {
          /** @var user $user */
          $user = user::byId($options['user_id']);
          if (!$scenario->hasRight('x', $user)) {
            JCLog::warning('/!\ scenario ' . $scenario->getHumanName() . " interdit pour l'utilisateur '" . $user->getLogin() . "' - droit limité");
            return self::raiseException('Vous n\'avez pas le droit d\'exécuter ce scenario ' . $scenario->getHumanName());
          }
        }

        $_tags = array();
        if (key_exists('tags', $options)) {
          $args = arg2array($options["tags"]);
          foreach ($args as $key => $value) {
            $valueTmp = trim($value);
            $tmp = scenarioExpression::setTags($valueTmp, $scenario);
            $_tags['#' . trim(trim($key), '#') . '#'] = $tmp;
          }
        }

        $textUser = '';
        if (key_exists('user_login', $options)) {
          $textUser =  " par l'utilisateur " . $options['user_login'];
          $_tags['#userJC#'] = $options['user_login'];
        }

        $scenario->setTags($_tags);

        $scenario_return = $scenario->launch('JeedomConnect', 'Lancement du scénario ' . $scenario->getHumanName() . ' (' . $id . ')' . $textUser);

        //if scenario returns a string, then display a toaster
        if (is_string($scenario_return)) {
          $toaterCmd = JeedomConnectCmd::byEqLogicIdAndLogicalId($eqLogicId, 'toaster');
          if (is_object($toaterCmd)) $toaterCmd->execCmd(array('message' => $scenario_return));
        }
      } else {
        throw new Exception("Le scenario $id n'existe pas");
      }
    } catch (Exception $e) {
      JCLog::error($e->getMessage());
      return self::raiseException($e->getMessage());
    }

    return null;
  }

  private static function stopSc($id) {
    try {
      $sc = scenario::byId($id);
      $sc->stop();
    } catch (Exception $e) {
      JCLog::error($e->getMessage());
    }
  }

  private static function setActiveSc($id, $active) {
    try {
      $sc = scenario::byId($id);
      $sc->setIsActive($active);
      $sc->save();
    } catch (Exception $e) {
      JCLog::error($e->getMessage());
    }
  }

  // INTERACTION
  public static function queryInteract($query, $options, $keywordIndex) {
    $param = array();
    if (isset($options['reply_cmd'])) {
      $reply_cmd = cmd::byId($options['reply_cmd']);
      if (is_object($reply_cmd)) {
        $param['reply_cmd'] = $reply_cmd;
        $param['force_reply_cmd'] = 1;
      }
    }
    if (isset($options['user_login'])) {
      $param['profile'] = $options['user_login'];
    }
    $param['plugin'] = 'JeedomConnect';
    $result = interactQuery::tryToReply($query, $param);
    return  array(
      'type' => 'QUERY_ANSWER',
      'payload' => array_merge($result, array('keywordIndex' => $keywordIndex))
    );
  }

  // FILES
  public static function getFiles($folder, $recursive = false, $isRelativePath = true, $prefixe = null) {
    $result = JeedomConnectUtils::getFiles($folder, $recursive, $isRelativePath, $prefixe);

    return  array(
      'type' => 'SET_FILES',
      'payload' => array(
        'path' => $folder,
        'files' => $result
      )
    );
  }


  private static function removeFile($file) {
    $pathInfo = pathinfo($file);
    unlink($file);
    return
      self::getFiles(preg_replace('#/+#', '/', $pathInfo['dirname']), true, false);
  }

  private static function removeFiles($files, $path = null) {
    $folder = $path ?? preg_replace('#/+#', '/', pathinfo($files[0])['dirname']);
    foreach ($files as $file) {
      unlink($file);
    }

    return
      self::getFiles($folder, true, $path != null);
  }

  /**
   * Raised an exception message
   *
   * @param string $errMsg
   * @param string $method
   * @param mixed $detail
   * @return array
   */
  public static function raiseException($errMsg = '', $method = '', $detail = null) {
    $txtType = ($method == '') ? '' : "Error with '" . $method . "' method ";
    $result = array(
      "type" => "EXCEPTION",
      "payload" => array(
        "message" => $txtType . $errMsg
      )
    );

    if (!is_null($detail)) {
      $result['payload']['details'] = $detail;
    }

    JCLog::debug('Send ' . json_encode($result));
    JCLog::error($result["payload"]["message"]);

    return $result;
  }

  // MANAGE LOG FILE
  private static function getLog($type, $id, $withType = true) {
    $returnType = 'SET_LOG';

    $logRootDir =   __DIR__ . '/../../../../log/';

    $filePath = $logRootDir . (($type == 'scenario') ? 'scenarioLog/scenario' . $id . '.log' : $id);

    if (!file_exists($filePath)) {
      JCLog::warning('file ' . $filePath . ' does not exist');
      $fileContent = 'pas de log disponible - fichier introuvable';
    } else {
      $fileContent = file_get_contents($filePath);
    }

    return (!$withType) ? $fileContent : JeedomConnectUtils::addTypeInPayload(json_encode($fileContent), $returnType);
  }

  /**
   * @param JeedomConnect $eqLogic
   */
  private static function setDeviceInfos($eqLogic, $infos) {
    if ($infos['event'] == "boot") {
      $eqLogic->sendNotif("update_pref_app", array('type' => "ACTIONS", 'payload' => array('action' => "jcService", 'arg' => "ON")));
    }
    if (isset($infos['batteryLevel'])) {
      self::saveBatteryEquipment($eqLogic, $infos['batteryLevel']);
    }
    if (isset($infos['ipAddress'])) {
      $eqLogic->checkAndUpdateCmd('ipAddress', $infos['ipAddress']);
    }
    if (isset($infos['ssid'])) {
      $eqLogic->checkAndUpdateCmd('ssid', $infos['ssid']);
    }
    if (isset($infos['wifiEnabled'])) {
      $eqLogic->checkAndUpdateCmd('wifiEnabled', $infos['wifiEnabled'] ? 1 : 0);
    }
    if (isset($infos['bluetoothConnected'])) {
      $eqLogic->checkAndUpdateCmd('bluetoothConnected', $infos['bluetoothConnected'] ? 1 : 0);
    }
    if (isset($infos['isScreenOn'])) {
      $eqLogic->checkAndUpdateCmd('isScreenOn', $infos['isScreenOn'] ? 1 : 0);
    }
    if (isset($infos['isCharging'])) {
      $eqLogic->checkAndUpdateCmd('isCharging', $infos['isCharging'] ? 1 : 0);
    }
    if (isset($infos['nextAlarm']) && is_numeric($infos['nextAlarm'])) {
      $eqLogic->checkAndUpdateCmd('nextAlarm', floor(intval($infos['nextAlarm'] / 1000)));
      $eqLogic->checkAndUpdateCmd('nextAlarmPackage', $infos['alarmPackage']);
    } else {
      $eqLogic->checkAndUpdateCmd('nextAlarm', -1);
      $eqLogic->checkAndUpdateCmd('nextAlarmPackage', $infos['alarmPackage']);
    }

    if (isset($infos['alarmFiltered']) && $infos['alarmFiltered']) {
      JCLog::debug("La prochaine Alarme est émise par un package que vous n'avez pas filtré [" . ($infos['alarmPackage'] ?? 'N/A') . "], elle n'est donc pas retenue");
    }

    if (isset($infos['volumes'])) {
      self::setVolume($eqLogic, $infos['volumes']);
    }

    if (isset($infos['smsMessage'])) {
      $eqLogic->checkAndUpdateCmd('smsMessage', $infos['smsMessage']);
    }
    if (isset($infos['smsNumber'])) {
      $eqLogic->checkAndUpdateCmd('smsNumber', $infos['smsNumber']);
    }
  }

  private static function setFaceDetected($eqLogic, $infos) {
    $eqLogic->checkAndUpdateCmd('faceDetected', $infos['value']);
  }

  /**
   * @param JeedomConnect $eqLogic
   * @param boolean $value
   * @return void
   */
  private static function setWebsocket($eqLogic, $value) {
    $eqLogic->setConfiguration('useWs', $value ? '1' : '0');
    $eqLogic->save(true);
    $eqLogic->generateQRCode();

    $deamon_info = JeedomConnect::deamon_info();
    if ($deamon_info['launchable'] == 'ok' && $deamon_info['state'] != 'ok') {
      JeedomConnect::deamon_start();
    }

    return array(
      "type" => "GET_WEBSOCKET",
      "payload" => array(
        "useWs" => $value ? '1' : '0'
      )
    );
  }

  /**
   * @param JeedomConnect $eqLogic
   * @param boolean $value
   * @return void
   */
  private static function setPolling($eqLogic, $value) {
    $eqLogic->setConfiguration('polling', $value ? '1' : '0');
    $eqLogic->save(true);
    $eqLogic->generateQRCode();

    return array(
      "type" => "GET_POLLING",
      "payload" => array(
        "polling" => $value ? '1' : '0'
      )
    );
  }

  private static function setPicoKey($eqLogic, $value) {
    $eqLogic->setConfiguration('picovoiceKey', $value);
    $eqLogic->save(true);
  }

  private static function getPicoKey($eqLogic, $withType = true) {
    $returnType = 'SET_PICOKEY';

    $payload = array(
      'picovoiceKey' => $eqLogic->getConfiguration('picovoiceKey', null)
    );

    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }

  /**
   * @param string $oldApiKey
   * @return bool
   */
  public static function isApiKeyRegenerated($oldApiKey) {
    return (config::byKey('newApiKey::' . $oldApiKey, 'JeedomConnect', false) !== false);
  }

  /**
   * @param string $oldApiKey
   * @return array
   */
  public static function getApiKeyRegenerated($oldApiKey, $withType = true) {
    $returnType = 'SET_NEW_APIKEY';

    $newApiKey = config::byKey('newApiKey::' . $oldApiKey, 'JeedomConnect', null);

    if (is_null($newApiKey)) throw new Exception('No equipment found - no apikey regen');

    config::remove('newApiKey::' . $oldApiKey, 'JeedomConnect');

    $payload = array('apiKey' => $newApiKey);

    return (!$withType) ? $payload : JeedomConnectUtils::addTypeInPayload($payload, $returnType);
  }



  /**
   *
   * Allow to update an event on a command
   *
   * @param string $commandId
   * @param string $message
   * @return void
   */
  public static function setEvent($commandId, $message) {

    $cmd = cmd::byId($commandId);

    if (!is_object($cmd)) {
      throw new Exception($commandId . " is not a valid command");
    }

    JCLog::info("Mise à jour de la commande $commandId par un évènement [" . json_encode($message) . "]");
    $cmd->event($message);
  }

  /**
   * Set the cmd Info Volume base on the setting set by user
   *
   * @param JeedomConnect $eqLogic
   * @param array $volume list of volume set
   * @return void
   */
  public static function setVolume($eqLogic, $volume) {
    $volumeType = array_keys(JeedomConnect::$_volumeType);

    /** @var cmd $cmdInfoVolume */
    $cmdInfoVolume = $eqLogic->getCmd('info', 'volume');
    if (!is_object($cmdInfoVolume)) return;

    $volumeOption = $eqLogic->getConfiguration('volume', 'all');

    if ($volumeOption == 'all') {
      $volumeFinal = '';
      foreach ($volumeType as $item) {
        $volumeFinal .= ($volume[$item] ?? '-1') . ';';
      }
    } else {
      $volumeFinal = $volume[$volumeOption] ?? '-1';
    }

    JCLog::debug("Final volume set to => " . $volumeFinal);
    $cmdInfoVolume->event($volumeFinal);
    return;
  }
}
