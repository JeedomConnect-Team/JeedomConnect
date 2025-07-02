$("#notifsUL").sortable({
	axis: "y", cursor: "move", items: ".notifItem", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true
});

$('.tablinks').on('click', function () {
	$(".tabcontent").css('display', 'none');

	var toShow = $(this).data('link');
	$("#" + toShow).css('display', 'block');
});


function getIconModal(_options, _callback) {
	$("#mod_selectIcon").dialog('destroy').remove();
	if ($("#mod_selectIcon").length == 0) {
		$('body').append('<div id="mod_selectIcon"></div>');
		$("#mod_selectIcon").dialog({
			title: _options.title,
			closeText: '',
			autoOpen: false,
			modal: true,
			height: (jQuery(window).height() - 150),
			width: 1500
		});
		jQuery.ajaxSetup({
			async: false
		});
		let params = `&withIcon=${_options.withIcon}&withImg=${_options.withImg}`;
		if (_options.icon.source) {
			params += `&source=${_options.icon.source}`;
		}
		if (_options.icon.name) {
			params += `&name=${encodeURI(_options.icon.name)}`;
		}
		if (_options.icon.color) {
			params += `&color=${_options.icon.color.substring(1)}`;
		}
		if (_options.icon.shadow) {
			params += `&shadow=${_options.icon.shadow}`;
		}

		$('#mod_selectIcon').load(`index.php?v=d&plugin=JeedomConnect&modal=assistant.iconModal.JeedomConnect${params}`);
		jQuery.ajaxSetup({
			async: true
		});
	}

	$("#mod_selectIcon").dialog({
		title: _options.title, buttons: {
			"Annuler": function () {
				$(this).dialog("close");
			},
			Save: {
				text: "Valider",
				id: "saveSimple",
				click: function () {
					var icon = $('.iconSelected .iconSel').html();
					if (icon === undefined) {
						$('#div_iconSelectorAlert').showAlert({ message: 'Aucune icône sélectionnée', level: 'danger' });
						return;
					}

					let result = {};
					result.source = $('.iconSelected .iconSel').children().first().attr("source");
					result.name = $('.iconSelected .iconSel').children().first().attr("name");
					if (result.source == 'fa') {
						result.prefix = $('.iconSelected .iconSel').children().first().attr("prefix");
					}
					if ((result.source == 'jeedom' | result.source == 'md' | result.source == 'fa') & $("#mod-color-input").val() != '') {
						result.color = $("#mod-color-input").val();
					}
					if (result.source == 'jc' | result.source == 'user') {
						result.shadow = $("#bw-input").is(':checked');
					}

					if ($.trim(result) != '' && 'function' == typeof (_callback)) {
						_callback(result);
					}

					$(this).dialog('close');
				}
			}
		}
	});

	$('#mod_selectIcon').dialog('open');

}

function getSimpleModal(_options, _callback) {
	if (!isset(_options)) {
		return;
	}
	$("#simpleModal").dialog('destroy').remove();
	if ($("#simpleModal").length == 0) {
		$('body').append('<div id="simpleModal"></div>');
		$("#simpleModal").dialog({
			title: _options.title,
			closeText: '',
			autoOpen: false,
			modal: true,
			width: 350
		});
		jQuery.ajaxSetup({
			async: false
		});
		$('#simpleModal').load('index.php?v=d&plugin=JeedomConnect&modal=assistant.simpleModal.JeedomConnect');
		jQuery.ajaxSetup({
			async: true
		});
	}
	setSimpleModalData(_options.fields);
	$("#simpleModal").dialog({
		title: _options.title, buttons: {
			"Annuler": function () {
				$(this).dialog("close");
			},
			Save: {
				text: "Valider",
				id: "saveSimple",
				click: function () {
					try {
						var result = {};
						if (_options.fields.find(i => i.type == "enable")) {
							result.enable = $("#mod-enable-input").is(':checked');
						}
						if (_options.fields.find(i => i.type == "name")) {
							if ($("#mod-name-input").val() == '') {
								throw 'La nom est obligatoire';
							}
							result.name = $("#mod-name-input").val();
						}
						if (_options.fields.find(i => i.type == "icon")) {
							result.icon = $("#mod-icon-input").val();
						}
						if (_options.fields.find(i => i.type == "move")) {
							result.moveToId = $("#mod-move-input").val();
						}
						if (_options.fields.find(i => i.type == "expanded")) {
							result.expanded = $("#mod-expanded-input").is(':checked');
						}
						if (_options.fields.find(i => i.type == "widget")) {
							result.widgetId = $("#mod-widget-input").val();
							result.widgetName = $("#mod-widget-input option:selected").text();
						}

						if ($.trim(result) != '' && 'function' == typeof (_callback)) {
							_callback(result);
						}
						$(this).dialog('close');
					}
					catch (error) {
						$('#div_simpleModalAlert').showAlert({ message: error, level: 'danger' });
						console.error(error);
					}
				}
			}
		}
	});
	$('#simpleModal').dialog('open');
	$('#simpleModal').keydown(function (e) {
		if (e.which == 13) {
			$('#saveSimple').click();
			return false;
		}
	})
};


function getNotifModal(_options, _callback) {
	if (!isset(_options)) {
		return;
	}
	if ($("#notifModal").length == 0) {
		$('body').append('<div id="notifModal"></div>');

		$("#notifModal").dialog({
			title: _options.title,
			closeText: '',
			autoOpen: false,
			modal: true,
			width: 850,
			height: 450
		});
		jQuery.ajaxSetup({
			async: false
		});
		$('#notifModal').load('index.php?v=d&plugin=JeedomConnect&modal=notifs.notifModal.JeedomConnect');
		jQuery.ajaxSetup({
			async: true
		});
	}
	setNotifModalData(_options);
	$("#ongoing-input").change(function () {
		$("#update-input").prop("disabled", this.checked);
		$("#update-input").prop("checked", this.checked);
	});
	$("#notifModal").dialog({
		title: _options.title, buttons: {
			"Annuler": function () {
				$('#notif-alert').hideAlert();
				$(this).dialog("close");
			},
			"Valider": function () {
				try {
					var result = _options.notif || {};
					if ($("#mod-notifName-input").val() == '') {
						throw 'La commande Nom est obligatoire';
					}
					result.name = $("#mod-notifName-input").val();
					result.channel = $("#mod-channel-input").val();
					result.update = $("#update-input").is(':checked')
					result.ongoing = $("#ongoing-input").is(':checked');
					result.critical = $("#critical-input").is(':checked');
					result.criticalVolume = parseFloat($("#criticalVolume-input").val());
					if ($("#mod-color-input").val() != '') {
						result.color = $("#mod-color-input").val();
					}
					else {
						result.color = undefined;
					}
					result.image = htmlToIcon($("#icon-div").children().first());
					if (result.image == {}) { delete result.image; }

					if (actionList.length > 0) {
						actionList.forEach(item => {
							item.name = $("#" + item.id + "-name-input").val();
						});
						result.actions = actionList;
					}
					else {
						result.actions = undefined;
					}

					if ('function' == typeof (_callback)) {
						_callback(result);
					}

					$('#notif-alert').hideAlert();
					$(this).dialog('close');
				}
				catch (error) {
					$('#notif-alert').showAlert({ message: error, level: 'danger' });
					console.error(error);
				}

			}
		}
	});
	$('#notifModal').dialog('open');
};



/////////////////  FROM NOTIF MANAGER

var notifData;

$.post({
	url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
	data: {
		'action': 'getNotifs',
		'apiKey': apiKey
	},
	cache: false,
	success: function (config) {
		notifData = json_decode(config).result;
		idCounter = notifData.idCounter
		initData();
	}
});



function initData() {
	$('ul#channelsUL').empty
	$('ul#notifsUL').empty
	$(".tablinks:first").click()
	refreshChannelTabData();
	refreshNotifsTabData();
}

function refreshChannelTabData() {
	var items = [];
	$.each(notifData.channels, function (key, val) {
		itemHtml = createElementNotifChannel({ id: val.id, name: val.name }, 'channel', false);
		items.push(itemHtml);
	});
	$("#channelsUL").html(items.join(""));
}

function refreshNotifsTabData() {
	notifs = notifData.notifs.sort(function (s, t) {
		return s.index - t.index;
	});
	var items = [];
	var itemsProg = [];
	$.each(notifs, function (key, val) {
		itemHtml = createElementNotifChannel(val);
		items.push(itemHtml);
		itemsProgHtml = createElementNotifProg(val);
		itemsProg.push(itemsProgHtml);
	});
	$("#notifsUL").html(items.join(""));
	$("#progUL").html(itemsProg.join(""));
}


function createElementNotifProg(item) {

	var checked = defaultProgNotif == item.id ? 'checked' : '';
	var itemHtml = `<div>
		<input type="radio" id="${item.id}" name="prog" value="${item.name}" ${checked} />
		<label for="${item.name}">${item.name}</label>
		</div>
		`;

	return itemHtml;

}

function createElementNotifChannel(item, type = 'notif', movable = true) {

	var editClass = (type == 'notif') ? 'editNotif' : 'editChannel';

	var isDefault = (item.id == 'default' || item.id == 'defaultNotif')

	if (typeof item.id === 'undefined') {
		item['id'] = type + '-' + idCounter
		incrementIdCounter();
	}

	var itemHtml = `<li class="notifItem" ><a class="${editClass}" data-id="${item.id}" data-object='${escapeHtml(JSON.stringify(item))}'>${item.name}</a>`;
	if (movable && !isDefault) {
		itemHtml += '<i class="mdi mdi-arrow-up-down-bold" title="Déplacer" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;margin-left:10px;cursor:grab!important;"></i>';
	}
	if (!isDefault) {
		itemHtml += '<i class="mdi mdi-minus-circle deleteItem" style="color:rgb(185, 58, 62);font-size:24px;margin-left:5px;"></i>';
	}
	itemHtml += '</li>';

	return itemHtml;
}


function incrementIdCounter() {
	idCounter += 1;
}

function save() {

	// saving channel
	channels = []
	$('ul#channelsUL li').each(function (i, obj) {
		item = $(this).find('a').data('object');
		channels.push(item);
	});

	// saving notifications
	notifications = []
	$('ul#notifsUL li').each(function (i, obj) {
		item = $(this).find('a').data('object');
		item.index = i
		notifications.push(item);

	});

	progNotifDefault = $('input[name="prog"]:checked').attr('id');

	var notifDataFinal = {}
	notifDataFinal.idCounter = idCounter
	notifDataFinal.channels = channels
	notifDataFinal.notifs = notifications
	notifDataFinal.defaultForAutomation = progNotifDefault
	console.log('notifDataFinal', notifDataFinal)


	$.post({
		url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
		data: {
			'action': 'saveNotifs',
			'config': JSON.stringify(notifDataFinal),
			'apiKey': apiKey
		},
		success: function () {
			$('#jc-assistant').showAlert({ message: 'Configuration des notifications sauvegardée', level: 'success' });
		},
		error: function (error) {
			console.log(error);
			$('#jc-assistant').showAlert({ message: 'Erreur lors de la sauvegarde', level: 'danger' });
		}
	});

}

function resetConfig() {
	getSimpleModal({ title: "Confirmation", fields: [{ type: "string", value: "La configuration va être remise à zéro. Voulez-vous continuer ?" }] }, function (result) {
		idCounter = 0;
		notifData = {
			'idCounter': idCounter,
			'channels': [
				{
					'id': 'default',
					'name': 'Défaut'
				}
			],
			'notifs': [
				{
					'id': 'defaultNotif',
					'name': 'Notification',
					'channel': 'default',
					'index': 0
				}
			]
		};
		initData();
	});
}

/* CHANNELS TAB FUNCTIONS */

function addChannelTabModal() {
	getSimpleModal({ title: "Ajouter un canal", fields: [{ type: "name" }] }, function (result) {
		var newName = result.name;
		if (newName == '') return;

		var newId = 'channel-' + idCounter
		var newElt = createElementNotifChannel({ id: newId, name: newName }, 'channel', false);
		$('#channelsUL').append(newElt);

		result['id'] = newId
		$('#channelsUL .editChannel[data-id=' + newId + ']').attr('data-object', JSON.stringify(result));

		incrementIdCounter();
	});
}

$('body').off('click', '.editChannel').on('click', '.editChannel', function () {
	//no change on the default channel
	if ($(this).data('id') == 'default') return;

	$(this).addClass('notifToUpdate');

	getSimpleModal({ title: "Editer un canal", fields: [{ type: "name", value: $(this).text() }] }, function (result) {
		$('.notifToUpdate').text(result.name);
		$('.notifToUpdate').attr('data-object', JSON.stringify(result));
		$('.notifToUpdate').removeClass('notifToUpdate');
	});
});


/* NOTIFICATIONS */

function addNotifModal() {
	getNotifModal({ title: "Ajouter une notification" }, function (result) {
		var newName = result.name;
		if (newName == '') return;

		var newId = 'notif-' + idCounter
		var newElt = createElementNotifChannel({ id: newId, name: newName });
		$('#notifsUL').append(newElt);

		itemsProgHtml = createElementNotifProg({ id: newId, name: newName });
		$("#progUL").append(itemsProgHtml);

		incrementIdCounter();
		result['id'] = newId
		$('#notifsUL .editNotif[data-id=' + newId + ']').attr('data-object', JSON.stringify(result));
	});
}

$('body').off('click', '.editNotif').on('click', '.editNotif', function () {

	var notifToEdit = $(this).data('object');
	$(this).addClass('notifToUpdate');

	getNotifModal({ title: "Editer une notification", notif: notifToEdit }, function (result) {
		$('.notifToUpdate').text(result.name);
		$('.notifToUpdate').attr('data-object', JSON.stringify(result));
		$('.notifToUpdate').removeClass('notifToUpdate');

	});
})

var entityMap = {
	'&': '&amp;',
	'<': '&lt;',
	'>': '&gt;',
	'"': '&quot;',
	"'": '&#39;',
	'/': '&#x2F;',
	'`': '&#x60;',
	'=': '&#x3D;'
};

function escapeHtml(string) {
	return String(string).replace(/[&<>"'`=\/]/g, function (s) {
		return entityMap[s];
	});
}