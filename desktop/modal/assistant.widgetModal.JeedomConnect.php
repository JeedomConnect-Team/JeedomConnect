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

?>

<style>
	#widgetImg {
		display: block;
		margin-left: auto;
		margin-right: auto;
		width: 100px;
		margin-bottom: 25px;
		margin-top: 15px;
	}
</style>

<link href="/plugins/JeedomConnect/desktop/css/md/css/materialdesignicons.css" rel="stylesheet">

<div>
	<div style="display:none;" id="widget-alert"></div>
	<div class="col-sm-12">
		<div class="input-group pull-right widgetMenu" style="display:inline-flex;">
			<span class="input-group-btn">
				<!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
				<a class="btn btn-sm btn-danger roundedLeft removeWidget"><i class="fas fa-minus-circle"></i> {{Supprimer}}
				</a><a class="btn btn-sm btn-default duplicateWidget"><i class="far fa-copy"></i> {{Dupliquer}}
				</a><a class="btn btn-sm btn-warning hideWidget"><i class="fas fa-times"></i> {{Annuler}}
				</a><a class="btn btn-sm btn-primary saveWidget" data-close="false"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
				</a><a class="btn btn-sm btn-success roundedRight saveWidget" data-close="true"><i class="fas fa-check-circle"></i> {{Sauvegarder et Fermer}}
				</a>
			</span>
		</div>
	</div>
	<div class="col-sm-12 jcItemModal">
		<div class="col-xs-2">
			<div class="form-group">
				<h3>Choix du <span class="itemType">widget</span></h3>
				<select name="widgetsList" id="widgetsList-select">
				</select>
			</div>
			<div class="form-group autoFillWidgetCmds" style="display: none;">
				<a class="btn btn-sm btn-success btnAutoFillWidgetCmds"><i class="fas fa-magic"></i> {{Création automatique}}</a>
			</div>
			<div class="form-group searchForJCeq" style="display: none;">
				<a class="btn btn-sm btn-info btnSearchForJCeq"><i class="fas fa-magic"></i> {{Choisir un équipement JC}}</a>
			</div>
			<div class="form-group">
				<img id="widgetImg" />
				<div class="alert alert-info" id="widgetDescription"></div>
				<a class="btn btn-info btn-xs" id="widgetDocLink" href="#" target="_blank" style="display:none; margin-bottom: 10px;"><i class="fas fa-book"></i> {{Documentation du widget}}</a>
				<div class="alert alert-info" id="widgetVariables"></div>
				<div class="alert alert-info" id="widgetInclusion" style="display:none;"></div>
				<div class="alert alert-info" id="widgetPerso" style="display:none;"></div>
			</div>
		</div>

		<div class="col-xs-10 borderLef">
			<h3 style="margin-left:25px;">Options du <span class="itemType">widget</span></h3><br>
			<div style="margin-left:25px; font-size:12px; margin-top:-20px; margin-bottom:15px;">Les options marquées d'une étoile sont obligatoires.</div>
			<div id="missingOptions" style="margin-left:25px; font-size:12px; margin-bottom:15px;color:red"></div>
			<form class="form-horizontal widgetForm" style="overflow: hidden;">
				<ul id="widgetOptions" style="padding-left:10px; list-style-type:none;">
				</ul>
			</form>
		</div>
	</div>

</div>

<?php include_file('desktop', 'assistant.JeedomConnect', 'css', 'JeedomConnect'); ?>
<?php include_file('desktop', 'JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'assistant.JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'widget.JeedomConnect', 'js', 'JeedomConnect'); ?>