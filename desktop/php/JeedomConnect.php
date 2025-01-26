<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}

$customPath = config::byKey('userImgPath', 'JeedomConnect');
sendVarToJS('userImgPath', $customPath);

// Déclaration des variables obligatoires
$plugin = plugin::byId('JeedomConnect');
sendVarToJS('eqType', $plugin->getId());

$isExpert = config::byKey('isExpert', 'JeedomConnect') ? true : false;
sendVarToJS('isJcExpert', $isExpert);

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

/** @var array<JeedomConnect> $eqLogics */
$eqLogics = JeedomConnect::getAllJCequipment();;

list($widgetInError, $widgetInWarning, $roomInError) = JeedomConnectWidget::checkCmdSetupInWidgets();

foreach ($roomInError as $widgetId) {
	JCLog::debug("removing room for widget Id " . $widgetId);
	//remove key room for widget with unexisting room
	JeedomConnectWidget::updateConfig($widgetId, 'room');
}

$widgetArray = JeedomConnectWidget::getWidgets();

$jcFilter = $_GET['jcFilter'] ?? '';
$orderBy = $_GET['jcOrderBy'] ?? config::byKey('jcOrderByDefault', 'JeedomConnect', 'object');
$widgetSearch = $_GET['jcSearch'] ?? '';

$jcComponentFilter = $_GET['jcComponentFilter'] ?? '';
$componentSearch = $_GET['jcComponentSearch'] ?? '';

sendVarToJS('jcOrderBy', $orderBy);

$widgetArray = JeedomConnectUtils::orderWidget($widgetArray, $orderBy);

$allConfig = JeedomConnect::getWidgetParam();
// JCLog::debug('--------------------- $allConfig =>' . json_encode($allConfig));
$widgetTypeArray = array();
$componentTypeArray = array();

$listWidget = '';
$listComponent = '';

$hasErrorPage = $hasWarningPage = false;
foreach ($widgetArray as $widget) {
	$needSign = '';
	$hasError = '';
	$img = $widget['img'];

	$opacity = $widget['enable'] ? '' : 'disableCard';
	$widgetName = $widget['nameDisplayed'] ?: $widget['name'];
	$widgetRoom = $widget['roomName'];;
	$id = $widget['id'];
	$widgetType = $widget['type'];

	if (in_array($id, $widgetInError) || $widgetName == 'inconnu' || trim($widgetName) == '') {
		$hasError = 'hasError';
		$tooltip = ($widgetName == 'inconnu' || trim($widgetName) == '') ? 'Nom du widget à modifier' : 'Commandes orphelines';
		$needSign = '<i class="fas fa-exclamation-circle" style="color: var(--al-danger-color) !important;" title="' . $tooltip . '"></i>';
		$hasErrorPage =  true;
	} elseif (in_array($id, $widgetInWarning)) {
		$hasError = 'hasWarning';
		$tooltip =  'Equipement inexistant ou désactivé';
		$needSign = '<i class="fas fa-exclamation-circle" style="color: var(--al-warning-color) !important;" title="' . $tooltip . '"></i>';
		$hasWarningPage =  true;
	}
	$name = '<span class="label labelObjectHuman" style="text-shadow : none;">' . $widgetRoom . '</span><br><strong> ' . $widgetName . ' ' .  $needSign . '</strong>';

	$tmpItem = '<img src="' . $img . '"/>';
	$tmpItem .= '<br>';
	$tmpItem .= '<span class="name">' . $name . '</span>';
	$tmpItem .= '</div>';

	if ($widgetType != 'component') {

		//used later by the filter select item
		if (!in_array($widgetType, $widgetTypeArray, true) && key_exists($widgetType, $allConfig)) $widgetTypeArray[$widgetType] = $allConfig[$widgetType];

		$styleHide = ($jcFilter == '') ? '' : ($jcFilter == $widgetType ? '' : 'style="display:none;"');

		$listWidget .= '<div class="widgetDisplayCard cursor  widget ' . $hasError . ' ' . $opacity . '" ' . $styleHide . ' title="id=' . $id . '" data-widget_id="' . $id . '" data-widget_type="' . $widgetType . '">';
		$listWidget .= $tmpItem;
	} else {

		if (!in_array($widget['component'], $componentTypeArray, true)) $componentTypeArray[$widget['component']] = $allConfig[$widget['component']];

		$styleHide = ($jcComponentFilter == '') ? '' : ($jcComponentFilter == $widgetType ? '' : 'style="display:none;"');

		$listComponent .= '<div class="componentDisplayCard cursor  component ' . $hasError . ' ' . $opacity . '" ' . $styleHide . ' title="id=' . $id . '" data-widget_id="' . $id . '" data-widget_type="' . $widget['component'] . '">';
		$listComponent .= $tmpItem;
	}
}


// $optionsOrderBy = $_GET['jcOrderBy'] ?? '';
$optionsOrderBy = '';
$orderByArray = array(
	"object" => "Pièce",
	"name" => "Nom",
	"type" => "Type"
);

foreach ($orderByArray as $key => $value) {
	$selected = ($key ==  $orderBy) ? 'selected' : '';
	$optionsOrderBy .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
}


asort($widgetTypeArray);
asort($componentTypeArray);

$typeSelection2 = '';
$hasSelected = false;
foreach ($widgetTypeArray as $key => $value) {
	$selected = ($key ==  $jcFilter) ? 'selected' : '';
	$hasSelected = $hasSelected || ($key ==  $jcFilter);
	$typeSelection2 .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
}
$sel = $hasSelected ? '' : 'selected';
$typeSelection = '<option value="none" ' . $sel . '>Tous</option>' . $typeSelection2;

$typeSelectionComponent2 = '';
$hasSelectedComponent = false;
foreach ($componentTypeArray as $key => $value) {
	$selectedComponent = ($key ==  $jcComponentFilter) ? 'selected' : '';
	$hasSelectedComponent = $hasSelectedComponent || ($key ==  $jcFilter);
	$typeSelectionComponent2 .= '<option value="' . $key . '" ' . $selectedComponent . '>' . $value . '</option>';
}
$selComponent = $hasSelectedComponent ? '' : 'selected';
$typeSelectionComponent = '<option value="none" ' . $selComponent . '>Tous</option>' . $typeSelectionComponent2;

$displayWarning = config::byKey('displayWarning', 'JeedomConnect', 'false') == 'false';

$hasDNSConnexion = JeedomConnectUtils::hasDNSConnexion();
$pollingDefault = $hasDNSConnexion ? 'checked' : '';
$wsDisable = $hasDNSConnexion ? 'disabled' : '';

$jeedomVersion  = jeedom::version() ?? '0';
$displayInfoValue = version_compare($jeedomVersion, '4.3.0', '>=');


$jsClickCommunityPost = 'createCommunityPostJC';

?>

<div class="row row-overflow">
	<!-- Page d'accueil du plugin -->
	<div class="col-xs-12 eqLogicThumbnailDisplay">

		<div class="row">
			<div class="col-sm-10" style="min-height:200px">
				<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
				<!-- Boutons de gestion du plugin -->
				<div class="eqLogicThumbnailContainer">
					<div class="cursor eqLogicAction " data-action="add" style="color:rgb(27,161,242);">
						<i class="fas fa-mobile-alt"></i>
						<br>
						<span>{{Ajouter un Appareil}}</span>
					</div>
					<div class="cursor eqLogicAction " data-action="addWidget" style="color:rgb(27,161,242);">
						<i class="fas fa-icons"></i>
						<br>
						<span style="color:var(--txt-color)">{{Ajouter un Widget}}</span>
					</div>

					<div class="cursor eqLogicAction " data-action="addComponent" style="color:rgb(27,161,242);">
						<i class="fas fa-tools"></i>
						<br>
						<span style="color:var(--txt-color)">{{Ajouter un Composant}}</span>
					</div>

					<div class="cursor eqLogicAction " data-action="addWidgetBulk" style="color:rgb(27,161,242);">
						<i class="fas fa-magic"></i>
						<br>
						<span style="color:var(--txt-color)">{{Création de widgets en masse}}</span>
					</div>

					<div class="cursor eqLogicAction logoSecondary" data-action="showNotifAll" style="color:rgb(27,161,242);">
						<i class="fas fa-comments"></i>
						<br>
						<span style="color:var(--txt-color)">{{Notifications multiples}}</span>
					</div>


					<div class="cursor eqLogicAction" data-action="showMaps" style="color:rgb(27,161,242);">
						<i class="fas fa-map-marked-alt"></i>
						<br>
						<span style="color:var(--txt-color)">{{Localisation}}</span>
					</div>

					<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
						<i class="fas fa-wrench"></i>
						<br>
						<span>{{Configuration}}</span>
					</div>

					<?php if ($hasErrorPage || $hasWarningPage) { ?>
						<div class="cursor eqLogicAction" data-action="showError" style="<?= $hasErrorPage ? 'color:red;' : 'color:orange;' ?>">
							<i class="fas fa-exclamation-circle"></i>
							<br>
							<span style="color:var(--txt-color)" id="spanWidgetErreur"><?= $hasErrorPage ? '{{Erreur}}' : '{{Warning}}' ?></span>
							<sup>
								<i class="fas fa-question-circle floatright" style="color: var(--al-info-color) !important;" title="Il semblerait que vous ayez quelques widgets avec de mauvaises commandes configurées (ou inexistantes).<br/>Vous pouvez les filtrer en appuyant sur ce bouton"></i>
							</sup>
						</div>
					<?php }
					?>
					<div class="cursor eqLogicAction logoSecondary" data-action="moreJcOptions" style="color:rgb(27,161,242);">
						<i class="fas fa-ellipsis-h"></i>
						<br>
						<span style="color:var(--txt-color)" id="spanMoreJcOptions" data-type="more">{{Plus d'options}}</span>
					</div>
				</div>
				<br />
				<div class="hideOptionMenu" style="display:none;">
					<div class="eqLogicThumbnailContainer">
						<div class="cursor eqLogicAction logoSecondary" data-action="showSummary" style="color:rgb(27,161,242);">
							<i class="fas fa-tasks"></i>
							<br>
							<span style="color:var(--txt-color)">{{Synthèse des widgets}}</span>
						</div>

						<div class="cursor eqLogicAction logoSecondary" data-action="showEquipmentSummary" style="color:rgb(27,161,242);">
							<i class="mdi mdi-devices"></i>
							<br>
							<span style="color:var(--txt-color)">{{Synthèse des équipments JC}}</span>
						</div>

						<!--
						Start Generic Types
						HACK Remove when gentype config in plugin is not needed anymore
						-->
						<div class="cursor eqLogicAction logoSecondary" data-action="gotoGenTypeConfig" style="color:rgb(27,161,242);">
							<i class="fas fa-building"></i>
							<br>
							<span style="color:var(--txt-color)">{{Config types génériques}}</span>
						</div>
						<!-- End Generic Types -->
					</div>
				</div>
			</div>
			<div class="col-sm-2">
				<div class="hideWhileShowqrcode">
					<legend><i class=" fas fa-comments"></i> {{Community}}</legend>
					<!-- Boutons de gestion du plugin -->
					<div class="eqLogicThumbnailContainer">
						<div class="cursor eqLogicAction logoSecondary" data-action="<?php echo $jsClickCommunityPost; ?>" style="color:rgb(27,161,242);">
							<i class="fas fa-ambulance"></i>
							<br>
							<span style="color:var(--txt-color)">{{Créer un post Community}}</span>
						</div>
						<div class="cursor eqLogicAction logoSecondary" data-action="showCommunity" style="color:rgb(27,161,242);">
							<i class="fas fa-question-circle"></i>
							<br>
							<span>{{Infos}}</span>
							<div style="display:none">
								<?php if ($displayWarning) { ?>
									<span class="displayJCWarning">
										Pour chacun des sujets que vous partagez sur le <a href="https://community.jeedom.com/tag/plugin-jeedomconnect" target="_blank"><span style="color:rgb(27,161,242);"> forum community</span> <i class="fas fa-external-link-alt"></i></a>
										afin de vous aider le plus facilement et rapidement possible, merci de <u><strong>systématiquement</strong></u> partager les informations
										de votre installation, qui sont disponibles en seulement un clic sur le bouton 'Community Infos' en haut à droite de la page principale de JeedomConnect (JC pour les intimes) !
										<br /><br />
										Ces informations nous permettent de savoir quelle version vous utilisez afin de mieux répondre à votre demande.
										<br /><br />
										<span class="description timerSpan">Affichage des boutons dans : <span class="timer"></span> sec</span>
									</span>
								<?php } ?>
								<span class="txtInfoPlugin">
									Si vous avez des interrogations, postez un message sur le <a href="https://community.jeedom.com/tag/plugin-jeedomconnect" target="_blank"><span style="color:rgb(27,161,242);"> forum community</span> <i class="fas fa-external-link-alt"></i></a>
									<br /><i>après avoir vérifié que le sujet n'a pas déjà été traité !</i>
									<br /><br />Appuyez sur le bouton 'copier' en bas de la fenêtre pour récupérer l'ensemble des informations affichées, et partagez/collez-les à chaque nouveau post sur le forum !
									<br /><br />
								</span>
							</div>
						</div>

					</div>
				</div>
				<?php
				if (config::byKey('showQrCodeMainPage', 'JeedomConnect', false)) { ?>
					<div class="showqrcode-content" style="display:none;">
						<legend><i class="mdi mdi-qrcode-scan"></i> {{Qr Code}}</legend>
						<img class="showqrcode" src='' width='150px' height="150px">
					</div>
				<?php } ?>
			</div>
		</div>

		<!--   PANEL DES EQUIPEMENTS  -->
		<legend style="margin-top:10px"><i class="fas fa-mobile-alt fa-lg"></i> {{Mes appareils}}</legend>
		<!-- Champ de recherche -->
		<div class="input-group" style="margin:10px 5px;">
			<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
			<div class="input-group-btn">
				<a id="bt_resetSearch" class="btn roundedRight" style="width:30px"><i class="fas fa-times"></i></a>
			</div>
		</div>
		<!-- Liste des équipements du plugin -->
		<div class="eqLogicThumbnailContainer">
			<?php
			$allEqToDisplay = '';
			foreach ($eqLogics as $eqLogic) {

				$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
				// $allEqToDisplay .= '<div class="eqLogicDisplayCardParent">';
				$allEqToDisplay .= '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';

				$imgPlugin = JeedomConnectUtils::getCustomPathIcon($eqLogic);
				$apiKey = $eqLogic->getConfiguration('apiKey');
				$imgQrCode = 'plugins/JeedomConnect/data/qrcodes/' . $apiKey . '.png';
				if (!file_exists('/var/www/html/' . $imgQrCode)) {
					$eqLogic->generateQRCode();
				}
				$allEqToDisplay .= '<img class="eqlogic-qrcode" data-qrcode="' . $imgQrCode . '" data-plugin="' . $imgPlugin . '" src="' . $imgPlugin . '"/>';
				$allEqToDisplay .= '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
				$allEqToDisplay .= '<span>';
				// $allEqToDisplay .= '</div>';
				$allEqToDisplay .= '<a class="btn btn-success btnAssistant" title="Assistant configuration"><i class="fas fa-icons"></i></a>&nbsp;';
				$allEqToDisplay .= '<a class="btn btn-success btnNotification" title="Assistant notification"><i class="fas fa-comment-dots"></i></a>&nbsp;';
				$allEqToDisplay .= '<a class="btn btn-success btnGeofencing" title="Gérer le geofencing"><i class="fas fa-crosshairs"></i></a>';
				$allEqToDisplay .= '</span>';
				$allEqToDisplay .= '</div>';
			}

			echo $allEqToDisplay;
			?>
		</div>
		<!--  FIN --- PANEL DES EQUIPEMENTS  -->


		<!-- Champ de recherche widget -->
		<div class="input-group" style="margin:10px 5px;">
			<input class="form-control roundedLeft jcInSearch" placeholder="{{Rechercher sur le nom d'un élément ou son id}}" id="in_search" value="<?= $widgetSearch ?>" />
			<div class="input-group-btn">
				<a id="bt_resetSearchWidget" class="btn roundedRight jcResetSearch" data-input="in_search" style=" width:30px"><i class="fas fa-times"></i></a>
			</div>
		</div>

		<!--   PANEL DES WIDGETS  -->
		<legend>
			<a class="accordion-toggle" data-toggle="collapse" href="#collapseWidgets">
				<i class="fas fa-table"></i> {{Mes widgets}} <span id="coundWidget"></span>
			</a>
			<div class="pull-right">
				<span style="margin-right:10px">{{Tri}}
					<select id="widgetOrder" class="updateOrderWidget" style="width:100px">
						<?php
						echo $optionsOrderBy;
						?>
					</select>
				</span>
				<span>{{Filtre}}
					<select id="widgetTypeSelect" class="JC jcItemSelect widget" data-type="widget" style="width:auto">
						<?php
						echo $typeSelection;
						?>
					</select>
				</span>
				<span id="eraseFilterChoice">
					<!-- <i class="fas fa-times"></i> -->
					<i class="fas fa-trash-alt"></i>
				</span>
			</div>
		</legend>
		<div id="collapseWidgets" class="panel-collapse collapse in">
			<div class="input-group" style="display:none">
				<!-- leave it empty -->
			</div>
			<!-- Liste des widgets du plugin -->
			<div class="eqLogicThumbnailContainer" id="widgetsList-div" style="background-color:white">
				<?php
				echo $listWidget;
				?>
			</div>
		</div>
		<!--  FIN ---  PANEL DES WIDGETS  -->

		<!--   PANEL DES COMPOSANTS  -->
		<legend>
			<a class="accordion-toggle" data-toggle="collapse" href="#collapseComponents">
				<i class="fas fa-table"></i> {{Mes composants}} <span id="coundComponent"></span>
			</a>
			<div class="pull-right">
				<span>{{Filtre}}
					<select id="componentTypeSelect" class="JC jcItemSelect component" data-type="component" style="width:auto">
						<?php
						echo $typeSelectionComponent;
						?>
					</select>
				</span>
			</div>
		</legend>
		<div id="collapseComponents" class="panel-collapse collapse in">
			<div class="input-group" style="display:none">
				<!-- leave it empty -->
			</div>

			<!-- Liste des composant du plugin -->
			<div class="eqLogicThumbnailContainer" id="componentsList-div" style="background-color:white">
				<?php
				echo $listComponent;
				?>
			</div>
		</div>
		<!--  FIN ---  PANEL DES COMPOSANTS  -->

	</div> <!-- /.eqLogicThumbnailDisplay -->


	<!-- Page de présentation de l'équipement -->
	<div class="col-xs-12 eqLogic" style="display: none;">
		<!-- barre de gestion de l'équipement -->
		<div class="input-group pull-right" style="display:inline-flex;">
			<span class="input-group-btn">
				<!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
				<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
				</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
				</a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
				</a>
			</span>
		</div>
		<!-- Onglets -->
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i><span class="hidden-xs"> {{Équipement}}</span></a></li>
			<li role="presentation"><a href="#commandtabInfo" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-info-circle"></i><span class="hidden-xs"> {{Commandes Infos}}</span></a></li>
			<li role="presentation"><a href="#commandtabPosition" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-map-marker-alt"></i><span class="hidden-xs"> {{Commandes Position}}</span></a></li>
			<li role="presentation"><a href="#commandtabAction" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-play-circle"></i><span class="hidden-xs"> {{Commandes Actions}}</span></a></li>
			<li role="presentation"><a href="#commandtabNotification" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-comment-dots"></i><span class="hidden-xs"> {{Commandes Notifications}}</span></a></li>
		</ul>
		<div class="tab-content">
			<!-- Onglet de configuration de l'équipement -->
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<!-- Partie gauche de l'onglet "Equipements" -->
				<!-- Paramètres généraux de l'équipement -->
				<form class="form-horizontal">
					<fieldset>
						<div class="col-lg-7 jeedomConnect">
							<legend><i class="fas fa-wrench"></i> {{Général}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Nom de l'appareil}}</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
									<input type="text" class="eqLogicAttr form-control needJCRefresh" data-l1key="name" placeholder="{{Nom de l'équipement}}" />
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Objet parent}}</label>
								<div class="col-sm-7">
									<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
										<option value="">{{Aucun}}</option>
										<?php
										$options = '';
										foreach ((jeeObject::buildTree(null, false)) as $object) {
											$options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
										}
										echo $options;
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Catégorie}}</label>
								<div class="col-sm-9">
									<?php
									foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
										echo '<label class="checkbox-inline">';
										echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
										echo '</label>';
									}
									?>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Options}}</label>
								<div class="col-sm-7">
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" />{{Activer}}</label>
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" />{{Visible}}</label>
								</div>
							</div>

							<div class="form-group" style="display:none;">
								<label class="col-sm-3 control-label">{{Type}}</label>
								<div class="col-sm-7">
									<select id="sel_type" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="type">
										<option value="mobile">mobile</option>
									</select>
								</div>
							</div>
							<br>

							<legend><i class="fa fa-cogs"></i> {{Paramètres}}</legend>
							<!-- ELT TO KNOW IF IMPORTANT DATA HAS BEEN CHANGED -->
							<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="qrRefresh" type="checkbox" style="display:none;">
							<!-- END -->
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Activer la connexion par Websocket}}</label>
								<div class="col-sm-7">
									<input class="eqLogicAttr form-control checkJcConnexionOption needJCRefresh" data-l1key="configuration" data-l2key="useWs" type="checkbox" placeholder="{{}}" <?= $wsDisable ?>>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Activer le polling}}
									<sup>
										<i class="fas fa-question-circle floatright" style="color: var(--al-info-color) !important;" title="Cette option est plus que recommandée si vous utilisez les DNS Jeedom.<br/>(incompatible avec l'option websocket)"></i>
									</sup>
								</label>
								<div class="col-sm-7">
									<input class="eqLogicAttr form-control checkJcConnexionOption needJCRefresh" data-l1key="configuration" data-l2key="polling" type="checkbox" placeholder="{{}}" <?= $pollingDefault ?>>
									<?php
									if ($hasDNSConnexion) {
									?>
										<br /><span class="description">L'utilisation des DNS Jeedom semble apparaître dans votre configuration.<br />Si c'est bien le cas, pour le bon fonctionnement de l'application, l'activation du polling est recommandée/nécessaire.</span>
									<?php
									}
									?>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Utilisateur}}</label>
								<div class="col-sm-7">
									<select class="eqLogicAttr configuration form-control needJCRefresh" data-l1key="configuration" data-l2key="userId">
										<option value="">{{Aucun}}</option>
										<?php
										foreach (user::all() as $user) {
											echo '<option value="' . $user->getId() . '">' . $user->getLogin() . '</option>';
										}
										?>
									</select>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Mot de passe}}
									<sup>
										<i class="fas fa-question-circle floatright" style="color: var(--al-info-color) !important;" title="Vous avez la possibilité d'utiliser un mot de passe alphanumérique pour confirmer une action<br>Il est nécessaire de le définir ici pour s'en servir dans l'application."></i>
									</sup>
								</label>
								<div class="col-sm-6 pass_show">
									<div class="input-group">
										<input type="text" class="eqLogicAttr form-control inputPassword" data-l1key="configuration" data-l2key="pwdAction" placeholder="{{Mot de passe pour confirmer une action sur l'application}}" />
										<span class="input-group-btn">
											<a class="btn btn-default form-control bt_showPass roundedRight"><i class="fas fa-eye"></i></a>
										</span>
									</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Assistant}}</label>
								<div class="col-sm-7">
									<a class="btn btn-success" id="assistant-btn"><i class="fa fa-wrench"></i> {{Configurer l'appareil}}
									</a>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Configuration de l'équipement}}</label>
								<div class="col-sm-7 input-group" style="display:inline-flex;">
									<span class="input-group-btn">
										<input type="file" accept=".json" id="import-input" style="display:none;">
										<a class="btn btn-warning" id="export-btn"><i class="fa fa-save"></i> {{Exporter}}</a>
										<a class="btn btn-primary" id="import-btn"><i class="fa fa-cloud-upload-alt"></i> {{Importer}}</a>
										<a class="btn btn-default" id="copy-btn"><i class="fas fa-copy"></i> {{Copier vers}}</a>
										&nbsp;&nbsp;<i class="fas fa-question-circle floatright" style="color: var(--al-info-color) !important;" title="Partagez votre configuration sur un autre équipement"></i>
									</span>

								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Appareil enregistré}}</label>
								<div class="col-sm-7" style="display:inline-flex;">
									<span class="eqLogicAttr label" style="font-size:1em!important;margin-right:5px;" data-l1key="configuration" type="text" data-l2key="deviceName"></span>
									<a class="btn btn-danger" id="removeDevice"><i class="fa fa-minus-circle"></i> {{Détacher}} </a>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Notifications}}</label>
								<div class="col-sm-7">
									<a class="btn btn-success" id="notifConfig-btn"><i class="fa fa-wrench"></i> {{Configurer}}
									</a>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Accès scénarios}}</label>
								<div class="col-sm-7">
									<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="scenariosEnabled" type="checkbox" placeholder="{{}}">
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Accès à la timeline}}</label>
								<div class="col-sm-7">
									<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="timelineEnabled" type="checkbox" placeholder="{{}}">
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Accès Interface web}}</label>
								<div class="col-sm-7">
									<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="webviewEnabled" type="checkbox" placeholder="{{}}">
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Masquer la batterie sur page Equipement Jeedom}}</label>
								<div class="col-sm-7">
									<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="hideBattery" type="checkbox">
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Commande info Volume}}</label>
								<div class="col-sm-7">
									<select class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="volume">
										<option value="all">{{Toutes}}</option>
										<?php
										foreach (JeedomConnect::$_volumeType as $key => $value) {
											echo '<option value="' . $key . '">' . $value . '</option>';
										}
										?>
									</select>
									<span class="description italic descConfigVolume"><?= "Si 'Toutes', format de la commande : " . join(";", array_values(JeedomConnect::$_volumeType)) . ";" ?></span>
								</div>
							</div>


							<legend><i class="fas fa-map-marked-alt"></i>{{Informations Position}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Ajouter données à la position}}
									<sup>
										<i class="fas fa-question-circle floatright" style="color: var(--al-info-color) !important;" title="Consulter la documentation pour connaitre le détail des données ajoutées."></i>
									</sup>
								</label>
								<div class="col-sm-7">
									<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="addAltitude" type="checkbox" placeholder="{{}}">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Afficher la position sur la carte globale}}</label>
								<div class="col-sm-7">
									<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="displayPosition" type="checkbox" placeholder="{{}}">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Personnaliser le repère}}</label>
								<div class="col-sm-7">
									<a class="btn btn-success roundedRight imagePicker"><i class="fas fa-check-square">
										</i> Choisir </a>
									<a data-id="icon-div" class="removeImage">
										<img class="customImg" src='' style="width:25px;" />
									</a>
									<input class="eqLogicAttr form-control removeImage" data-l1key="configuration" data-l2key="customImg" type="text" placeholder="{{}}" value="" style="display:none">
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Personnaliser les Geofencing}}</label>
								<div class="col-sm-7">
									<a class="btn btn-success roundedRight showGeofence"><i class="fas fa-crosshairs"></i> Voir</a>
								</div>
							</div>


						</div>

						<!-- Partie droite de l'onglet "Équipement" -->
						<!-- Affiche l'icône du plugin par défaut mais vous pouvez y afficher les informations de votre choix -->
						<div class="col-lg-5 jeedomConnect">
							<legend><i class="fas fa-info"></i> {{Informations}}</legend>
							<div class="form-group">
								<div class="alert alert-info" style="width:300px; margin: 10px auto;text-align:center;">
									Utilisez l'assistant de configuration pour gérer l'interface de l'application.<br />
									Dans la partie Login de l'application, scannez directement le QR Code.
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Clé API :}}</label>
								<div class="col-sm-9">
									<span class="eqLogicAttr label label-info" style="font-size:1em;" data-l1key="configuration" type="text" data-l2key="apiKey"></span>

									<!-- <a class="btn btn-default form-control btRegenerateApiKey roundedRight" style="width:30px"><i class="fas fa-sync"></i></a> -->
									<a class="btRegenerateApiKey" style="padding-left:10px" title="Regénérer la clé API de cet équipement"><i class="fas fa-sync"></i></a>
									<sup>
										<i class="fas fa-question-circle floatright" style="color: var(--al-info-color) !important;" title="Regénérer la clé API de cet équipement"></i>
									</sup>

								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{QR Code :}}</label>
								<div class="qrCodeImg">
									<div class="row">
										<div class="col-sm-6">
											<img id="img_config" class="img-responsive" style="margin:10px auto; max-height : 250px;" />
										</div>
									</div>

									<div class="row" style="margin: 0px auto;text-align:center;">
										<span class="eqNameQrCode" style="font-size:1.2em;font-weight: bold;"></span>
									</div>
									<div class="row" style="margin: 10px auto;text-align:center;">
										<a class="btn btn-infos" id="qrcode-regenerate"><i class="fa fa-qrcode"></i> {{Regénérer QR Code}}</a>
									</div>
								</div>


								<div class="row">
									<div class="alert alert-danger infoRefresh text-center" style=" margin: 10px auto; width:350px;display:none;">
										Il semblerait que vous ayez modifié certaines données utilisées dans le QR-Code. <br />
										Sauvegardez les modifications sur cet équipement pour accéder à un nouveau QR-Code valide !
									</div>
								</div>

								<div class="row">
									<div class="alert alert-warning" style=" margin: 10px auto; width:350px;">
										Veuillez re-générer le QR code si vous avez modifié :<br />
										<ul>
											<li>Les adresses dans la configuration du plugin</li>
											<li>L'utilisateur de cet équipement</li>
											<li>Le type de connexion de cet équipement</li>
										</ul>
									</div>
								</div>
							</div>

							<legend><i class="fa fa-bug"></i> {{Partager le fichier de configuration}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Debug Configuration}}</label>
								<div class="col-sm-7 input-group" style="display:inline-flex;">
									<span class="input-group-btn">
										<a class="btn btn-default" id="exportAll-btn"><i class="fa fa-file-export"></i> {{Partager}}</a>
										&nbsp;&nbsp;<i class="fas fa-question-circle floatright" style="color: var(--al-info-color) !important;" title="A la demande du développeur, partagez votre fichier de configuration finale"></i>
									</span>
								</div>
							</div>
						</div>
					</fieldset>
				</form>
				<hr>
			</div><!-- /.tabpanel #eqlogictab-->

			<!-- Onglet des commandes INFO de l'équipement -->
			<div role="tabpanel" class="tab-pane" id="commandtabInfo" class="commandtab">
				<!-- <a class="btn btn-default btn-sm pull-right cmdAction" data-action="add" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}}</a> -->
				<div class="table-responsive">
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th style="width: 50px;">#</th>
								<th style="width: 300px;">{{Nom}}</th>
								<th style="width: 100px;">{{Sous-type}}</th>
								<?php
								if ($displayInfoValue) {
								?>
									<th style="width: 300px;">{{Valeur}}</th>
								<?php } ?>
								<th style="width: 200px;">{{Options}}</th>
								<th style="width: 50px;">{{Ordre}}</th>
								<th style="width: 100px;"></th>
							</tr>
						</thead>
						<tbody class="cmd_info">
						</tbody>
					</table>
				</div>
			</div><!-- /.tabpanel #commandtabInfo-->

			<div role="tabpanel" class="tab-pane" id="commandtabPosition" class="commandtab">
				<div class="table-responsive">
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th style="width: 50px;">#</th>
								<th style="width: 300px;">{{Nom}}</th>
								<th style="width: 100px;">{{Sous-type}}</th>
								<?php
								if ($displayInfoValue) {
								?>
									<th style="width: 300px;">{{Valeur}}</th>
								<?php } ?>
								<th style="width: 200px;">{{Options}}</th>
								<th style="width: 50px;">{{Ordre}}</th>
								<th style="width: 100px;"></th>
							</tr>
						</thead>
						<tbody class="cmd_infoPosition">
						</tbody>
					</table>
				</div>
			</div><!-- /.tabpanel #commandtab-->

			<!-- Onglet des commandes ACTION -->
			<div role="tabpanel" class="tab-pane" id="commandtabAction" class="commandtab">
				<div class="table-responsive">
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th style="width: 50px;">#</th>
								<th style="width: 300px;">{{Nom}}</th>
								<th style="width: 160px;">{{Sous-type}}</th>
								<th style="width: 100px;">{{Options}}</th>
								<th style="width: 50px;">{{Ordre}}</th>
								<th style="width: 100px;"></th>
							</tr>
						</thead>
						<tbody class="cmd_action">
						</tbody>
					</table>
				</div>
			</div><!-- /.tabpanel #commandtabAction-->

			<!-- Onglet des commandes NOTIFICATION -->
			<div role="tabpanel" class="tab-pane" id="commandtabNotification" class="commandtab">
				<div class="table-responsive">
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th style="width: 50px;">#</th>
								<th style="width: 300px;">{{Nom}}</th>
								<th style="width: 160px;">{{Sous-type}}</th>
								<th style="width: 100px;">{{Options}}</th>
								<th style="width: 50px;">{{Ordre}}</th>
								<th style="width: 100px;"></th>
							</tr>
						</thead>
						<tbody class="cmd_actionNotification">
						</tbody>
					</table>
				</div>
			</div><!-- /.tabpanel #commandtabNotification-->

		</div><!-- /.tab-content -->
	</div><!-- /.eqLogic -->

</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', 'JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'assistant.JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'widget.JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'generic.JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'eqLogic.JeedomConnect', 'js', 'JeedomConnect'); ?>
<?php include_file('desktop', 'JeedomConnect', 'css', 'JeedomConnect'); ?>
<?php include_file('desktop', 'md/css/materialdesignicons', 'css', 'JeedomConnect'); ?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js'); ?>