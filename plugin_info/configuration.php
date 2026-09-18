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

include_file('core', 'authentification', 'php');

if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}

$existOldFormat = false;
/** @var JeedomConnect $eqLogic */
foreach (JeedomConnect::getAllJCequipment() as $eqLogic) {
  $configFile = $eqLogic->getConfig(false);
  if (!is_null($configFile) && !array_key_exists('formatVersion', $configFile)) $existOldFormat = true;
}

$pluginVersion = JeedomConnect::getPluginInfo();
$apkLink = $pluginVersion['enrollment'];
$beta = $pluginVersion['typeVersion'] == 'beta';

$orderBy = config::byKey('jcOrderByDefault', 'JeedomConnect', 'object');
$orderByArray = array(
  "object" => "Pièce",
  "name" => "Nom",
  "type" => "Type"
);

$optionsOrderBy = '';
foreach ($orderByArray as $key => $value) {
  $selected = ($key ==  $orderBy) ? 'selected' : '';
  $optionsOrderBy .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
}


$userHash = '';
if (isConnect()) {
  if (isset($_SESSION['user']) && is_object($_SESSION['user'])) {
    $user = user::byId($_SESSION['user']->getId());
    if (is_object($user)) {
      // JCLog::debug('user session:' . $user->getHash());
      $userHash = $user->getHash();
    }
  }
}
sendVarToJS('userHash', $userHash);


?>
<form class="form-horizontal jeedomConnect">

  <?php
  if ($beta) {
  ?>
    <div style="text-align:center;margin-bottom:10px;">
      <div style="margin-bottom:10px;">
        <span class="alert alert-success">
          <a href="<?= $apkLink ?>" style="color: white !important;padding: 0px 10px;" target="_blank">S'enregister en tant que bêta-testeur</a>
        </span>
      </div>

      <div>
        <span>Afin d'accèder à l'application dans sa version bêta depuis le Store, vous devez être inscrit comme bêta-testeur</span>
      </div>
    </div>
  <?php
  }

  if (JeedomConnect::install_notif_info() == 'nok') {
  ?>

    <div class="alert alert-warning" style="text-align:center;">
      Vous ne pouvez pas envoyer de notifications !<br />
      Forcez la mise à jour.
      <a class="btn btn-danger" id="reinitBin"><i class="fas fa-exclamation-triangle"></i> {{Réinitialiser}}
      </a>
    </div>

  <?php
  }
  ?>
  <!-- used for refresh when page saved -->
  <div class="customJCObject"></div>
  <!-- end used for refresh -->
  <div class="alert alert-warning infoRefresh" style="text-align:center;display:none;">
    Pour information : vous êtes en train de modifier des éléments de configuration essentiels au bon fonctionnement de vos équipements JC. <br />
    Les QR-Code de l'ensemble de vos équipements JC seront automatiquement regénérés après la sauvegarde de vos modifications.<br />
    Le démon sera automatiquement redémarré (si nécessaire).
  </div>

  <div class="alert alert-info" style="text-align:center;">
    Les paramètres ci-dessous doivent être configurés correctement pour le bon fonctionnement de l'application.<br />
    Après tout changement ici, veuillez redémarrer l'application.
  </div>
  <fieldset>
    <div class="row">
      <div class="form-group col-lg-6">
        <label class="col-lg-6 control-label">{{Adresse http externe}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Si vous pouvez vous connecter à votre jeedom depuis l'extérieur de votre domicile, renseignez ici l'url de connexion à utiliser.<br/>Cela peut être :<ul><li>votre nom de domaine personnel &#x2794; https://jeedom.moi-et-moi.fr</li><li>l'adresse de votre option DNS Jeedom &#x2794; https://a1234.eu.jeedom.link</li><li>votre IP public &#x2794; http://81.23.653.32</li><li>une url no-ip &#x2794; https://monJedom.ddns.net</li><li>...</li></ul><i>Note : dans la plupart des cas des redirections de ports sur votre box internet sont nécessaires...</i>"></i>
          </sup>
        </label>
        <div class="col-lg-6">
          <input class="configKey form-control needJCRefresh" type="string" data-l1key="httpUrl" placeholder="<?php echo network::getNetworkAccess('external'); ?>" />
        </div>
      </div>
      <div class="form-group col-lg-6">
        <label class="col-lg-3 control-label">{{Adresse http interne}}</label>
        <div class="col-lg-6">
          <input class="configKey form-control needJCRefresh" type="string" data-l1key="internHttpUrl" placeholder="<?php echo network::getNetworkAccess('internal'); ?>" />
        </div>
      </div>
    </div>
    <br />

    <div class="row alert alert-info" style="text-align:center;">
      Les paramètres liés au websocket ne sont nécessaires que si vous l'activez sur un équipement.
      Si vous n'utilisez pas le websocket, vous pouvez désactiver le démon.<br />
      La connexion par Websocket nécessite une configuration supplémentaire sur votre réseau, au moins pour un accès extérieur.<br />
      Vous pouvez suivre <a href='https://community.jeedom.com/t/plugin-jeedomconnect-actualites/71794/4' target='_blank'>ce tuto sur community <i class="fas fa-external-link-alt"></i></a><br />
      <i class="fas fa-exclamation-triangle"></i>&nbsp;NON-compatible avec les DNS Jeedom.&nbsp;<i class="fas fa-exclamation-triangle"></i>
    </div>
    <div class="row">
      <div class="form-group col-lg-4">
        <label class="col-lg-6 control-label">{{Connexion IPV6}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Protocole de connexion en IPV6"></i>
          </sup>
        </label>
        <div class="col-lg-3">
          <input type="checkbox" class="configKey needJCRefresh" data-l1key="ipv6" />
        </div>
      </div>
      <div class="form-group col-lg-4">
        <label class="col-lg-4 control-label">{{Port Websocket JC}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Port d'écoute sur lequel le websocket sera connecté. A utiliser sur l'application JC pour vous connecter à votre Jeedom."></i>
          </sup>
        </label>
        <div class="col-lg-3">
          <input class="configKey form-control needJCRefresh" type="number" data-l1key="port" placeholder="8090" />
        </div>
      </div>
      <div class="form-group col-lg-4">
        <label class="col-lg-4 control-label">{{Port Socket Démon}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Port d'échange interne entre le démon et votre Jeedom."></i>
          </sup>
        </label>
        <div class="col-lg-3">
          <input class="configKey form-control needJCRefresh" type="number" data-l1key="socketport" placeholder="58090" />
        </div>
      </div>
    </div>

    <div class="row">
      <div class="form-group col-lg-6">
        <label class="col-lg-6 control-label">{{Adresse websocket externe}}</label>
        <div class="col-lg-6">
          <input class="configKey form-control needJCRefresh" type="string" data-l1key="wsAddress" placeholder="<?php echo 'ws://' . config::byKey('externalAddr') . ':8090'; ?>" />
        </div>
      </div>
      <div class="form-group col-lg-6">
        <label class="col-lg-3 col-md-6 control-label">{{Adresse websocket interne}}</label>
        <div class="col-lg-6">
          <input class="configKey form-control needJCRefresh" type="string" data-l1key="internWsAddress" placeholder="<?php echo 'ws://' . config::byKey('internalAddr', 'core', 'localhost') . ':8090'; ?>" />
        </div>
      </div>
    </div>

    <br />
    <!-- TURN hors LAN + tunnel webview : voir la modale "Services de
         streaming" (page Gestion), desktop/modal/streamingServices.JeedomConnect.php -->

    <br />
    <!-- CUSTOM ZONE -->
    <div class="alert alert-success" style="text-align:center;">
      {{Personnalisation}}
    </div>

    <div class="row">
      <div class="form-group col-lg-6">
        <label class="col-lg-7 control-label">{{Chemin pour les images perso}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Chemin où sont stockés vos images personnelles<br/>Indiquez-le SANS la racine de votre installation jeedom [/var/www/html/]<br/>Par exemple, renseignez ' data/img/' pour le répertoire '/var/www/html/data/img/'"></i>
          </sup>
        </label>
        <div class=" col-lg-5">
          <input class="configKey form-control" type="string" data-l1key="userImgPath" placeholder="<?= config::byKey('userImgPath', 'JeedomConnect'); ?>" />
        </div>
      </div>

      <div class="form-group col-lg-6">
        <label class="col-lg-3 control-label">{{Tri des widgets}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Tri par défaut sur la page principale du plugin et dans l'assistant de configuration"></i>
          </sup>
        </label>
        <div class="col-lg-6">
          <select class="configKey form-control" data-l1key="jcOrderByDefault">
            <?php
            echo $optionsOrderBy;
            ?>
          </select>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="form-group col-lg-6">
        <label class="col-lg-4 control-label">{{Qr Code : }}</label>
        <label class="col-lg-3 control-label">{{Logo JC}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Insère le logo JC à l'intérieur du qr code de chaque équipement"></i>
          </sup>
        </label>
        <div class="col-lg-3">
          <input type="checkbox" class="configKey" data-l1key="withQrCode" />
        </div>
      </div>

      <div class="form-group col-lg-6">
        <label class="col-lg-3 control-label">{{Afficher sur page principale}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Au survol d'un équipement, son QR Code est affiché en haut de la page principale du plugin."></i>
          </sup>
        </label>
        <div class="col-lg-3">
          <input type="checkbox" class="configKey" data-l1key="showQrCodeMainPage" />
        </div>
      </div>
    </div>

    <div class="row">
      <div class="form-group col-lg-6">
        <label class="col-lg-4 control-label">{{Création de widget : }}</label>
        <label class="col-lg-3 control-label">{{Mode strict}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Impose que tous les types génériques nécessaires soient correctement configurés pour la création de widget en masse mais réduit le nombre d'erreur de l'assistant"></i>
          </sup>
        </label>
        <div class="col-lg-3">
          <input type="checkbox" class="configKey" data-l1key="isStrict" />
        </div>
      </div>

      <div class="form-group col-lg-6">
        <label class="col-lg-3 control-label">{{Mode Expert}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Permet de laisser l'utilisateur modifier les commandes manuellement"></i>
          </sup>
        </label>
        <div class="col-lg-3">
          <input type="checkbox" class="configKey" data-l1key="isExpert" />
        </div>
      </div>

    </div>

    <div class="row">
      <div class="form-group col-lg-6">
        <label class="col-lg-4 control-label">{{Logs : }}</label>
        <label class="col-lg-3 control-label">{{Plugin Verbose}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Affiche plus de logs que DEBUG"></i>
          </sup>
        </label>
        <div class="col-lg-3">
          <input type="checkbox" class="configKey" data-l1key="traceLog" />
        </div>
      </div>

      <div class="form-group col-lg-6">
        <label class="col-lg-3 control-label">{{Démon}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Vous pouvez choisir un autre niveau de log pour le démon"></i>
          </sup>
        </label>
        <div class="col-lg-6">
          <select class="form-control configKey needJCRefresh" data-l1key="daemonLog">
            <option value="parent">Même que le plugin</option>
            <option value="100">Debug</option>
            <option value="200">Infos</option>
            <option value="300">Warning</option>
            <option value="400">Erreur</option>
          </select>
        </div>
      </div>


    </div>

    <div class="row">
      <div class="form-group col-lg-6">
        <label class="col-lg-4 control-label">{{Sauvegarde : }}</label>
        <label class="col-lg-3 control-label">{{Nombre}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Nombre de copies de sauvegarde de vos préférences applicatives que vous souhaitez conserver"></i>
          </sup>
        </label>
        <div class="col-lg-5">
          <select class="form-control configKey" data-l1key="bkpCount">
            <option value="all">Toutes</option>
            <option value="1">1</option>
            <option value="5">5</option>
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="30">30</option>
          </select>
        </div>
      </div>

    </div>
    <br />

    <!-- LOCALISATION ZONE -->
    <div class="alert alert-success text-center">{{Localisation}}</div>

    <div class="description text-center">Point de répère par défaut pour calculer les distances avec les positions de chaque équipement et pour centrer la carte lors de l'initialisation.<br />
      Par défaut la localisation de votre Jeedom est prise en compte, à défaut ça sera Paris !</div>
    <div class="row">
      <div class="form-group col-lg-6">
        <label class="col-lg-6 control-label">{{Latitude}}</label>
        <div class="col-lg-6">
          <input class="configKey form-control" type="string" data-l1key="latitude" placeholder="<?= config::bykey('info::latitude', 'core', 'celle de paris :)'); ?>" />
        </div>
      </div>
      <div class="form-group col-lg-6">
        <label class="col-lg-3 control-label">{{Longitude}}</label>
        <div class="col-lg-6">
          <input class="configKey form-control" type="string" data-l1key="longitude" placeholder="<?= config::bykey('info::longitude', 'core', 'celle de paris :)'); ?>" />
        </div>
      </div>
    </div>

    <br />

    <!-- BEGIN DANGER ZONE -->
    <div class="alert alert-danger" style="text-align:center;">
      <i class="fas fa-skull-crossbones"></i>&nbsp;&nbsp;&nbsp;&nbsp;{{Attention vous entrez en zone de Dangers !}}&nbsp;&nbsp;&nbsp;&nbsp;<i class="fas fa-skull-crossbones"></i>
    </div>
    <div class="actions-detail" style="text-align:center;">
    </div>

    <div class="row">
      <div class="form-group col-lg-6">
        <label class="col-sm-6 control-label">{{Réinitialiser l'ensemble des équipements}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Fait une réinitialisation de l'ensemble des équipements.<br>Vous aurez donc des appareils vierges."></i>
          </sup>
        </label>
        <div class="col-sm-1">
          <a class="btn btn-danger" id="reinitAllEq"><i class="fas fa-exclamation-triangle"></i> {{Réinitialiser}}
          </a>
        </div>
      </div>

      <div class="form-group col-lg-6">
        <label class="col-sm-3 control-label">{{Suppprimer tous les widgets}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Supprime tous les widgets,<br>et réinitialise la configuration de tous les équipements<br>Vos équipements seront vierges et vous devrez recréer tous vos widgets."></i>
          </sup>
        </label>
        <div class="col-sm-1">
          <a class="btn btn-danger" id="removeAllWidgets"><i class="fas fa-exclamation-triangle"></i> {{Supprimer}}
          </a>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="form-group col-lg-4">
        <label class="col-sm-6 control-label">{{Exporter}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Permet d'exporter vos widgets ou les personnalisations de vos widgets"></i>
          </sup>
        </label>
        <div class="col-sm-6 input-group" style="display:inline-flex;">
          <span class="input-group-btn">
            <input type="file" accept=".json" id="importConfig-input" style="display:none;">
            <a class="btn btn-warning exportConf" id="exportWidgetConf" data-type="exportWidgets"><i class="fa fa-save"></i> {{Widgets}}</a>
            <a class="btn btn-warning exportConf" style="margin-left:10px" id="exportCustomDataWidgetConf" data-type="exportCustomData"><i class="fa fa-save"></i> {{Personnalisation}}</a>
          </span>

        </div>
      </div>

      <div class="form-group col-lg-2">
        <label class="col-sm-6 control-label">{{Importer}}
          <sup>
            <i class="fas fa-question-circle floatright" title="Permet d'importer les configurations de vos wigets et/ou de vos personnalisations de widgets"></i>
          </sup>
        </label>
        <div class="col-sm-6 input-group" style="display:inline-flex;">
          <span class="input-group-btn">
            <input type="file" accept=".json" id="importConfig-input" style="display:none;">
            <a class="btn btn-primary importConf" id="importWidgetConf" data-type="exportWidgets"><i class="fa fa-cloud-upload-alt"></i> {{Importer}}</a>
          </span>

        </div>
      </div>

      <div class="form-group col-lg-6">
        <div class="form-group">
          <label class="col-sm-3 control-label">{{Utilisation des widgets}}
            <sup>
              <i class="fas fa-question-circle floatright" title="Permet de savoir dans combien d'équipements, chaque widget est utilisé."></i>
            </sup>
          </label>
          <div class="col-sm-1">
            <a class="btn btn-default" id="listWidget"><i class="fas fa-clipboard-list"></i> {{Lister}}
            </a>
          </div>
          <div class="col-sm-8 filterOption"> N'afficher que les widgets
            <label class="radio-inline"><input type="radio" name="filter" id="unusedOnly" /> non-utilisés</label>
            <label class="radio-inline"><input type="radio" name="filter" id="unexistingOnly" /> non-existants</label>
            <label class="radio-inline"><input type="radio" name="filter" id="all" checked /> tous</label>
          </div>
        </div>
        <div class="resultListWidget">
        </div>
      </div>
    </div>
    <!-- END DANGER ZONE -->

  </fieldset>
</form>

<?php include_file('desktop', 'configuration.JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'generic.JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'JeedomConnect', 'css', 'JeedomConnect'); ?>
<?php include_file('desktop', 'md/css/materialdesignicons', 'css', 'JeedomConnect'); ?>