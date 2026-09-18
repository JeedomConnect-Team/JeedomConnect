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

require_once dirname(__FILE__) . '/../../core/class/JeedomConnect.class.php';

// Le remplissage générique des champs ".configKey" (jeedom.config.load +
// setJeeValues côté JS core) n'est déclenché QUE pour le panneau de
// configuration standard d'un plugin - jamais pour du contenu chargé
// dynamiquement dans une modale comme celle-ci. D'où ce pré-remplissage
// direct en PHP pour les champs qui affichent une valeur déjà enregistrée
// (le mot de passe/jeton API, lui, reste volontairement vide - jamais
// réaffiché, un champ laissé vide conserve la valeur existante à la
// sauvegarde, comportement générique Jeedom inchangé).
$currentTurnMode = config::byKey('turnMode', 'JeedomConnect', 'cloudflare');
$currentCloudflareTurnKeyId = config::byKey('cloudflareTurnKeyId', 'JeedomConnect', '');
// Indicateur visuel uniquement (placeholder, jamais une value) : confirme
// qu'un jeton EST enregistré sans jamais le réafficher ni risquer de
// l'écraser si le champ est soumis tel quel (vide = conserve l'existant).
$hasCloudflareTurnApiToken = config::byKey('cloudflareTurnApiToken', 'JeedomConnect', '') !== '';
?>
<div id="streamingServicesModalRoot">

  <!-- TURN HORS LAN (regroupé avec le tunnel webview sous une seule
       modale "Services de streaming") -->
  <div class="panel panel-primary" id="turnHorsLanPanel">
    <div class="panel-heading" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
      <h3 class="panel-title"><i class="fas fa-video"></i> {{TURN - Caméras hors LAN}}</h3>
      <div style="display:flex; align-items:center; gap:8px;">
        <span class="alert-inline-msg" id="saveTurnConfigResult"></span>
        <a class="btn btn-success btn-xs" id="saveTurnConfig"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
        <a class="btn btn-info btn-xs" href="https://jeedomconnect-team.github.io/jc-doc/docs/documentation/integration/cameraTurn" target="_blank"><i class="fas fa-book"></i> {{Documentation}}</a>
      </div>
    </div>
    <div class="panel-body">
    <div class="alert alert-info" style="text-align:center;">
      Accéder aux caméras hors du LAN avec une connexion chiffrée (widgets caméra avec "Flux vidéo optimisé (go2rtc)").<br />
      Facultatif - sans configuration, le flux hors LAN reste en MSE.
    </div>
    <div class="row">
      <div class="form-group col-lg-6">
        <label class="col-lg-3 control-label">{{Mode}}</label>
        <div class="col-lg-9">
          <select class="form-control configKey needJCRefresh" id="turnModeSelect" data-l1key="turnMode">
            <option value="cloudflare" <?php echo $currentTurnMode == 'cloudflare' ? 'selected' : ''; ?>>{{Gratuit - mon propre compte Cloudflare}}</option>
            <option value="managed" <?php echo $currentTurnMode == 'managed' ? 'selected' : ''; ?>>{{Payant - abonnement géré (~6€/an, 10 Go/mois)}}</option>
          </select>
        </div>
      </div>
      <div class="form-group col-lg-6" style="text-align:center;">
        <a class="btn btn-default" id="restartGo2rtc"><i class="fas fa-sync-alt"></i> {{Redémarrer go2rtc}}</a>
        <span class="alert-inline-msg" id="restartGo2rtcResult" style="margin-left:10px;"></span>
        <sup>
          <i class="fas fa-question-circle floatright" title="go2rtc n'est pas le démon principal du plugin et n'a donc pas de contrôle dédié sur la page équipement. À utiliser après un changement de mode TURN, ou en cas de souci avec le flux hors LAN."></i>
        </sup>
      </div>
    </div>

    <div class="turnModeSection" data-mode="cloudflare">
      <div class="alert alert-info" style="text-align:center;">
        Gratuit jusqu'à 1000 Go/mois (carte bancaire requise par Cloudflare pour activer le service, mais non débitée sous ce seuil).<br />
        <a href="https://jeedomconnect-team.github.io/jc-doc/docs/tutorials/cloudflare_turn" target="_blank">Tutoriel pas à pas avec captures d'écran <i class="fas fa-external-link-alt"></i></a>
      </div>
      <div class="row">
        <div class="form-group col-lg-6">
          <label class="col-lg-6 control-label">{{Cloudflare Turn Key ID}}
            <sup>
              <i class="fas fa-question-circle floatright" title="Identifiant de la Turn Key créée dans le dashboard Cloudflare (Realtime > TURN)."></i>
            </sup>
          </label>
          <div class="col-lg-6">
            <input class="configKey form-control needJCRefresh" type="string" data-l1key="cloudflareTurnKeyId" value="<?= htmlspecialchars($currentCloudflareTurnKeyId, ENT_QUOTES) ?>" />
          </div>
        </div>
        <div class="form-group col-lg-6">
          <label class="col-lg-3 control-label">{{Cloudflare API Token}}
            <sup>
              <i class="fas fa-question-circle floatright" title="Jeton API associé à cette Turn Key. Reste côté serveur, jamais transmis à l'application."></i>
            </sup>
          </label>
          <div class="col-lg-6">
            <input class="configKey form-control needJCRefresh" type="password" autocomplete="new-password" data-l1key="cloudflareTurnApiToken" placeholder="<?= $hasCloudflareTurnApiToken ? '••••••••••••••••' : '' ?>" />
          </div>
        </div>
      </div>
      <div class="row">
        <div class="form-group col-lg-12" style="text-align:center;">
          <a class="btn btn-default" id="testCloudflareTurn"><i class="fas fa-vial"></i> {{Tester mes identifiants}}</a>
          <span class="alert-inline-msg" id="testCloudflareTurnResult" style="margin-left:10px;"></span>
        </div>
      </div>
    </div>

    <div class="turnModeSection" data-mode="managed">
      <div class="alert alert-info" style="text-align:center;">
        Aucun compte Cloudflare nécessaire.<br />
        <b>Essai gratuit de 7 jours / 4 Go, sans carte bancaire</b> - à démarrer explicitement ci-dessous.<br />
        Passé ce délai (ou ~6€/an pour un accès illimité dans le temps, 10 Go/mois) : ouvrez l'application JeedomConnect sur votre téléphone, puis <b>Préférences &gt; Caméras hors LAN</b> - l'abonnement s'achète et se gère directement depuis l'App Store / Google Play.
      </div>
      <div class="row" id="managedTurnStatusRow" style="display:none;">
        <div class="form-group col-lg-12">
          <div class="alert" id="managedTurnStatusBox" style="text-align:center;"></div>
        </div>
      </div>
      <div class="row" id="startManagedTrialRow" <?php echo Go2rtc::isManagedTrialStarted() ? 'style="display:none;"' : ''; ?>>
        <div class="form-group col-lg-12" style="text-align:center;">
          <a class="btn btn-success" id="startManagedTrial"><i class="fas fa-play"></i> {{Démarrer mon essai gratuit (7 jours)}}</a>
          <span class="alert-inline-msg" id="startManagedTrialResult" style="margin-left:10px;"></span>
        </div>
      </div>
      <div class="row">
        <div class="form-group col-lg-12" style="text-align:center;">
          <a class="btn btn-default" id="testManagedTurn"><i class="fas fa-vial"></i> {{Tester mon accès}}</a>
          <span class="alert-inline-msg" id="testManagedTurnResult" style="margin-left:10px;"></span>
          <sup>
            <i class="fas fa-question-circle floatright" title="Vérifie l'obtention d'identifiants TURN via l'abonnement actif ou, à défaut, l'essai gratuit."></i>
          </sup>
        </div>
      </div>
    </div>
    </div>
  </div>

  <br />

  <!-- WEBVIEW HORS LAN (tunnel Cloudflare) -->
  <div class="panel panel-primary">
    <div class="panel-heading" style="display:flex; align-items:center; justify-content:space-between;">
      <h3 class="panel-title"><i class="fas fa-globe"></i> {{Webview - Accès hors LAN}}</h3>
      <a class="btn btn-info btn-xs" href="https://jeedomconnect-team.github.io/jc-doc/docs/documentation/integration/webviewTunnel" target="_blank"><i class="fas fa-book"></i> {{Documentation}}</a>
    </div>
    <div class="panel-body">
    <div class="alert alert-info" style="text-align:center;">
      Widgets webview avec une "URL locale" renseignée (et aucune URL publique) : accessibles hors LAN via un tunnel dédié, géré automatiquement à la sauvegarde du widget.
    </div>
    <div class="row">
      <div class="form-group col-lg-6" style="text-align:center;">
        <b>{{Démon tunnel (cloudflared) : }}</b>
        <span id="tunnelDaemonStatus"><i class="fas fa-spinner fa-spin"></i></span>
      </div>
      <div class="form-group col-lg-6" style="text-align:center;">
        <a class="btn btn-default" id="restartCloudflaredTunnel"><i class="fas fa-sync-alt"></i> {{Redémarrer le tunnel}}</a>
        <span class="alert-inline-msg" id="restartCloudflaredTunnelResult" style="margin-left:10px;"></span>
      </div>
    </div>
    <div class="alert alert-info" style="text-align:center;">
      1 widget hors-LAN tunnelé gratuit par installation - un abonnement TURN géré (voir ci-dessus) porte ce quota à 10.<br />
      Le quota s'applique au nombre de widgets webview tunnelés simultanément, indépendamment de leur usage réel (le trafic webview ne consomme pas le forfait Go/mois du TURN).
    </div>
    <div class="row">
      <div class="form-group col-lg-12" style="text-align:center;">
        <div id="tunnelQuotaBox"><i class="fas fa-spinner fa-spin"></i></div>
      </div>
    </div>
    <div class="row">
      <div class="form-group col-lg-12">
        <table class="table" id="tunnelWidgetsTable">
          <thead>
            <tr>
              <th>{{Widget}}</th>
              <th>{{Hostname}}
                <span class="text-warning" style="font-weight:normal;" title="Ce lien donne un accès direct au widget, sans mot de passe ni identifiant - toute personne qui le connaît peut y accéder.">
                  <i class="fas fa-exclamation-triangle"></i> {{Ne pas diffuser}}
                </span>
              </th>
            </tr>
          </thead>
          <tbody id="tunnelWidgetsTableBody">
            <tr>
              <td colspan="2" style="text-align:center;"><i class="fas fa-spinner fa-spin"></i></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    </div>
  </div>

</div>

<?php
include_file('desktop', 'streamingServices.JeedomConnect', 'js', 'JeedomConnect');
?>
