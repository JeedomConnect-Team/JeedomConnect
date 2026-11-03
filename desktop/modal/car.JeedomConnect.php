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

/** @var JeedomConnect $eqLogic */
$eqLogic = eqLogic::byId(init('eqLogicId'));
if (!is_object($eqLogic)) {
  throw new Exception('{{Équipement introuvable}}');
}

// widget type -> name / image, like the widget list of the equipment page
sendVarToJS('jcCarEqLogicId', $eqLogic->getId());
sendVarToJS('jcCarTypeNames', JeedomConnect::getWidgetParam());
sendVarToJS('jcCarTypeImages', JeedomConnectWidget::getElementImgPath());
sendVarToJS('jcCarDefaultImage', plugin::byId('JeedomConnect')->getPathImgIcon());
?>

<div id="jc-car" style="padding:0 10px;">
  <div style="display:none;" id="jc-car-alert"></div>
  <div class="alert alert-info" style="margin-top:5px;">
    {{Ce qui s'affiche dans Android Auto et sur les voitures avec Android intégré pour cet équipement. Les mêmes réglages que dans l'application : Préférences → Voiture. Chaque modification est enregistrée immédiatement.}}
  </div>

  <legend><i class="fas fa-sitemap"></i> {{Navigation}}</legend>
  <div id="jc-car-navigation" style="margin-bottom:15px;"></div>

  <legend>
    <i class="fas fa-star"></i> {{Favoris}} <span id="jc-car-favorites-count" class="label label-info"></span>
  </legend>
  <div class="description" style="margin-bottom:5px;">
    {{Glissez pour changer l'ordre. Au-delà du nombre de tuiles accepté par la voiture (6 au minimum), une tuile « Plus » ouvre la suite : placez les plus utiles en premier.}}
  </div>
  <ul id="jc-car-favorites" class="list-group" style="margin-bottom:15px;"></ul>

  <legend><i class="fas fa-plus-circle"></i> {{Ajouter des favoris}}</legend>
  <div class="input-group" style="margin-bottom:5px;">
    <input id="jc-car-filter" class="form-control roundedLeft" placeholder="{{Rechercher un widget, une pièce...}}">
    <span class="input-group-btn">
      <a id="jc-car-add" class="btn btn-success roundedRight"><i class="fas fa-plus"></i> {{Ajouter la sélection}}</a>
    </span>
  </div>
  <ul id="jc-car-available" class="list-group" style="max-height:300px; overflow-y:auto;"></ul>

  <div class="description">
    {{Les actions protégées par un code ne sont pas disponibles en voiture, et celles qui demandent une confirmation la demanderont sur l'écran de la voiture. Les caméras, images, pages web et historiques ne peuvent pas s'afficher en voiture.}}
  </div>
</div>

<?php include_file('desktop', 'car.JeedomConnect', 'js', 'JeedomConnect'); ?>
