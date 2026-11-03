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

// Car configuration of an equipment (desktop/modal/car.JeedomConnect.php): the same settings as the app's
// Preferences -> Car (prefCar.js / prefCarFavorites.js), saved through JeedomConnectCar like the app does.

var jcCarConfig = null;

// how the car organizes the favorites, see JeedomConnectCar::getGroups
var jcCarNavigationModes = [
  { id: 'none', label: "{{Aucune}}", description: "{{Tous les favoris sur la première page}}" },
  { id: 'room', label: "{{Par pièce}}", description: "{{Les pièces contenant au moins un favori, puis leurs favoris}}" },
  { id: 'type', label: "{{Par type}}", description: "{{Lumières, volets, climat... puis les favoris de ce type}}" },
  { id: 'menu', label: "{{Selon les menus de l'appli}}", description: "{{Menus bas puis menus haut, comme dans l'appli}}" }
];

// what a tap does in the car, see JeedomConnectCar::getCarDevice
function jcCarModeLabel(mode) {
  switch (mode) {
    case 'toggle': return "{{Appui : change l'état}}";
    case 'exec': return "{{Appui : lance l'action}}";
    case 'menu': return "{{Appui : choix de l'action}}";
    case 'range': return "{{Appui : réglage de la valeur}}";
    case 'panel': return "{{Appui : commandes et modes}}";
    case 'folder': return "{{Appui : contenu du groupe}}";
    default: return "{{Affichage seul}}";
  }
}

function jcCarEscape(text) {
  return $('<div>').text(text == null ? '' : String(text)).html();
}

function jcCarCall(action, data, onSuccess) {
  $.ajax({
    type: 'POST',
    url: 'plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php',
    data: Object.assign({ action: action, eqLogicId: jcCarEqLogicId }, data),
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error, $('#jc-car-alert'));
    },
    success: function (result) {
      if (result.state != 'ok') {
        $('#jc-car-alert').showAlert({ message: result.result, level: 'danger' });
        return;
      }
      onSuccess(result.result);
    }
  });
}

function jcCarItemHtml(item, withRemove, withCheckbox) {
  var image = jcCarTypeImages[item.widgetType] || jcCarDefaultImage;
  var type = jcCarTypeNames[item.widgetType] || item.widgetType;
  var details = [item.subtitle, type, jcCarModeLabel(item.mode)].filter(Boolean).map(jcCarEscape).join(' · ');
  var html = '<li class="list-group-item" data-id="' + jcCarEscape(item.id) + '" style="display:flex; align-items:center; gap:10px; padding:6px 10px;">';
  if (withCheckbox) html += '<input type="checkbox" class="jc-car-check">';
  if (withRemove) html += '<i class="fas fa-grip-vertical jc-car-handle" style="cursor:move; opacity:.6;" title="{{Glisser pour déplacer}}"></i>';
  html += '<img src="' + image + '" style="width:28px; height:28px; object-fit:contain;">';
  html += '<div style="flex:1; min-width:0;"><div>' + jcCarEscape(item.title) + '</div><div class="description" style="font-size:11px;">' + details + '</div></div>';
  if (withRemove) html += '<a class="btn btn-xs btn-danger jc-car-remove" title="{{Retirer des favoris}}"><i class="fas fa-times"></i></a>';
  html += '</li>';
  return html;
}

function jcCarRender() {
  var config = jcCarConfig;
  var byId = {};
  config.available.forEach(function (item) { byId[item.id] = item; });

  var navigation = config.navigation || 'none';
  $('#jc-car-navigation').html(jcCarNavigationModes.map(function (mode) {
    return '<div class="radio" style="margin:2px 0;"><label>' +
      '<input type="radio" name="jc-car-navigation" value="' + mode.id + '"' + (navigation == mode.id ? ' checked' : '') + '> ' +
      '<b>' + mode.label + '</b> <span class="description">' + mode.description + '</span></label></div>';
  }).join(''));

  var favorites = config.favorites.filter(function (id) { return byId[id]; });
  $('#jc-car-favorites-count').text(favorites.length);
  $('#jc-car-favorites').html(favorites.length > 0
    ? favorites.map(function (id) { return jcCarItemHtml(byId[id], true, false); }).join('')
    : '<li class="list-group-item description">{{Aucun favori : ajoutez des widgets ci-dessous.}}</li>');
  $('#jc-car-favorites').sortable({
    handle: '.jc-car-handle',
    items: 'li[data-id]',
    update: function () {
      jcCarSaveFavorites($('#jc-car-favorites li[data-id]').map(function () { return String($(this).data('id')); }).get());
    }
  });

  var available = config.available.filter(function (item) { return favorites.indexOf(item.id) < 0; });
  $('#jc-car-available').html(available.length > 0
    ? available.map(function (item) { return jcCarItemHtml(item, false, true); }).join('')
    : '<li class="list-group-item description">{{Tous les widgets compatibles sont déjà en favoris.}}</li>');
  jcCarFilter();
}

function jcCarFilter() {
  var search = ($('#jc-car-filter').val() || '').toLowerCase().trim();
  $('#jc-car-available li[data-id]').each(function () {
    $(this).toggle(search == '' || $(this).text().toLowerCase().indexOf(search) >= 0);
  });
}

function jcCarSaveFavorites(favorites) {
  jcCarCall('setCarFavorites', { favorites: JSON.stringify(favorites) }, function (config) {
    jcCarConfig = config;
    jcCarRender();
  });
}

$('#jc-car').on('change', 'input[name=jc-car-navigation]', function () {
  jcCarCall('setCarNavigation', { navigation: $(this).val() }, function (config) {
    jcCarConfig = config;
    jcCarRender();
    $('#jc-car-alert').showAlert({ message: "{{Navigation enregistrée}}", level: 'success' });
  });
});

$('#jc-car').on('click', '.jc-car-remove', function () {
  var id = String($(this).closest('li').data('id'));
  jcCarSaveFavorites(jcCarConfig.favorites.filter(function (fav) { return fav != id; }));
});

// a click anywhere on a row of the available widgets ticks it
$('#jc-car').on('click', '#jc-car-available li[data-id]', function (event) {
  if ($(event.target).is('input')) return;
  var checkbox = $(this).find('.jc-car-check');
  checkbox.prop('checked', !checkbox.prop('checked'));
});

$('#jc-car-filter').on('keyup', jcCarFilter);

$('#jc-car-add').on('click', function () {
  var picked = $('#jc-car-available li[data-id]').filter(function () {
    return $(this).find('.jc-car-check').is(':checked');
  }).map(function () { return String($(this).data('id')); }).get();
  if (picked.length == 0) {
    $('#jc-car-alert').showAlert({ message: "{{Cochez les widgets à ajouter}}", level: 'warning' });
    return;
  }
  jcCarSaveFavorites(jcCarConfig.favorites.concat(picked));
});

jcCarCall('getCarConfig', {}, function (config) {
  jcCarConfig = config;
  jcCarRender();
});
