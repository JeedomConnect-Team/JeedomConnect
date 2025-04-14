<?php

/* * ***************************Includes********************************* */

class JeedomConnectUtils {

    public static function getCustomPathIcon(JeedomConnect $eqLogic) {
        $plugin = plugin::byId('JeedomConnect');

        $platform = $eqLogic->getConfiguration('platformOs');
        $standardIcon = $plugin->getPathImgIcon();

        if ($platform == '') return $standardIcon;

        $path_parts = pathinfo($standardIcon);
        $extension = $path_parts['extension'];
        $extensionSize = strlen($extension) + 1;
        $finalIcon = substr($standardIcon, 0, $extensionSize * -1) . '_' . $platform . '.' . $extension;

        if (!file_exists($finalIcon)) return $standardIcon;

        return $finalIcon;
    }

    public static function isCoreGreaterThan($version = '0.0.0') {
        $update = update::byTypeAndLogicalId('core', 'jeedom');
        if (is_object($update)) {
            $currentVersion =  $update->getLocalVersion();
            return version_compare($currentVersion, $version, ">");
        }

        return  false;
    }

    public static function getInstallDetails($str = false): string {

        $infoPlugin = '<b>Jeedom Core</b> : ' . config::byKey('version', 'core', '#NA#') . ' (' . config::byKey('core::branch') . ')<br/>';
        $infoPlugin .= '<b>DNS Jeedom</b> : ' . (self::hasDNSConnexion() ? 'oui ' : 'non') . '<br/><br/>';

        $daemon_info = JeedomConnect::deamon_info();

        $infoPlugin .= '<b>Statut Démon</b> : ' . ($daemon_info['state'] == 'ok' ? 'Démarré ' : 'Stoppé') . ' (' . ($daemon_info['last_launch'] ?? 'NA') . ')<br/>';
        $infoPlugin .= JeedomConnect::getConfigForCommunity(false);

        if ($str) {
            $infoPlugin = br2nl('< Ajoutez un titre puis rédigez votre question/problème ici, sans effacer les infos de config indiquées ci-dessous ><br/><br/><br/><br/>--- <br/>**Mes infos de config** : <br/>```<br/>' . $infoPlugin . '```');
            $infoPlugin = str_replace(array('<b>', '</b>', '&nbsp;'), array('', '', ' '), $infoPlugin);
        }

        return $infoPlugin;
    }

    public static function getCommunityUrl() {

        $installDetail = JeedomConnectUtils::getInstallDetails(true);

        /** @var plugin $plugin */
        $plugin = plugin::byId('JeedomConnect');

        $communitUrl = 'https://community.jeedom.com';
        $ressource = '/new-topic?';

        $data = array(
            'category' => 'plugins/' . $plugin->getCategory(),
            'tags' => 'plugin-' . $plugin->getId(),
            'body' => $installDetail
        );


        $query = http_build_query($data);

        $url = $communitUrl . $ressource . $query;

        return $url;
    }

    public static function getUserInfo($userId) {

        /** @var user $user */
        $user = user::byId($userId);
        if (!is_object($user)) {
            return 'NA';
        }

        $userProfil = $user->getProfils();
        $return = ($userProfil == 'admin') ? 'PA' : ($userProfil == 'restrict' ? 'PR' : 'PU');

        $return .= $user->getOptions('localOnly') ? 'L' : '';

        return $return;
    }

    /**
     * @param plugin $pluginObj
     * @return array
     */
    public static function getPluginDetails($pluginObj) {

        $update = update::byLogicalId($pluginObj->getId());
        $item = array();
        $item['pluginId'] =  $update->getLogicalId();
        $item['name'] = $pluginObj->getName();
        $item['img'] = $pluginObj->getPathImgIcon();
        $item['changelogLink'] =  $pluginObj->getChangelog();
        $item['docLink'] =  $pluginObj->getDocumentation();
        $item['doNotUpdate'] = $update->getConfiguration('doNotUpdate') == 1;
        $item['pluginType'] = $update->getConfiguration('version');
        $item['currentVersion'] =  $update->getLocalVersion();
        $item['updateVersion'] = $update->getRemoteVersion();
        $item['isActive'] = $pluginObj->isActive() == "1";
        $item['logFiles'] = $pluginObj->getLogList();

        return $item;
    }

    public static function getCmdForGenericType($genericTypes, $eqLogicId = null) {
        $cmds = cmd::byGenericType($genericTypes, $eqLogicId);
        // JCLog::debug("found:" . count($cmds));

        $results = array();
        foreach ($cmds as $cmd) {
            $eqLogic = $cmd->getEqLogic();
            if ($eqLogic->getIsEnable() == 0) continue;
            $results[$eqLogic->getId()]['name'] = $eqLogic->getName();
            $results[$eqLogic->getId()]['room'] = $eqLogic->getObject() ? $eqLogic->getObject()->getName() : 'none';
            $results[$eqLogic->getId()]['roomId'] = $eqLogic->getObject() ? $eqLogic->getObject()->getId() : null;
            $results[$eqLogic->getId()]['cmds'][] = array(
                'id' => $cmd->getId(),
                'humanName' => '#' . $cmd->getHumanName() . '#',
                'name' => $cmd->getName(),
                'type' => $cmd->getType(),
                'subType' => $cmd->getSubType(),
                'generic_type' => $cmd->getGeneric_type(),
                'minValue' => $cmd->getConfiguration('minValue'),
                'maxValue' => $cmd->getConfiguration('maxValue'),
                'unit' => $cmd->getUnite(),
                'value' => $cmd->getValue(),
                'icon' => self::getIconAndColor($cmd->getDisplay('icon'))
            );
            // JCLog::debug("cmd:{$eqLogic->getId()}/{$eqLogic->getName()}-{$cmd->getId()}/{$cmd->getName()}");
        }

        // JCLog::debug('temp results:' . count($results) . '-' . json_encode($results));
        return $results;
    }

    public static function getGenericType($widgetConfig) {
        $genericTypes = array();
        foreach ($widgetConfig['options'] as $option) {
            if (isset($option['generic_type']) && $option['generic_type'] != '') {
                $genericTypes[] = $option['generic_type'];
            }
        }
        return array_unique($genericTypes);
    }

    /**
     * return an array of widget of type $_widget_Type with commands matching the corresponding generic type
     *
     * @param string $_widget_Type
     * @param array $_widgetConf
     * @param array $_cmd_GenType
     * @return array
     */
    public static function createAutoWidget($_widget_Type, $_widgetConf, $_cmd_GenType) {

        $result = array();
        foreach ($_cmd_GenType as $eqLogicId => $eqLogicConfig) {
            $current = array();
            $current['enable'] = true;
            $current['type'] = $_widget_Type;
            $current['room'] = intval($eqLogicConfig['roomId']);
            $current['name'] = $eqLogicConfig['name'];

            foreach ($_widgetConf['options'] as $option) {
                if (isset($option['category']) && isset($option['generic_type'])) {
                    if ($option['category'] == 'cmd') {
                        foreach ($eqLogicConfig['cmds'] as $cmds) {
                            if ($cmds['generic_type'] != $option['generic_type']) continue;

                            $current[$option['id']] = $cmds;
                            break;
                        }
                    }
                    if ($option['category'] == 'cmdList') {
                        $cmdList = [];
                        foreach ($eqLogicConfig['cmds'] as $cmds) {
                            if ($cmds['generic_type'] != $option['generic_type']) continue;
                            array_push($cmdList, $cmds);
                        }
                        if (count($cmdList) > 0) {
                            $current[$option['id']] = $cmdList;
                        }
                    }
                }
            }

            array_push($result, $current);
        }
        // JCLog::debug('temp createAutoWidget:' .  json_encode($result));
        return $result;
    }

    public static function filterWidgetsWithStrictMode($results, $eqLogicId, $widgetConfig) {
        $isStrict = config::byKey('isStrict', 'JeedomConnect', true);
        foreach ($results as $eqLogicId => $eqLogicConfig) {
            // JCLog::debug("checking eqLogic {$eqLogicId}/{$eqLogicConfig['name']}");
            $requiredCmdWithGenericTypeInConfig = false;
            $requiredCmdWithGenericTypeFound = false;
            foreach ($widgetConfig['options'] as $option) {
                if (isset($option['generic_type']) && isset($option['required']) && $option['required'] == true) {
                    $requiredCmdWithGenericTypeInConfig = true;
                    // JCLog::debug("checking {$option['generic_type']}");
                    $requiredCmdWithGenericTypeFound = false;
                    foreach ($eqLogicConfig['cmds'] as $cmds) {
                        if ($cmds['generic_type'] == $option['generic_type']) {
                            $requiredCmdWithGenericTypeFound = true;
                            break;
                        }
                    }
                    if ($isStrict && !$requiredCmdWithGenericTypeFound) {
                        // JCLog::debug("Strict mode and could not find a required cmd with generic type {$option['generic_type']} for eqLogic {$eqLogicId}/{$eqLogicConfig['name']}, removing it from results");
                        unset($results[$eqLogicId]);
                        break;
                    }
                }
            }
            if (!$isStrict && $requiredCmdWithGenericTypeInConfig && !$requiredCmdWithGenericTypeFound) {
                // JCLog::debug("Could not find ANY required cmd with generic type {$option['generic_type']} for eqLogic {$eqLogicId}/{$eqLogicConfig['name']}, removing it from results");
                unset($results[$eqLogicId]);
            }
        }

        return $results;
    }


    public static function widgetAlreadyExistWithRequiredCmd($allGeneratedWidgets, $widgetConfig) {
        $allExistingWidgets = JeedomConnectWidget::getWidgets('all', false, true);
        // JCLog::debug("All existing widgets currently : " . json_encode($allExistingWidgets));

        $cmdsWithGenType = array();
        foreach ($widgetConfig['options'] as $config) {
            if (key_exists('generic_type', $config) && in_array($config['category'], array('cmd', 'cmdList'))) {
                array_push($cmdsWithGenType, $config['id']);
            }
        }
        // JCLog::debug("All required Cmds id : " . json_encode($cmdsWithGenType));

        foreach ($allGeneratedWidgets as $key => $generatedWidget) {
            if (count($cmdsWithGenType) == 0) {
                // JCLog::debug("no required cmds found -- skipped control");
                $generatedWidget['alreadyExist'] = false;
            } else {
                // JCLog::debug("will check for generatedWidget " . json_encode($generatedWidget));
                foreach ($allExistingWidgets as $widget) {
                    $allCmdAlreadyUsed = true;
                    foreach ($cmdsWithGenType as $cmd) {
                        // JCLog::debug("will check for {$cmd} : generated=>" . ($generatedWidget[$cmd]['id'] ?? 'none') . ' // widget=>' . ($widget[$cmd]['id'] ?? 'none'));
                        if (isset($generatedWidget[$cmd]['id']) && $generatedWidget[$cmd]['id'] != ($widget[$cmd]['id'] ?? 'none')) {
                            $allCmdAlreadyUsed = false;
                            // JCLog::debug(" -- return false !");
                            break;
                        }
                    }
                    if ($allCmdAlreadyUsed) {
                        // JCLog::debug(" -- same id found !!");
                        // JCLog::debug(" ** generatedWidget already exist with widget id " . $widget['id']);
                        $generatedWidget['alreadyExist'] = true;
                        break;
                    }
                    $generatedWidget['alreadyExist'] = false;
                }
            }
            $allGeneratedWidgets[$key] = $generatedWidget;
        }
        // JCLog::debug("all generated final ==> " . json_encode($allGeneratedWidgets));
        return $allGeneratedWidgets;
    }

    public static function generateWidgetWithGenType($_widget_type, $_eqLogicId) {

        if ($_widget_type == null) return null;

        $widgetConfigParam = JeedomConnect::getWidgetParam(false, array($_widget_type));
        $widgetConfig = $widgetConfigParam[$_widget_type] ?? null;

        if ($widgetConfig == null) return null;

        $genericTypes = JeedomConnectUtils::getGenericType($widgetConfig);
        if ($genericTypes == null) return null;

        $cmdGeneric = JeedomConnectUtils::getCmdForGenericType($genericTypes, $_eqLogicId);

        $widgetsAvailable = JeedomConnectUtils::filterWidgetsWithStrictMode($cmdGeneric, $_eqLogicId, $widgetConfig);

        $generatedWidgets = JeedomConnectUtils::createAutoWidget($_widget_type, $widgetConfig, $widgetsAvailable);

        $result = JeedomConnectUtils::widgetAlreadyExistWithRequiredCmd($generatedWidgets, $widgetConfig);
        // JCLog::debug('generateWidgetWithGenType => ' . count($result) . '-' . json_encode($result));

        return $result;
    }

    public static function getIconAndColor($iconClass) {
        $newIconClass = trim(preg_replace('/ icon_(red|yellow|blue|green|orange)/', '', $iconClass));
        $matches = array();
        preg_match('/(.*)class=\"(.*)\"(.*)/', $iconClass, $matches);

        if (count($matches) > 3) {
            list($iconType, $iconImg) = explode(" ", $matches[2], 2);
            $iconType = ($iconType == 'icon') ? 'jeedom' : 'fa';
            $iconImg = ($iconType == 'fa') ? trim(str_replace('fa-', '', $iconImg)) : trim($iconImg);

            preg_match('/(.*) icon_(.*)/', $iconImg, $matches);
            $color = '';
            if (count($matches) > 2) {
                switch ($matches[2]) {
                    case 'blue':
                        $color = '#0000FF';
                        break;
                    case 'yellow':
                        $color = '#FFFF00';
                        break;
                    case 'orange':
                        $color = '#FFA500';
                        break;
                    case 'red':
                        $color = '#FF0000';
                        break;
                    case 'green':
                        $color = '#008000';
                        break;
                    default:
                        $color = '';
                        break;
                }
                $iconImg = trim(str_replace('icon_' . $matches[2], '', $iconImg));
            }

            return array('icon' => $newIconClass, 'source' => $iconType, 'name' => $iconImg, 'color' => $color);
        }

        return array('icon' => $newIconClass, 'source' => '', 'name' => '', 'color' => '');
    }

    public static function isBeta($text = false) {
        $plugin = plugin::byId('JeedomConnect');
        $update = $plugin->getUpdate();
        $isBeta = false;
        if (is_object($update)) {
            $version = $update->getConfiguration('version');
            $isBeta = ($version && $version != 'stable');
        }

        if ($text) {
            return $isBeta ? 'beta' : 'stable';
        }
        return $isBeta;
    }

    public static function getLinks() {

        $isBeta = self::isBeta();
        $linksData = json_decode(file_get_contents(JeedomConnect::$_plugin_info_dir . 'links.json'), true);

        foreach ($linksData as $key => $item) {
            if ((strpos($item['id'], 'donate') !== false) && file_exists(JeedomConnect::$_plugin_info_dir . 'partiallink')) {
                unset($linksData[$key]);
                continue;
            }

            if ($item['id'] == 'changelog') {
                $item['link'] .=  $isBeta ? "beta" : "stable";
                $linksData[$key] = $item;
                continue;
            }
        }

        usort($linksData, function ($a, $b) {
            return $a['index'] <=> $b['index'];
        });
        // JCLog::debug('result : ' .  json_encode($linksData));

        return $linksData;
    }

    public static function getFileContent($path) {

        if (!file_exists($path)) {
            JCLog::error('File not found  : ' . $path);
            return null;
        }

        $content = file_get_contents($path);

        if (is_json($content)) {
            return json_decode($content, true);
        }

        return $content;
    }

    public static function createListOption($data, $dict) {

        $list = '';
        foreach ($data as $item) {
            $val = $dict[$item] ?? $item;
            $list .= $item . '|' . $val . ';';
        }
        $list = ($list != '') ? substr($list, 0, -1) : '';

        return $list;
    }

    /**
     * @return array
     */
    public static function getTimelineFolders() {
        $folders = array("main" => "Principal");

        $custom = array();
        foreach ((timeline::listFolder()) as $folder) {
            if ($folder == 'main') continue;
            array_push($custom, $folder);
        }
        if (count($custom) > 0) {
            $folders['custom'] = $custom;
        }

        return $folders;
    }

    /**
     * @param string $folder
     * @return array
     */
    public static function getTimelineEvents($folder = 'main', $userId = null) {

        $return = array();
        $user = user::byId($userId) ?: null;
        /** @var array<timeline> $events */
        $events = timeline::byFolder($folder);
        foreach ($events as $event) {
            // hasRight method available with core 4.2
            if (method_exists($event, 'hasRight') && !$event->hasRight($user)) {
                continue;
            }
            $info = self::getTimelineEventDetails($event);
            if ($info != null) {
                $return[] = $info;
            }
        }
        return $return;
    }

    /**
     * @param timelime $event
     * @return array
     */
    public static function getTimelineEventDetails($event) {
        $return = array();
        $return['date'] = $event->getDatetime();
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $event->getDatetime());
        $return['timestamp'] = $d->getTimestamp();
        $return['type'] = $event->getType();
        $return['folder'] = $event->getFolder();

        switch ($event->getType()) {
            case 'cmd':
                $cmd = cmd::byId($event->getLink_id());
                if (!is_object($cmd)) {
                    return null;
                }
                $eqLogic = $cmd->getEqLogic();
                $object = $eqLogic->getObject();
                $return['object'] = is_object($object) ? $object->getName() : 'aucun';
                $return['name'] = $cmd->getName();
                $return['isHistorized'] = $cmd->getIsHistorized() == "1";

                $return['id'] = $cmd->getId();
                $return['cmdtype'] = $cmd->getType();
                $return['plugin'] = $eqLogic->getEqType_name();
                $return['eqLogic'] = $eqLogic->getName();

                if ($cmd->getType() == 'info') {
                    $return['value'] = $event->getOptions('value');
                }
                break;

            case 'scenario':
                $scenario = scenario::byId($event->getLink_id());
                if (!is_object($scenario)) {
                    return null;
                }
                $object = $scenario->getObject();
                $return['object'] = is_object($object) ? $object->getName() : 'aucun';

                $return['name'] = $scenario->getName();
                $return['id'] = $scenario->getId();

                $pattern = '/\<(.*)\> /i';
                $trigger = preg_replace($pattern, '', $event->getOptions('trigger'));
                $return['trigger'] =  $trigger;
                break;
        }
        return $return;
    }

    /**
     * @param string $plugin
     * @return array
     */
    public static function getJeedomMessages($plugin = '') {
        $nbMessage = message::nbMessage();
        $messages = array();

        if ($nbMessage != 0) {
            if ($plugin == '') {
                $messages = utils::o2a(message::all());
            } else {
                $messages = utils::o2a(message::byPlugin($plugin));
            }
        }

        return array('nbMessages' => $nbMessage, 'messages' => $messages);
    }

    /**
     * @param string|null $messageId
     * @return array|Exception
     */
    public static function removeJeedomMessage($messageId = null) {

        if ($messageId == 'all') return message::removeAll();

        $message = message::byId($messageId);
        if (!is_object($message) || is_null($messageId)) {
            throw new Exception(__('Message inconnu. Vérifiez l\'ID', __FILE__));
        }
        $message->remove();

        return true;
    }

    /**
     * @param array $payload
     * @param string $type
     * @return array
     */
    public static function addTypeInPayload($payload, $type) {
        $result = array(
            'type' => $type,
            'payload' => $payload
        );

        return $result;
    }

    /**
     * @param int $nbBytes
     * @return string
     */
    public static function generateApiKey($nbBytes = 16) {
        return bin2hex(random_bytes($nbBytes));
    }

    /**
     *
     * Copy a folder and his content to another place
     *
     * @param string $src path of the current folder to copy
     * @param string $dst path with the final folder name where copy has to be done
     * @return void
     */
    public static function recurse_copy($src, $dst, $ext = '*') {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::recurse_copy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    $fileInfo = pathinfo($file);
                    if ($ext != '*' && $fileInfo['extension'] != $ext) continue;
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     *
     * Allow to remove a folder contening files
     *
     * @param string $src path of the current folder to copy
     * @param string $dst path with the final folder name where copy has to be done
     * @return void
     */
    public static function delTree($dir) {
        $files = @scandir($dir);
        if ($files === false) return true;
        foreach (array_diff($files, array('.', '..')) as $file) {
            (is_dir("$dir/$file")) ? self::delTree("$dir/$file") : @unlink("$dir/$file");
        }
        return @rmdir($dir);
    }


    public static function getCmdName(array $cmdIds, bool $full_name = false): array {

        $result = array();
        foreach ($cmdIds as $id) {
            $cmd = cmd::byId($id);
            if (is_object($cmd)) {
                if ($full_name) {
                    array_push($result, $cmd->getHumanName());
                } else {
                    array_push($result, $cmd->getName());
                }
            }
        }

        return $result;
    }


    public static function hideSensitiveData(string $log, string $type): string {

        $defaultSizeKept = 10;

        $keysSensitive = array(
            "main" => array(
                "userHash" => $defaultSizeKept,
                "password" => 0
            ),
            "pluginConfig" => array(
                "httpUrl" => 12,
                "wsAddress" => 12
            ),

        );

        $logArray = json_decode($log, true);
        $tab = ($type == 'send') ? 'payload' : 'params';

        foreach ($keysSensitive as $key => $value) {

            foreach ($value as $item => $indice) {
                $strSearched =  ($logArray[$item] ?? null) ?: ($logArray[$tab][$item] ?? null) ?: ($logArray[$tab][$key][$item] ?? null);
                if (is_null($strSearched) || empty($strSearched)) continue;

                // JCLog::debug('found key ' . $key . ' + item ' . $item . ' => ' . $strSearched);
                $sizeValue = strlen($strSearched) - $indice;
                $newValue = $sizeValue >= 0 ? substr_replace($strSearched, str_repeat('*', $sizeValue), $sizeValue * -1) : $strSearched;

                // JCLog::debug('  will replace ' . $strSearched . ' , by : ' . $newValue);
                $log = str_replace(json_encode($strSearched), json_encode($newValue), $log);
            }
        }
        return $log;
    }

    private static function isImgFile($extension) {
        return in_array($extension, array('gif', 'jpeg', 'jpg', 'png', 'wepb'));
    }

    private static function isVideoFile($extension) {
        return in_array($extension, array('avi', 'mpeg', 'mpg', 'mkv', 'mp4', 'mpe'));
    }

    public static function getNotifData($data, $eqLogic) {
        // title and body
        if (($data['payload']['title'] == '' && $data['payload']['message'] == '') ||
            ($data['payload']['title'] == '[Jeedom] Message de test')
        ) {
            $data['payload']['title'] = '<span style="color: #4caf50;"><b>Message test</b></span> &#128576;';
            $data['payload']['message'] = '&#127881; Tout est <span style="color: #ffffff; background-color: #9c27b0"><i>personnalisable</i></span> dans <a href="https://jared-94.github.io/JeedomConnectDoc/fr_FR/index">Jeedom Connect</a> ! &#127881;';
            if (!isset($data['payload']['actions'])) {
                $data['payload']['actions'] = array(
                    array(
                        "name" => "Yeah !",
                        "id" => "test",
                        "type" => "cancel"
                    )
                );
            }
            if (empty($data['payload']['image'])) {
                $data['payload']['image'] = array("source" => "jc", "name" => "favorites.webp");
            }
        }

        if (!is_null($data['payload']['answer']) &&  empty($data['payload']['title'])) {
            $data['payload']['title'] = "Question de " . config::byKey('name', 'core', 'Jeedom');
        }

        // files url
        if (isset($data["payload"]["files"])) {
            $httpUrl = config::byKey('httpUrl', 'JeedomConnect', network::getNetworkAccess('external'));
            $userHash = user::byId($eqLogic->getConfiguration('userId'))->getHash();

            foreach ($data["payload"]["files"] as &$file) {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                $filePath = $httpUrl . "/core/php/downloadFile.php?apikey=" . $userHash . "&pathfile=" . $file;
                if (self::isImgFile($extension) || self::isVideoFile($extension)) {
                    $filePath .= "&t=" . round(microtime(true) * 10000);
                }
                $file = $filePath;
            }
            unset($file);
        }

        // iOS category
        if ($eqLogic->getConfiguration('platformOs') == 'ios') {
            // actionsData
            $actionsData = array();
            //Notifs actions
            if (isset($data['payload']['actions']) && count($data['payload']['actions']) > 0 &&  is_null($data['payload']['answer'])) {
                foreach ($data['payload']['actions'] as $action) {
                    array_push($actionsData, array(
                        "id" => $action["id"],
                        "title" => $action["name"],
                        "type" => $action["type"]
                    ));
                }
            }
            //Ask actions
            if (!is_null($data['payload']['answer']) && count($data['payload']['answer']) > 0) {
                $actionsData = array(array(
                    "title" => "Répondre",
                    "id" => $data['payload']["cmdId"],
                    "input" => true,
                    "type" => "askReply"
                ));
                foreach ($data['payload']['answer'] as $action) {
                    array_push($actionsData, array(
                        "id" => $action,
                        "title" => $action,
                        "type" => "askReply"
                    ));
                }
            }

            //Actions navigate to page
            if ($data['payload']["options"] != null && isset($data['payload']["options"]["gotoPageId"])) {
                $pageId = intval($data['payload']["options"]["gotoPageId"]);
                $config = $eqLogic->getConfig(true);
                $actionName = "";
                $tabIndex = array_search($pageId, array_column($config['payload']["tabs"], "id"));
                if ($tabIndex !== false) {
                    $actionName = "Page " . $config['payload']["tabs"][$tabIndex]["name"];
                } else {
                    $sectionIndex = array_search($pageId, array_column($config['payload']["sections"], "id"));
                    if ($sectionIndex !== false) {
                        $actionName = "Page " . $config['payload']["sections"][$sectionIndex]["name"];
                    } else {
                        $roomIndex = array_search($pageId, array_column($config['payload']["rooms"], "id"));
                        if ($roomIndex !== false) {
                            $actionName = "Page " . $config['payload']["rooms"][$roomIndex]["name"];
                        }
                    }
                }
                if ($actionName != "") {
                    array_push($actionsData, array(
                        "id" => 'gotoPageId',
                        "title" => $actionName,
                        "foreground" => true
                    ));
                }
            }

            if ($data['payload']["options"] != null && isset($data['payload']["options"]["gotoWidgetId"])) {
                $config = $eqLogic->getGeneratedConfigFile();
                $widgetId = $data['payload']["options"]["gotoWidgetId"];
                $widgetIndex = array_search($widgetId, array_column($config['payload']["widgets"], "widgetId"));
                if ($widgetIndex !== false) {
                    array_push($actionsData, array(
                        "id" => 'gotoWidgetId',
                        "title" => "Widget " .  $config['payload']["widgets"][$widgetIndex]["name"],
                        "foreground" => true
                    ));
                }
            }

            $data["payload"]["category"] = array(array(
                "id" => $data['payload']["id"],
                "actions" => $actionsData
            ));
        }


        return $data;
    }

    public static function getIosPostData($data) {
        //clean body and title cause html not supported in native notif
        $display_options = array(
            "title" => $data['payload']["title"] == $data['payload']["message"] ? "" : trim(urldecode(html_entity_decode(strip_tags($data['payload']["title"])))),
            "body" => trim(urldecode(html_entity_decode(strip_tags($data['payload']["message"]), ENT_QUOTES)))
        );

        $display_options["ios"] = array(
            "categoryId" => $data['payload']["id"],
            "timestamp" => $data['payload']["time"] * 1000,
            "sound" => "default"
        );

        if ($data['payload']["critical"] == true) {
            $display_options["ios"]["critical"] = true;
            if ($data['payload']["criticalVolume"] != null) {
                $display_options["ios"]["criticalVolume"] = $data['payload']["criticalVolume"];
            } else {
                $display_options["ios"]["criticalVolume"] = 0.9;
            }
        }

        if (isset($data["payload"]["files"]) && count($data["payload"]["files"]) > 0) {
            $attachments = [];
            foreach ($data["payload"]["files"] as $url) {
                array_push($attachments, array(
                    "url" => $url
                ));
            }
            $display_options["ios"]["attachments"] = $attachments;
        }

        $display_options["data"] = array(
            "extraData" => json_encode(array(
                "actions" => $data['payload']["category"][0]["actions"],
                "notificationId" => $data['payload']["id"],
                "otherAskCmdId" => $data['payload']["otherAskCmdId"],
                "options" => $data['payload']["options"]
            ))
        );

        return $display_options;
    }

    public static function startsWith($haystack, $needle) {
        $length = strlen($needle);
        return substr($haystack, 0, $length) === $needle;
    }


    public static function getFiles($folder, $recursive = false, $isRelativePath = true, $prefixe = null) {
        $dir = $isRelativePath ? __DIR__ . '/../../../..' . $folder : $folder;
        $result = array();
        try {
            if (is_dir($dir)) {
                $dh = new DirectoryIterator($dir);
                foreach ($dh as $item) {
                    if (!$item->isDot() && substr($item, 0, 1) != '.') {
                        if (!$item->isDir()) {
                            if ($prefixe != null && !self::startsWith($item->getBasename(), $prefixe)) continue;
                            array_push($result, array(
                                'path' =>  realpath($item->getPathname()),
                                'timestamp' => $item->getMTime()
                            ));
                        } else if ($recursive) {
                            $subFolderFiles = self::getFiles(realpath($item->getPathname()), true, false);
                            $result = array_merge($result, $subFolderFiles);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            JCLog::error($e->getMessage());
        }

        return  $result;
    }

    /**
     * Return if the connexion uses a jeedom DNS 
     *
     * @return boolean
     */
    public static function hasDNSConnexion() {
        $url = config::byKey('httpUrl', 'JeedomConnect', network::getNetworkAccess('external'));
        if ((strpos($url, 'jeedom.com') !== false || strpos($url, 'eu.jeedom.link')) !== false) {
            return true;
        }

        return false;
    }


    public static function addCronItems() {
        self::addCronCheckDaemon();
        self::addCronRemoveBackupFiles();
    }

    /**
     * Add a task on jeedom crontab every monday to check if daemon is required
     *
     * @return void
     */
    private static function addCronCheckDaemon() {
        $cron = cron::byClassAndFunction('JeedomConnect', 'checkDaemon');
        if (!is_object($cron)) {
            $cron = new cron();
            $cron->setClass('JeedomConnect');
            $cron->setFunction('checkDaemon');
        }
        $cron->setEnable(1);
        $cron->setDeamon(0);
        $cron->setSchedule('0 0 * * 1');
        $cron->setTimeout(5);
        $cron->save();

        JeedomConnect::checkDaemon();
    }

    private static function addCronRemoveBackupFiles() {
        $cron = cron::byClassAndFunction('JeedomConnect', 'removeBackupFiles');
        if (!is_object($cron)) {
            $cron = new cron();
            $cron->setClass('JeedomConnect');
            $cron->setFunction('removeBackupFiles');
        }
        $cron->setEnable(1);
        $cron->setDeamon(0);
        $cron->setSchedule('0 23 * * *');
        $cron->setTimeout(5);
        $cron->save();
    }

    public static function removeCronItems() {
        try {
            $crons = cron::searchClassAndFunction('JeedomConnect', 'checkDaemon');
            if (is_array($crons)) {
                foreach ($crons as $cron) {
                    $cron->remove();
                }
            }

            $crons = cron::searchClassAndFunction('JeedomConnect', 'removeBackupFiles');
            if (is_array($crons)) {
                foreach ($crons as $cron) {
                    $cron->remove();
                }
            }
        } catch (Exception $e) {
        }
    }


    /**
     * Return distance between 2 coordinates. If 2nd is not defined, by default get the one from JC, or if not defined the one from jeedom, or if not defined Paris
     *
     * @param int $lat1
     * @param int $lon1
     * @param int $lat2
     * @param int $lon2
     * @return int
     */
    public static function getDistance($lat1, $lon1, $lat2 = null, $lon2 = null) {
        //by default the one from JC, or the one from jeedom, or Paris
        if (is_null($lat2) || is_null($lon2)) {
            list($lon2, $lat2) = self::getJcCoordinates();
        }

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $dist = ($dist * 60 * 1.1515) * 1609.344;
        return floor($dist);
    }

    public static function getJcCoordinates() {
        list($lngDefault, $latDefault) = JeedomConnectUtils::getDefaultCoordinates();

        $lng = config::bykey('longitude', 'JeedomConnect', config::bykey('info::longitude', 'core', $lngDefault));
        $lat = config::bykey('latitude', 'JeedomConnect', config::bykey('info::latitude', 'core', $latDefault));

        return array($lng, $lat);
    }

    public static function getDefaultCoordinates() {
        $lng = 2.349903;
        $lat = 48.852969;

        return array($lng, $lat);
    }

    public static function orderWidget($widgetArray, $orderBy) {
        switch ($orderBy) {
            case 'name':
                usort($widgetArray, function ($a, $b) {
                    return strcmp(strtolower($a['nameDisplayed'] ?: $a['name']),  strtolower($b['nameDisplayed'] ?: $b['name']));
                });
                break;

            case 'type':
                usort($widgetArray, function ($a, $b) {
                    if (strtolower($a['type']) ==  strtolower($b['type'])) {
                        return strcmp(strtolower($a['nameDisplayed'] ?: $a['name']),  strtolower($b['nameDisplayed'] ?: $b['name']));
                    }
                    return strcmp(strtolower($a['type']),  strtolower($b['type']));
                });
                break;

            default:
                usort($widgetArray, function ($a, $b) {
                    if (strtolower($a['roomName']) ==  strtolower($b['roomName'])) {
                        return strcmp(strtolower($a['nameDisplayed'] ?: $a['name']),  strtolower($b['nameDisplayed'] ?: $b['name']));
                    }
                    return strcmp(strtolower($a['roomName']),  strtolower($b['roomName']));
                });
                break;
        }
        return $widgetArray;
    }

    /**
     * Retrieve the list of files into a dir, order by modification time
     *
     * @param string $dir
     * @param string $prefix
     * @return mixed
     */
    public static function scan_dir($dir, $prefix = null, $withTime = false) {
        $ignored = array('.', '..', '.htaccess');

        $files = array();
        foreach (scandir($dir) as $file) {
            if (in_array($file, $ignored)) continue;
            if (!is_null($prefix) && !preg_match('/^' . $prefix . '.*$/', $file)) continue;
            $files[$file] = filemtime($dir . '/' . $file);
        }

        arsort($files);
        if (!$withTime) $files = array_keys($files);

        return ($files) ? $files : false;
    }

    /**
     * Check backup files for the application preference, and remove oldest ones if necessary
     *
     * @return void
     */
    public static function removeBackupFiles() {
        $_backup_dir = JeedomConnect::$_backup_dir;
        $prefix = 'appPref';

        if (!is_dir($_backup_dir))  return;

        $keepMaxCount = config::byKey('bkpCount', 'JeedomConnect', 'all');
        if ($keepMaxCount == 'all') {
            JCLog::trace('removeBackupFiles - no remove because setup to keep them all');
            return;
        }

        /**
         * @param JeedomConnect $eqLogic
         */
        foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
            $eqDir = $_backup_dir . $eqLogic->getConfiguration('apiKey') . '/';
            if (!is_dir($eqDir)) continue;

            $files = JeedomConnectUtils::scan_dir($eqDir, $prefix);
            JCLog::trace('all files =>' . json_encode($files));
            if (is_array($files) && count($files)  > 0) {
                $countFiles = count($files);
                // JCLog::debug('file count : ' . $countFiles . ' - keeping max : ' . $keepMaxCount);
                if ($countFiles  <= $keepMaxCount) continue;

                for ($i = $keepMaxCount; $i < $countFiles; $i++) {
                    JCLog::trace('removing old backup file : ' . $files[$i]);
                    unlink($eqDir . $files[$i]);
                }
            }
        }

        return;
    }

    /**
     * return detail of cmd type info from a list of cmd Ids
     *
     * @param array $cmdsIds
     * @param boolean $withType
     * @return void
     */
    public static function getCmdInfoDataIds($cmdsIds, $withType = true) {
        $returnType = 'SET_CMD_INFO';

        $payload = array();

        foreach ($cmdsIds as $cmdId) {
            $cmd_info = self::getCmdInfoDataDetails($cmdId);
            if (!is_null($cmd_info)) array_push($payload, $cmd_info);
        }

        return (!$withType) ? $payload : self::addTypeInPayload($payload, $returnType);
    }

    public static function getCmdInfoDataDetails($cmdId) {
        $cmd = cmd::byId($cmdId);
        if (!is_object($cmd)) return null;

        $state = $cmd->getCache(array('valueDate', 'collectDate', 'value'));

        $cmd_info = array(
            'id' => (string) $cmd->getId(),
            'value' => $state['value'],
            'modified' => strtotime($state['valueDate']),
            'collectDate' => strtotime($state['collectDate']),
            'history' => self::getHistoryValueInfo($cmd->getId())
        );
        // JCLog::debug('getCmdInfoDataDetails result => ' . json_encode($cmd_info));
        return $cmd_info;
    }

    public static function getCmdValues($cmdIds) {
        $data = array();

        foreach ($cmdIds as $cmdId) {
            $cmd = cmd::byId($cmdId);
            if (is_object($cmd)) {
                $data[$cmdId] = $cmd->execCmd();
            }
        }
        return array('data' => $data);
    }

    public static function getInfosCmdIds($widget) {

        switch ($widget['type']) {
            case 'alarm':
                $cmdIds = array($widget['enableInfo']['id'] ?? null, $widget['modeInfo']['id'] ?? null);
                break;

            case 'camera':
                $cmdIds = array($widget['snapshotUrlInfo']['id'] ?? null);
                break;

            case 'brightness':
            case 'frontgate':
            case 'generic-info-binary':
            case 'generic-info-string':
            case 'generic-info-numeric':
            case 'generic-slider':
            case 'generic-switch':
            case 'humidity':
            case 'pir':
            case 'plug':
            case 'power':
            case 'shutter':
            case 'single-light-switch':
            case 'temperature':
            case 'door':
            case 'window':
            case ($widget['type'] == 'component' && $widget['component'] == 'slider'):
            case ($widget['type'] == 'component' && $widget['component'] == 'switch'):
                // case ($widget['type'] == 'component' && $widget['component'] == 'text'):
                $cmdIds = array($widget['statusInfo']['id'] ?? null);
                break;

            case 'single-light-dim':
                $cmdIds = array($widget['statusInfo']['id'] ?? null, $widget['brightInfo']['id'] ?? null);
                break;

            case 'single-light-color':
                $cmdIds = array($widget['statusInfo']['id'] ?? null, $widget['brightInfo']['id'] ?? null, $widget['colorInfo']['id'] ?? null);
                break;

            case 'air-con':
                $cmdIds = array($widget['setpointInfo']['id'] ?? null, $widget['statusInfo']['id'] ?? null);
                break;

            case 'thermostat':
                $cmdIds = array($widget['setpointInfo']['id'] ?? null, $widget['modeInfo']['id'] ?? null);
                break;

            default:
                $cmdIds = array();
                break;
        }

        $cmdIds = array_filter($cmdIds);
        return array_merge($cmdIds, self::getCmdIdFromText($widget["name"]));
    }

    public static function getCmdIdFromText($text) {
        preg_match_all("/#.*?#/", $text, $match);
        $res = array_map(function ($t) {
            return str_replace("#", "", $t);
        }, $match[0]);
        return $res;
    }

    /**
     * @deprecated please use getExpressionEvaluated function instead
     *
     * @param [type] $text
     * @param [type] $cmdData
     * @return void
     */
    public static function getFormatedText($text, $cmdData) {
        foreach (self::getCmdIdFromText($text) as $id) {
            $text = str_replace("#{$id}#", $cmdData[$id], $text);
        }
        return $text;
    }

    public static function getExpressionEvaluated($expression, $widget) {
        $return = array();
        $scenario = null;
        $expression = str_replace("#room#", self::getRoomName($widget), $expression);
        $myExp = jeedom::fromHumanReadable($expression);
        $return['evaluate'] = scenarioExpression::setTags($myExp, $scenario, true);
        $return['result'] = evaluate($return['evaluate']);
        $return['correct'] = 'ok';
        if (trim($return['result']) == trim($return['evaluate'])) {
            $return['correct'] = 'nok';
        }
        return $return;
    }


    public static function getRoomName($widget) {
        if (!array_key_exists('room', $widget)) return "";
        $roomObjet = jeeObject::byId(intval($widget['room']));
        return is_object($roomObjet) ? $roomObjet->getName() : 'Aucun';
    }

    public static function getActionCmd($action) {
        $res = array(
            'action' => 'execCmd',
            'cmdId' =>  $action['id']
        );
        if (key_exists('options', $action) && $action['options'] != null)
            $res['options'] = $action['options'];
        if (key_exists('confirm', $action) && $action['confirm']) {
            $res['challenge'] = "ACK";
        } else if ((key_exists('secure', $action) && $action['secure']) || (key_exists('pwd', $action) && $action['pwd'])) {
            $res['challenge'] = "PIN";
        }
        return $res;
    }

    public static function getRangeStatus($cmdData, $action, &$device) {
        $device['rangeStatus'] = $cmdData[$action['id']];
        if (isset($action['minValue'])) {
            $device['minValue'] = floatval($action['minValue']);
        }
        if (isset($action['maxValue'])) {
            $device['maxValue'] = floatval($action['maxValue']);
        }
        if (isset($action['step'])) {
            $device['stepValue'] = floatval($action['step']);
        }
        if (isset($action['unit'])) {
            $device['rangeUnit'] = $action['unit'];
        }
    }

    public static function experimentalGetMode($modeName) {
        $modeName = strtolower($modeName);
        // JCLog::debug('experimentalGetMode - name = ' . $modeName);
        if (in_array($modeName, ["off", "eteindre", "éteindre"])) return 'off';
        if (strpos($modeName, "froid") !== false) return 'cold';
        if (strpos($modeName, "chaud") !== false) return 'heat';
        if (strpos($modeName, "auto") !== false) return 'heat_cool';
        if (strpos($modeName, "eco") !== false) return 'eco';

        return "";
    }

    public static function getModes($modes) {
        $res = array();
        foreach ($modes as $mode) {
            array_push($res, self::experimentalGetMode($mode['name']));
        }
        return array_unique($res);
    }

    public static function getHistoryValueInfo($cmdId) {

        $cmd = cmd::byId($cmdId);

        try {

            if (is_object($cmd) && $cmd->getIsHistorized() == 1 && $cmd->getSubType() != 'string') {
                $startHist = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -' . config::byKey('historyCalculPeriod') . ' hour'));
                $historyStatistique = $cmd->getStatistique($startHist, date('Y-m-d H:i:s'));

                $val = $cmd->execCmd();
                if ($historyStatistique['avg'] == 0 && $historyStatistique['min'] == 0 && $historyStatistique['max'] == 0 && $val != '') {
                    $averageHistoryValue = round($val, 1);
                    $minHistoryValue = round($val, 1);
                    $maxHistoryValue = round($val, 1);
                } else {
                    $averageHistoryValue = round($historyStatistique['avg'], 1);
                    $minHistoryValue = round($historyStatistique['min'], 1);
                    $maxHistoryValue = round($historyStatistique['max'], 1);
                }

                $startTendance = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -' . config::byKey('historyCalculTendance') . ' hour'));
                $tendanceData = $cmd->getTendance($startTendance, date('Y-m-d H:i:s'));
                if ($tendanceData > config::byKey('historyCalculTendanceThresholddMax')) {
                    $tendance = "up";
                } else if ($tendanceData < config::byKey('historyCalculTendanceThresholddMin')) {
                    $tendance = "down";
                } else {
                    $tendance = "stable";
                }
            }
        } catch (TypeError $e) {
            JCLog::warning('history error with cmdId ' . $cmdId . ' / val=' . ($val ?? null));
            JCLog::debug('Error :' . $e->getMessage());
        } catch (Exception $e) {
            JCLog::error('exception history cmdId ' . $cmdId . ' -> ' . $e->getMessage());
        }

        return array(
            'averageValue' => $averageHistoryValue ?? 0,
            'minValue' => $minHistoryValue ?? 0,
            'maxValue' => $maxHistoryValue ?? 0,
            'tendance' => $tendance ?? null,
        );
    }

    public static function hasObjectId($obj, $id) {

        foreach ($obj as $item) {
            // JCLog::debug('check id [' . $id . '] in item ' . json_encode($item));
            if ($item['id'] == $id) return true;
        }

        return false;
    }


    /**
     * Do the copy of the appPref backup file from one equipment to one or several other. Only the gridLayout part is copied
     *
     * @param string $eqFrom apiKey from the JC equipment source
     * @param string $eqToList one or several apiKey (separated with a coma) of the target JC equipments
     * @return bool true if success, otherwise false
     */
    public static function copyGridLayout($eqFrom, $eqToList) {
        $allGood = true;
        $eqTemp = JeedomConnect::byLogicalId($eqFrom, 'JeedomConnect');
        if (!is_object($eqTemp)) {
            JCLog::warning('Origne Id [' . $eqFrom . '] does not match any of the current JC equipment');
            return false;
        }

        $dir = JeedomConnect::$_backup_dir . '/' . $eqFrom;
        $lastAppPref = self::getLastFile($dir, 'appPref*');

        $file = $dir . '/' . $lastAppPref;

        if ($lastAppPref == null || !file_exists($file)) {
            JCLog::info('No backup found, sorry ... ');
            return false;
        }

        $fileContent = json_decode(file_get_contents($file), true);
        if (!key_exists('gridLayout', $fileContent)) {
            JCLog::info('Sorry no "gridLayout" property found in the backup File ');
            return false;
        }

        // JCLog::debug('"gridLayout" property =>' . json_encode($fileContent['gridLayout']));

        $eqToList = explode(",", $eqToList);

        foreach ($eqToList as $eq) {
            JCLog::debug('Trying to copy gridLayout backup from ' . $eqFrom . ' to ' . $eq);
            $eqTemp = JeedomConnect::byLogicalId($eq, 'JeedomConnect');
            if (!is_object($eqTemp)) {
                JCLog::warning('Final Id [' . $eq . '] does not match any of the current JC equipment');
                continue;
            }

            $fileCopy = JeedomConnect::$_backup_dir .  $eq . '/appPref-GridLayout-' . date('d-m-y') . '-' . time() . '.json';
            JCLog::debug('Filename destination : ' . $fileCopy);
            $copy = file_put_contents($fileCopy, json_encode(array('gridLayout' => $fileContent['gridLayout']), JSON_PRETTY_PRINT));

            if (!$copy) {
                $allGood = false;
                JCLog::warning('Copy to [' . $eq . '] goes wrong');
            } else {
                JCLog::info('Copy to [' . $eq . '] - SUCCESS');
            }
        }

        return $allGood;
    }


    public static function getLastFile($dir, $pattern = null) {

        $latest_ctime = 0;
        $latest_filename = null;

        $d = dir($dir);
        while (false !== ($entry = $d->read())) {
            if ($pattern != null && !fnmatch($pattern, $entry)) continue;

            $filepath = "{$dir}/{$entry}";
            if (is_file($filepath) && filectime($filepath) > $latest_ctime) {
                $latest_ctime = filectime($filepath);
                $latest_filename = $entry;
            }
        }

        return $latest_filename;
    }


    /**
     * Copy the configuration file from equipement $from to one or several equipement $toArray
     * If $withCustom apply, then also the customsation of the equipment will be copy
     *
     * @param string $from
     * @param array $toArray
     * @param boolean $withCustom
     * @return void
     */
    public static function copyConfig($from, $toArray  = array(), $withCustom = false, $throwException = true) {

        $copy = JeedomConnect::copyConfig($from, $toArray);

        if ($withCustom) {
            $copy &= JeedomConnectWidget::copyCustomData($from, $toArray);
        }

        if (!$copy && $throwException) {
            throw new Exception("Issue while copying the config");
        }
        return;
    }

    public static function installAndMigration() {

        if (config::byKey('userImgPath',   'JeedomConnect') == '') {
            config::save('userImgPath', 'plugins/JeedomConnect/data/img/user_files/', 'JeedomConnect');
        } else {
            $userImgPath = ltrim(config::byKey('userImgPath',   'JeedomConnect'), "/");
            if (substr($userImgPath, -1) != "/") {
                $userImgPath .= "/";
            }
            config::save('userImgPath', $userImgPath, 'JeedomConnect');
        }

        $realPath = __DIR__ . '/../../../../' . config::byKey('userImgPath',   'JeedomConnect');
        if (!is_dir($realPath)) {
            if (!mkdir($realPath))  JCLog::error("mkdir FAILED for " . $realPath);
        }

        if (config::byKey('migration::imgCond',   'JeedomConnect') == '') {
            JeedomConnect::migrateCondImg();
        }

        if (config::byKey('migration::customData',   'JeedomConnect') == '') {
            JeedomConnect::migrateCustomData();
        }

        if (config::byKey('migration::notifAll',   'JeedomConnect') == '') {
            JeedomConnect::migrationAllNotif();
        }

        if (config::byKey('migration::notifAll2',   'JeedomConnect') == '') {
            JeedomConnect::migrationAllNotif2();
        }

        if (config::byKey('migration::appPref',   'JeedomConnect') == '') {
            JeedomConnect::migrateAppPref();
        }

        if (config::byKey('fix::notifID',   'JeedomConnect') == '') {
            JeedomConnect::fixNotif();
        }
        if (config::byKey('fix::notifCmdDummy',   'JeedomConnect') == '') {
            JeedomConnect::fixNotifCmdDummy();
        }
        if (config::byKey('migration::widgetsConfig',   'JeedomConnect') == '') {
            JeedomConnectWidget::migrateWidgetsConfig();
        }

        $pluginInfo = JeedomConnect::getPluginInfo();
        config::save('version', $pluginInfo['version'] ?? '#NA#', 'JeedomConnect');
    }
}
