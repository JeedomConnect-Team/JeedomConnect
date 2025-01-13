<?php

/* * ***************************Includes********************************* */
// require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

require_once __DIR__  . '/JeedomConnectLock.class.php';
require_once __DIR__  . '/JeedomConnectLogs.class.php';

class JeedomConnectWidget extends config {

	public static $_plugin_id = 'JeedomConnect';

	public static function getMaxIndex() {
		return config::byKey('index::max', self::$_plugin_id) ?: '0';
	}

	public static function incrementIndex() {
		JCLog::debug('increment widget index ');
		$lock = new JeedomConnectLock('Widget_incrementIndex');
		try {
			if ($lock->Lock()) {
				$current = config::byKey('index::max', self::$_plugin_id) ?: '0';
				JCLog::debug('current index : ' . $current);

				$next = intval($current) + 1;
				config::save('index::max', strval($next), self::$_plugin_id);
				JCLog::debug('incrementIndex done');
				return $next;
			}
		} finally {
			unset($lock);
		}
	}

	public static function setConfiguration($_widgetId, $_key, $_value) {

		$currentConf = self::getConfiguration($_widgetId);
		JCLog::debug(' ## current conf ' . json_encode($currentConf));
		$conf = utils::setJsonAttr($currentConf, $_key, $_value);
		JCLog::debug(' ## conf saved ' . json_encode($conf));
		return self::saveConfig($conf, $_widgetId);
	}

	public static function getConfiguration($_widgetId, $_key = '', $_default = '') {

		$conf = self::byKey('widget::' . $_widgetId, self::$_plugin_id);
		// JCLog::info( ' ##  getConfiguration -- id '. $_widgetId. ' = retrieved : ' . json_encode($conf) ) ;
		return utils::getJsonAttr($conf, $_key, $_default);
	}

	public static function getJsonData($_data, $_key = '', $_default = '') {

		// JCLog::info( ' ##  getJsonData  -- data received => ' . json_encode($_data) );
		$conf = json_encode($_data);
		return utils::getJsonAttr($conf, $_key, $_default);
	}

	public static function getAllConfigurations() {

		$result = array();
		foreach (config::searchKey('widget::', self::$_plugin_id)  as $config) {
			$id = str_replace('widget::', '', $config['key']);
			$newConf['id'] = $id;
			$newConf['key'] = $config['key'];
			$newConf['conf'] = $config['value'];

			if ($id != '' && is_numeric($id)) {
				array_push($result, $newConf);
			} else {
				JCLog::warning("something goes wrong with widget : " . json_encode($config));
			}
		}
		return $result;
	}

	public static function getWidgets($_id = 'all', $_fullConfig = true, $_onlyConfig = false, $check_type = true) {

		if ($_id === 'all') {
			if ($_fullConfig) JCLog::debug('getWidgets for all widgets with full config');
			$widgets = JeedomConnectWidget::getAllConfigurations();
		} else {
			$widgets = array();
			$tmp = JeedomConnectWidget::getConfiguration($_id, '', null);
			if (!empty($tmp)) {
				//JCLog::debug( ' tmp result for '.$_id. '>' . json_encode($tmp) . '<' );
				$tmp['conf'] = $tmp;
				array_push($widgets, $tmp);
			}
		}


		$allImgPath = self::getElementImgPath();
		// JCLog::debug('all img =>' . json_encode($allImgPath));

		$widgetArray = array();
		if (!empty($widgets)) {
			foreach ($widgets as $widget) {
				// JCLog::debug('** current widget =>' . json_encode($widget));
				$widgetItem = array();

				if ($check_type && !key_exists('type', $widget['conf'])) {
					JCLog::warning(' -- SKIP -- no type for widget : ' . json_encode($widget));
					continue;
				}

				if ($_onlyConfig) {
					$widgetItem = $widget['conf'] ?? '';
				} else {
					$widgetJC = $widget['conf'] ?? '';
					if ($_fullConfig) $widgetItem['widgetJC'] = $widgetJC;
					$widgetItem['enable'] = $widgetJC['enable'];
					$widgetItem['name'] = $widgetJC['name'] ?? 'inconnu';
					$widgetItem['nameDisplayed'] = $widgetJC['nameDisplayed'] ?? null;
					$widgetItem['type'] = $widgetJC['type'] ?? 'none';
					$widgetItem['roomId'] = $widgetJC['room'] ?? '';
					$widgetRoomObjet = jeeObject::byId($widgetItem['roomId']);
					$widgetItem['roomName'] = $widgetItem['roomId'] == 'global' ? 'Global' : (is_object($widgetRoomObjet) ? $widgetRoomObjet->getName() : 'Aucun');
					$widgetItem['id'] = $widgetJC['id'] ?? 'none';
					$widgetItem['component'] = $widgetJC['component'] ?? 'none';

					$typeImg = ($widgetItem['type'] == 'component') ? 'component-' . $widgetItem['component'] : $widgetItem['type'];
					$widgetItem['img'] = $allImgPath[$typeImg] ?? plugin::byId(self::$_plugin_id)->getPathImgIcon();
				}

				array_push($widgetArray, $widgetItem);
			}

			if (!$_onlyConfig) {
				$widgetArray = JeedomConnectUtils::orderWidget($widgetArray, 'object');
			}

			//JCLog::debug( ' final result sent >' . json_encode($widgetArray) );
		}
		return $widgetArray;
	}


	public static function getElementImgPath() {

		$widgetsConfigJonFile = json_decode(file_get_contents(JeedomConnect::$_plugin_config_dir . 'widgetsConfig.json'), true);

		$imgPath = array();
		$pluginImg = plugin::byId(self::$_plugin_id)->getPathImgIcon();

		foreach ($widgetsConfigJonFile['components'] as $config) {
			if (file_exists(__DIR__ . '/../../data/img/' . $config['img'])) {
				$imgPath['component-' . $config['type']] = 'plugins/JeedomConnect/data/img/' . $config['img'];
			} else {
				$imgPath['component-' . $config['type']] = $pluginImg;
			}
		}
		foreach ($widgetsConfigJonFile['widgets'] as $config) {
			if (file_exists(__DIR__ . '/../../data/img/' . $config['img'])) {
				$imgPath[$config['type']] = 'plugins/JeedomConnect/data/img/' . $config['img'];
			} else {
				$imgPath[$config['type']] = $pluginImg;
			}
		}
		return $imgPath;
	}

	public static function getWidgetsList() {

		$widgetArray = self::getWidgets('all', false);
		usort($widgetArray, function ($a, $b) {
			return strcmp($a['name'], $b['name']);
		});

		return $widgetArray;
	}

	public static function updateImgPath($widgetId, $newPath, $reload = true) {
		$widgetSettings = self::getConfiguration($widgetId);
		if (empty($widgetSettings)) {
			JCLog::debug('updateImgPath - widgetId ' . $widgetId . ' NOT found');
			return;
		}

		$widgetSettings['imgPath'] = $newPath;
		self::saveConfig($widgetSettings, $widgetId);
		JCLog::debug('updateImgPath - img path updated to "' . $newPath . '" for widget id [' . $widgetId . ']');
		if ($reload) JeedomConnect::checkAllEquimentsAndUpdateConfig($widgetId);
		return;
	}

	public static function updateConfig($widgetId, $key, $value = null, $reload = true) {

		$widgetJC = self::getConfiguration($widgetId);
		if (empty($widgetJC)) {
			JCLog::debug('updateConfig - widgetId ' . $widgetId . ' NOT found');
			return;
		}

		if (isset($widgetJC[$key])) {
			if (is_null($value)) {
				unset($widgetJC[$key]);
			} else {
				$widgetJC[$key] = $value;
			}
			// $widgetSettings['widgetJC'] = json_encode($widgetJC);

			JCLog::debug('updateConfig - key "' . $key . '" found - updating details id [' . $widgetId . '] - conf : ' . $value);
			self::saveConfig($widgetJC, $widgetId);
			if ($reload) JeedomConnect::checkAllEquimentsAndUpdateConfig($widgetId);
			return;
		}
		JCLog::debug('updateConfig - key ' . $key . ' NOT found');
	}

	public static function updateWidgetConfig($config, $reload = true) {
		if ($config != null) {
			$widgetId = $config['id'];

			JCLog::debug('   ---  updating widget id ' . $widgetId . ' with conf => ' . json_encode($config));
			self::saveConfig($config, $widgetId);
			if ($reload) JeedomConnect::checkAllEquimentsAndUpdateConfig($widgetId);
		}
	}

	public static function saveConfig($conf, $widgetId = null) {

		$cpl = '';
		if (is_null($widgetId)) {
			$widgetId = self::incrementIndex();
			$cpl = ' [new creation]';
		}

		JCLog::debug('saveConfiguration details received for id : ' . $widgetId . $cpl . ' - conf : ' . json_encode($conf));
		try {
			config::save('widget::' . $widgetId, $conf, self::$_plugin_id);
		} catch (Exception $e) {
			JCLog::error("Error while saving widget => " . $e->getMessage());
			return null;
		}
		JCLog::debug('saveConfiguration done');
		return $widgetId;
	}

	public static function removeWidget($idToRemove = '') {
		if (!$idToRemove) {
			JCLog::warning('Removing widget(s) -- no data received -- abort');
			return;
		}

		if (is_array($idToRemove)) {
			JCLog::info('Removing widget(s) -- data received : (array) ' . json_encode($idToRemove));
			$arrayIdToRemove = $idToRemove;
		} else {
			JCLog::info('Removing widget -- data received : (int) ' . json_encode($idToRemove));
			$arrayIdToRemove = array($idToRemove);
		}


		// remove the widget ID inside json file config of each JC equipement
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$apiKey = $eqLogic->getConfiguration('apiKey');
			if ($apiKey !=  '') {
				$eqLogic->removeWidgetConf($arrayIdToRemove);
			}
		}

		// remove the widget ID inside widgets of a widget (favourite, group, ...)
		$allWidgets = self::getAllConfigurations();
		JCLog::info('all widget : ' . json_encode($allWidgets));
		foreach ($allWidgets as $widget) {
			$hasChanged = false;
			$conf = $widget['conf'];

			if (!array_key_exists('widgets', $conf) && !array_key_exists('moreWidgets', $conf)) {
				continue;
			}

			if (isset($conf['widgets'])) {
				foreach ($conf['widgets'] as $index => $obj) {

					if (in_array($obj['id'],  $arrayIdToRemove)) {
						JCLog::info('removing obj id [widgets] : ' .  $obj['id'] . ' at index ' . $index . ' for parent ' . $widget['id']);
						unset($conf['widgets'][$index]);
						$hasChanged = true;
					}
				}
				$conf['widgets'] = array_values($conf['widgets']);
			}

			if (isset($conf['moreWidgets'])) {
				foreach ($conf['moreWidgets'] as $index => $obj) {

					if (in_array($obj['id'],  $arrayIdToRemove)) {
						JCLog::info('removing obj id [moreWidgets] : ' .  $obj['id'] . ' at index ' . $index . ' for parent ' . $widget['id']);
						unset($conf['moreWidgets'][$index]);
						$hasChanged = true;
					}
				}
				$conf['moreWidgets'] = array_values($conf['moreWidgets']);
			}

			if ($hasChanged) self::saveConfig($conf, str_replace('widget::', '', $widget['id']));
		}

		foreach ($arrayIdToRemove as $idToRemove) {
			self::removeWidgetConf('widget::' . $idToRemove);
		}

		return;
	}

	public static function removeWidgetConf($idToRemove) {
		JCLog::debug('removing widget id : ' . $idToRemove);
		self::remove($idToRemove, self::$_plugin_id);
	}

	public static function duplicateWidget($widgetId) {

		JCLog::debug('duplicating widget id : ' . $widgetId);
		$configInit = self::getConfiguration($widgetId);

		$newId = self::incrementIndex();
		$configInit['id'] = $newId;

		self::saveConfig($configInit, $newId);
		return $newId;
	}

	public static function countWidgetByEq() {

		// init an array with all available widget Id
		$allWidgets = self::getAllConfigurations();
		$widgetList = array();
		foreach ($allWidgets as $widget) {
			if (!array_key_exists($widget['id'], $widgetList))  $widgetList[$widget['id']] = 0;

			if (array_key_exists('widgets', $widget)) {
				foreach ($widget['widgets'] as $sousWidget) {
					if (!array_key_exists($sousWidget['id'], $widgetList)) {
						$widgetList[$sousWidget['id']] = 0;
					}
				}
			}
		}

		$unexistingId = array();
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			if ($eqLogic->getIsEnable()) {

				$conf = $eqLogic->getConfig(true);

				foreach ($conf['payload']['widgets'] as $key => $value) {
					if (array_key_exists($value['id'], $widgetList)) {
						$widgetList[$value['id']]++;
					} else {
						array_push($unexistingId, $value['id']);
					}
				}
			} else {
				JCLog::debug('Equipment excluded because disable : ' . $eqLogic->getName() . ' [' . $eqLogic->getId() . ']');
			}
		}

		$onlyUnused  = array_filter($widgetList, function ($k) {
			return $k == 0;
		});

		JCLog::debug('final result count : ' . json_encode($widgetList));
		JCLog::debug('onlyUnused   : ' . json_encode(array_keys($onlyUnused)));
		JCLog::debug('list of unexisting widget Id : ' . json_encode($unexistingId));

		return array('count' => $widgetList, 'unused' => array_keys($onlyUnused), 'unexisting' => $unexistingId);
	}

	public static function uploadWidgetConf($dataJson) {
		JCLog::debug('uploading widgets conf');

		try {

			foreach ($dataJson as $conf) {
				JCLog::debug(' import key => ' . $conf['key'] . ' // value ==> ' . json_encode($conf['value']));

				if (!is_int($conf['value'])) $conf['value'] = json_encode($conf['value']);

				config::save($conf['key'], $conf['value'], self::$_plugin_id);
			}

			return true;
		} catch (Exception $e) {
			JCLog::error('Unable to upload config file : ' . $e->getMessage());
			throw new Exception("Error avec l'import");
		}
	}

	public static function exportWidgetConf() {
		$export_file = JeedomConnect::$_config_dir . "export_Widgets.json";

		$result = array();

		$maxId = self::getMaxIndex();
		array_push($result, array('key' => 'index::max', 'value' => intval($maxId)));

		$allWidgets = self::getAllConfigurations();

		foreach ($allWidgets as $widget) {
			array_push($result, array('key' => $widget['key'], 'value' => $widget['conf']));
		}

		try {
			JCLog::debug('Saving widgets conf file : ' . $export_file);
			$resultFinal = JeedomConnectUtils::addTypeInPayload($result, 'JC_EXPORT_WIDGETS_DATA');

			//saving file
			file_put_contents($export_file, json_encode($resultFinal, JSON_PRETTY_PRINT));

			//return data
			return $resultFinal;
		} catch (Exception $e) {
			JCLog::error('Unable to write file : ' . $e->getMessage());
		}
	}

	public static function exportWidgetCustomConf() {
		$export_file = JeedomConnect::$_config_dir . "export_custom_data_Widgets.json";

		$allCustomData = config::searchKey('customData::', 'JeedomConnect');

		$result = array();
		foreach ($allCustomData as $item) {
			array_push($result, array('key' => $item['key'], 'value' => $item['value']));
		}

		try {
			JCLog::debug('Saving  custom widgets conf file : ' . $export_file);
			$resultFinal = JeedomConnectUtils::addTypeInPayload($result, 'JC_EXPORT_CUSTOM_DATA');

			//saving file
			file_put_contents($export_file, json_encode($resultFinal, JSON_PRETTY_PRINT));

			//return data
			return $resultFinal;
		} catch (Exception $e) {
			JCLog::error('Unable to write file : ' . $e->getMessage());
		}
	}

	/**
	 * @param string $oldApiKey 
	 * @param array $newApiKey 
	 * @param bool $removeOld 
	 * @return bool
	 */
	public static function copyCustomData($oldApiKey, $newApiKey, $removeOld = false) {
		JCLog::debug('Copying custom data from ' . $oldApiKey . ' to ' . json_encode($newApiKey));


		$customData = config::searchKey('customData::' . $oldApiKey, 'JeedomConnect');

		if (!empty($customData)) {
			foreach ($customData as $item) {
				foreach ($newApiKey as $api) {
					$newKey = str_replace($oldApiKey, $api, $item['key']);
					JCLog::debug(' ******** copying key ' . $item['key'] . ' to ' . $newKey);
					config::save($newKey, $item['value'], 'JeedomConnect');
				}

				if ($removeOld) config::remove($item['key'], 'JeedomConnect');
			}
		}

		return true;
	}

	//***************  EXPERIMENTAL ZONE  =) ****************************/

	public static function replaceTextConfig($widgetId, $searchAndReplace, $reload = true) {
		//$searchAndReplace = array("icon_user.png" => "icon_userSSS.png", "pas_content.png" => "hyper_content.png");

		$widgetSettings = self::getConfiguration($widgetId);
		if (empty($widgetSettings)) {
			JCLog::debug('replaceTextConfig - widgetId ' . $widgetId . ' NOT found');
			return;
		}

		$widgetJC = $widgetSettings['widgetJC'];
		try {
			self::replaceJC($widgetJC, $searchAndReplace);

			self::saveConfig($widgetJC, $widgetId);
			if ($reload) JeedomConnect::checkAllEquimentsAndUpdateConfig($widgetId);
			return;
		} catch (Exception $e) {
			JCLog::error('replaceTextConfig - ' . $e->getMessage());
		}
	}

	public static function replaceJC(&$array, $replaces) {
		foreach ($array as $k => $v) {
			$new_k = self::replaceJC_word($k, $replaces);
			if (is_array($v)) {
				self::replaceJC($v, $replaces);
			} else {
				$v = self::replaceJC_word($v, $replaces);
			}
			$array[$new_k] = $v;
			if ($new_k != $k) {
				unset($array[$k]);
			}
		}
	}

	public static function replaceJC_word($word, $replaces) {
		if (array_key_exists($word, $replaces)) {
			$word = str_replace($word, $replaces[$word], $word);
		}
		return $word;
	}



	public static function checkCmdSetupInWidgets() {
		//get all widgets saved in DB
		$widgetsDb = self::getWidgets();

		// get setup of every type of widget
		$widgetParam = JeedomConnect::getWidgetParam(false);

		$cmdArrayError = array();
		$cmdArrayWarning = array();
		$roomArrayError = array();
		// JCLog::debug(" all widget DB => " . json_encode($widgetsDb));
		foreach ($widgetsDb as $item) {
			$widget = $item['widgetJC'];

			$widgetType = ($widget['type'] == 'component') ? $widget['component'] : $widget['type'];
			$config = $widgetParam[$widgetType];
			foreach ($config['options'] as $option) {
				// will check only the cmd data
				if (!in_array($option['category'], array("cmd", "cmdList"))) {
					continue;
				}

				if ($option['category'] == "cmd") {
					if (array_key_exists($option['id'], $widget)) {
						$cmdWidgetId = $widget[$option['id']]['id'] ?: null;
						$cmdStatus = self::isCmd($cmdWidgetId, $widget['id']);
						if ($cmdStatus == -1 && !in_array($widget['id'], $cmdArrayError)) {
							$cmdArrayError[] = $widget['id'];
						} elseif ($cmdStatus == -2 && !in_array($widget['id'], $cmdArrayWarning)) {
							$cmdArrayWarning[] = $widget['id'];
						}
					}
				} elseif ($option['category'] == "cmdList") {
					if (array_key_exists('actions', $widget)) {
						foreach ($widget['actions'] as $action) {
							$cmdWidgetId = $action['id'] ?: null;
							$cmdStatus = self::isCmd($cmdWidgetId, $widget['id']);
							if ($cmdStatus == -1 && !in_array($widget['id'], $cmdArrayError)) {
								$cmdArrayError[] = $widget['id'];
							} elseif ($cmdStatus == -2 && !in_array($widget['id'], $cmdArrayWarning)) {
								$cmdArrayWarning[] = $widget['id'];
							}
						}
					}
				}
			}

			// checking room
			if (key_exists('room', $widget) && $widget['room'] != 'global' && $widget['type'] != 'component') {
				$obj = jeeObject::byId($widget['room']);
				// JCLog::debug("checking room => " . $widget['room'] . " // result :" . json_encode($obj));
				if ($obj == '') $roomArrayError[] = $widget['id'];
			}

			// checking moreInfos
			if (key_exists('moreInfos', $widget)) {
				foreach ($widget['moreInfos'] as $info) {
					$cmdWidgetId = $info['id'] ?: null;
					$cmdStatus = self::isCmd($cmdWidgetId, $widget['id']);
					if ($cmdStatus == -1 && !in_array($widget['id'], $cmdArrayError)) {
						$cmdArrayError[] = $widget['id'];
					} elseif ($cmdStatus == -2 && !in_array($widget['id'], $cmdArrayWarning)) {
						$cmdArrayWarning[] = $widget['id'];
					}
				}
			}
		}

		// JCLog::debug(' ## all errors :    ' . json_encode($cmdArrayError));
		return array($cmdArrayError, $cmdArrayWarning, $roomArrayError);
	}


	/**
	 * Check if a cmd exist and/or if the equipment linked is enabled
	 *
	 * @param int $id
	 * @return boolean  -1 if cmd does not exist, -2 is equipment does not exist or is disable, 0 otherwise
	 */
	public static function isCmd($id, $widgetId = null) {

		if (!is_null($id)) {
			$widget = is_null($widgetId) ? '' : ' on widget ' . $widgetId;

			/** @var cmd $cmd */
			$cmd = cmd::byId($id);
			if (!is_object($cmd)) {
				JCLog::warning('cmdId [' . $id . '] does not exist' . $widget);
				return -1;
			}
			/** @var eqLogic $eqLogic  */
			$eqLogic = $cmd->getEqLogic();
			if (!is_object($eqLogic)) {
				JCLog::warning('equipment of cmdId [' . $id . '] does not exist' . $widget);
				return -2;
			}
			if (!$eqLogic->getIsEnable()) {
				JCLog::warning('equipment hosting cmd "' . $cmd->getHumanName() . '" [' . $id . '] is disable' . $widget);
				return -2;
			}
		}
		return 0;
	}


	/**
	 * function to migration DB item from string(json) to real json 
	 * without img element (beta 1.7.1)
	 *
	 * @return void
	 */
	public static function migrateWidgetsConfig() {
		$allWidgetsDb = self::getWidgets('all', true, false, false);

		foreach ($allWidgetsDb as $widget) {

			JCLog::debug('will migrate widget =>' . json_encode($widget));
			JeedomConnectWidget::updateWidgetConfig(json_decode($widget['widgetJC']['widgetJC'], true), false);
		}

		config::save('migration::widgetsConfig', 'done', 'JeedomConnect');
	}
}
