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
// require_once __DIR__  . '/../../core/class/JeedomConnect.class.php';

if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}


/** @var eqLogic */
$eqLogic = eqLogic::byId(init('eqLogicId'));

$automations = JeedomConnectAutomations::getAutomations($eqLogic->getId(), true);

$cronUL = '';
$listenerUL = '';


function getActions($allActions) {
  $result = '';

  usort($allActions, function ($a, $b) {
    return $a['index'] <=> $b['index'];
  });

  foreach ($allActions as $action) {
    switch ($action['action']) {
      case 'notif':
        $name = 'Notification : ' . $action['options']['name'] ?: '';
        break;
      case 'cmd':
        $cmd = cmd::byId($action['options']['id']);
        $name = $cmd ? 'Commande : ' . $cmd->getHumanName() : '';
        break;
      case 'scenario':
        $sc = scenario::byId($action['options']['scenario_id']);
        $name = $sc ? 'Scénario : ' . $sc->getHumanName() : '';
        break;

      default:
        $name = '';
        break;
    }
    $result .= '(' . ($action['index'] + 1) . ') ' . $name . '<br>';
  }

  $result = rtrim($result, '<br>');
  return $result;
}


function getStringEvent($event) {
  $cmds = JeedomConnectUtils::getCmdIdFromText($event);

  foreach ($cmds as $cmdId) {
    $cmd = cmd::byId($cmdId);
    if (is_object(($cmd))) {
      $event = str_replace("#" . $cmdId . "#", $cmd->getHumanName(), $event);
    }
  }

  return $event;
}


$cronUL .= '<tr>';
$cronUL .= '<td></td>';
$cronUL .= '<td>Actif</td>';
$cronUL .= '<td>Date</td>';
$cronUL .= '<td>Actions</td>';
$cronUL .= '</tr>';
foreach ($automations['crons'] as $cron) {
  $cronUL .= '<tr>';
  $cronUL .= '<td data-type="cron" data-id="' . $cron['id'] . '">';
  $cronUL .= '<i class="fas fa-minus-circle"></i> ';
  $cronUL .= '</td>';


  $cronUL .= '<td>';
  $cronUL .= '<input type="checkbox" class="form-check-input" data-type="cron" data-id="' . $cron['id'] . '"  ' . ((($cron['disabled'] ?? false) == 0) ? 'checked' : '') . ' >';
  $cronUL .= '</td>';

  $cronUL .= '<td>';
  if ($cron['triggers'][0]['options']['unique']) {

    if (isset($cron['triggers'][0]['options']['fixedTime'])) {
      $tmpDate = date("d/m/Y H:i", ($cron['triggers'][0]['options']['fixedTime'] / 1000));
    } else {
      $cronDate = $cron['triggers'][0]['options']['cron'] . ' ' . date('Y');
      $dueDateCronArray = explode(' ', $cronDate);
      $tmpDate = sprintf("%02d", $dueDateCronArray[2]) . '/' . sprintf("%02d", $dueDateCronArray[3]) . '/' . $dueDateCronArray[5] .  ' ' . $dueDateCronArray[1] . ':' . $dueDateCronArray[0];
    }

    $cronUL .= '<i class="icon divers-calendar2"></i>  ' .  $tmpDate;
  } else {

    $cronDate = $cron['triggers'][0]['options']['cron'];
    $cronUL .= '<i class="icon kiko-reload-arrow"></i>  ' . $cron['triggers'][0]['options']['cron'];
  }
  $cronUL .= '</td><td>';

  $cronUL .=  getActions($cron['actions']);

  $cronUL .= '</td>';

  $cronUL .= '</tr>';
}

$listenerUL .= '<tr>';
$listenerUL .= '<td></td>';
$listenerUL .= '<td>Actif</td>';
$listenerUL .= '<td>Déclencheur</td>';
$listenerUL .= '<td>Actions</td>';
$listenerUL .= '</tr>';
foreach ($automations['events'] as $event) {
  $listenerUL .= '<tr>';
  $listenerUL .= '<td data-type="event" data-id="' . $event['id'] . '">';
  $listenerUL .= '<i class="fas fa-minus-circle"></i> ';
  $listenerUL .= '</td>';

  $listenerUL .= '<td>';
  $listenerUL .= '<input type="checkbox" class="form-check-input" data-type="event" data-id="' . $event['id'] . '"  ' . ((($event['disabled'] ?? false) == 0) ? 'checked' : '') . ' >';
  $listenerUL .= '</td>';

  $listenerUL .= '<td>';

  $listener = $event['triggers'][0]['options']['event'] ?: '';

  $listenerUL .= '<i class="fas fa-assistive-listening-systems"></i> ' . getStringEvent($listener);

  $listenerUL .= '</td><td>';
  $listenerUL .=  getActions($event['actions']);
  $listenerUL .= '</td>';

  $listenerUL .= '</tr>';
}


?>

<div class="container-modal">

  <a class="btn btn-success pull-right" id="quit"><i class="fa fa-check-circle"></i> {{Fermer}}</a>
  <a class="btn btn-danger pull-right" id="removeAll"><i class="fa fa-times-circle"></i> {{Tout supprimer}}</a>

  <div id="widgetNotifContainer" class="col-sm-12">

    <div id="cronTab">
      <div>
        <h3>Programmé</h3>
        <table id="cronUL" style="border-collapse: separate;border-spacing: 10px;">
          <tbody>
            <?= $cronUL; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div id="listenerTab">
      <div>
        <h3>Provoqué</h3>
        <table id="listenerUL" style="border-collapse: separate;border-spacing: 10px;">
          <tbody>
            <?= $listenerUL; ?>
          </tbody>
        </table>
      </div>

    </div>

  </div>

</div>


<script>
  $(function() {

    function callAutomationAPI(action, data, callback) {
      $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
          'action': action,
          'data': data
        },
        dataType: 'json',
        success: function(response) {
          if (callback) callback(response);
        },
        error: function(xhr, status, error) {
          alert('Erreur lors de la communication avec le serveur : ' + error);
        }
      });
    }


    // Quitter le modal (avec jQuery UI si présent)
    $('#quit').on('click', function() {
      if ($('#md_modal').length && $('#md_modal').dialog) {
        $('#md_modal').dialog('close');
      } else if ($('.md-modal').length) {
        $('.md-modal').hide();
      }
    });

    // Supprimer toutes les programmations
    $('#removeAll').on('click', function() {
      if (confirm('Êtes-vous sûr de vouloir supprimer toutes les programmations ?')) {
        callAutomationAPI('removeAllAutomations', {
          eqLogicId: <?= $eqLogic->getId(); ?>
        }, function(response) {
          if (response.state == 'ok') {
            $('#quit').trigger('click');
          } else {
            alert('Erreur lors de la communication avec le serveur : ' + response.result);
          }
        });
      }
    });

    // Gestion des clics sur les icônes de suppression
    $('#cronUL, #listenerUL').on('click', '.fa-minus-circle', function() {
      if (confirm('Êtes-vous sûr de vouloir supprimer cette programmation ?')) {
        var parent = $(this).parent();
        var type = parent.data('type');
        var id = parent.data('id');
        callAutomationAPI('removeAutomation', {
          eqLogicId: <?= $eqLogic->getId(); ?>,
          type: type,
          id: id
        }, function(response) {
          if (response.state == 'ok') {
            parent.closest('tr').remove();
          } else {
            alert('Erreur lors de la communication avec le serveur : ' + response.result);
          }
        });
      }
    });

    // Gestion des changements de checkbox
    $('#cronUL, #listenerUL').on('change', 'input[type="checkbox"]', function() {
      var type = $(this).data('type');
      var id = $(this).data('id');
      var checked = $(this).is(':checked');


      callAutomationAPI('setAutomationStatus', {
        eqLogicId: <?= $eqLogic->getId(); ?>,
        type: type,
        id: id,
        status: checked ? 0 : 1
      }, function(response) {
        if (response.state != 'ok') {
          alert('Erreur lors de la communication avec le serveur : ' + response.result);
        }
      });
    });
  });
</script>