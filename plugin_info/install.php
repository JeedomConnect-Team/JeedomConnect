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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function JeedomConnect_install() {

  JeedomConnect::displayMessageInfo();

  JeedomConnectUtils::installAndMigration();
  JeedomConnectUtils::addCronItems();
  JeedomConnect::createMapEquipment();

  // default option for configuration page
  config::save('daemonLog', 'parent', 'JeedomConnect');
  config::save('bkpCount', 'all', 'JeedomConnect');
  config::save('jcOrderByDefault', 'object', 'JeedomConnect');
  config::save('withQrCode', '1', 'JeedomConnect');
  config::save('isStrict', '1', 'JeedomConnect');

  if (JeedomConnect::install_notif_info() == 'nok') {
    JCLog::debug('suppression de ' . __DIR__ . '/../resources/sendNotif*');
    array_map('unlink', glob(__DIR__ . '/../resources/sendNotif*'));
    array_map('unlink', glob(__DIR__ . '/../resources/sendNotif/*'));
    JeedomConnect::install_notif();
  }
}

function JeedomConnect_update() {

  /** @var JeedomConnect $eqLogic */
  foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
    $eqLogic->updateConfig();
    $eqLogic->generateNewConfigVersion();
  }

  JeedomConnectUtils::installAndMigration();
  JeedomConnectUtils::addCronItems();
  JeedomConnect::createMapEquipment();

  if (JeedomConnect::install_notif_info() == 'nok') {
    JCLog::debug('suppression de ' . __DIR__ . '/../resources/sendNotif*');
    array_map('unlink', glob(__DIR__ . '/../resources/sendNotif*'));
    array_map('unlink', glob(__DIR__ . '/../resources/sendNotif/*'));
    JeedomConnect::install_notif();
  }


  $wsDir = __DIR__ . '/../resources/JeedomConnectd/websocket_server';
  JCLog::debug('removing websocket_server dir');
  JeedomConnectUtils::delTree($wsDir);

  $dependencyInfo = JeedomConnect::dependancy_info();
  if (!isset($dependencyInfo['state'])) {
    message::add('JeedomConnect', __('Veuilez vérifier les dépendances', __FILE__));
  } elseif ($dependencyInfo['state'] == 'nok') {
    try {
      $plugin = plugin::byId('JeedomConnect');
      $plugin->dependancy_install();
    } catch (\Throwable $th) {
      message::add('JeedomConnect', __('Cette mise à jour nécessite de réinstaller les dépendances même si elles sont marquées comme OK', __FILE__));
    }
  }



  //////// PLEASE KEEP IT AT THE END !! 
  // FORCE save on all equipments to save new cmd
  /** @var JeedomConnect $eqLogic */
  foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
    $eqLogic->getGeneratedConfigFile(true);
    $eqLogic->save();
  }
  ///---------- NOTHING BELOW PLZ !!!!!!!! 
}

function JeedomConnect_remove() {
  JeedomConnectUtils::removeCronItems();
}
