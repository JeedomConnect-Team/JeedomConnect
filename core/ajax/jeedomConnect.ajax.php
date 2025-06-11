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

try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
	require_once dirname(__FILE__) . '/../class/JeedomConnect.class.php';
	include_file('core', 'authentification', 'php');

	/**************************************************************/
	/********************* USER ONLY PART *************************/
	/**************************************************************/

	if (!isConnect()) {
		throw new Exception(__('401 - Accès non autorisé', __FILE__));
	}

	if (init('action') == 'getDefaultPosition') {

		list($lng, $lat) = JeedomConnectUtils::getJcCoordinates();
		list($lngDefault, $latDefault) = JeedomConnectUtils::getDefaultCoordinates();

		$defaultZoom = (($lat . $lng) == ($latDefault . $lngDefault)) ? 'Autour Paris' : 'Autour de mon Jeedom';

		ajax::success(array('lng' => $lng, 'lat' => $lat, 'defaultText' => $defaultZoom));
	}

	if (init('action') == 'getAllPositions') {
		$result = array();
		$id = init('id', 'all');

		$user = user::byId($_SESSION['user']->getId());
		if (!is_object($user)) ajax::error('unable to find user details');

		if ($id == 'all') {
			$eqLogics = JeedomConnect::getAllJCequipment();
		} else {
			/** @var cmd $cmdTmp */
			$cmdTmp = cmd::byId($id);
			if (!is_object($cmdTmp)) return;
			$eqTmp = eqLogic::byId($cmdTmp->getEqLogic_id());
			$eqLogics = array($eqTmp);
		}

		/** @var JeedomConnect $eqLogic */
		foreach ($eqLogics as $eqLogic) {
			if ($eqLogic->getConfiguration('displayPosition', 0) == 0) continue;

			/** @var cmd $cmd */
			$cmd = $eqLogic->getCmd(null, 'position');
			if (!is_object($cmd)) continue;
			// JCLog::debug("position cmd/id => " . $cmd->getId());
			if (!$cmd->hasRight($user)) {
				JCLog::warning('limited user try to access equipment position');
				continue;
			}

			/** @var string $position */
			$position = $cmd->execCmd();
			if ($position == "") continue;

			$data = explode(',', $position);
			if (count($data) < 2) continue;
			$cmdDistance = cmd::byEqLogicIdAndLogicalId($eqLogic->getId(),  'distance');
			$distance = is_object($cmdDistance) ? number_format(floatval($cmdDistance->execCmd()), 0, ',', ' ') . ' ' . $cmdDistance->getUnite() : '';
			$img = $eqLogic->getConfiguration('customImg', 'plugins/JeedomConnect/data/img/pin.webp');
			$infoImg = getimagesize('/var/www/html/' . $img);
			$result[] = array(
				'id' => $cmd->getId(),
				'name' => $eqLogic->getName(),
				'eqId' => $eqLogic->getId(),
				'lat' => round($data[0], 6),
				'lng' => round($data[1], 6),
				'lastSeen' => $cmd->getCollectDate(),
				'icon' => $img,
				'infoImg' => $infoImg,
				'distance' => $distance
			);
		}
		ajax::success($result);
	}

	/**************************************************************/
	/*********************** ADMIN PART ***************************/
	/**************************************************************/


	if (!isConnect('admin')) {
		throw new Exception(__('401 - Accès non autorisé', __FILE__));
	}

	if (init('action') == 'getCmdsForWidgetType') {
		$widget_type = init('widget_type');
		$eqLogicId = !is_numeric(init('eqLogic_Id')) ? null : init('eqLogic_Id');
		JCLog::debug('getCmdsForWidgetType:' . $widget_type . ' - for eqLogicId : ' . $eqLogicId);

		$results = JeedomConnectUtils::generateWidgetWithGenType($widget_type, $eqLogicId);
		JCLog::debug('final generic result:' . count($results) . '-' . json_encode($results));

		ajax::success($results);
	}

	if (init('action') == 'saveWidgetConfig') {
		JCLog::debug('-- manage fx ajax saveWidgetConfig for id >' . init('eqId') . '<');

		$id = init('eqId') ?: JeedomConnectWidget::incrementIndex();

		$jcTemp = json_decode(init('widgetJC'), true);
		// JCLog::debug('-- manage fx ajax saveWidgetConfig data >' . json_encode($jcTemp) . '<');
		$jcTemp['id'] = intval($id);

		JeedomConnectWidget::saveConfig($jcTemp, $id);

		if (!is_null(init('eqId'))  && init('eqId') != '') {
			/** @var JeedomConnect $eqLogic */
			foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
				$eqLogic->checkEqAndUpdateConfig(init('eqId'));
			}
		}

		ajax::success(array('id' => $id));
	}

	if (init('action') == 'generateFile') {
		switch (init('type')) {
			case 'exportEqConf':
				$content = JeedomConnect::getWidgetConfigContent(init('apiKey'));
				$content = JeedomConnectUtils::addTypeInPayload($content, 'JC_EXPORT_EQLOGIC_CONFIG');
				break;

			case 'exportWidgets':
				JCLog::debug('ajax -- fx exportWidgets');
				$content = JeedomConnectWidget::exportWidgetConf();
				break;

			case 'exportCustomData':
				JCLog::debug('ajax -- fx exportCustomData');
				$content = JeedomConnectWidget::exportWidgetCustomConf();
				break;

			default:
				ajax::error('Pas de type d\'export !');
		}

		ajax::success($content);
	}

	if (init('action') == 'uploadWidgets') {
		JCLog::debug('ajax -- fx uploadWidgets');

		$allConf = json_decode(init('data'), true);
		// JCLog::debug('content file ==> ' . init('data'));
		$type = $allConf['type'] ?? null;
		// JCLog::debug('Type ==> ' . $type);

		$import = init('import');
		// JCLog::debug('Import ==> ' . $import);

		switch ($type) {
			case 'JC_EXPORT_EQLOGIC_CONFIG':
				if ($import != 'eqConfig') {
					throw new Exception("Mauvais fichier de configuration importé");
				}
				// JCLog::debug('Starting JC_EXPORT_EQLOGIC_CONFIG import ');
				$apiKey = init('apiKey');

				$configJson = $allConf['payload'];
				/** @var JeedomConnect */
				$eqLogic = eqLogic::byLogicalId($apiKey, 'JeedomConnect');
				if (!is_object($eqLogic) or $configJson == null) {
					throw new Exception("Pas d'équipement trouvé");
				} else {
					$eqLogic->saveConfig($configJson);
					$eqLogic->setConfiguration('configVersion', $configJson->payload->configVersion);
					$eqLogic->save(true);

					$eqLogic->getConfig(true, true);
					$eqLogic->cleanCustomData();
				}
				break;

			case 'JC_EXPORT_WIDGETS_DATA':
			case 'JC_EXPORT_CUSTOM_DATA':
				if ($import != 'genericConfig') {
					throw new Exception("Mauvais fichier de configuration importé");
				}
				JeedomConnectWidget::uploadWidgetConf($allConf['payload']);
				break;

			default:
				throw new Exception("Type d'import inconnu");
		}

		ajax::success("Import avec succès");
	}


	if (init('action') == 'reinitBin') {

		JeedomConnect::install_notif();
		ajax::success();
	}

	if (init('action') == 'reinitEquipement') {
		$nbEq = 0;
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$eqLogic->resetConfigFile();
			$nbEq++;
		}

		ajax::success(array('eqLogic' => $nbEq));
	}

	if (init('action') == 'getWidgetMass') {
		$ids = init('id') ?? 'all';
		$allWidgets = JeedomConnectWidget::getWidgets($ids);

		$jsonConfig = json_decode(file_get_contents(__DIR__ . '/../config/widgetsConfig.json'), true);
		$widgetArrayConfig = array();
		foreach (array_merge($jsonConfig['widgets'], $jsonConfig['components']) as $config) {
			$widgetArrayConfig[$config['type']] =  $config;
		}


		$widgetsByEquipment = array();
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$item = array();

			$widgetForEq = $eqLogic->getWidgetId();
			$item['eqId'] = $eqLogic->getId();
			$item['eqName'] = $eqLogic->getName();
			$item['widgets'] = $widgetForEq;

			array_push($widgetsByEquipment, $item);
		}
		JCLog::debug('ajax -- widgetsByEquipment => ' . json_encode($widgetsByEquipment));

		$html = '';
		foreach ($allWidgets as $widget) {
			$widgetJC = $widget['widgetJC'];
			$itemType = ($widget['type'] == 'component') ? 'component' : 'widget';
			$html .= ($ids == 'all') ? '<tr class="tr_object" data-widget_id="' . $widget['id'] . '" data-item_type="' . $itemType . '">' : '';
			$html .= '<td style="width:40px;"><span class="label label-info objectAttr bt_openWidget" data-l1key="widgetId" style="cursor: pointer !important;">' . $widget['id'] . '</span></td>';

			// **********    TYPE    ****************
			$tmpType = ($widget['type'] == 'component') ? $widget['component'] : $widget['type'];
			$html .= '<td style="width:40px;"><span class="label objectAttr" data-l1key="type" data-l2key="' . $tmpType . '">' . str_replace('de génériques ', '',  $widgetArrayConfig[$tmpType]['name']) . '</span></td>';
			// $html .= '<td style="width:40px;"><span class="label objectAttr" data-l1key="type" data-l2key="' . $tmpType . '">' . str_replace('de génériques ', '',  $widgetArrayConfig[$widget['type']]['name']) . '</span></td>';


			// **********    ROOM    ****************
			$html .= '<td >';
			$html .= '<select style="width:150px;" class="objectAttr"  data-l1key="roomId">';
			$html .= '<option value="none">Aucun</option>';

			foreach ((jeeObject::buildTree(null, false)) as $object) {
				$select = ($widget['roomId'] == $object->getId()) ? 'selected' : '';
				$html .= ' <option value="' . $object->getId() . '" ' . $select . '>' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
			}

			$html .= '</select>';
			$html .= '</td>';
			// ****************************************

			$html .= '<td><input type="text" class="objectAttr" data-l1key="name" value="' . cmd::cmdToHumanReadable($widget['name']) . '"  style="width:250px;"/></td>';


			// **********   SUBTITLE    ****************
			$sub =  isset($widgetJC['subtitle']) ? cmd::cmdToHumanReadable($widgetJC['subtitle'])  : '';
			$html .= '<td><textarea  type="text" class="objectAttr"  data-l1key="subtitle" style="width:500px;">' . $sub . '</textarea>';

			// **********  END SUBTITLE ****************

			if ($widget['enable']) {
				$html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" checked data-l1key="enable" /></td>';
			} else {
				$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="enable" /></td>';
			}

			// **********    DISPLAY    ****************
			$dataDisplayMode = array();
			foreach ($widgetArrayConfig[$widget['type']]['options'] as $opt) {
				if ($opt['id'] != 'display') continue;
				$dataDisplayMode = $opt['choices'];
				break;
			}
			$html .= '<td>';
			$html .= '<select class="objectAttr"  data-l1key="display" style="width:130px;">';
			$html .= '<option value="none">Aucun</option>';

			foreach ($dataDisplayMode as $display) {
				$select = (isset($widgetJC['display']) && $widgetJC['display'] == $display['id']) ? 'selected' : '';
				$html .= ' <option value="' . $display['id'] . '" ' . $select . '>' . $display['name'] . '</option>';
			}

			$html .= '</select>';
			$html .= '</td>';
			// ************ END DISPLAY **************


			// **********    HIDE OTIONS    ****************
			// $hideOptions = array();
			// foreach ($widgetArrayConfig[$widget['type']]['options'] as $opt) {
			// 	if ($opt['id'] != 'hideItem') continue;

			// 	foreach ($opt['choices'] as $choice) {
			// 		$hideOptions[] = $choice['id'];
			// 	}
			// 	break;
			// }

			// if (in_array('hideTitle', $hideOptions)) {
			// 	if (isset($widgetJC['hideTitle']) && $widgetJC['hideTitle']) {
			// 		$html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" checked data-l1key="hideTitle" /></td>';
			// 	} else {
			// 		$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="hideTitle" /></td>';
			// 	}
			// } else {
			// 	$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="hideTitle" disabled /></td>';
			// }

			// if (in_array('hideSubTitle', $hideOptions)) {
			// 	if (isset($widgetJC['hideSubTitle']) && $widgetJC['hideSubTitle']) {
			// 		$html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" checked data-l1key="hideSubTitle"  /></td>';
			// 	} else {
			// 		$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="hideSubTitle" /></td>';
			// 	}
			// } else {
			// 	$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="hideSubTitle" disabled /></td>';
			// }

			// if (in_array('hideStatus', $hideOptions)) {
			// 	if (isset($widgetJC['hideStatus']) && $widgetJC['hideStatus']) {
			// 		$html .= '<td align="center" style="max-width:65px;"><input type="checkbox" class="objectAttr" checked data-l1key="hideStatus" /></td>';
			// 	} else {
			// 		$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="hideStatus" /></td>';
			// 	}
			// } else {
			// 	$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="hideStatus" disabled /></td>';
			// }

			// if (in_array('hideIcon', $hideOptions)) {
			// 	if (isset($widgetJC['hideIcon']) && $widgetJC['hideIcon']) {
			// 		$html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" checked data-l1key="hideIcon" /></td>';
			// 	} else {
			// 		$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="hideIcon" /></td>';
			// 	}
			// } else {
			// 	$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="hideIcon" disabled /></td>';
			// }
			// **********    END HIDE OTIONS    ****************



			if (isset($widgetJC['blockDetail']) && $widgetJC['blockDetail']) {
				$html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" checked data-l1key="blockDetail" /></td>';
			} else {
				$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="blockDetail" /></td>';
			}

			if (!JeedomConnectUtils::hasObjectId($widgetArrayConfig[$widget['type']]['options'], 'hideControlDevice')) {
				$html .= '<td></td>';
			} else {
				if (isset($widgetJC['hideControlDevice']) && $widgetJC['hideControlDevice']) {
					$html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" checked data-l1key="hideControlDevice" /></td>';
				} else {
					$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="hideControlDevice" /></td>';
				}
			}

			if (!JeedomConnectUtils::hasObjectId($widgetArrayConfig[$widget['type']]['options'], 'allowOnUnlock')) {
				$html .= '<td></td>';
			} else {
				if (isset($widgetJC['allowOnUnlock']) && $widgetJC['allowOnUnlock']) {
					$html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" checked data-l1key="allowOnUnlock" /></td>';
				} else {
					$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="objectAttr" data-l1key="allowOnUnlock" /></td>';
				}
			}


			//**************  EQUIPEMENT INCLUSION **********************/
			$nb = 0;
			$names = '';
			$label = ' labelObjectHuman';
			foreach ($widgetsByEquipment as $item) {

				if (in_array($widget['id'], $item['widgets'])) {
					$nb++;
					$names .= ($names == '') ? $item['eqName'] : ', ' . $item['eqName'];
					$label = ' label-success';
				}
			}
			$titleEqInclusion = $names != '' ? 'title="' . $names . '"' : '';
			$html .= '<td style="width:60px;" class=""><span class="label ' . $label . ' nbEquipIncluded" data-title="' . $names . '" ' . $titleEqInclusion . '>' . $nb . '</span></td>';

			//************************************/

			$html .= '<td align="center" style="width:75px;"><input type="checkbox" class="removeWidget"/></td>';

			$html .= ($ids == 'all') ? '</tr>' : '';
		}

		ajax::success($html);
	}

	if (init('action') == 'removeEquipmentConfig') {

		$equipmentReceived = init('eqIds');
		foreach ($equipmentReceived as $id) {
			$eqLogic = eqLogic::byId($id);
			JCLog::debug("removing equipment " . $eqLogic->getName() . " [" . $id . "]");
			if (is_object($eqLogic)) $eqLogic->remove();
		}
		ajax::success();
	}
	if (init('action') == 'updateEquipmentMass') {

		$equipmentReceived = init('equipementsObj');

		$allNotif = config::byKey('notifAll', 'JeedomConnect', array());
		foreach ($equipmentReceived as $eqData) {
			// JCLog::debug("data eq received => " . json_encode($eqData));

			/** @var JeedomConnect $eqLogic */
			$eqLogic = eqLogic::byId($eqData['eqId']);
			if (!is_object($eqLogic)) break;

			$eqLogic->setName($eqData['name']);
			$eqLogic->setObject_id($eqData['roomId']);
			$eqLogic->setIsEnable($eqData['isEnable']);

			$eqLogic->setConfiguration('polling', $eqData['polling']);
			$eqLogic->setConfiguration('useWs', $eqData['useWs']);
			$eqLogic->setConfiguration('userId', $eqData['userId']);
			$eqLogic->setConfiguration('scenariosEnabled', $eqData['scenariosEnabled']);
			$eqLogic->setConfiguration('timelineEnabled', $eqData['timelineEnabled']);
			$eqLogic->setConfiguration('webviewEnabled', $eqData['webviewEnabled']);
			$eqLogic->setConfiguration('automationsEnabled', $eqData['automationsEnabled']);
			$eqLogic->setConfiguration('addAltitude', $eqData['addAltitude']);
			$eqLogic->setConfiguration('displayPosition', $eqData['displayPosition']);
			$eqLogic->setConfiguration('hideBattery', $eqData['hideBattery']);

			$eqLogic->save();

			foreach ($eqData['NotifAll'] as $id => $value) {
				$id = strval($id);
				if ($value == 0) {
					$allNotif = array_diff($allNotif, array($id));
				} elseif ($value == 1) {
					if (!in_array($id, $allNotif)) $allNotif[] = $id;
				}
			}
		}

		config::save('notifAll', json_encode($allNotif), 'JeedomConnect');

		ajax::success();
	}

	if (init('action') == 'updateWidgetMass') {

		$widgetReceived = init('widgetsObj');

		foreach ($widgetReceived as $widgetData) {
			$widgetJC = JeedomConnectWidget::getConfiguration($widgetData['widgetId']);
			JCLog::debug('massUpdate - widget [' . $widgetData['widgetId'] . '] will be updated -- current data ' . json_encode($widgetJC));

			// $widgetJC = $existingWidget['widgetJC'];

			$widgetJC['enable'] = boolval($widgetData['enable']);
			$widgetJC['name'] = cmd::humanReadableToCmd($widgetData['name']);
			$widgetJC['subtitle'] = cmd::humanReadableToCmd($widgetData['subtitle']);

			$widgetJC['room'] = intval($widgetData['roomId']);

			$widgetJC['display'] = $widgetData['display'];

			$widgetJC['blockDetail'] = boolval($widgetData['blockDetail']);
			if (isset($widgetData['hideControlDevice'])) $widgetJC['hideControlDevice'] = boolval($widgetData['hideControlDevice']);
			if (isset($widgetData['allowOnUnlock'])) $widgetJC['allowOnUnlock'] = boolval($widgetData['allowOnUnlock']);

			JeedomConnectWidget::saveConfig($widgetJC, $widgetData['widgetId']);
		}

		ajax::success();
	}

	if (init('action') == 'countWigdetUsage') {
		$data = JeedomConnectWidget::countWidgetByEq();
		ajax::success($data);
	}

	if (init('action') == 'removeWidgetConfig') {
		$allConfig = (init('all') !== null) && init('all');

		if ($allConfig) {
			JCLog::debug('-- manage fx ajax removeWidgetConfig -- ALL widgets will be removed');
			$allWidgets = JeedomConnectWidget::getAllConfigurations();
			$nb = 0;
			foreach ($allWidgets as $widget) {
				JeedomConnectWidget::removeWidgetConf($widget['key']);
				$nb++;
			}
			JCLog::debug('-- manage fx ajax removeWidgetConfig -- widget index reinit');
			JeedomConnectWidget::removeWidgetConf('index::max');

			$nbEq = 0;
			/** @var JeedomConnect $eqLogic */
			foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
				$eqLogic->resetConfigFile();
				$nbEq++;
			}

			ajax::success(array('widget' => $nb, 'eqLogic' => $nbEq));
		} else {
			JCLog::debug('-- manage fx ajax removeWidgetConfig for id >' . init('eqId') . '<');
			JeedomConnectWidget::removeWidget(init('eqId'));
			ajax::success();
		}
	}

	if (init('action') == 'getWidgetConfigAll') {
		JCLog::debug('-- manage fx ajax getWidgetConfigAll ~~ retrieve config for ALL widgets');
		$widgets = JeedomConnectWidget::getWidgets('all', false, true);

		if ($widgets == '') {
			JCLog::warning('no widgets found');
			//ajax::error('Erreur - pas d\'équipement trouvé');
		}

		$list = array();
		$options = '';
		foreach ((jeeObject::buildTree(null, false)) as $object) {
			$options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
			array_push($list, array("id" => intval($object->getId()), "name" => $object->getName(), "space" =>  str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber'))));
		}

		JCLog::debug('getWidgetConfigAll ~~ result : ' . json_encode($widgets));

		ajax::success(array('widgets' => $widgets, 'room_details' => $list, 'room_options' => $options));
	}

	if (init('action') == 'getWidgetExistance') {
		$myId = init('id');
		$arrayName = array();
		$arrayNameCusto = array();
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			if ($eqLogic->isWidgetIncluded($myId)) {
				JCLog::trace($myId . ' exist in [' . $eqLogic->getName() . ']');
				$arrayName[] = $eqLogic->getName();

				$all_widgetIds = $eqLogic->getWidgetWidgetId($myId);
				JCLog::debug('looking for widgetIds => ' .  json_encode($all_widgetIds));
				$custo = $eqLogic->getCustomWidget();
				$all_custo_keys = array_keys($custo['widgets']);
				JCLog::debug('$custo : ' .  json_encode($all_custo_keys));
				foreach ($all_widgetIds as $search_custo_id) {
					if (in_array($search_custo_id, $all_custo_keys) && !in_array($eqLogic->getName(), $arrayNameCusto)) {
						JCLog::debug(' ***** ' . $myId . ' custo exist for [' . $eqLogic->getName() . ']');
						$arrayNameCusto[] = $eqLogic->getName();
					}
				}
			} else {
				JCLog::trace($myId . ' does NOT exist in [' . $eqLogic->getName() . ']');
			}
		}

		JCLog::trace('ajax -- all name final -- ' . json_encode($arrayName));
		ajax::success(array('names' => $arrayName, 'custo' => $arrayNameCusto));
	}

	if (init('action') == 'getInstallDetails') {
		ajax::success(JeedomConnectUtils::getInstallDetails());
	}

	if (init('action') == 'createCommunityPost') {

		$url = JeedomConnectUtils::getCommunityUrl();
		// JCLog::debug('url => ' . $url);

		ajax::success(array('url' => $url));
	}

	if (init('action') == 'humanReadableToCmd') {

		$stringWithCmdId = cmd::humanReadableToCmd(init('human'));
		if (strcmp($stringWithCmdId, init('human')) == 0) {
			JCLog::debug('ajax -- fx humanReadableToCmd -- string is the same with humanCmdString and cmdId => ' . $stringWithCmdId);
			// ajax::error('La commande n\'existe pas');
		}
		ajax::success($stringWithCmdId);
	}

	if (init('action') == 'cmdToHumanReadable') {

		$cmdIdToHuman = cmd::cmdToHumanReadable(init('strWithCmdId'));
		if (strcmp($cmdIdToHuman, init('strWithCmdId')) == 0) {
			JCLog::debug('ajax -- fx cmdToHumanReadable -- string is the same with cmdId and no humanCmdString => ' . $cmdIdToHuman);
			// ajax::error('La commande n\'existe pas');
		}
		ajax::success($cmdIdToHuman);
	}

	if (init('action') == 'getEquipments') {

		$result = array();
		/** @var JeedomConnect $eqLogic */
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$apiKey = $eqLogic->getConfiguration('apiKey');
			$name = $eqLogic->getName();
			$eqId = $eqLogic->getId();
			array_push($result, array('apiKey' => $apiKey, 'name' => $name, 'eqId' => $eqId));
		}
		ajax::success($result);
	}

	if (init('action') == 'copyConfig') {
		$copy = JeedomConnectUtils::copyConfig(init('from'), init('to', array()), init('withCustom', false), false);
		ajax::success($copy);
	}

	if (init('action') == 'saveConfig') {
		$config = init('config');
		$apiKey = init('apiKey');

		$configJson = json_decode($config);
		/** @var JeedomConnect $eqLogic */
		$eqLogic = eqLogic::byLogicalId($apiKey, 'JeedomConnect');
		if (!is_object($eqLogic) or $configJson == null) {
			ajax::error('Erreur');
		} else {
			$eqLogic->saveConfig($configJson);
			$eqLogic->setConfiguration('configVersion', $configJson->payload->configVersion);
			$eqLogic->save(true);

			$eqLogic->getConfig(true, true);
			$eqLogic->cleanCustomData();
			ajax::success();
		}
	}

	if (init('action') == 'getConfig') {
		$apiKey = init('apiKey');
		/** @var JeedomConnect $eqLogic */
		$eqLogic = eqLogic::byLogicalId($apiKey, 'JeedomConnect');
		$allConfig = (init('all') !== null) && init('all');
		$saveGenerated = (init('all') !== null) && init('all');
		if (!is_object($eqLogic)) {
			ajax::error('Erreur - no equipment found');
		} else {
			//$eqLogic->updateConfig();
			$configJson = $eqLogic->getConfig($allConfig, $saveGenerated);
			ajax::success($configJson);
		}
	}

	if (init('action') == 'getNotifs') {
		$apiKey = init('apiKey');
		/** @var JeedomConnect $eqLogic */
		$eqLogic = eqLogic::byLogicalId($apiKey, 'JeedomConnect');
		if (!is_object($eqLogic)) {
			ajax::error('Error - no equipment found');
		} else {
			$notifs = $eqLogic->getNotifs();
			ajax::success($notifs);
		}
	}

	if (init('action') == 'saveNotifs') {
		$config = init('config');
		$apiKey = init('apiKey');

		$configJson = json_decode($config, true);
		/** @var JeedomConnect $eqLogic */
		$eqLogic = eqLogic::byLogicalId($apiKey, 'JeedomConnect');
		if (!is_object($eqLogic) or $configJson == null) {
			ajax::error('Error - no equipment found');
		} else {
			$eqLogic->saveNotifs($configJson);
			ajax::success();
		}
	}

	if (init('action') == 'removeNotifAll') {
		$key = init('key');
		// JCLog::debug('Trying to remove all cmd with logicalId : ' . $key);
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$cmd = cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), $key);
			if (is_object($cmd)) {
				// JCLog::debug('Removed for eqLogic : ' . $eqLogic->getName());
				$cmd->remove();
			}
		}

		config::remove($key, 'JeedomConnect');
		ajax::success();
	}

	if (init('action') == 'editNotifAll') {
		$key = init('key');
		// $oldName = init('oldName');
		$newName = init('newName');
		// JCLog::debug('Trying to remove all cmd with logicalId : ' . $key);
		foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			$cmd = cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), $key);
			if (is_object($cmd)) {
				// JCLog::debug('Removed for eqLogic : ' . $eqLogic->getName());
				$cmd->setName($newName);
				$cmd->save();
			}
		}

		ajax::success();
	}

	if (init('action') == 'saveNotifAll') {
		$cmdList = init('cmdList', array());
		$key = init('key');
		$name = init('name');
		$value = array("name" => $name, "cmd" => $cmdList);

		JCLog::trace('saveNotifAll - info received : ' . json_encode($value) . ']');
		config::save($key, json_encode($value), 'JeedomConnect');

		$notifConf = array(array(
			"logicalId" => $key,
			"name" => $name,
			"type" => "action",
			"subtype" => "message"
		));
		try {
			foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
				$eqLogic->createCommandsFromConfigFile($notifConf, null);
			}
		} catch (Exception $e) {
			JCLog::warning("Exception while creating cmd on saveNotifAll => " . $e->getMessage());
			ajax::error('Création de la commande en erreur');
		}

		ajax::success();
	}

	if (init('action') == 'removeDevice') {
		$id = init('id');
		/** @var JeedomConnect $eqLogic */
		$eqLogic = eqLogic::byId($id);
		$eqLogic->removeDevice();
		ajax::success();
	}

	if (init('action') == 'getAllJeedomData') {

		// $result = apiHelper::getFullJeedomData();
		$result = array();
		foreach (cmd::all() as $item) {
			$array = utils::o2a($item);
			$cmd = array(
				'id' => $array['id'],
				'name' => $array['name'],
				'humanName' => $item->getHumanName(),
				'type' => $array['type'],
				'subType' => $array['subType'],
				'eqLogic_id' => $array['eqLogic_id'],
				'unite' => $array['unite'],
				'isHistorized' => $array['isHistorized'],
				'configuration' => $array['configuration'],
				'shortcutAllowed' => ($array['configuration']['actionConfirm'] ?? "0") === "0",
			);
			array_push($result, $cmd);
		}
		ajax::success($result);
	}

	if (init('action') == 'getCmd') {
		$id = init('id');
		if ($id == '') throw new Exception("id est obligatoire");
		/** @var cmd $cmd */
		$cmd = (preg_match("/^\d+$/", $id)) ? cmd::byId($id) : cmd::byString($id);
		if (!is_object($cmd)) {
			throw new Exception(__('Commande inconnue : ', __FILE__) . $id);
		}
		ajax::success(array(
			'id' => $cmd->getId(),
			'type' => $cmd->getType(),
			'subType' => $cmd->getSubType(),
			'humanName' => $cmd->getHumanName(),
			'name' => $cmd->getName(),
			'minValue' => $cmd->getConfiguration('minValue'),
			'maxValue' => $cmd->getConfiguration('maxValue'),
			'unit' => $cmd->getUnite(),
			'value' => $cmd->getValue(),
			'icon' => $cmd->getDisplay('icon'),
			'display' => $cmd->getDisplay()
		));
	}

	if (init('action') == 'generateQRcode') {
		$id = init('id', 'all');

		if ($id == 'all') {
			// JCLog::debug('QRCode regen all');
			$eqLogics = JeedomConnect::getAllJCequipment();
		} else {
			// JCLog::debug('QRCode regen unit for id=' . $id);
			$eqTmp = eqLogic::byId($id);
			if (!is_object($eqTmp)) ajax::error('Error - no equipment found');
			$eqLogics = array($eqTmp);
		}

		/** @var JeedomConnect $eqLogic */
		foreach ($eqLogics as $eqLogic) {
			$eqLogic->generateQRCode();
		}
		ajax::success();
	}

	if (init('action') == 'incrementWarning') {
		config::save('displayWarning', date('Y-m-d'), 'JeedomConnect');

		ajax::success();
	}


	if (init('action') == 'updateEqWidgetMaps') {
		/** @var eqLogic $eqLogic */
		$eqLogic = eqLogic::byLogicalId('jcmapwidget', 'JeedomConnect');
		if (!is_object($eqLogic)) ajax::error('Error - no equipment found');

		$data = init('data');

		switch (init('type')) {
			case 'isVisible':
				$eqLogic->setIsVisible(($data == "true") ? 1 : 0);
				break;

			case 'object_id':
				$eqLogic->setObject_id($data);
				break;

			default:
				# code...
				break;
		}

		$eqLogic->save();
		// JCLog::debug('eqId received =>' . json_encode(init('eqId')));
		ajax::success();
	}

	if (init('action') == 'createOrUpdateCmdGeo') {
		$data = init('data');
		$type = init('type');
		$eqId = init('eqId');

		/** @var JeedomConnect $eqLogic */
		$eqLogic = eqLogic::byId($eqId);
		if (!is_object($eqLogic)) {
			$errEq = 'createOrUpdateCmdGeo ==> no eqLogic found [' . $eqId . ']';
			JCLog::warning($errEq);
			ajax::error($errEq);
		}

		switch ($type) {
			case 'createOrUpdate':
				/** @var cmd $cmd */
				$cmd = cmd::byId($data['id']);
				if (!is_object($cmd)) {
					$cmd = new Cmd();
				}

				$cmd->setName($data['name']);
				$cmd->setType('info');
				$cmd->setSubType('binary');
				$cmd->setConfiguration('latitude', doubleval($data['lat']));
				$cmd->setConfiguration('longitude', doubleval($data['lng']));
				$cmd->setConfiguration('radius', doubleval($data['radius']));
				$cmd->setConfiguration('parent', $data['parent'] ?: $data['id']);
				$cmd->setEqLogic_id($eqId);
				try {
					$cmd->save();
					$cmd->setLogicalId('geofence_' . $cmd->getId());
					$cmd->save();
				} catch (Exception $err) {
					JCLog::error($err->getMessage());
					ajax::error('Affectation impossible');
				}
				$result = array('id' => $cmd->getId());
				break;

			case 'remove':
				$cmd = cmd::byId($data['id']);
				if (!is_object($cmd)) {
					$err = "no cmd found with id=[" . $data['id'] . "]";
					JCLog::error($err);
					break;
				}
				$cmd->remove();
				$result = '';
				break;
		}

		ajax::success($result);
	}

	if (init('action') == 'createOrUpdateConfigGeo') {
		$id = init('id');
		$data = init('data');
		$type = init('type');

		switch ($type) {
			case 'createOrUpdate':
				config::save('geofence::' . $id, $data, 'JeedomConnect');
				break;

			case 'remove':
				config::remove('geofence::' . $id, 'JeedomConnect');
				break;
		}
		ajax::success();
	}

	if (init('action') == 'updateCmdParent') {
		$cmdId = init('id');
		$parentId = init('parentId');

		$cmd = cmd::byId($cmdId);
		if (!is_object($cmd)) ajax::error('cmd not found');

		$cmd->setConfiguration('parent', $parentId);
		$cmd->save();
		ajax::success();
	}

	if (init('action') == 'getAllGeofences') {
		$result = array();
		$config = array();

		$eqId = init('eqId');
		/** @var JeedomConnect $eqLogic */
		$eqLogic = eqLogic::byId($eqId);
		if (is_object($eqLogic)) {
			// foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
			foreach ($eqLogic->getCmd('info') as $cmd) {
				if (substr($cmd->getLogicalId(), 0, 8) === "geofence") {
					array_push($result, array(
						'id' => $cmd->getId(),
						'name' => $cmd->getName(),
						'radius' => $cmd->getConfiguration('radius'),
						'lat' => round($cmd->getConfiguration('latitude'), 6),
						'lng' => round($cmd->getConfiguration('longitude'), 6),
						'cmdId' => $cmd->getId(),
						'eqId' => $eqLogic->getId(),
						'parent' => $cmd->getConfiguration('parent', null),
					));
				}
			}
		}


		foreach (config::searchKey('geofence::', 'JeedomConnect')  as $conf) {
			array_push($config, $conf['value']);
		}

		ajax::success(array("equipment" => $result, "config" => $config));
	}

	if (init('action') == 'restartDaemon') {

		/** @var plugin $plugin */
		$plugin = plugin::byId('JeedomConnect');
		if (is_object($plugin)) {
			$daemon_info = $plugin->deamon_info();
			if ($daemon_info['state'] == 'ok') {
				message::add('JeedomConnect', 'Redémarrage du démon après sauvegarde de la configuration');
				JCLog::info('DAEMON restart after saving new setup');
				$plugin->deamon_start(true);
			} else {
				JCLog::info('DAEMON not automatically restarted - daemon state KO');
			}
		}
		ajax::success();
	}

	if (init('action') == 'regenerateApiKey') {
		$id = init('eqId');
		$currentApiKey = init('apiKey');
		/** @var JeedomConnect $eqLogic */
		$eqLogic = JeedomConnect::byId($id);
		if (!is_object($eqLogic)) {
			ajax::error('Error - no equipment found');
		} else {
			// generate new apiKey
			$newApiKey = JeedomConnectUtils::generateApiKey();
			JCLog::debug('new api key generated : ' . $newApiKey . ' - previous one was [' . $currentApiKey . ']');

			// save new apiKey
			$eqLogic->setConfiguration('apiKey', $newApiKey);
			$eqLogic->setLogicalId($newApiKey);

			// generate new QR Code
			$eqLogic->generateQRCode();

			//saving all new info
			$eqLogic->save(true);

			// copy & remove config files
			JeedomConnect::copyNotifConfig($currentApiKey, $newApiKey);
			JeedomConnect::copyBackupConfig($currentApiKey, $newApiKey);
			JeedomConnectWidget::copyCustomData($currentApiKey, array($newApiKey), true);
			JeedomConnect::copyConfig($currentApiKey, array($newApiKey));
			JeedomConnect::removeAllData($currentApiKey);

			// add new apikey in conf => used during ping and/or connection
			config::save('newApiKey::' . $currentApiKey, $newApiKey, 'JeedomConnect');

			ajax::success(array('newapikey' => $newApiKey));
		}
	}

	throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
} catch (Exception $e) {
	if (function_exists('displayException')) {
		ajax::error(displayException($e), $e->getCode());
	} else {
		ajax::error($e->getMessage(), $e->getCode());
	}
}
