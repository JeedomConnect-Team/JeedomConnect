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
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}

/** @var eqLogic */
$eqLogic = eqLogic::byId(init('eqLogicId'));
sendVarToJS('apiKey', $eqLogic->getConfiguration('apiKey'));
sendVarToJS('platformOs', $eqLogic->getConfiguration('platformOs'));
$customPath = config::byKey('userImgPath', 'JeedomConnect');
sendVarToJS('userImgPath', $customPath);

$defaultProgNotif = config::byKey('defaultProgNotif', 'JeedomConnect', 'defaultNotif');
// JCLog::debug('defaultProgNotif ==> ' . $defaultProgNotif);
sendVarToJS('defaultProgNotif', $defaultProgNotif);

include_file('desktop', 'notifs.JeedomConnect', 'js', 'JeedomConnect');
include_file('desktop', 'assistant.JeedomConnect', 'css', 'JeedomConnect');
include_file('desktop', 'md/css/materialdesignicons', 'css', 'JeedomConnect');
?>

<div class="container-modal">

  <div style="display:none;" id="jc-assistant"></div>

  <a class="btn btn-success pull-right" onclick="save()"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
  <a class="btn btn-danger pull-right" onclick="resetConfig()"><i class="fa fa-times-circle"></i> {{Réinitialiser}}</a>

  <div id="widgetNotifContainer" class="col-sm-12">

    <div id="detailMenu" class="col-sm-2 " style="margin-right: 20px;">
      <legend> {{Notifications Jeedom Connect}}</legend>
      <div class="tab fixed">
        <?php
        if ($eqLogic->getConfiguration('platformOs') == 'android') {
          echo '<button class="tablinks" data-link="channelsTab">Canaux</button>';
        }
        ?>
        <button class="tablinks" data-link="notifsTab">Notifications</button>
        <button class="tablinks" data-link="progTab">Programmation</button>
      </div>
    </div>


    <div id="detailTab" class="col-sm-9 ">
      <div id="channelsTab" class="tabcontent" style="display:none">
        <div class="leftContent">
          <h3>Canaux</h3>
          <a class="btn btn-success btn-sm " style="margin-top:5px;" onclick="addChannelTabModal()"><i class="fa fa-plus-circle"></i> Ajouter un canal</a>
          <ul id="channelsUL" class="tabsUL"></ul>
        </div>
        <div class="rightContent">
          <div class="alert alert-info">
            Vous pouvez ici configurer les canaux de notification (Android 8+). Sur Android (8+), une application peut avoir plusieurs canaux de notifications.
            Chaque canal peut être personnalisé par l'utilisateur (Sonnerie, vibreur, importance...)
          </div>
          <img src="" />
        </div>
      </div>

      <div id="notifsTab" class="tabcontent" style="display:none">
        <div class="leftContent">
          <h3>Notifications</h3>
          <a class="btn btn-success btn-sm " style="margin-top:5px;margin-left:10px;" onclick="addNotifModal()"><i class="fa fa-plus-circle"></i> Ajouter une notification</a>
          <ul id="notifsUL" class="tabsUL"></ul>
        </div>
        <div class="rightContent">
          <div class="alert alert-info">
            Vous pouvez ajouter des commandes de notification personalisées.
          </div>
          <img src="" />
        </div>
      </div>

      <div id="progTab" class="tabcontent">
        <div class="leftContent">
          <h3>Notifications par défaut sur les programmations</h3>
          <div class="description">La notification sélectionné sera proposée par défaut sur l'action "Me Notifier" des programmations</div>
          <div id="progUL" class="tabsUL">
          </div>
        </div>
      </div>
    </div>

  </div>

</div>