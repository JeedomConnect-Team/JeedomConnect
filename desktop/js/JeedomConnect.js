
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

$('.eqLogicThumbnailContainer').off('click', '.widgetDisplayCard, .componentDisplayCard').on('click', '.widgetDisplayCard, .componentDisplayCard', function () {

  var eqId = $(this).attr('data-widget_id');
  var itemType = $(this).hasClass('widget') ? 'widget' : 'component';
  editWidgetModal(eqId, itemType, true, true, true, true);

})

async function editWidgetModal(widgetId, itemType, removeAction, exit, duplicate, checkEquipment) {

  if (checkEquipment) {

    var mesEquipments = await getUsedByEquipement(widgetId);
    var inEquipments = mesEquipments.result.names;
    var inCusto = mesEquipments.result.custo;
  }
  else {
    var inEquipments = undefined;
    var inCusto = undefined;
  }

  // var itemDetail = (itemType == 'widget') ? allWidgetsDetail : allWidgetsDetail;
  var widgetToEdit = allWidgetsDetail.find(w => w.id == widgetId);
  if (itemType == 'component') {
    widgetToEdit.type = widgetToEdit.component
  }

  getWidgetModal({ title: "Editer un widget", eqId: widgetId, widget: widgetToEdit, removeAction: removeAction, exit: exit, duplicate: duplicate, inEquipments: inEquipments, inCusto: inCusto, itemType: itemType }, function (result) {
    refreshWidgetDetails();
    if (!exit) refreshWidgetsContent();
  });

}

async function getUsedByEquipement(eqId) {
  const result = await $.post({
    url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
    data: {
      action: 'getWidgetExistance',
      id: eqId
    },
    cache: false,
    dataType: 'json',
    async: false,
  });

  if (result.state != 'ok') {
    $('#div_alert').showAlert({
      message: result.result,
      level: 'danger'
    });
  }

  return result;
}




/**************************************************** */
/**************************************************** */
/**************************************************** */
/**************************************************** */
/**************************************************** */
/**************************************************** */
/**************************************************** */





var allWidgetsDetail;
var roomList;
var roomListOptions;

refreshWidgetDetails();

function refreshWidgetDetails() {
  $.post({
    url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
    data: {
      'action': 'getWidgetConfigAll'
    },
    cache: false,
    dataType: 'json',
    async: false,
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({
          message: data.result,
          level: 'danger'
        });
      }
      else {
        allWidgetsDetail = data.result.widgets;
        roomList = data.result.room_details;
        roomListOptions = data.result.room_options;
        sortWidgets();
      }
    }
  });

}

if (typeof allJeedomData === 'undefined') {
  $.post({
    url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
    data: {
      'action': 'getAllJeedomData'
    },
    cache: false,
    dataType: 'json',
    async: true,
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({
          message: data.result,
          level: 'danger'
        });
      }
      else {
        allJeedomData = data.result;
      }
    }
  });
}


$.post({
  url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
  data: {
    'action': 'getEquipments'
  },
  dataType: 'json',
  success: function (data) {
    if (data.state != 'ok') {
      $('#div_alert').showAlert({
        message: data.result,
        level: 'danger'
      });
    }
    else {
      allJCEquipments = data.result
    }
  }
});


var widgetsOption = [];//used for widgets option
var cmdCat = []; //used for cmdList
var imgCat = []; //used for img list
var moreInfos = []; //used for moreInfos

var widgetsList = (function () {
  var json = null;
  $.ajax({
    'async': false,
    'global': false,
    'cache': false,
    'url': "plugins/JeedomConnect/core/config/widgetsConfig.json",
    'dataType': "json",
    'success': function (data) {
      var availableWidgets = data.widgets.filter(function (item) {
        if (item.hide !== undefined && item.hide) {
          return false;
        }
        return true;
      });

      data.widgets = availableWidgets

      data.widgets.sort(function (a, b) {
        return a.name.localeCompare(b.name);
      });
      json = data;
    }
  });
  return json;
})();

itemsConfig = [];
widgetsList.widgets.forEach(item => {
  itemsConfig.push({ 'type': item.type, 'class': "widget", 'name': item.name });
});

widgetsList.components.forEach(item => {
  itemsConfig.push({ 'type': item.type, 'class': "component", 'name': item.name });
});

itemsConfig.sort(function (a, b) {
  return a.name.localeCompare(b.name);
});

optionsSelect = [];
itemsConfig.forEach(item => {
  optionsSelect.push('<option value="' + item.type + '" class="' + item.class + '">' + item.name + '</option>');
});


$("#widgetsList-select").html(optionsSelect.join(""));
$("#room-input").html(roomListOptions);


function refreshCmdData(name, id, value, concat = false) {
  if (name == '' || id == '') return;
  getCmd({
    id: id,
    error: function (error) {
      $('#widget-alert').showAlert({ message: error.result, level: 'danger' });
    },
    success: function (data) {
      $('#widget-alert').hideAlert();

      var configtype = $("#" + name + "-input").attr('configtype');
      var configsubtype = $("#" + name + "-input").attr('configsubtype');

      if (configtype != undefined && configtype != data.result.type) {
        $('#widget-alert').showAlert({
          message: "La commande " + id + " n'est pas de type '" + configtype + "'", level: 'danger'
        });
        return;
      }
      if (configsubtype != "undefined" && configsubtype != data.result.subType) {
        $('#widget-alert').showAlert({
          message: "La commande " + id + " n'a pas le sous-type '" + configsubtype + "'", level: 'danger'
        });
        return;
      }

      let prev = '';
      if (concat) {
        let prev = $("#" + name + "-input").val();
        prev = (prev != '') ? prev + ' ' : prev;
      }
      $("#" + name + "-input").val(prev + '#' + data.result.humanName + '#');

      $("#" + name + "-input").attr('cmdId', data.result.id);
      $("#" + name + "-input").attr('title', '#' + data.result.humanName + '#');
      $("#" + name + "-input").attr('cmdType', data.result.type);
      $("#" + name + "-input").attr('cmdSubType', data.result.subType);
      if (data.result.type == 'action') {
        $("#confirm-div-" + name).css('display', '');
        $("#secure-div-" + name).css('display', '');
        $("#pwd-div-" + name).css('display', '');
        $("#" + name + "-input").css({
          'width': $("#" + name + "-input").width() * 0.85
        })
      } else {
        $("#confirm-div-" + name).css('display', 'none');
        $("#secure-div-" + name).css('display', 'none');
        $("#pwd-div-" + name).css('display', 'none');
      }
      if (data.result.subType == 'slider' | data.result.subType == 'numeric') {
        $("#" + name + "-minInput").css('display', '');
        $("#" + name + "-maxInput").css('display', '');
        $("#" + name + "-minInput").val(data.result.minValue);
        $("#" + name + "-maxInput").val(data.result.maxValue);
        $("#" + name + "-input").css({
          'width': $("#" + name + "-input").width() * 0.85
        })
      } else {
        $("#" + name + "-minInput").css('display', 'none');
        $("#" + name + "-maxInput").css('display', 'none');
      }
      if (data.result.subType == 'binary' | data.result.subType == 'numeric') {
        $("#invert-div-" + name).css('display', '');
        //$("#invert-"+name).prop('checked', data.result.invertBinary == '1' ? "checked" : "");
      } else {
        $("#invert-div-" + name).css('display', 'none');
      }
      if (data.result.subType == 'numeric') {
        $("#" + name + "-unitInput").css('display', '');
        $("#" + name + "-unitInput").val(data.result.unit);
        $("#" + name + "-input").css({
          'width': $("#" + name + "-input").width() * 0.85
        })
      } else {
        $("#" + name + "-unitInput").css('display', 'none');
      }
      if (data.result.subType == 'slider') {
        $("#" + name + "-stepInput").css('display', '');
        $("#" + name + "-stepInput").val(data.result.step);
        $("#" + name + "-input").css({
          'width': $("#" + name + "-input").width() * 0.85
        })
      }
      else {
        $("#" + name + "-stepInput").css('display', 'none');
      }
      if (value != 'undefined' & data.result.value != '') {
        refreshCmdData(value, data.result.value, 'undefined');
      }
      refreshImgListOption();
      refreshInfoSelect();
    }
  });
}


// TODO
// call twice !?
$("body").on('change', '.subtitleSelected', function (e) {
  if ($(this).val() == 'custom') {
    $("#subtitle-input-value").show();
    $("#subtitle-select").show();
  } else {
    $("#subtitle-input-value").hide();
    $("#subtitle-select").hide();
    $("#subtitle-input-value").val($("#subtitle-input").val());
  }
});


function refreshCmdListOption(optionsJson) {
  var options = JSON.parse(optionsJson);

  curOption = "";
  cmdCat.sort(function (s, t) {
    return s.index - t.index;
  });

  var tempIndex = 0;
  cmdCat.forEach(item => {
    isDisabled = isJcExpert ? '' : 'disabled';

    item.index = item.index || tempIndex;
    //open the div
    curOption += `<div class='input-group col-lg-12 jcCmdList' style="display:flex;border:0.5px black solid;margin: 0 5px;" data-id="${item.id}" data-index="${item.index}">`;

    //left part
    curOption += `<div class="col-lg-6 form-group">`;

    curOption += `<div class="input-group input-group-sm" style="width: 100%">
                            <span class="input-group-addon roundedLeft" style="width: 100px">Commande</span>
                            <input style="width:240px;" class='input-sm form-control roundedRight title jcCmdListOptions jcCmdListOptionsCommand' data-cmd-id="${item.id}" data-id="name-${item.id}" data-index="${item.index}" value='' ${isDisabled}>
                        </div>`;

    curOption += getCmdOptions(item);

    curOption += `</div>`;
    // --- END left part --

    ////right part
    curOption += `<div class="col-lg-6">`;
    curOption += `<div class="col-lg-12 form-group">`;

    if (options.type == 'action') {
      curOption += `
                <div class="col-lg-6">
                    <i class='mdi mdi-help-circle-outline'></i><input type="checkbox" style="margin-left:5px;" class="jcCmdListOptions" data-id="confirm-${item.id}" data-index="${item.index}">
                    <i class='mdi mdi-fingerprint'></i><input type="checkbox" style="margin-left:5px;" class="jcCmdListOptions" data-id="secure-${item.id}" data-index="${item.index}"  >
                    <i class='mdi mdi-numeric'></i><input type="checkbox" style="margin-left:5px;" class="jcCmdListOptions" data-id="pwd-${item.id}" data-index="${item.index}"  >
                </div> `;

    }

    curOption += `<div class="col-lg-6" >
                  <i class="mdi mdi-arrow-up-down-bold" title="Déplacer" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;margin-left:10px;cursor:grab!important;" aria-hidden="true"></i>

                  <!-- <i class="mdi mdi-arrow-up-circle" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;margin-left:10px;" aria-hidden="true" data-id="${item.id}" data-index="${item.index}" onclick="upCmdOption(this,'${optionsJson.replace(/"/g, '&quot;')}');"></i>
                  <i class="mdi mdi-arrow-down-circle" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;" aria-hidden="true" data-id="${item.id}" data-index="${item.index}" onclick="downCmdOption(this,'${optionsJson.replace(/"/g, '&quot;')}');"></i> -->

                  <i class="mdi mdi-minus-circle" style="color:rgb(185, 58, 62);font-size:24px;" aria-hidden="true" data-id="${item.id}" data-index="${item.index}"  onclick="deleteCmdOption(this,'${optionsJson.replace(/"/g, '&quot;')}');"></i>
                </div>`

    curOption += `</div>`;

    if (options.hasIcon | options.hasImage) {
      curOption += `<div class="col-lg-12 form-group">`;
      curOption += `
              <div>
                      <a class="btn btn-success roundedRight imagePicker"><i class="fas fa-check-square">
                      </i> Icône </a>
                      <a class="jcCmdListOptions removeImage" data-id="icon-${item.id}" data-index="${item.index}">${iconToHtml(item.image)}</a>
              </div>`;
      curOption += `</div>`;
    }



    curOption += `</div>`;
    //// ---  END right part
    curOption += `</div>`;

    tempIndex++;


  });
  $("#cmdList-option").html(curOption);
  cmdCat.forEach(item => {
    var confirm = item.confirm ? "checked" : "";
    $('.jcCmdListOptions[data-id="confirm-' + item.id + '"][data-index="' + item.index + '"]').prop('checked', confirm);

    var secure = item.secure ? "checked" : "";
    $('.jcCmdListOptions[data-id="secure-' + item.id + '"][data-index="' + item.index + '"]').prop('checked', secure);

    var pwd = item.pwd ? "checked" : "";
    $('.jcCmdListOptions[data-id="pwd-' + item.id + '"][data-index="' + item.index + '"]').prop('checked', pwd);

    $('.jcCmdListOptions[data-id="custom-name-' + item.id + '"][data-index="' + item.index + '"]').val(item.name || '');

    getCmd({
      id: item.id,
      success: function (data) {
        $('.jcCmdListOptions[data-id="name-' + item.id + '"][data-index="' + item.index + '"]').val("#" + data.result.humanName + "#");
        if (!isIcon(item.image)) {
          item.image = jeedomIconToIcon(data.result.icon);
          $('.jcCmdListOptions[data-id="icon-' + item.id + '"][data-index="' + item.index + '"]').html(iconToHtml(item.image));
        }
      }
    });

    if (['message', 'slider', 'color'].indexOf(item.subtype) > -1 && item.options != null) {
      Object.entries(item.options).forEach(entry => {
        const [key, value] = entry;
        if ($.inArray(key, ['displayTitle', 'keepLastMsg']) != -1) {
          $('.jcCmdListOptions[data-id="' + key + '-' + item.id + '"][data-index="' + item.index + '"]').prop('checked', value);
          return true;
        }
        if (value != '') {
          getHumanNameFromCmdId({ alert: '#widget-alert', cmdIdData: value, id: item.id, index: item.index }, function (result, _params) {
            $('.jcCmdListOptions[data-id="' + key + '-' + _params.id + '"][data-index="' + _params.index + '"]').val(result);
          });
        }
      });

    }

  })
}








function infoSelected(value, el) {
  let inputId = $(el).parent().attr("input");
  //$("#"+inputId).val( $("#"+inputId).val() + value);
  let input = $("#" + inputId);
  input.val([input.val().slice(0, input[0].selectionStart), value, input.val().slice(input[0].selectionStart)].join(''));
}

function refreshStrings() {
  const infoCmd = moreInfos.slice();
  $('input[cmdType="info"]').each((i, el) => {
    infoCmd.push({ id: $("input[id=" + el.id + "]").attr('cmdid'), human: el.title });
  });
  var nameInput = idToHuman($("#name-input").val(), infoCmd);
  $("#name-input").val(nameInput);
  $("#name-input").attr('title', nameInput);
  $("#subtitle-input-value").val(idToHuman($("#subtitle-input-value").val(), infoCmd));
}



// Image condition list
function refreshImgListOption(dataType = 'widget') {
  var options = [];


  if (dataType != 'widget') {
    //if summary modal open, then get the summary Config object
    var widget = summaryConfig.find(i => i.type == 'summary');
  }
  else {
    //otherwise use the widget config based on the selected type one
    var type = $("#widgetsList-select").val();
    var widget = widgetsList.widgets.find(i => i.type == type);
  }

  curOption = "";
  //get all info
  $('input[cmdType="info"]').each((i, el) => {
    options.push({ type: 'cmd', id: $("input[id=" + el.id + "]").attr('cmdid'), human: el.title })
  });
  options = options.concat(moreInfos);

  if (widget?.variables) {
    widget.variables.forEach(v => {
      options.push({ type: 'var', id: v.name, human: `#${v.name}#` })
    });
  }

  imgCat.sort(function (s, t) {
    return s.index - t.index;
  });

  imgCat.forEach(item => {
    curOption += `
		<div data-id="${item.index}" class='input-group jcImgListMovable'>
			Si
			<input style="width:385px;height:31px;margin-left:5px" class=' roundedLeft' index="${item.index}" id="imglist-cond-${item.index}" value="${item.condition}"
			 onchange="setCondValue(this, 'imgList')" />`;
    curOption += `
        <div class="dropdown" id="imglist-cond-select" style="display:inline !important;">
        <a class="btn btn-default dropdown-toggle" data-toggle="dropdown" style="height" >
        <i class="fas fa-plus-square"></i> </a>
        <ul class="dropdown-menu infos-select" input="imglist-cond-${item.index}">`;
    if (widget.variables) {
      widget.variables.forEach(v => {
        curOption += `<li info="${v.name}" onclick="infoSelected('#${v.name}#', this)"><a href="#">#${v.name}#</a></li>`;
      });
    }
    curOption += `</ul></div>`;
    curOption += `<a class="btn btn-success roundedRight imagePicker" index="${item.index}"><i class="fas fa-plus-square">
    </i> Image </a>
    <a data-id="icon-div" id="icon-div-${item.index}" class="removeImage">${iconToHtml(item.image)}</a>
    <i class="mdi mdi-arrow-up-down-bold" title="Déplacer" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;margin-left:10px;cursor:grab!important;" aria-hidden="true"></i>
    <i class="mdi mdi-minus-circle" style="color:rgb(185, 58, 62);font-size:24px;" aria-hidden="true" onclick="deleteImgOption('${item.index}');"></i>
    </div>` ;


  });
  $("#imgList-option").html(curOption);
  setCondToHuman('imgList');
  refreshInfoSelect();
}



function addImgOption(dataType) {
  saveImgOption();
  var maxIndex = getMaxIndex(imgCat);
  imgCat.push({ index: maxIndex + 1 });
  refreshImgListOption(dataType);
  if (dataType == 'widget') refreshInfoSelect();
}



function getWidgetPath(id) {
  var widget = allWidgetsDetail.find(w => w.id == id);
  if (typeof widget === 'undefined') {
    console.log('issue with getWidgetPath - widget not found with id ' + id)
    return 'inconnu !';
  }
  var name = (' ' + (widget.nameDisplayed || widget.name)).slice(1);

  if (widget.parentId === undefined || widget.parentId == null || typeof configData === 'undefined') {
    return name;
  }
  var id = (' ' + widget.parentId.toString()).slice(1);
  parent = configData.payload.groups.find(i => i.id == id);
  if (parent) {
    name = parent.name + " / " + name;
    if (parent.parentId === undefined || parent.parentId == null) {
      return name;
    }
    id = (' ' + parent.parentId.toString()).slice(1);
  }
  parent2 = configData.payload.sections.find(i => i.id == id);
  if (parent2) {
    name = parent2.name + " / " + name;
    if (parent2.parentId === undefined || parent2.parentId == null) {
      return name;
    }
    id = (' ' + parent2.parentId.toString()).slice(1);
  }
  parent3 = configData.payload.tabs.find(i => i.id == id);
  if (parent3) {
    name = parent3.name + " / " + name;
  }
  return name;
}

function getRoomName(id) {
  if (id == 'global') { return 'Global'; }
  const room = roomList.find(r => r.id == id);
  if (room) {
    return room.name;
  } else {
    return undefined;
  }
}

function selectInfoCmd(elt, conf) {
  let input = $(elt);
  jeedom.cmd.getSelectModal({ cmd: { type: 'info' } }, function (result) {
    input.val([input.val().slice(0, input[0].selectionStart), result.human, input.val().slice(input[0].selectionStart)].join(''));
    setCondValue(input, conf);
  })
}

function setCondValue(elm, confArr) {
  if (confArr == 'bg') {
    conf = configData.payload.background.condBackgrounds
  }
  else if (confArr == 'batteries') {
    conf = configData.payload.batteries.condImages
  }
  else {
    conf = imgCat
  }

  var curCond = conf.find(c => c.index == $(elm).attr('index'));
  let res = $(elm).val()
  const match = res.match(/#.*?#/g);
  if (match) {
    match.forEach(item => {
      $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
          action: 'humanReadableToCmd',
          human: item
        },
        cache: false,
        dataType: 'json',
        async: false,
        success: function (data) {
          if (data.state == 'ok') {
            res = res.replace(item, data.result)
          }
        }
      });
    });
  }
  curCond.condition = res;
}

function setCondToHuman(confArr) {
  if (confArr == 'bg') {
    conf = configData.payload.background.condBackgrounds;
    idName = 'bg-cond-input-';
  }
  else if (confArr == 'batteries') {
    conf = configData.payload.batteries.condImages;
    idName = 'batteries-cond-input-';
  }
  else {
    conf = imgCat;
    idName = 'imglist-cond-';
  }

  conf.forEach(cond => {
    let input = $("#" + idName + cond.index);
    let value = cond.condition ? cond.condition.slice() : '';
    const match = value.match(/#.*?#/g);
    if (match) {
      match.forEach(item => {
        $.post({
          url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
          data: {
            action: 'cmdToHumanReadable',
            strWithCmdId: item
          },
          cache: false,
          dataType: 'json',
          async: false,
          success: function (data) {
            if (data.state == 'ok') {
              value = value.replace(item, data.result);
            }
          }
        });
      });
    }
    input.val(value);
  });
}




/**
 * *************
 * TOP ACTION BUTTON
 * *************
 */


$('.eqLogicAction[data-action=addWidget]').off('click').on('click', function () {
  getWidgetModal({ title: "Configuration du widget", removeAction: false, exit: true, itemType: "widget" });
})

$('.eqLogicAction[data-action=addComponent]').off('click').on('click', function () {
  getWidgetModal({ title: "Configuration du composant", removeAction: false, exit: true, itemType: "component" });
})

$('.eqLogicAction[data-action=showError]').off('click').on('click', function () {

  var hide = ($('#spanWidgetErreur').text() != 'Tous') ? true : false;
  if (hide) {
    $('.widgetDisplayCard').not(".hasError,.hasWarning").hide();
    $('.componentDisplayCard').not(".hasError,.hasWarning").hide();
  }
  else {
    $('.widgetDisplayCard').show();
    $('.componentDisplayCard').show();
  }


  var typeSelected = $('#widgetTypeSelect').val();

  if (typeSelected != 'none') {
    $('.widgetDisplayCard').not("[data-widget_type=" + typeSelected + "]").hide();
  }

  var typeComponentSelected = $('#componentTypeSelect').val();

  if (typeComponentSelected != 'none') {
    $('.componentDisplayCard').not("[data-widget_type=" + typeComponentSelected + "]").hide();
  }


  $('.eqLogicThumbnailContainer').packery();

  if (hide) {
    $('#spanWidgetErreur').text('Tous');
    $('.eqLogicAction[data-action=showError]').css('color', 'grey');
  }
  else {
    if (($('.widgetDisplayCard.hasError').length + $('.componentDisplayCard.hasError').length) > 0) {
      $('#spanWidgetErreur').text('Erreur');
      $('.eqLogicAction[data-action=showError]').css('color', 'red');
    }
    else if (($('.widgetDisplayCard.hasWarning').length + $('.componentDisplayCard.hasWarning').length) > 0) {
      $('#spanWidgetErreur').text('Warning');
      $('.eqLogicAction[data-action=showError]').css('color', 'orange');
    }
  }
  updateWidgetCount()

})

$('.eqLogicAction[data-action=addWidgetBulk]').off('click').on('click', function () {
  $("#widgetModal").dialog('destroy').remove();
  $('body').append('<div id="widgetModal"></div>');
  $('#widgetModal').dialog({
    title: "{{Ajout de widgets en masse}}",
    width: 0.95 * $(window).width(),
    height: 0.8 * $(window).height(),
    modal: true,
    closeText: ''
  });
  $('#widgetModal').load('index.php?v=d&plugin=JeedomConnect&modal=assistant.widgetBulkModal.JeedomConnect').dialog('open');
})

$('.eqLogicAction[data-action=showMaps]').off('click').on('click', function () {
  $("#widgetModal").dialog('destroy').remove();
  $('body').append('<div id="mapsModal"></div>');
  $('#mapsModal').dialog({
    title: "{{Localisation de vos équipements JC}}",
    width: 710,
    height: 770, //0.8 * $(window).height(),
    modal: true,
    closeText: ''
  });
  $('#mapsModal').load('index.php?v=d&plugin=JeedomConnect&modal=position.JeedomConnect').dialog('open');
})

$('.eqLogicAction[data-action=showCommunity]').off('click').on('click', function () {
  showCommunity($('.txtInfoPlugin').html())

});

async function showCommunity(txtInfoPlugin) {

  var data = {
    action: 'getInstallDetails'
  }
  var infoPlugin = await asyncAjaxGenericFunction(data);

  getSimpleModal({
    title: "Forum",
    width: 0.5 * $(window).width(),
    fields: [{
      type: "string",
      value: txtInfoPlugin
    },
    {
      type: "string",
      id: "infoPluginModal",
      value: infoPlugin.result
    }],
    buttons: {
      "Fermer": function () {
        $('#simpleModalAlert').hide();
        $(this).dialog("close");
      },
      "Copier": function () {
        copyDivToClipboard('#infoPluginModal', true)
      }
    }
  }, function (result) { });


}

$('.eqLogicAction[data-action=showSummary]').off('click').on('click', function () {
  $('body').append('<div id="widgetSummaryModal"></div>');
  $('#widgetSummaryModal').dialog({
    title: "{{Synthèse globale des widgets}}",
    autoOpen: false,
    modal: true,
    closeText: '',
    width: 0.9 * $(window).width(),
    height: 0.8 * $(window).height(),
    closeOnEscape: false,
    // open: function(event, ui) { $(".ui-dialog-titlebar-close").hide(); },
    close: function (ev, ui) { check_before_closing(); }
  });
  $('#widgetSummaryModal').load('index.php?v=d&plugin=JeedomConnect&modal=assistant.widgetSummary.JeedomConnect').dialog('open');
})

$('.eqLogicAction[data-action=showEquipmentSummary]').off('click').on('click', function () {
  $('body').append('<div id="equipmentSummaryModal"></div>');
  $('#equipmentSummaryModal').dialog({
    title: "{{Synthèse des équipements JC}}",
    autoOpen: false,
    modal: true,
    closeText: '',
    width: 0.9 * $(window).width(),
    height: 0.8 * $(window).height(),
    closeOnEscape: false,
    // open: function(event, ui) { $(".ui-dialog-titlebar-close").hide(); },
    close: function (ev, ui) { check_before_closing(); }
  });
  $('#equipmentSummaryModal').load('index.php?v=d&plugin=JeedomConnect&modal=assistant.equipmentSummary.JeedomConnect').dialog('open');
})

$('.eqLogicAction[data-action=showNotifAll]').off('click').on('click', function () {
  $('body').append('<div id="notifAllModal"></div>');
  $('#notifAllModal').dialog({
    title: "{{Configurer les notifications multiples}}",
    autoOpen: false,
    modal: true,
    closeText: '',
    width: 0.7 * $(window).width(),
    height: 0.8 * $(window).height(),
    closeOnEscape: false
  });
  $('#notifAllModal').load('index.php?v=d&plugin=JeedomConnect&modal=assistant.notifAll.JeedomConnect').dialog('open');
})

// Start Generic Types
// HACK Remove when gentype config in plugin is not needed anymore
function gotoGenTypeConfig() {
  $('#md_modal').dialog({ title: "{{Objets / Pièces}}" });
  $('#md_modal').load('index.php?v=d&plugin=JeedomConnect&modal=gentype.objects').dialog('open');
}

$('.eqLogicAction[data-action=gotoGenTypeConfig]').off('click').on('click', function () {
  gotoGenTypeConfig();
})
// End Generic Types

// Start Community Post
async function createCommunityPost() {
  var data = {
    action: 'createCommunityPost'
  }
  var infoPlugin = await asyncAjaxGenericFunction(data);
  // console.log('info plugin', infoPlugin);

  var element = document.createElement('a');

  element.setAttribute('href', infoPlugin.result.url);
  element.setAttribute('target', '_blank');

  element.style.display = 'none';
  document.body.appendChild(element);

  element.click();

  document.body.removeChild(element);


}

$('.eqLogicAction[data-action=createCommunityPostJC]').off('click').on('click', function () {
  createCommunityPost();
})
// End Community Post


$('.eqLogicAction[data-action=moreJcOptions]').off('click').on('click', function () {
  $('.hideOptionMenu').show();

  var display = ($('#spanMoreJcOptions').attr('data-type') == 'more') ? true : false;
  if (display) {
    $('.hideOptionMenu').show();
    $('#spanMoreJcOptions').text("Moins d'options");
    $('#spanMoreJcOptions').attr('data-type', 'less')
  }
  else {
    $('.hideOptionMenu').hide();
    $('#spanMoreJcOptions').text("Plus d'options");
    $('#spanMoreJcOptions').attr('data-type', 'more');
  }

})



// ------------- END TOP ACTION BUTTON



/**
 * *************
 * SEARCH, ORDER & FILTER BAR
 * *************
 */

$('#widgetsList-div').on('change', function () {
  updateWidgetCount()
})

$('#componentsList-div').on('change', function () {
  updateWidgetCount('component')
})

function updateWidgetCount(type = 'widget') {
  var itemClass = (type == 'widget') ? '.widgetDisplayCard' : '.componentDisplayCard';
  var itemId = (type == 'widget') ? '#coundWidget' : '#coundComponent';

  var nbVisible = $(itemClass + ':visible').length;
  var nbTotal = $(itemClass).length;

  var text = (nbTotal != nbVisible) ? nbVisible + "/" + nbTotal : nbTotal;

  $(itemId).text("(" + text + ")");
}

$('.jcItemSelect').on('change', function () {
  var dataType = $(this).attr('data-type');
  var typeSelected = this.value;

  var itemClass = (dataType == 'widget') ? '.widgetDisplayCard' : '.componentDisplayCard';

  $(itemClass).show();
  if (typeSelected != 'none') {
    $(itemClass).not("[data-widget_type=" + typeSelected + "]").hide();
  }

  var widgetSearch = $('#in_search').val()?.trim();
  if (widgetSearch != '') {
    $('#in_search').keyup()
  }

  $('.eqLogicThumbnailContainer').packery();

  updateWidgetCount(dataType);

});

$('#eraseFilterChoice').off('click').on('click', function () {
  var vars = getUrlVars()
  var url = 'index.php?'
  for (var i in vars) {
    if (i != 'id' && i != 'saveSuccessFull' && i != 'removeSuccessFull' &&
      i != 'jcOrderBy' && i != 'jcSearch' && i != 'jcFilter') {
      url += i + '=' + vars[i].replace('#', '') + '&'
    }
  }

  jeedomUtils.loadPage(url)
})


$('.jcResetSearch').on('click', function () {
  var inputId = $(this).attr('data-input')
  $('#' + inputId).val('').keyup()
})

$('.updateOrderWidget').on('change', function () {

  var type = $(this).val();

  var vars = getUrlVars()
  var url = 'index.php?'

  url = getCustomParamUrl(url, vars);

  jeedomUtils.loadPage(url)

});

function getCustomParamUrl(url, vars) {

  for (var i in vars) {
    if (i != 'jcOrderBy' && i != 'jcFilter' && i != 'jcSearch') {
      url += i + '=' + vars[i].replace('#', '') + '&'
    }
  }

  var widgetFilter = $("#widgetTypeSelect option:selected").val();
  if (widgetFilter != 'none') {
    url += '&jcFilter=' + widgetFilter
  }

  var widgetOrder = $("#widgetOrder option:selected").val();
  if (widgetOrder != 'none') {
    url += '&jcOrderBy=' + widgetOrder
  }

  var widgetSearch = $("#in_search").val()?.trim();
  if (widgetSearch != '') {
    url += '&jcSearch=' + widgetSearch
  }

  return url;
}

function sortWidgets() {
  if (jcOrderBy === undefined) jcOrderBy = 'object';

  allWidgetsDetail.sort(SortByName);
  if (jcOrderBy == 'object') {
    allWidgetsDetail.sort(SortByRoom);
  } else if (jcOrderBy == 'type') {
    allWidgetsDetail.sort(SortByType);
  }
}

function SortByName(a, b) {
  var aName = a.name?.toLowerCase();
  var bName = b.name?.toLowerCase();
  return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
}

function SortByType(a, b) {
  var aType = a.type?.toLowerCase();
  var bType = b.type?.toLowerCase();
  return ((aType < bType) ? -1 : ((aType > bType) ? 1 : 0));
}

function SortByRoom(a, b) {

  var aObj = ('room' in a) ? roomList.find(r => r.id == a.room) || undefined : undefined;
  var bObj = ('room' in b) ? roomList.find(r => r.id == b.room) || undefined : undefined;

  var aRoom = (aObj != undefined) ? aObj.name.toLowerCase() : 'AAAucun';
  var bRoom = (bObj != undefined) ? bObj.name.toLowerCase() : 'AAAucun';

  return ((aRoom < bRoom) ? -1 : ((aRoom > bRoom) ? 1 : 0));
}

$('.jcInSearch').off('keyup').keyup(function () {
  var dataType = $(this).attr('data-type')

  var search = $(this).value()

  var widgetFilter = $("#widgetTypeSelect option:selected").val();
  var componentFilter = $("#componentTypeSelect option:selected").val();

  if (search == '') {
    if (widgetFilter == 'none') {
      $('.widgetDisplayCard').show()
    }
    else {
      $('.widgetDisplayCard').each(function () {
        widgetType = $(this).attr('data-widget_type');
        if (widgetFilter == widgetType) {
          $(this).closest('.widgetDisplayCard').show()
        }
      })
    }

    if (componentFilter == 'none') {
      $('.componentDisplayCard').show()
    }
    else {
      $('.componentDisplayCard').each(function () {
        componentType = $(this).attr('data-widget_type');
        if (componentFilter == componentType) {
          $(this).closest('.componentDisplayCard').show()
        }
      })
    }

    $('.eqLogicThumbnailContainer').packery()
    updateWidgetCount();
    updateWidgetCount('component');
    return;
  }


  $('.widgetDisplayCard,.componentDisplayCard').hide()
  search = jeedomUtils.normTextLower(search)
  var text
  var widgetId

  $('.widgetDisplayCard,.componentDisplayCard').each(function () {
    text = jeedomUtils.normTextLower($(this).children('.name').text())
    widgetId = jeedomUtils.normTextLower($(this).attr('data-widget_id'))
    widgetType = $(this).attr('data-widget_type');
    if (text.indexOf(search) >= 0 || widgetId.indexOf(search) >= 0) {
      if ($(this).hasClass('widget')) {
        if (widgetFilter == 'none' || widgetFilter == widgetType) {
          $(this).closest('.widgetDisplayCard').show()
        }
      }
      else if ($(this).hasClass('component')) {
        if (componentFilter == 'none' || componentFilter == widgetType) {
          $(this).closest('.componentDisplayCard').show()
        }
      }
    }
  })
  $('.eqLogicThumbnailContainer').packery()
  updateWidgetCount();
  updateWidgetCount('component');
})

// ------------- END SEARCH & FILTER BAR

/**
 * *************
 * COMMUNITY INFO
 * *************
 */

function copyDivToClipboard(myInput, addBacktick = false) {
  var initialText = $(myInput).html();
  if (addBacktick) {
    $(myInput).html('```<br/>' + initialText.replaceAll('<b>', '').replaceAll('</b>', '') + '```');
  }
  var range = document.createRange();
  range.selectNode($(myInput).get(0));
  window.getSelection().removeAllRanges(); // clear current selection
  window.getSelection().addRange(range); // to select text
  document.execCommand("copy");
  window.getSelection().removeAllRanges();// to deselect
  $('#div_simpleModalAlert').showAlert({
    message: 'Infos copiées',
    level: 'success'
  });
  if (addBacktick) {
    $(myInput).html(initialText);
  }
}
// ------------- END COMMUNITY INFO


/**
 * *************
 * WARNING COMMUNITY INFO
 * *************
 */

function displayJCWarning() {
  var color = [
    'background-color: #fc59cc !important;',
    'background-color: #933ed6 !important;',
    'background-color: rgb(27,161,242)!important;',
    'background-color: orange !important;'
  ];
  shuffle(color);

  var varButton = [
    {
      text: "Ok mais j'ai pas lu",
      open: function () {
        $(this).attr('style', color[0]);
      },
      click: function () {
        warningResponse($(this), false);
      }
    },
    {
      text: "Je clique sans lire",
      open: function () {
        $(this).attr('style', color[1]);
      },
      click: function () {
        warningResponse($(this), false);
      }
    },
    {
      text: "J'ai lu et je le ferai. Promis juré",
      open: function () {
        $(this).attr('style', color[2]);
      },
      click: function () {
        $.post({
          url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
          data: {
            'action': 'incrementWarning'
          },
          cache: false,
          dataType: 'json',
          async: true,
        });
        warningResponse($(this), true);
      }
    },
    {
      text: "J'ai jamais besoin d'aide je suis trop balaise",
      open: function () {
        $(this).attr('style', color[3]);
      },
      click: function () {
        warningResponse($(this), false);
      }
    }
  ];


  shuffle(varButton);

  getSimpleModal({
    title: "Important - JeedomConnect - A lire",
    width: 0.5 * $(window).width(),
    hideCloseButton: true,
    hideActionButton: true,
    fields: [{
      type: "string",
      value: $('.displayJCWarning').html()
    }],
    buttons: varButton
  }, function (result) { });


};

function warningResponse(elt, good) {
  JCwarningAlreadyDisplayed = true;
  $(elt).dialog("close");
  $.fn.showAlert({
    message: good ? 'Merci. On compte sur toi !' : 'Mauvais réponse ... on se revoit bientot !',
    level: good ? 'success' : 'warning'
  });
  $(".displayJCWarning").remove();
}


/**
 * *************
 * ASSISTANT BUTTON
 * *************
 */

$(".btnAssistant").click(function (event) {
  var id = $(this).closest('.eqLogicDisplayCard').data('eqlogic_id');
  openAssistantWidgetModal(id, event);
});

function openAssistantWidgetModal(id, event) {
  if (event !== undefined) event.stopPropagation();
  $('#md_modal').dialog({ title: "{{Configuration de l'équipement}}" });
  $('#md_modal').load('index.php?v=d&plugin=JeedomConnect&modal=assistant.JeedomConnect&eqLogicId=' + id).dialog('open');
}


$(".btnGeofencing").click(function (event) {
  if (event !== undefined) event.stopPropagation();
  var id = $(this).closest('.eqLogicDisplayCard').data('eqlogic_id');

  $("#mapsModal").dialog('destroy').remove();
  $('body').append('<div id="mapsModal"></div>');
  var coeff = ($(window).width() < 1920) ? 0.9 : 0.8;
  $('#mapsModal').dialog({
    title: "{{Geofencing}}",
    width: coeff * $(window).width(),
    height: 640,
    modal: true,
    closeText: ''
  });
  $('#mapsModal').load('index.php?v=d&plugin=JeedomConnect&modal=position.JeedomConnect&geo=true&eqId=' + id).dialog('open');

});

$(".btnNotification").click(function (event) {
  var id = $(this).closest('.eqLogicDisplayCard').data('eqlogic_id');
  openAssistantNotificationModal(id, event);
});

$("#seeProg-btn").click(function () {
  openAssistantProgModal($('.eqLogicAttr[data-l1key=id]').value());
});

function openAssistantProgModal(id, event) {
  if (event !== undefined) event.stopPropagation();
  $('#md_modal').dialog({ title: "{{Visualiser les programmations}}" });
  $('#md_modal').load('index.php?v=d&plugin=JeedomConnect&modal=prog.JeedomConnect&eqLogicId=' + id).dialog('open');
}

function openAssistantNotificationModal(id, event) {
  if (event !== undefined) event.stopPropagation();
  $('#md_modal').dialog({ title: "{{Configuration des notifications}}" });
  $('#md_modal').load('index.php?v=d&plugin=JeedomConnect&modal=notifs.JeedomConnect&eqLogicId=' + id).dialog('open');
}

// ------------- END ASSISTANT BUTTON


/**
 * *************
 * COMMAND TAB
 * *************
 */

$(".commandtab").sortable({
  axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true
});

function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} };
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td style="min-width:50px;width:70px;">';
  tr += '<span class="cmdAttr" data-l1key="id"></span>';
  tr += '</td>';
  tr += '<td style="min-width:300px;width:350px;">';
  tr += '<div class="row">';
  tr += '<div class="col-xs-7">';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la commande}}">';
  tr += '</div>';
  tr += '<div class="col-xs-5">';
  tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fas fa-flag"></i> {{Icône}}</a>';
  tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
  tr += '</div>';
  tr += '</div>';
  tr += '</td>';
  tr += '<td>';
  tr += '<span class="type" style="display:none;" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
  tr += '</td>';
  if (init(_cmd.type) == 'info' && typeof jeeFrontEnd !== 'undefined' && jeeFrontEnd.jeedomVersion !== 'undefined') {
    tr += '<td >';
    tr += '<span class="cmdAttr" data-l1key="htmlstate"></span> ';
    tr += '</td>';
  }
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> '
  tr += '<div style="margin-top:7px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '</div>'
  tr += '</td>'
  tr += '<td >';
  tr += '<input style="min-width:30px;width:50px;" class="cmdAttr" data-l1key="order" placeholder="{{Ordre affichage}}"/> ';
  tr += '</td>';
  tr += '<td>';
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> Tester</a>';
  }
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" style="display:none"></i>';
  tr += '</td>';
  tr += '</tr>';



  cpltOnglet = '';
  if (_cmd.logicalId != null && _cmd.logicalId != '') {
    if (($.inArray(_cmd.logicalId, ["distance", "position", "activity"]) != -1) || ((_cmd.logicalId).indexOf('geofence_') >= 0)) {
      cpltOnglet = 'Position';
    }
    else if (($.inArray(_cmd.logicalId, ["defaultNotif"]) != -1) || ((_cmd.logicalId).indexOf('notif') >= 0)) {
      cpltOnglet = 'Notification';
    }
  }


  $('#table_cmd tbody.cmd_' + _cmd.type + cpltOnglet).append(tr);
  var tr = $('#table_cmd tbody.cmd_' + _cmd.type + cpltOnglet + ' tr').last();

  jeedom.eqLogic.buildSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function (error) {
      $('#div_alert').showAlert({ message: error.message, level: 'danger' });
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result);
      tr.setValues(_cmd, '.cmdAttr');
      jeedom.cmd.changeType(tr, init(_cmd.subType));
    }
  });
}

// ------------- END COMMAND TAB


$(".eqlogic-qrcode").hover(function () {
  $('.showqrcode').attr('src', $(this).data('qrcode') + '?' + new Date().getTime());
  $('.hideWhileShowqrcode').hide();
  $('.showqrcode-content').show();
}, function () {
  $('.showqrcode-content').hide();
  $('.showqrcode').attr('src', '');
  $('.hideWhileShowqrcode').show();
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=volume]').on('change', function () {
  console.log('on change', $(this).find(":selected"))
  if ($(this).find(":selected").val() == 'all') {
    $('.descConfigVolume').show();
  }
  else {
    $('.descConfigVolume').hide();
  }

});


/**
 * *************
 * DOCUMENT READY
 * *************
 */

$(document).ready(
  function () {
    if ($(".displayJCWarning").length != 0 && (typeof JCwarningAlreadyDisplayed === 'undefined')) {
      displayJCWarning();
    }

    if ($('.jcInSearch[data-type=component]').val() != '') {
      $('.jcInSearch[data-type=component]').keyup();
    }
    if ($('.jcInSearch[data-type=widget]').val() != '') {
      $('.jcInSearch[data-type=widget]').keyup();
    }
  }
);


updateWidgetCount();
updateWidgetCount('component');
