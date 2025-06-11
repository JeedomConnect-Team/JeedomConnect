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

if (!isConnect()) {
  throw new Exception('{{401 - Accès non autorisé}}');
}

require_once dirname(__FILE__) . '/../../core/class/JeedomConnect.class.php';


?>

<div id="alert_JcEquipmentSummary"></div>
<a class="btn btn-success btn-sm pull-right" id="bt_saveJcEquipmentSummary"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
<a class="btn btn-danger btn-sm pull-right" id="bt_removeJcEquipmentSummary" style="display:none;"><i class="fas fa-trash-alt"></i> {{Supprimer}}</a>
<table id="table_JcEquipmentSummary" class="table table-bordered table-condensed tablesorter stickyHead">
  <thead>
    <tr>
      <th colspan="5" data-sorter="false" data-filter="false">&nbsp;</th>
      <th colspan="2" data-sorter="false" data-filter="false" class="text-center">Connexion</th>
      <th colspan="5" data-sorter="false" data-filter="false" class="text-center">Accès à</th>
      <th data-sorter="false" data-filter="false" class="text-center">Masquer</th>
      <th colspan="4" data-sorter="false" data-filter="false">&nbsp;</th>
    </tr>
    <tr>
      <th>{{ID}}</th>
      <th data-sorter="checkbox" data-filter="false">{{Actif}}
        <a class="btn btn-success btn-xs jcMassAction" data-jcaction="checked" data-jctype="isEnable" style="width:22px;"><i class="fas fa-check"></i></a>
        <a class="btn btn-danger btn-xs jcMassAction" data-jcaction="unchecked" data-jctype="isEnable" style="width:22px;"><i class="fas fa-times"></i></a>
      </th>
      <th data-sorter="input-text">{{Nom équipement}}</th>
      <th data-sorter="text">{{Appareil}}</th>
      <th data-sorter="select-text">{{Pièce}}</th>
      <th data-sorter="checkbox" data-filter="false">{{Websocket}}
        <a class="btn btn-success btn-xs jcMassAction" data-jcaction="checked" data-jctype="useWs" style="width:22px;"><i class="fas fa-check"></i></a>
        <a class="btn btn-danger btn-xs jcMassAction" data-jcaction="unchecked" data-jctype="useWs" style="width:22px;"><i class="fas fa-times"></i></a>
      </th>
      <th data-sorter="checkbox" data-filter="false">{{Polling}}
        <a class="btn btn-success btn-xs jcMassAction" data-jcaction="checked" data-jctype="polling" style="width:22px;"><i class="fas fa-check"></i></a>
        <a class="btn btn-danger btn-xs jcMassAction" data-jcaction="unchecked" data-jctype="polling" style="width:22px;"><i class="fas fa-times"></i></a>
      </th>
      <th data-sorter="checkbox" data-filter="false">{{Scenario}}
        <a class="btn btn-success btn-xs jcMassAction" data-jcaction="checked" data-jctype="scenariosEnabled" style="width:22px;"><i class="fas fa-check"></i></a>
        <a class="btn btn-danger btn-xs jcMassAction" data-jcaction="unchecked" data-jctype="scenariosEnabled" style="width:22px;"><i class="fas fa-times"></i></a>
      </th>
      <th data-sorter="checkbox" data-filter="false">{{Timeline}}
        <a class="btn btn-success btn-xs jcMassAction" data-jcaction="checked" data-jctype="timelineEnabled" style="width:22px;"><i class="fas fa-check"></i></a>
        <a class="btn btn-danger btn-xs jcMassAction" data-jcaction="unchecked" data-jctype="timelineEnabled" style="width:22px;"><i class="fas fa-times"></i></a>
      </th>
      <th data-sorter="checkbox" data-filter="false">{{WebView}}
        <a class="btn btn-success btn-xs jcMassAction" data-jcaction="checked" data-jctype="webviewEnabled" style="width:22px;"><i class="fas fa-check"></i></a>
        <a class="btn btn-danger btn-xs jcMassAction" data-jcaction="unchecked" data-jctype="webviewEnabled" style="width:22px;"><i class="fas fa-times"></i></a>
      </th>
      <th data-sorter="checkbox" data-filter="false">{{WebView}}
        <a class="btn btn-success btn-xs jcMassAction" data-jcaction="checked" data-jctype="automationsEnabled" style="width:22px;"><i class="fas fa-check"></i></a>
        <a class="btn btn-danger btn-xs jcMassAction" data-jcaction="unchecked" data-jctype="automationsEnabled" style="width:22px;"><i class="fas fa-times"></i></a>
      </th>
      <th data-sorter="checkbox" data-filter="false">{{Altitude}}
        <a class="btn btn-success btn-xs jcMassAction" data-jcaction="checked" data-jctype="addAltitude" style="width:22px;"><i class="fas fa-check"></i></a>
        <a class="btn btn-danger btn-xs jcMassAction" data-jcaction="unchecked" data-jctype="addAltitude" style="width:22px;"><i class="fas fa-times"></i></a>
      </th>
      <th data-sorter="checkbox" data-filter="false">{{Position}}
        <a class="btn btn-success btn-xs jcMassAction" data-jcaction="checked" data-jctype="displayPosition" style="width:22px;"><i class="fas fa-check"></i></a>
        <a class="btn btn-danger btn-xs jcMassAction" data-jcaction="unchecked" data-jctype="displayPosition" style="width:22px;"><i class="fas fa-times"></i></a>
      </th>
      <th data-sorter="checkbox" data-filter="false">{{Batterie}}
        <a class="btn btn-success btn-xs jcMassAction" data-jcaction="checked" data-jctype="hideBattery" style="width:22px;"><i class="fas fa-check"></i></a>
        <a class="btn btn-danger btn-xs jcMassAction" data-jcaction="unchecked" data-jctype="hideBattery" style="width:22px;"><i class="fas fa-times"></i></a>
      </th>
      <th data-sorter="select-text">{{Utilisateur}}</th>
      <th data-sorter="false" data-filter="false">QrCode</th> <!-- info qr code -->
      <th data-sorter="false" data-filter="false" style="text-align:center;"><i class="fas fa-trash-alt"></i></th>
    </tr>
  </thead>
  <tbody>
    <?php

    $html = '';

    /** @var JeedomConnect $eqLogic */
    foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {

      // **********    ID    ****************
      $html .= '<tr class="tr_object" data-equipment_id="' . $eqLogic->getId() . '" >';
      $html .= '<td style="width:40px;"><span class="label label-info objectAttr objectAttrSelect" data-l1key="eqId" style="cursor: pointer !important;">' . $eqLogic->getId() . '</span></td>';

      // **********    Enable    ****************
      $eqEnable = $eqLogic->getIsEnable() ? 'checked' : '';
      $html .= '<td align="center" style="width:60px;"><input type="checkbox" class="objectAttr" ' . $eqEnable . ' data-l1key="isEnable" /></td>';

      // **********    Name    ****************
      $eqName =  $eqLogic->getName();
      // item span dont display, only usefull here to use the filter option which is not available on input for text
      $html .= '<td style="width:40px;"><span class="eqJcName" style="display:none">' . $eqName . '</span><input type="text" class="objectAttr" data-l1key="name" value="' . $eqName . '" /></td>';

      // **********    deviceName    ****************
      $platform = ($eqLogic->getConfiguration('platformOs') == '') ? ''
        : (
          ($eqLogic->getConfiguration('platformOs') == 'ios') ? ' <i class="fab fa-apple"></i>' : ' <i class="fab fa-android"></i>'
        );
      $html .= '<td style="width:200px;"><span class="label" data-l1key="deviceName" title="' . $eqLogic->getConfiguration('platformOs') . '">' . $eqLogic->getConfiguration('deviceName') .  '</span>' . $platform . '</td>';

      // **********    ROOM    ****************
      $currentRoom = $eqLogic->getObject_id();
      $html .= '<td style="width:150px;">';
      $html .= '<select class="objectAttr"  data-l1key="roomId">';
      $html .= '<option value="none">Aucun</option>';

      foreach ((jeeObject::buildTree(null, false)) as $object) {
        $select = ($currentRoom == $object->getId()) ? 'selected' : '';
        $html .= ' <option value="' . $object->getId() . '" ' . $select . '>' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
      }

      $html .= '</select>';
      $html .= '</td>';
      // ****************************************


      // **********    Websocket    ****************
      // **********    Polling    ****************
      $wsEnable = $eqLogic->getConfiguration('useWs', false) ? 'checked' : '';
      $pollingEnable =  (!$wsEnable && $eqLogic->getConfiguration('polling', false)) ? 'checked' : '';
      $wsForbidden = (!$wsEnable && $pollingEnable)  ? ' disabled="disabled"' : '';
      $pollingForbidden = $wsEnable ? ' disabled="disabled"' : '';
      $html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr checkJcConnexionOption" ' . $wsEnable . ' data-l1key="useWs" ' . $wsForbidden . '/></td>';
      $html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr checkJcConnexionOption" ' . $pollingEnable . ' data-l1key="polling" ' . $pollingForbidden . '/></td>';

      // **********    scenariosEnabled    ****************
      $scenariosEnabled = $eqLogic->getConfiguration('scenariosEnabled', false) ? 'checked' : '';
      $html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" ' . $scenariosEnabled . ' data-l1key="scenariosEnabled" /></td>';

      // **********    timelineEnabled    ****************
      $timelineEnabled = $eqLogic->getConfiguration('timelineEnabled', false) ? 'checked' : '';
      $html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" ' . $timelineEnabled . ' data-l1key="timelineEnabled" /></td>';

      // **********    webviewEnabled    ****************
      $webviewEnabled = $eqLogic->getConfiguration('webviewEnabled', false) ? 'checked' : '';
      $html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" ' . $webviewEnabled . ' data-l1key="webviewEnabled" /></td>';

      // **********    automationsEnabled    ****************
      $automationsEnabled = $eqLogic->getConfiguration('automationsEnabled', false) ? 'checked' : '';
      $html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" ' . $automationsEnabled . ' data-l1key="automationsEnabled" /></td>';

      // **********    addAltitude    ****************
      $addAltitude = $eqLogic->getConfiguration('addAltitude', false) ? 'checked' : '';
      $html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" ' . $addAltitude . ' data-l1key="addAltitude" /></td>';

      // **********    Position    ****************
      $displayPosition = $eqLogic->getConfiguration('displayPosition', false) ? 'checked' : '';
      $html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" ' . $displayPosition . ' data-l1key="displayPosition" /></td>';

      // **********    hideBattery    ****************
      $hideBattery = $eqLogic->getConfiguration('hideBattery', false) ? 'checked' : '';
      $html .= '<td align="center" style="width:65px;"><input type="checkbox" class="objectAttr" ' . $hideBattery . ' data-l1key="hideBattery" /></td>';

      // **********    User    ****************
      $currentUser = $eqLogic->getConfiguration('userId');
      $html .= '<td style="width:100px;">';
      $html .= '<select  class="objectAttr"  data-l1key="userId">';
      $html .= '<option value="none">Aucun</option>';

      foreach (user::all() as $user) {
        $userSelected = ($currentUser == $user->getId()) ? 'selected' : '';
        $html .= ' <option value="' . $user->getId() . '" ' . $userSelected . '>' . $user->getLogin() . '</option>';
      }

      $html .= '</select>';
      $html .= '</td>';

      // **********    Api    ****************
      // $html .= '<td style="width:250px;"><span class="label" data-l1key="apiKey" >' . $eqLogic->getConfiguration('apiKey') . '</span></td>';

      // **********    QrCode    ****************
      $apiKey = $eqLogic->getConfiguration('apiKey');
      $img = 'plugins/JeedomConnect/data/qrcodes/' . $apiKey . '.png';
      if (!file_exists('/var/www/html/' . $img)) {
        $eqLogic->generateQRCode();
      }
      if (file_exists('/var/www/html/' . $img)) {
        $html .= '<td style="width:40px;"  class="th-qrcode"><div class="qrcode-panel"><div class="qrcode-content" data-img="' . $img . '" data-apiKey="' . $apiKey . '" data-name="' . $eqName . '"><i class="fas fa-solid fa-qrcode"></i></div></div></td>';
      } else {
        $html .= '<td style="width:40px;" data-img="" data-apiKey="' . $apiKey . '" data-name="' . $eqName . '"><i class="fas fa-ban"></i></td>';
      }


      // **********    Notif All    ****************
      // $alreadyChecked = config::byKey('notifAll', 'JeedomConnect', array());
      // $cmdCount = 0;
      // $tmpCheckbox = '';
      // /** @var JeedomConnect $eqLogic */
      // foreach ($eqLogic->getCmd('action') as $cmd) {
      //   if (strpos(strtolower($cmd->getLogicalId()), 'notif') !== false) {
      //     if ($cmd->getLogicalId() == 'notifall') continue;

      //     $checked = in_array($cmd->getId(), $alreadyChecked) ? 'checked' : '';
      //     $cmdCount += in_array($cmd->getId(), $alreadyChecked) ? 1 : 0;
      //     $cmdId =  $cmd->getId();
      //     $tmpCheckbox .= '<label for="' . $cmdId . '"><input type="checkbox" class="objectAttr"  data-l1key="NotifAll" data-l2key="' . $cmdId . '" value="' . $cmdId . '"  ' . $checked . '/> ' . $cmd->getName() . ' [' . $cmdId . ']</label>';
      //   }
      // }

      // $needPlurial = ($cmdCount > 1) ? 's' : '';
      // $titleSelect = ($cmdCount == 0) ? 'Sélectionnez une commande' : $cmdCount . ' commande' . $needPlurial . ' sélectionnée' . $needPlurial;
      // $html .= '<td style="width:250px;">
      // <div class="multiselect">
      //   <div class="selectBox">
      //     <select>
      //       <option class="titleOption">' . $titleSelect . '</option>
      //     </select>
      //     <div class="overSelect"></div>
      //   </div>
      //   <div class="checkboxes">';

      // $html .= $tmpCheckbox;

      // $html .= '</div>
      //         </div>
      //       </td>';

      // **********    Delete    ****************
      $html .= '<td align="center" style="width:75px;"><input type="checkbox" class="removeEquipment" data-eqId="' . $eqLogic->getId() . '"/></td>';

      $html .=  '</tr>';
    }
    echo $html;

    ?>
    <div class="qrCodeModal">
      <div class="qrCodeModal-content">
        <span class="close digiFunctionCancel">&times;</span>
        <b>
          <div class="text-center jcName"></div>
        </b>
        <div class="jcQrCode">
          <img src="" alt="" style="margin:10px auto; max-height : 250px;" />
        </div>
        <b><span>Api Key : </span></b><br />
        <span class="jcApiKey"></span><br />
      </div>
    </div>
  </tbody>
</table>




<?php include_file('desktop', 'equipementsummary.JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'assistant.JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'generic.JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'equipmentJC', 'css', 'JeedomConnect'); ?>
<?php include_file('desktop/common', 'utils', 'js'); ?>