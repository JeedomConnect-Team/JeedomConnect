
/*********** FOR EXPERT MODE *********/

$("#widgetOptions").on('focusin', '.jcCmdListOptionsCommand', function () {
    $(this).attr('data-value-focusin', $(this).val());
})

$("#widgetOptions").on('focusout', '.jcCmdListOptionsCommand', function () {
    var previousData = $(this).attr('data-value-focusin');
    if (previousData != $(this).val()) {

        var elt = $(this);
        var currentId = elt.attr('data-cmd-id');
        var currentIndex = elt.attr('data-index');

        getCmd({
            id: elt.val(),
            error: function (error) {
                $('#widget-alert').showAlert({ message: error.result, level: 'danger' });
            },
            success: function (data) {
                $('#widget-alert').hideAlert();

                // remove options of the previous cmd
                elt.parent().siblings().each(function () {
                    if ($(this).find('.jcCmdListOptions').length !== 0) {
                        $(this).remove();
                    }
                });

                // create options for the new cmd selected
                newItem = { id: data.result.id, subtype: data.result.subType, index: currentIndex, name: data.result.name };
                divListOption = getCmdOptions(newItem);
                elt.parent().parent().append(divListOption);

                // update cmdCat
                cmdCat.forEach(item => {
                    if (item.id == currentId && item.index == currentIndex) {
                        item.id = data.result.id;
                        item.name = data.result.humanName;
                        item.subtype = data.result.subType;
                    }
                })
                saveCmdList();

            }
        });

    }

    $(this).attr('data-value-focusin', '');
})
/**----------- END EXPERT MODE ---------*/

function getWidgetModal(_options, _callback) {
    // console.log("getWidgetModal option recues : ", _options)
    if (!isset(_options)) {
        return;
    }
    $("#widgetModal").dialog('destroy').remove();
    $("#simpleModal").dialog('destroy').remove();

    if ($("#widgetModal").length == 0) {
        $('body').append('<div id="widgetModal" widgetOld="' + (_options.widget ? JSON.stringify(_options.widget).replace(/"/g, '&quot;') : '{}') + '"></div>');

        $("#widgetModal").dialog({
            title: _options.title,
            closeText: '',
            autoOpen: false,
            modal: true,
            width: 1250,
            height: 0.8 * $(window).height()
        });
        jQuery.ajaxSetup({
            async: false
        });
        $('#widgetModal').load('index.php?v=d&plugin=JeedomConnect&modal=assistant.widgetModal.JeedomConnect');
        jQuery.ajaxSetup({
            async: true
        });
    }
    setWidgetModalData(_options);

    if (_options.inEquipments !== undefined && _options.inEquipments != '') {

        $('#widgetInclusion').html('<span id="widgetExistInEquipement" style="display:none">' + _options.inEquipments.join(', ') + '</span>');
        $('#widgetInclusion').append('Utilisé dans :<br/><ul>');

        $.each(_options.inEquipments, function (index, value) {
            $("#widgetInclusion").append('<li>' + value + '</li>');
        });
        $("#widgetInclusion").append('</ul>');
        $('#widgetInclusion').css('display', 'block');
    }

    if (_options.inCusto !== undefined && _options.inCusto != '') {

        $('#widgetPerso').append('Personnalisé sur :<br/><ul>');

        $.each(_options.inCusto, function (index, value) {
            $("#widgetPerso").append('<li>' + value + '</li>');
        });
        $("#widgetPerso").append('</ul>');
        $('#widgetPerso').css('display', 'block');
    }

    if (_options.removeAction != true) {
        $('.widgetMenu .removeWidget').hide();
        $('.widgetMenu .hideWidget').addClass('roundedRight');
    }

    if ($('#widgetOptions').attr('widget-id') == undefined || $('#widgetOptions').attr('widget-id') == '' || !(_options.duplicate)) {
        $('.widgetMenu .duplicateWidget').hide();
        $('.widgetMenu .saveWidget').addClass('roundedLeft');
    }
    else {
        $('.widgetMenu .duplicateWidget').show();
        $('.widgetMenu .saveWidget').removeClass('roundedLeft');
    }

    if ($('#widgetOptions').attr('widget-id') != undefined && $('#widgetOptions').attr('widget-id') != '') {
        $('.autoFillWidgetCmds').hide();
    }

    if (_options.exit == true) {
        $('.widgetMenu .saveWidget').attr('exit-attr', 'true');
    }

    $("#widgetModal").dialog({ title: _options.title });
    $('#widgetModal').dialog('open');

};



function setWidgetModalData(options) {
    $('#summaryModal').dialog('destroy').remove();

    if (options.itemType == 'component') {
        $('#widgetsList-select option:not(.component)').hide();
        $('#widgetsList-select option.component:first').prop("selected", "selected");
        $('.jcItemModal .itemType').text('composant');
    }
    else {
        $('#widgetsList-select option:not(.widget)').hide();
        $('#widgetsList-select option.widget:first').prop("selected", "selected");

    }

    refreshAddWidgets();


    if (options.widget !== undefined) {
        $('#widgetsList-select option.' + options.itemType + '[value="' + options.widget.type + '"]').prop('selected', true);
        refreshAddWidgets();
        //Enable
        var enable = options.widget.enable ? "checked" : "";
        $("#enable-input").prop('checked', enable);
        var blockDetail = options.widget.blockDetail ? "checked" : "";
        $("#blockDetail-input").prop('checked', blockDetail);

        //Room
        if (options.widget.room !== undefined) {
            $('#room-input option[value="' + options.widget.room + '"]').prop('selected', true);
        }

        // visible
        var visibilityCond = options.widget.visibilityCond || '';
        if (visibilityCond != '') {
            const match = visibilityCond.match(/#.*?#/g);
            if (match) {
                match.forEach(item => {
                    getHumanNameFromCmdId({ alert: '#widget-alert', cmdIdData: item }, function (humanResult, _params) {
                        visibilityCond = visibilityCond.replace(item, humanResult);
                    });
                });
            }
            $('#widgetModal #visibility-cond-input').val(visibilityCond);
        }

        moreInfos = options.widget.moreInfos || [];
        refreshMoreInfos();

        $("#widgetOptions").attr('widget-id', options.eqId ?? '');

        var itemList = (options.itemType == 'component') ? widgetsList.components : widgetsList.widgets
        var widgetConfig = itemList.find(i => i.type == options.widget.type);
        //  console.log("widgetsList => ", widgetsList) ;
        //  console.log("widgetConfig => ", widgetConfig) ;
        widgetConfig.options.forEach(option => {
            if (option.category == "string" & options.widget[option.id] !== undefined) {
                $("#" + option.id + "-input").val(options.widget[option.id]);
            } else if (option.category == "binary" & options.widget[option.id] !== undefined) {
                $("#" + option.id + "-input").prop('checked', options.widget[option.id] ? "checked" : "");
            } else if (option.category == "color" & options.widget[option.id] !== undefined) {
                $("#" + option.id + "-input").val(options.widget[option.id]);
                $("#" + option.id + "-picker").val(options.widget[option.id]);
            } else if (option.category == "cmd" & options.widget[option.id] !== undefined) {
                $("#" + option.id + "-input").attr('cmdId', options.widget[option.id].id);
                cmdHumanName = getHumanName(options.widget[option.id].id)
                if (cmdHumanName != '') {
                    $("#" + option.id + "-input").val(cmdHumanName);
                    $("#" + option.id + "-input").attr('title', cmdHumanName);
                    refreshImgListOption();
                    refreshInfoSelect();
                }
                else {
                    $("#" + option.id + "-input").val('#' + options.widget[option.id].id + '#');
                }
                $("#" + option.id + "-input").attr('cmdType', options.widget[option.id].type);
                $("#" + option.id + "-input").attr('cmdSubType', options.widget[option.id].subType);
                if (options.widget[option.id].type == 'action') {
                    $("#confirm-div-" + option.id).css('display', '');
                    $("#secure-div-" + option.id).css('display', '');
                    $("#pwd-div-" + option.id).css('display', '');
                    $("#confirm-" + option.id).prop('checked', options.widget[option.id].confirm ? "checked" : "");
                    $("#secure-" + option.id).prop('checked', options.widget[option.id].secure ? "checked" : "");
                    $("#pwd-" + option.id).prop('checked', options.widget[option.id].pwd ? "checked" : "");
                    $("#" + option.id + "-input").css({
                        'width': $("#" + option.id + "-input").width() * 0.85
                    })
                } else {
                    $("#confirm-div-" + option.id).css('display', 'none');
                    $("#secure-div-" + option.id).css('display', 'none');
                    $("#pwd-div-" + option.id).css('display', 'none');
                }
                if (options.widget[option.id].subType == 'slider' | options.widget[option.id].subType == 'numeric') {
                    $("#" + option.id + "-minInput").css('display', '');
                    $("#" + option.id + "-maxInput").css('display', '');
                    $("#" + option.id + "-minInput").val(options.widget[option.id].minValue);
                    $("#" + option.id + "-maxInput").val(options.widget[option.id].maxValue);
                    $("#" + option.id + "-input").css({
                        'width': $("#" + option.id + "-input").width() * 0.85
                    })
                } else {
                    $("#" + option.id + "-minInput").css('display', 'none');
                    $("#" + option.id + "-maxInput").css('display', 'none');
                }
                if (options.widget[option.id].subType == 'binary' | options.widget[option.id].subType == 'numeric') {
                    $("#invert-div-" + option.id).css('display', '');
                    $("#invert-" + option.id).prop('checked', options.widget[option.id].invert ? "checked" : "");
                } else {
                    $("#invert-div-" + option.id).css('display', 'none');
                }
                if (['slider', 'numeric'].includes(options.widget[option.id].subType)) {
                    $("#" + option.id + "-stepInput").css('display', '');
                    $("#" + option.id + "-stepInput").val(options.widget[option.id].step);
                    $("#" + option.id + "-input").css({
                        'width': $("#" + option.id + "-input").width() * 0.85
                    })
                }
                else {
                    $("#" + option.id + "-stepInput").css('display', 'none');
                }
                if (options.widget[option.id].subType == 'numeric') {
                    $("#" + option.id + "-unitInput").css('display', '');
                    $("#" + option.id + "-unitInput").val(options.widget[option.id].unit);
                    $("#" + option.id + "-input").css('width', '450px');
                    $("#" + option.id + "-input").css({
                        'width': $("#" + option.id + "-input").width() * 0.85
                    })
                } else {
                    $("#" + option.id + "-unitInput").css('display', 'none');
                }

            } else if (option.category == "scenario" & options.widget[option.id] !== undefined) {
                getScenarioHumanName({
                    id: options.widget[option.id],
                    error: function (error) { },
                    success: function (data) {
                        data.forEach(sc => {
                            if (sc['id'] == options.widget[option.id]) {
                                $("#" + option.id + "-input").attr('scId', options.widget[option.id]);
                                $("#" + option.id + "-input").val(sc['humanName']);
                            }
                        })
                    }
                });
                $('#optionScenario-' + option.id).css('display', 'block');

                if (options.widget['options'] !== undefined &&
                    options.widget['options']['tags'] !== undefined &&
                    options.widget['options']['tags'] != '') {
                    getHumanNameFromCmdId({ alert: '#widget-alert', cmdIdData: options.widget['options']['tags'] }, function (result, _params) {
                        $('#tags-scenario-' + option.id + '-input').val(result);
                    });
                }
                if (options.widget['options'] !== undefined) {
                    $("#confirm-" + option.id).prop('checked', options.widget['options'].confirm ? "checked" : "");
                    $("#secure-" + option.id).prop('checked', options.widget['options'].secure ? "checked" : "");
                    $("#pwd-" + option.id).prop('checked', options.widget['options'].pwd ? "checked" : "");
                }

            } else if (option.category == "stringList" & options.widget[option.id] !== undefined) {
                var selectedChoice = option.choices.find(s => s.id == options.widget[option.id]);
                if (selectedChoice !== undefined) {
                    $('#' + option.id + '-input option[value="' + options.widget[option.id] + '"]').prop('selected', true);
                    if (option.id == "subtitle") {
                        $("#subtitle-input-value").val(selectedChoice.id)
                    }
                } else if (option.id == "subtitle" & options.widget.subtitle !== undefined) {
                    $('#subtitle-input option[value="custom"]').prop('selected', true);
                    $("#subtitle-input-value").val(options.widget.subtitle)
                    $("#subtitle-input-value").css('display', 'block');
                    $("#subtitle-select").show();
                }
            } else if (option.category == "widgets" & options.widget[option.id] !== undefined) {
                widgetsOption = options.widget[option.id];
                refreshWidgetOption();
            } else if (option.category == "cmdList" & options.widget[option.id] !== undefined) {
                cmdCat = options.widget[option.id];
                refreshCmdListOption(JSON.stringify(option.options));
            } else if (option.category == "ifImgs" & options.widget[option.id] !== undefined) {
                imgCat = options.widget[option.id];
                refreshImgListOption();
            } else if (option.category == "choicesList") {
                option.choices.forEach(v => {
                    $("#" + v.id + "-jc-checkbox").prop('checked', options.widget[v.id] ? "checked" : "");
                });
            } else if (option.category == "img" & options.widget[option.id] !== undefined) {
                $("#icon-div-" + option.id).html(iconToHtml(options.widget[option.id]));
            } else if (option.category == "actionList") {
                // console.log('setwidget, options', options);
                options.widget.actions.forEach(item => {
                    let type = item.action
                    let index = item.index
                    let html = getHtmlItem(type, { id: index, from: 'actionList', 'noSecurity': true, withBorder: true, move: true, remove: true });
                    console.log('item actions ', item, index, type)
                    $('#actionList-div').append(html);

                    if (type == 'scenario') {
                        $('#optionScenario-' + 'actionList-' + index).css('display', 'block');
                    } else if (type == 'cmd') {
                        item.options.name = '#' + item.options.id + '#'
                    }

                    $('#actionList-div .actionList:last').setValues(item, '.actionListAttr');

                });
            } else if (option.category == "security") {
                $('.jcSecurityDiv-' + option.id).setValues({ security: options.widget[option.id] }, '.jcItemAttr-' + option.id);
            }
        });
    }
    refreshStrings();
    loadSortable('all');
}


$('#widgetsList-select').change(function () {
    refreshAddWidgets();
})


function refreshAddWidgets() {
    widgetsOption = [];
    cmdCat = [];
    imgCat = [];
    moreInfos = [];
    var type = $("#widgetsList-select").val();
    var itemType = $("#widgetsList-select").find("option:selected").hasClass('widget') ? 'widget' : 'component';

    var listItems = (itemType == 'widget') ? widgetsList.widgets : widgetsList.components
    var widget = listItems.find(i => i.type == type);
    showAutoFillWidgetCmds();

    (type == 'jc') ? $('.searchForJCeq').show() : $('.searchForJCeq').hide();

    $("#widgetImg").attr("src", "plugins/JeedomConnect/data/img/" + widget.img);

    $("#widgetDescription").html(widget.description);

    if (widget.variables) {
        let varDescr = `Variables disponibles : <ul style="padding-left: 15px;">`;
        widget.variables.forEach(v => {
            varDescr += `<li>#${v.name}# : ${v.descr}</li>`;
        });
        varDescr += '</ul>';
        $("#widgetVariables").html(varDescr);
        $("#widgetVariables").show();
    } else {
        $("#widgetVariables").hide();
    }

    var items = [];

    //Enable
    option = `<li><div class='form-group'>
  	<label class='col-xs-3 '>Actif</label>
  	<div class='col-xs-9'><div class='input-group'><input type="checkbox" style="width:150px;" id="enable-input" checked></div></div></div></li>`;
    items.push(option);

    //visible
    option = `<li><div class='form-group'>
        <label class='col-xs-3 '>Visible sous condition
            <sup>
                <i class="fas fa-question-circle floatright" style="color: var(--al-info-color) !important;" title="Permet d'ajouter une condition pour afficher ou masquer cet élément (uniquement si 'actif' est coché)"></i>
            </sup>
        </label>
        <div class='col-xs-9'>
        <div class='input-group'>
        <input style="width:600px;" class="roundedLeft" id="visibility-cond-input" value="" cmdtype="info" cmdsubtype="undefined" configtype="info" configsubtype="undefined" />
        
        <a class='btn btn-default btn-sm cursor bt_selectTrigger' tooltip='Choisir une commande' onclick="selectCmd('widgetModal #visibility-cond', 'info', 'undefined', 'undefined', true);">
        <i class='fas fa-list-alt'></i></a>`;

    // option += `<div class="dropdown" id="visibility-cond-select" style="display:inline !important;" >
    //     <a class="btn btn-default dropdown-toggle" data-toggle="dropdown" style="height" >
    //     <i class="fas fa-plus-square"></i> </a>
    //     <ul class="dropdown-menu infos-select" input="visibility-cond-input">`;
    // if (widget.variables) {
    //     widget.variables.forEach(v => {
    //         option += `<li info="${v.name}" onclick="infoSelected('#${v.name}#', this)"><a href="#">#${v.name}#</a></li>`;
    //     });
    // }
    // option += `</ul></div >
    option += `</div></div></div></li>`;
    items.push(option);


    //Room
    option = `<li><div class='form-group'>
        <label class='col-xs-3 ${type == 'room' ? 'required' : ''}'>Pièce</label>
        <div class='col-xs-9'><div class='input-group'><select style="width:600px;" id="room-input" value=''>
        <option value="none">Sélection  d'une pièce</option>`;
    option += roomListOptions;

    if (type == 'room') {
        option += `<option value="global">Global</option>`;
    }
    option += `</select></div></div></div></li>`;
    items.push(option);

    let missingOptions = false;
    widget.options.forEach(option => {
        var required = (option.required) ? "required" : "";
        var description = (option.description == undefined) ? '' : option.description;
        var curOption = `<li><div class='form-group' id="${option.id}-div">
    <label class='col-xs-3  ${required}'   id="${option.id}-label">${option.name}</label>
    <div class='col-xs-9' id="${option.id}-div-right">
    <div class="description">${description}</div>`;

        if (option.category == "cmd") {
            isDisabled = isJcExpert ? '' : 'disabled';
            curOption += `<table><tr class="cmd">
            <td>
              <input class='input-sm form-control roundedLeft needRefresh' style="width:600px;" id="${option.id}-input" value='' cmdId='' cmdType='' cmdSubType='' ${isDisabled} configtype='${option.type}' configsubtype='${option.subtype}' configlink='${option.value}'>
              <td>
                 <a class='btn btn-default btn-sm cursor bt_selectTrigger' tooltip='Choisir une commande' onclick="selectCmd('${option.id}', '${option.type}', '${option.subtype}', '${option.value}');">
                    <i class='fas fa-list-alt'></i></a>
                    </td>
            </td>
            <td>
                <i class="mdi mdi-minus-circle removeCmd" id="${option.id}-remove"
                      style="color:rgb(185, 58, 62);font-size:16px;margin-right:10px;display:${option.required ? 'none' : 'block'};" aria-hidden="true" data-cmdid="${option.id}-input"></i>
            </td>
            <td>
                    <div style="width:50px;margin-left:5px; display:none;" id="invert-div-${option.id}">
                    <i class='fa fa-sync' title="Inverser"></i><input type="checkbox" style="margin-left:5px;" id="invert-${option.id}"></div>
            </td>
            <td>
                  <div style="width:50px;margin-left:5px; display:none;" id="confirm-div-${option.id}">
                  <i class='fa fa-question' title="Demander confirmation"></i><input type="checkbox" style="margin-left:5px;" id="confirm-${option.id}"></div>
            </td>
            <td>
                    <div style="width:50px; display:none;" id="secure-div-${option.id}">
                    <i class='fa fa-fingerprint' title="Sécuriser avec empreinte digitale"></i><input type="checkbox" style="margin-left:5px;" id="secure-${option.id}"  ></div>
            </td>
            <td>
                    <div style="width:50px; display:none;" id="pwd-div-${option.id}">
                    <i class='mdi mdi-numeric' title="Sécuriser avec un code"></i><input type="checkbox" style="margin-left:5px;" id="pwd-${option.id}"  ></div>
            </td>
            <td>
                <input type="number" style="width:50px; display:none;" id="${option.id}-minInput" value='' placeholder="Min" title="Min">
            </td>
            <td>
                <input type="number" style="width:50px;margin-left:5px; display:none;" id="${option.id}-maxInput" value='' placeholder="Max" title="Max">
            </td>
            <td>
                <input type="number" step="0.1" style="width:50px;margin-left:5px; display:none;" id="${option.id}-stepInput" value='1' placeholder="Step" title="Step">
            </td>
            <td>
                <input style="width:50px; margin-left:5px; display:none;" id="${option.id}-unitInput" value='' placeholder="Unité" title="Unité" >
            </td></tr></table>
                    `;

            curOption += "</div></div></li>";

        } else if (option.category == "string") {
            type = (option.subtype != undefined) ? option.subtype : 'text';
            if (option.subtype == "multiline") {
                curOption += `<div class='input-group'>
        <div style="display:flex"><textarea style="width:600px;" id="${option.id}-input" value=''></textarea>`;
            } else {
                var min = (option.min || false) ? `min="${option.min}"` : '';
                var max = (option.max || false) ? `max="${option.max}"` : '';
                var defaultValue = (option.default || false) ? `default="${option.default}"` : '';

                curOption += `<div class='input-group'>
        <div style="display:flex"><input type="${type}" style="width:600px;" ${min} ${max} ${defaultValue} id="${option.id}-input" value='${option.default || ''}'>`;
            }

            if (option.id == 'name' || (option.useCmd != 'undefined' && option.useCmd)) {
                curOption += `
              <div class="dropdown" id="name-select">
              <a class="btn btn-default dropdown-toggle" data-toggle="dropdown" style="height" >
                <i class="fas fa-plus-square"></i>
              </a>
              <ul class="dropdown-menu infos-select" input="${option.id}-input" style="left: -600px !important;">`;
                if (widget.variables) {
                    widget.variables.forEach(v => {
                        curOption += `<li info="${v.name}" onclick="infoSelected('#${v.name}#', this)"><a href="#">#${v.name}#</a></li>`;
                    });
                }
                curOption += `</ul></div></div>`
            }
            curOption += `</div></div></div></li>`;
        } else if (option.category == "binary") {
            curOption += `<div class='input-group'><input type="checkbox" style="width:150px;" id="${option.id}-input"></div>
         </div></div></li>`;
        } else if (option.category == "color") {
            curOption += `<div class='input-group'><input style="width:200px;" id="${option.id}-input" class="inputJCColor" >
      <input type="color" id="${option.id}-picker"  class="changeJCColor">
      </div>
         </div></div></li>`;
        } else if (option.category == "stringList") {
            var classSub = (option.id == "subtitle") ? "subtitleSelected" : "";
            curOption += '<div class="input-group"><select style="width:600px;" id="' + option.id + '-input" class="' + classSub + '">';
            let defaultValueSelect = option.defaultChoice || '';
            if (required == '' && defaultValueSelect == '') {
                curOption += `<option value="none">Aucun</option>`;
            }
            option.choices.forEach(item => {
                let selectedOption = (defaultValueSelect == item.id) ? 'selected' : '';
                curOption += `<option value="${item.id}" ${selectedOption}>${item.name}</option>`;
            })
            if (option.id == "subtitle") {
                curOption += `<option value="custom">Personnalisé</option></select>`;
                curOption += `<div style="display:flex">
  					<textarea style="width:600px; margin-top:5px; display:none;" id="subtitle-input-value" value='none'></textarea>`;

                curOption += `
            <div class="dropdown" id="subtitle-select" style=" display:none;">
            <a class="btn btn-default dropdown-toggle" data-toggle="dropdown" style="height" >
            <i class="fas fa-plus-square"></i> </a>
            <ul class="dropdown-menu infos-select" input="subtitle-input-value" style="left: -600px !important;">`;
                if (widget.variables) {
                    widget.variables.forEach(v => {
                        curOption += `<li info="${v.name}" onclick="infoSelected('#${v.name}#', this)"><a href="#">#${v.name}#</a></li>`;
                    });
                }
                curOption += `</ul></div></div>`
            } else {
                curOption += '</select>';
            }
            curOption += `</div></div></div></li>`;
        } else if (option.category == "img") {
            curOption += `
              <a class="btn btn-success roundedRight imagePicker"><i class="fas fa-check-square">
              </i> Choisir </a>
              <a data-id="icon-div-${option.id}" id="icon-div-${option.id}" class="removeImage"></a>
              </div></div></li>`;


        } else if (option.category == "widgets") {
            var widgetChoices = [];
            widgetsList.widgets.forEach(item => {
                if (option.whiteList !== undefined) {
                    if (option.whiteList.includes(item.type)) {
                        widgetChoices.push(item.type);
                    }
                } else if (option.blackList !== undefined) {
                    if (!option.blackList.includes(item.type)) {
                        widgetChoices.push(item.type);
                    }
                } else {
                    widgetChoices.push(item.type);
                }
            })
            curOption += `<span class="input-group-btn">
              <a class="btn btn-default roundedRight" onclick="addWidgetOption('${widgetChoices.join(".")}')"><i class="fas fa-plus-square">
              </i> Ajouter</a></span><div id="widget-option"></div>`;
            curOption += `</div></div></li>`;
        } else if (option.category == "cmdList") {
            curOption += `<span class="input-group-btn">
              <a class="btn btn-default roundedRight" onclick="addCmdOption('${JSON.stringify(option.options).replace(/"/g, '&quot;')}')"><i class="fas fa-plus-square">
              </i> Ajouter</a></span><div id="cmdList-option" data-cmd-options="${JSON.stringify(option.options).replace(/"/g, '&quot;')}" style='margin-left:-150px;'></div>`;
            curOption += `</div></div></li>`;
        } else if (option.category == "ifImgs") {
            curOption += `<span class="input-group-btn">
              <a class="btn btn-default roundedRight" onclick="addImgOption('widget')"><i class="fas fa-plus-square">
              </i> Ajouter</a></span><div id="imgList-option"></div>`;
            curOption += `</div></div></li>`;
        } else if (option.category == "scenario") {
            curOption += `<div class='input-group'><input class='input-sm form-control roundedLeft' id="${option.id}-input" value='' scId='' disabled style="width: 600px;">
    <span class='input-group-btn' style="position: absolute;left: 600px;"><a class='btn btn-default btn-sm cursor bt_selectTrigger selectScenario' tooltip='Choisir un scenario' data-id="${option.id}" data-related="${option.id}-input">
    <i class='fas fa-list-alt'></i></a></span></div>
      <div id='optionScenario-${option.id}' style='display:none;'>
        <div class="input-group input-group-sm" style="width: 100%">
            <span class="input-group-addon roundedLeft" style="width: 100px">Tags</span>
            <input style="width:100%;" class='input-sm form-control roundedRight title' type="string" id="tags-scenario-${option.id}-input" value="" placeholder="Si nécessaire indiquez des tags" />
        </div>
        <div class="" style="width: 100%;display: flex;">
          <div class="input-group input-group-sm">
            <span class="input-group-addon roundedLeft" style="width: 100px">Sécurité</span>
          </div>
          <div style="padding-left: 10px;">
            <label class="radio-inline"><input type="radio" name="secure-radio-${option.id}" id="confirm-${option.id}" ><i class='fa fa-question' title="Demander confirmation"></i></label>
            <label class="radio-inline"><input type="radio" name="secure-radio-${option.id}" id="secure-${option.id}"  ><i class='fa fa-fingerprint' title="Sécuriser avec empreinte digitale"></i></label>
            <label class="radio-inline"><input type="radio" name="secure-radio-${option.id}" id="pwd-${option.id}"     ><i class='mdi mdi-numeric' title="Sécuriser avec un code"></i></label>
            <label class="radio-inline"><input type="radio" name="secure-radio-${option.id}" id="none-${option.id}"  checked   >Aucun</label>
          </div>
        </div>
      </div>


    </div>
    </div></li>`;
        } else if (option.category == "choicesList") {
            curOption += `<div class='input-group'>`;

            option.choices.forEach(v => {
                curOption += `<label class="checkbox-inline">
      <input type="checkbox" class="eqLogicAttr" id="${v.id}-jc-checkbox" />${v.text}
      </label>`;
            });

            curOption += `</div></div></div></li>`;
        } else if (option.category == "security") {

            curOption += getHtmlItem(option.category, { id: option.id, from: option.category, key1: option.category });

        } else if (option.category == "actionList") {
            curOption += `<div class='input-group'>
            <select class="form-control col-3 input-sm jcCmdListOptions roundedRight" id="${option.id}-select">`;

            option.options.forEach(v => {
                curOption += `<option value="${v.category}" >${v.name}</option>`;
            });

            curOption += `</select>`;
            curOption += `<span class="col-3 input-group-btn">
                <a class="btn btn-default roundedRight jcAddActionList" data-input="${option.id}-select">
                <i class="fas fa-plus-square"></i> Ajouter</a>
                </span>`;
            curOption += `</div>
            <div id="actionList-div"></div>
            </div></div></div></li>`;
        } else {
            missingOptions = true
            return;
        }

        items.push(curOption);

    });

    //More infos
    if (!widget.noMoreInfos) {
        moreDiv = `<li><div class='form-group'>
      <label class='col-xs-3 '>Ajouter des infos</label>
      <div class='col-xs-9'>
      <div class="description">Permet d'ajouter des infos utilisables dans "Nom", la partie personnalisé du "Sous-titre" et/ou "Images sous conditions"</div>
      <div class='input-group'>
      <span class="input-group-btn">
        <a class="btn btn-default roundedRight" onclick="addMoreCmd()"><i class="fas fa-plus-square">
        </i> Ajouter une commande</a>
          </span>
          </div>
        <div id="moreInfos-div"></div>
      </div></div></li>`;
        items.push(moreDiv);
    }

    if (itemType == 'widget') {
        //Details access
        option = `<li><div class='form-group'>
        <label class='col-xs-3 '>Bloquer vue détails</label>
        <div class='col-xs-9'><div class='input-group'><input type="checkbox" style="width:150px;" id="blockDetail-input" ></div></div></div></li>`;
        items.push(option);
    }


    $("#missingOptions").html(missingOptions ? "Certains paramètres ne sont disponibles que sur l'application" : "");

    $("#widgetOptions").html(items.join(""));
    loadSortable('all');
}


$("body").on('click', '#widgetModal .imagePicker', function () {
    var newElt = $(this).nextAll("a[data-id^='icon-']:first");

    getIconModal({ title: "Choisir une icône ou une image", withIcon: "1", withImg: "1", icon: htmlToIcon(newElt.children().first()), elt: newElt }, (result, _params) => {
        $(_params.elt).html(iconToHtml(result));
    });
});


$("body").on('click', '#widgetModal .removeImage', function () {
    $(this).empty();
});

$('body').off('click', '#widgetModal .removeCmd').on('click', '#widgetModal .removeCmd', function () {
    console.log("entre");
    var id = $(this).data('cmdid');
    $('#' + id).attr('value', '');
    $('#' + id).val('');
    $('#' + id).attr('cmdId', '');
});

$("body").on('click', '.selectScenario', function () {
    var related = $(this).data('related');
    var itemId = $(this).data('id');
    jeedom.scenario.getSelectModal({}, function (result) {
        $("#" + related).attr('value', result.human);
        $("#" + related).val(result.human);
        $("#" + itemId + '-id').val(result.id);
        $("#" + related).attr('scId', result.id);
        if ($("#name-input").val() == "") {
            getScenarioHumanName({
                error: function (error) { },
                success: function (data) {
                    data.forEach(sc => {
                        if (sc['id'] == result.id) {
                            $("#name-input").val(sc.name);
                        }
                    })
                }
            });
            $("#name-input").val(result.name);

            previousData = $('#tags-scenario-' + itemId + '-input').val() || '';
            $('#tags-scenario-' + itemId + '-input').val(previousData);
        }
        $('#optionScenario-' + itemId).css('display', 'block');
    })
});

$("#widgetOptions").on('change', '.needRefresh', function () {
    var info = $(this).val();
    var name = $(this).attr('id').replace('-input', '');
    var configlink = $(this).attr('configlink') || 'undefined';
    refreshCmdData(name, info, configlink);

});




function getCmdOptions(item) {

    curOption = '';

    var d = new Date().getTime();
    var rand = Math.floor(Math.random() * 10000) * Math.floor(Math.random() * 10000);
    var timestamp = d + '' + rand;
    var customUid = 'cmd' + item.id + '____' + timestamp + '____';

    // create dynamic var
    if ('options' in item) {
        for (const [key, value] of Object.entries(item.options)) {
            eval('var option' + key.charAt(0).toUpperCase() + key.slice(1) + '= "' + value + '";');
        }
    }


    if (item.subtype == 'select') {
        var optionSelect = optionSelect || '';

        getCmdDetail({ id: item.id, item: item, optionSelect: optionSelect }, function (_result, _param) {

            var selectOptions = [];
            $.each(_result.configuration.listValue.split(';'), function (key, val) {
                var myData = val.split('|');
                if (myData.length == 1) {
                    var value = text = myData[0];
                }
                else {
                    var value = myData[0];
                    var text = myData[1];
                }
                selectOptions.push(`<option value="${value}">${text}</option>`);
            });

            $('.jcCmdListOptions[data-uid=' + customUid + '][data-l1key=options][data-l2key=select]').html(selectOptions.join(""));

            var optionSelect = _param.optionSelect || '';

            $('.jcCmdListOptions[data-uid=' + customUid + '][data-l1key=options][data-l2key=select]').value(optionSelect);


        });

        curOption = `
        <div class="input-group input-group-sm" style="width: 100%">
            <span class="input-group-addon roundedLeft" style="width: 100px">Choix</span>
            <select class="form-control input-sm jcCmdListOptions roundedRight" data-l1key="options" data-l2key="select" data-uid="${customUid}" data-id="select-${item.id}" data-index="${item.index}">
            </select>
        </div>
       `;

    }


    if (item.subtype == 'message') {

        var optionTitle = optionTitle || '';
        var optionMessage = optionMessage || '';

        curOption = `
        <div class="input-group input-group-sm" style="width: 100%">
            <span class="input-group-addon roundedLeft jcTitlePlaceholder" data-id="title-${item.id}" data-index="${item.index}" style="width: 100px">Titre</span>
            <input style="width:240px;" class='input-sm form-control roundedRight title jcCmdListOptions' type="string" data-id="title-${item.id}" data-index="${item.index}" value="${optionTitle}" />
            <input style="margin-left:20px;" class="jcCmdListOptions" type="checkbox" data-id="displayTitle-${item.id}" data-index="${item.index}" title="Afficher le champ titre" checked/>
        </div>
        <div class="input-group input-group-sm" style="width: 100%">
            <span class="input-group-addon roundedLeft jcMessagePlaceholder" style="width: 100px" data-id="message-${item.id}" data-index="${item.index}">Message</span>
            <textarea class="message form-control ta_autosize jcCmdListOptions" data-l1key="options" data-l2key="message" rows="1" style="resize:vertical;"  data-id="message-${item.id}" data-index="${item.index}" data-uid="${customUid}">${optionMessage}</textarea>
            <span class="input-group-addon hasBtn roundedRight">
              <button class="btn btn-default roundedRight listEquipementInfo" type="button" tooltip="Sélectionner la commande" data-cmd_id="${item.id}" data-index="${item.index}" data-uid="${customUid}" ><i class="fas fa-list-alt"></i></button>
            </span>
            <input style="margin-left:20px;" class="jcCmdListOptions" type="checkbox" data-id="keepLastMsg-${item.id}" data-index="${item.index}" title="Garder le dernier message"/>
        <script>
          $('.listEquipementInfo[data-uid=${customUid}]').on('click', function() {
              jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function(result) {
                $('.jcCmdListOptions[data-l1key=options][data-l2key=message][data-uid=${customUid}]').atCaret('insert', result.human);
              });
          });

        </script>
        </div>`;

        // get the real title and message placeholder, if defined on the cmd
        getCmdDetail({ id: item.id }, function (_result, _param) {
            item.display = _result.display;

            var displayTitle_placeholder = item.display.title_placeholder || '';
            var displayMessage_placeholder = item.display.message_placeholder || '';

            var displayTitle_disable = item.display.title_disable || '0';
            var displayMessage_disable = item.display.message_disable || '0';

            var eltTitle = $('.jcTitlePlaceholder[data-id=title-' + item.id + '][data-index=' + item.index + ']');
            var eltMsg = $('.jcMessagePlaceholder[data-id=message-' + item.id + '][data-index=' + item.index + ']');

            if (displayTitle_disable == '0') {
                if (displayTitle_placeholder != '') eltTitle.text(displayTitle_placeholder);
            } else {
                eltTitle.parent().hide();
                $('.jcCmdListOptions[data-id="displayTitle-' + item.id + '"][data-index="' + item.index + '"]').attr('checked', false);
            }

            if (displayMessage_disable == '0') {
                if (displayMessage_placeholder != '') eltMsg.text(displayMessage_placeholder);
            }
            else {
                eltMsg.parent().hide();
            }

        });
    }

    if (item.subtype == 'slider') {
        var optionSlider = optionSlider || '';

        curOption = `
        <div class="input-group input-group-sm" style="width: 100%">
            <span class="input-group-addon roundedLeft" style="width: 100px">Valeur</span>
            <textarea class="message form-control ta_autosize jcCmdListOptions" data-l1key="options" data-l2key="message" rows="1" style="resize:vertical;"  data-id="slider-${item.id}" data-index="${item.index}" data-uid="${customUid}">${optionSlider}</textarea>
            <span class="input-group-addon hasBtn roundedRight">
              <button class="btn btn-default roundedRight listEquipementInfo" type="button" tooltip="Sélectionner la commande" data-cmd_id="${item.id}" data-index="${item.index}" data-uid="${customUid}"><i class="fas fa-list-alt"></i></button>
            </span>

        <script>
          $('.listEquipementInfo[data-uid=${customUid}]').on('click', function() {
              jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function(result) {
                $('.jcCmdListOptions[data-l1key=options][data-l2key=message][data-uid=${customUid}]').atCaret('insert', result.human);
              });
          });
        </script>
        </div>`;
    }

    if (item.subtype == 'color') {
        var optionColor = optionColor || '';

        curOption = `
        <div class="input-group input-group-sm" style="width: 100%">
            <span class="input-group-addon roundedLeft" style="width: 100px">Couleur</span>
            <input type="color" class="form-control input-sm cursor colorChooser" data-uid="${customUid}" style="width: 20%; display: inline-block;" value="${optionColor}">
            <input class="jcCmdListOptions form-control input-sm" data-l1key="options" data-l2key="color"  data-uid="${customUid}" style="width: 80%; display: inline-block;" placeholder="Couleur (hexa)" data-id="color-${item.id}" data-index="${item.index}" value="${optionColor}">
            <span class="input-group-btn">
              <button class="btn btn-default listEquipementInfo roundedRight" type="button" tooltip="Sélectionner la commande" data-uid="${customUid}" data-index="${item.index}" data-cmd_id="${item.id}"><i class="fas fa-list-alt"></i></button>
            </span>

        <script>
          $('.listEquipementInfo[data-uid=${customUid}]').off('click').on('click', function () {
            var el = $(this);
            jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
              $('.jcCmdListOptions[data-uid=${customUid}][data-l1key=options][data-l2key=color]').value(result.human);
            });
          });
          $('.colorChooser[data-uid=${customUid}]').off('change').on('change', function () {
            $('.jcCmdListOptions[data-uid=${customUid}][data-l1key=options][data-l2key=color]').value($(this).value())
          });
        </script>
        </div>`;
    }

    curOption += `<div class="input-group input-group-sm" style="width: 100%">
                            <span class="input-group-addon roundedLeft" style="width: 100px">Nom</span>
                            <input style="width:240px;" class='input-sm form-control roundedRight title jcCmdListOptions' data-id="custom-name-${item.id}" data-index="${item.index}" value='${item.name || ""}' >
                        </div>`;


    return curOption;


}


function selectCmd(name, type, subtype, value, concat = false) {
    var cmd = { type: type }
    if (subtype != 'undefined') {
        cmd = { type: type, subType: subtype }
    }
    var obj = $('#room-input option:selected').val();
    obj = (obj == 'none') ? '' : obj;

    jeedom.cmd.getSelectModal({
        object: {
            id: obj
        },
        cmd: cmd
    }, function (result) {
        $('#' + name + '-id').val(result.cmd.id);
        refreshCmdData(name, result.cmd.id, value, concat);
    })
}

function refreshWidgetOption() {
    curOption = "";
    widgetsOption.sort(function (s, t) {
        return s.index - t.index;
    });
    widgetsOption.forEach(item => {
        var name = getWidgetPath(item.id);

        if (item.roomName) {
            roomName = (item.roomName == '') ? '' : ' (' + item.roomName + ')';
        }
        else {
            roomNameTmp = getRoomName(item.room || undefined) || '';
            roomName = (roomNameTmp == '') ? '' : ' (' + roomNameTmp + ')';
        }

        curOption += `<div class='input-group jcWidgetListMovable' data-id="${item.id}">
          <input style="width:240px;" class='input-sm form-control roundedLeft' title="id=${item.id}" id="${item.id}-input" value='${name}${roomName}' disabled>
          <i class="mdi mdi-arrow-up-down-bold" title="Déplacer" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;margin-left:10px;cursor:grab!important;" aria-hidden="true"></i>

          <!-- <i class="mdi mdi-arrow-up-circle" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;margin-left:10px;" aria-hidden="true" onclick="upWidgetOption('${item.id}');"></i>
		      <i class="mdi mdi-arrow-down-circle" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;" aria-hidden="true" onclick="downWidgetOption('${item.id}');"></i> -->

          <i class="mdi mdi-minus-circle" style="color:rgb(185, 58, 62);font-size:24px;" aria-hidden="true" onclick="deleteWidgetOption('${item.id}');"></i></li>
          </div>`;
    });
    $("#widget-option").html(curOption);
}

function saveCmdList() {
    cmdCat.forEach(item => {
        item.image = htmlToIcon($('.jcCmdListOptions[data-id="icon-' + item.id + '"][data-index="' + item.index + '"]').children().first());
        item['confirm'] = $('.jcCmdListOptions[data-id="confirm-' + item.id + '"][data-index="' + item.index + '"]').is(':checked') || undefined;

        item['secure'] = $('.jcCmdListOptions[data-id="secure-' + item.id + '"][data-index="' + item.index + '"]').is(':checked') || undefined;

        item['pwd'] = $('.jcCmdListOptions[data-id="pwd-' + item.id + '"][data-index="' + item.index + '"]').is(':checked') || undefined;

        item['options'] = {};
        if (item.subtype == 'message') {
            item['options']['title'] = $('.jcCmdListOptions[data-id="title-' + item.id + '"][data-index="' + item.index + '"]').val();
            item['options']['message'] = $('.jcCmdListOptions[data-id="message-' + item.id + '"][data-index="' + item.index + '"]').val();
            item['options']['displayTitle'] = $('.jcCmdListOptions[data-id="displayTitle-' + item.id + '"][data-index="' + item.index + '"]').is(':checked');
            item['options']['keepLastMsg'] = $('.jcCmdListOptions[data-id="keepLastMsg-' + item.id + '"][data-index="' + item.index + '"]').is(':checked');
        }
        else if (item.subtype == 'select') {
            item['options'][item.subtype] = $('.jcCmdListOptions[data-id="select-' + item.id + '"][data-index="' + item.index + '"] option:selected').val();
        }
        else {
            item['options'][item.subtype] = $('.jcCmdListOptions[data-id="' + item.subtype + '-' + item.id + '"][data-index="' + item.index + '"]').val();
        }

    });
}


function addCmdOption(optionsJson) {
    saveCmdList();
    var options = JSON.parse(optionsJson);
    var cmd = {};
    if (options.type) {
        cmd = { type: options.type }
    }
    if (options.subtype) {
        cmd = { type: options.type, subType: options.subtype }
    }
    jeedom.cmd.getSelectModal({ cmd: cmd }, function (result) {
        var name = result.human.replace(/#/g, '');
        name = name.split('[');
        name = name[name.length - 1].replace(/]/g, '');
        var maxIndex = getMaxIndex(cmdCat);
        cmdCat.push({ id: result.cmd.id, name: name, index: maxIndex + 1, subtype: result.cmd.subType });
        refreshCmdListOption(optionsJson);
    })
}



function deleteCmdOption(elm, optionsJson) {
    saveCmdList();

    var id = $(elm).data('id');
    var currentIndex = $(elm).data('index');

    var cmdToDelete = cmdCat.find(i => i.id == id && i.index == currentIndex);
    var index = cmdCat.indexOf(cmdToDelete);
    cmdCat.forEach(item => {
        if (item.index > cmdToDelete.index) {
            item.index = item.index - 1;
        }
    });
    cmdCat.splice(index, 1);
    refreshCmdListOption(optionsJson);
}


//More Infos

function refreshMoreInfos() {
    let div = '';
    moreInfos.forEach((item, i) => {
        item.index = i;
        var unit = item.unit || '';
        div += `<div class='input-group moreInfosItem' style="border-width:1px; border-style:dotted;" id="moreInfo-${item.id}" data-id="${item.id}">
          <input style="width:600px;" class='input-sm form-control roundedLeft' id="${item.id}-input" value='${item.id}' disabled><br>
          <label style="position:relative; margin-left:25px; width: 46px;"> Nom : </label>
          <input style="width:450px;position:absolute; " id="${item.id}-name-input" title='${item.name}' value='${item.name}'><br>
          <label style="position:relative; margin-left:25px; width: 42px;"> Unité : </label>
          <input style="width:80px;position:absolute; " id="${item.id}-unit-input" value='${unit}'>
          <i class="mdi mdi-arrow-up-down-bold" title="Déplacer" style="color:rgb(80, 120, 170);font-size:24px;margin-left:460px;cursor:grab!important;" aria-hidden="true"></i>
          <i class="mdi mdi-minus-circle" style="color:rgb(185, 58, 62);font-size:24px;position:absolute; margin-left:5px;" aria-hidden="true" onclick="deleteMoreInfo('${item.id}');"></i>
          </div>`;
    });
    $("#moreInfos-div").html(div);
    moreInfos.forEach(item => {
        cmdHumanName = getHumanName(item.id);
        if (cmdHumanName != '') {
            $("#" + item.id + "-input").val(cmdHumanName);
            $("#" + item.id + "-input").attr('title', cmdHumanName);
            item.human = cmdHumanName;
        }
        else {
            $("#" + item.id + "-input").val("#" + item.id + "#");
        }
    })

    refreshImgListOption();
    refreshInfoSelect();
}


function addMoreCmd() {
    jeedom.cmd.getSelectModal({ cmd: { type: 'info' } }, function (result) {
        getCmdDetail({ id: result.cmd.id, human: result.human }, function (result, _param) {
            moreInfos.push({ type: 'cmd', id: result.id, human: _param.human, name: result.name, unit: result.unite });
            saveImgOption();
            refreshMoreInfos();
        })
    });
}

function deleteMoreInfo(id) {
    var infoToDelete = moreInfos.find(i => i.id == id);
    var index = moreInfos.indexOf(infoToDelete);
    moreInfos.splice(index, 1);
    refreshMoreInfos();
}


// Infos select
function refreshInfoSelect() {
    let infosOptionHtml = '';
    var type = $("#widgetsList-select").val();
    var widget = widgetsList.widgets.find(i => i.type == type);
    if (widget?.variables) {
        widget.variables.forEach(v => {
            infosOptionHtml += `<li info="${v.name}" onclick="infoSelected('#${v.name}#', this)">
        <a href="#">#${v.name}#</a></li>`;
        });
    }
    $('input[cmdType="info"]').each((i, el) => {
        infosOptionHtml += `<li info="${$("input[id=" + el.id + "]").attr('cmdid')}" onclick="infoSelected('${el.title}', this)">
      <a href="#">${el.title}</a></li>`;
    });
    moreInfos.forEach(i => {
        if (i.human) {
            infosOptionHtml += `<li info="${i.id}" onclick="infoSelected('${i.human}', this)">
      <a href="#">${i.human}</a></li>`;
        }
    });
    $(".infos-select").html(infosOptionHtml);

    refreshStrings();
}

function saveImgOption() {
    imgCat.forEach(item => {
        item.image = htmlToIcon($("#icon-div-" + item.index).children().first());
        item.condition = $("#imglist-cond-" + item.index).val();
    });
}


function deleteImgOption(id) {
    saveImgOption();
    var imgToDelete = imgCat.find(i => i.index == id);
    var index = imgCat.indexOf(imgToDelete);
    imgCat.splice(index, 1);
    imgCat.forEach(item => {
        if (item.index > imgToDelete.index) {
            item.index = item.index - 1;
        }
    });
    refreshImgListOption();
}



function addWidgetOption(choices) {
    var widgets = choices.split(".");
    getSimpleModal({ title: "Choisir un widget", fields: [{ type: "widget", choices: widgets, typeFilter: true }] }, function (result) {
        var maxIndex = getMaxIndex(widgetsOption);
        widgetsOption.push({ id: result.widgetId, index: maxIndex + 1, roomName: result.roomName });
        refreshWidgetOption();
    });
}

function deleteWidgetOption(id) {
    var widgetToDelete = widgetsOption.find(i => i.id == id);
    var index = widgetsOption.indexOf(widgetToDelete);
    widgetsOption.forEach(item => {
        if (item.index > widgetToDelete.index) {
            item.index = item.index - 1;
        }
    });
    widgetsOption.splice(index, 1);
    refreshWidgetOption();
}





function loadSortable(elt) {

    if (elt == 'imgList' || elt == 'all') {
        $("#imgList-option").sortable({
            axis: "y", cursor: "move", items: ".jcImgListMovable", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true,
            start: function () { saveImgOption(); },
            update: function (event, ui) {
                $('#imgList-option > .jcImgListMovable').each((i, el) => {
                    var imgId = $(el).data('id');
                    var imgToMove = imgCat.find(i => i.index == parseInt(imgId));
                    imgToMove.index = i;
                }
                );
                refreshImgListOption();

            }
        });
    }

    if (elt == 'widgetList' || elt == 'all') {
        $("#widget-option").sortable({
            axis: "y", cursor: "move", items: ".jcWidgetListMovable", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true,
            update: function (event, ui) {
                $('#widget-option > .jcWidgetListMovable').each((i, el) => {
                    var widgetId = $(el).data('id');
                    var widgetToMove = widgetsOption.find(i => i.id == parseInt(widgetId));
                    widgetToMove.index = i;
                }
                );
                refreshWidgetOption();

            }
        });
    }

    if (elt == 'cmdList' || elt == 'all') {
        $("#cmdList-option").sortable({
            axis: "y", cursor: "move", items: ".jcCmdList", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true,
            start: function () { saveCmdList(); },
            update: function (event, ui) {
                $('#cmdList-option > .jcCmdList').each((i, el) => {
                    var cmdId = $(el).data('id');
                    var cmdIndex = $(el).data('index');

                    var cmdToMove = cmdCat.find(i => i.id == parseInt(cmdId) && i.index == cmdIndex);
                    cmdToMove.index = i;
                }
                );
                var opt = $("#cmdList-option").data('cmd-options');
                refreshCmdListOption(JSON.stringify(opt));

            }
        });
    }

    if (elt == 'moreInfos' || elt == 'all') {
        $("#moreInfos-div").sortable({
            axis: "y", cursor: "move", items: ".moreInfosItem", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true,
            update: function (event, ui) {
                moreInfos = [];
                $('#moreInfos-div > .moreInfosItem').each((i, el) => {
                    info = {};
                    info.id = $(el).data('id');
                    info.human = $(el).find("#" + info.id + "-input").val();
                    info.name = $(el).find("#" + info.id + "-name-input").val();
                    info.unit = $(el).find("#" + info.id + "-unit-input").val();
                    info.index = i;
                    moreInfos.push(info);
                }
                );
                refreshMoreInfos();
            }
        });

    }

    $("#actionList-div").sortable({
        axis: "y", cursor: "move", items: ".actionList", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true
    });

}

$('#widgetModal').off('click', '.jcAddActionList').on('click', '.jcAddActionList', function () {
    var input = $(this).attr('data-input');
    var type = $('#' + input).find('option:selected').val();
    var index = $('#actionList-div .actionList').length
    var html = getHtmlItem(type, { id: index, from: 'actionList', 'noSecurity': true, withBorder: true, move: true, remove: true });
    $('#actionList-div').append(html);
});


function getHtmlItem(type, option) {
    if (!isset(option)) {
        option = {};
    }

    var html = '';
    if (type == 'cmd') {
        isDisabled = isJcExpert ? '' : 'disabled';
        option.id = option.from + '-' + option.id;
        html = `
        <div class="input-group input-group-sm" style="width: 100%">
              <input class='input-sm form-control roundedLeft needRefresh actionListAttr' style="width:600px;" id="${option.id}-input" data-l1key="options" data-l2key="name" value='' cmdId='' cmdType='action' cmdSubType='other' ${isDisabled} configtype='action' configsubtype='other' configlink='${option.value}'>
              <input class='input-sm form-control roundedLeft actionListAttr' id="${option.id}-id" value='' data-l1key="options" data-l2key="id" style="display:none">              
               <a class='btn btn-default btn-sm cursor bt_selectTrigger' tooltip='Choisir une commande' onclick="selectCmd('${option.id}', 'action', 'other', '');">
                    <i class='fas fa-list-alt'></i></a>  
            </div>`;
        /*
                <table><tr class="cmd">
                    <td>
                        <input class='input-sm form-control roundedLeft needRefresh actionListAttr' style="width:250px;" id="${option.id}-input" data-l1key="options" data-l2key="name" value='' cmdId='' cmdType='action' cmdSubType='other' ${isDisabled} configtype='action' configsubtype='other' configlink='${option.value}'>
                            <input class='input-sm form-control roundedLeft actionListAttr' id="${option.id}-id" value='' data-l1key="options" data-l2key="id" style="display:none">
                                <td>
                                    <a class='btn btn-default btn-sm cursor bt_selectTrigger' tooltip='Choisir une commande' onclick="selectCmd('${option.id}', 'action', 'other', '');">
                                        <i class='fas fa-list-alt'></i></a>
                                </td>
                            </td>`;
                /*
                    <td>
                                <i class="mdi mdi-minus-circle removeCmd" id="${option.id}-remove"
                                    style="color:rgb(185, 58, 62);font-size:16px;margin-right:10px;display:${option.required ? 'none' : 'block'};" aria-hidden="true" data-cmdid="${option.id}-input"></i>
                            </td>
                            <td>
                                <div style="width:50px;margin-left:5px; display:none;" id="invert-div-${option.id}">
                                    <i class='fa fa-sync' title="Inverser"></i><input type="checkbox" style="margin-left:5px;" id="invert-${option.id}"></div>
                            </td>
                            <td>
                                <div style="width:50px;margin-left:5px; display:none;" id="confirm-div-${option.id}">
                                    <i class='fa fa-question' title="Demander confirmation"></i><input type="checkbox" style="margin-left:5px;" id="confirm-${option.id}"></div>
                            </td>
                            <td>
                                <div style="width:50px; display:none;" id="secure-div-${option.id}">
                                    <i class='fa fa-fingerprint' title="Sécuriser avec empreinte digitale"></i><input type="checkbox" style="margin-left:5px;" id="secure-${option.id}"  ></div>
                            </td>
                            <td>
                                <div style="width:50px; display:none;" id="pwd-div-${option.id}">
                                    <i class='mdi mdi-numeric' title="Sécuriser avec un code"></i><input type="checkbox" style="margin-left:5px;" id="pwd-${option.id}"  ></div>
                            </td>
                            <td>
                                <input type="number" style="width:50px; display:none;" id="${option.id}-minInput" value='' placeholder="Min">
                            </td>
                            <td>
                                <input type="number" style="width:50px;margin-left:5px; display:none;" id="${option.id}-maxInput" value='' placeholder="Max">
                            </td>
                            <td>
                                <input type="number" step="0.1" style="width:50px;margin-left:5px; display:none;" id="${option.id}-stepInput" value='1' placeholder="Step">
                            </td>
                            <td>
                                <input style="width:50px; margin-left:5px; display:none;" id="${option.id}-unitInput" value='' placeholder="Unité">
                            </td>    
        html += ` </tr></table>`;

        // html += getCmdOptions(option);
        */


    }

    else if (type == 'scenario') {
        option.id = option.from + '-' + option.id;
        html = `<div class='input-group' style="width: 300px">
                <input class='input-sm form-control roundedLeft actionListAttr' id="${option.id}-input" data-l1key="options" data-l2key="name" value='' scId='' disabled>
                    <input class='input-sm form-control roundedLeft actionListAttr' id="${option.id}-id" value='' data-l1key="options" data-l2key="scenario_id" style="display:none;">
                        <span class='input-group-btn'><a class='btn btn-default btn-sm cursor bt_selectTrigger selectScenario' tooltip='Choisir un scenario' data-id="${option.id}" data-related="${option.id}-input">
                            <i class='fas fa-list-alt'></i></a>
                        </span>
                    </div>
                    <div id='optionScenario-${option.id}' style='display:none;'>
                        <div class="input-group input-group-sm" style="width: 100%">
                            <span class="input-group-addon roundedLeft" style="width: 100px">Tags</span>
                            <input style="width:300px;" class='input-sm form-control roundedRight title actionListAttr' type="string" data-l1key="options" data-l2key="tags" id="tags-scenario-${option.id}-input" value="" placeholder="Si nécessaire indiquez des tags" />
                        </div>`;

        if (!option.noSecurity) {
            html += ` <div class="" style="width: 100%;display: flex;">
                <div class="input-group input-group-sm">
                    <span class="input-group-addon roundedLeft" style="width: 100px">Sécurité</span>
                </div>
                <div style="padding-left: 10px;">
                    <label class="radio-inline"><input type="radio" class="actionListAttr" name="secure-radio-${option.id}" id="confirm-${option.id}" data-l1key="options" data-l2key="confirm"><i class='fa fa-question' title="Demander confirmation"></i></label>
                    <label class="radio-inline"><input type="radio" class="actionListAttr" name="secure-radio-${option.id}" id="secure-${option.id}"  data-l1key="options" data-l2key="secure"><i class='fa fa-fingerprint' title="Sécuriser avec empreinte digitale"></i></label>
                    <label class="radio-inline"><input type="radio" class="actionListAttr" name="secure-radio-${option.id}" id="pwd-${option.id}"     data-l1key="options" data-l2key="pwd"><i class='mdi mdi-numeric' title="Sécuriser avec un code"></i></label>
                    <label class="radio-inline"><input type="radio" class="actionListAttr" name="secure-radio-${option.id}" id="none-${option.id}"  checked >Aucun</label>
                </div>
            </div>`;
        }
        html += `</div>`;

    }

    else if (type == 'goToPage') {
        html = `
            <div class="input-group input-group-sm" style="width: 100%">
                <span class="input-group-addon roundedLeft" style="width: 100px">Page</span>
                <input style="width:100%;" class='input-sm form-control roundedRight title actionListAttr' type="string" data-l1key="options" data-l2key="path" value="" placeholder="chemin de la page à afficher" />
            </div>`;

    } else if (type == 'security') {
        html = `
        <div class="jcSecurityDiv-${option.id}" style="width: 100%;display: flex;">
                <div class="input-group input-group-sm">
                    <span class="input-group-addon roundedLeft" style="width: 100px">Sécurité</span>
                </div>
                <div style="padding-left: 10px;">
                    <label class="radio-inline"><input type="radio" class="jcItemAttr-${option.id}" name="secure-radio-${option.id}" id="confirm-${option.id}" data-l1key="${option.key1 || 'options'}" data-l2key="confirm"><i class='fa fa-question' title="Demander confirmation"></i></label>
                    <label class="radio-inline"><input type="radio" class="jcItemAttr-${option.id}" name="secure-radio-${option.id}" id="secure-${option.id}"  data-l1key="${option.key1 || 'options'}" data-l2key="secure"><i class='fa fa-fingerprint' title="Sécuriser avec empreinte digitale"></i></label>
                    <label class="radio-inline"><input type="radio" class="jcItemAttr-${option.id}" name="secure-radio-${option.id}" id="pwd-${option.id}"     data-l1key="${option.key1 || 'options'}" data-l2key="pwd"><i class='mdi mdi-numeric' title="Sécuriser avec un code"></i></label>
                    <label class="radio-inline"><input type="radio" class="jcItemAttr-${option.id}" name="secure-radio-${option.id}" id="none-${option.id}"  checked >Aucun</label>
                </div>
            </div>`;


    } else if (type == 'launchApp') {
        html = `
            <div class="input-group input-group-sm" style="width: 100%">
                <span class="input-group-addon roundedLeft" style="width: 100px">Nom du package</span>
                <input style="width:100%;" class='input-sm form-control roundedRight title actionListAttr' type="string" data-l1key="options" data-l2key="packageName" value="" placeholder="Nom du package Android de l'application" />
            </div>`;


    }

    let movable = option.move ? `<i class="mdi mdi-arrow-up-down-bold" title="Déplacer" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;margin-left:10px;cursor:grab!important;" aria-hidden="true"></i>` : '';
    let remove = option.remove ? `<i class="mdi mdi-minus-circle deleteItem" style="color:rgb(185, 58, 62);font-size:24px;margin-left:5px;"></i>` : '';

    let border = option.withBorder ? "style='display:flex;border:0.5px black solid;margin: 0 5px;'" : "";

    html = "<div class='" + (option.from || '') + "' data-type='" + type + "' " + border + " >"
        + html
        + movable
        + remove
        + "</div>";

    return html;
}



/**
 * *************
 * SAVING WIDGET
 * *************
 */

$(".widgetMenu .saveWidget").click(function () {
    $('#widget-alert').hideAlert();

    var keepOpen = $(this).attr('data-close');

    try {
        var widgetOld = JSON.parse($("#widgetModal").attr('widgetOld'));
        var result = {};

        var itemType = $("#widgetsList-select").find("option:selected").hasClass('widget') ? 'widget' : 'component';

        var listItems = (itemType == 'widget') ? widgetsList.widgets : widgetsList.components
        var widgetConfig = listItems.find(i => i.type == $("#widgetsList-select").val());
        // var widgetConfig = widgetsList.widgets.find(w => w.type == $("#widgetsList-select").val());
        let infoCmd = moreInfos.slice();

        $('input[cmdType="info"]').each((i, el) => {
            infoCmd.push({ id: $("input[id=" + el.id + "]").attr('cmdid'), human: el.title });
        });

        widgetConfig.options.forEach(option => {
            if (option.category == "cmd") {
                if ($("#" + option.id + "-input").attr('cmdId') == '' & option.required) {
                    throw 'La commande ' + option.name + ' est obligatoire';
                }

                if ($("#" + option.id + "-input").attr('cmdId') != '') {
                    result[option.id] = {};
                    result[option.id].id = $("#" + option.id + "-input").attr('cmdId');
                    result[option.id].type = $("#" + option.id + "-input").attr('cmdType');
                    result[option.id].subType = $("#" + option.id + "-input").attr('cmdSubType');
                    result[option.id].minValue = $("#" + option.id + "-minInput").val() != '' ? $("#" + option.id + "-minInput").val() : undefined;
                    result[option.id].maxValue = $("#" + option.id + "-maxInput").val() != '' ? $("#" + option.id + "-maxInput").val() : undefined;
                    result[option.id].step = $("#" + option.id + "-stepInput").val() != '' ? $("#" + option.id + "-stepInput").val() : undefined;
                    result[option.id].unit = $("#" + option.id + "-unitInput").val() != '' ? $("#" + option.id + "-unitInput").val() : undefined;
                    result[option.id].invert = $("#invert-" + option.id).is(':checked') || undefined;
                    result[option.id].confirm = $("#confirm-" + option.id).is(':checked') || undefined;
                    result[option.id].secure = $("#secure-" + option.id).is(':checked') || undefined;
                    result[option.id].pwd = $("#pwd-" + option.id).is(':checked') || undefined;
                    Object.keys(result[option.id]).forEach(key => result[option.id][key] === undefined ? delete result[option.id][key] : {});
                }
                else {
                    result[option.id] = undefined;
                }
            }
            else if (option.category == "scenario") {

                if ($("#" + option.id + "-input").attr('scId') == '' & option.required) {
                    throw 'La commande ' + option.name + ' est obligatoire';
                }

                if ($("#" + option.id + "-input").attr('scId') != '') {
                    result[option.id] = $("#" + option.id + "-input").attr('scId');

                    result['options'] = {};
                    result['options']['scenario_id'] = $("#" + option.id + "-input").attr('scId');
                    result['options']['action'] = 'start';
                    if ($('#tags-scenario-' + option.id + '-input').val() != '') {
                        getCmdIdFromHumanName({
                            alert: '#widget-alert', stringData: $('#tags-scenario-' + option.id + '-input').val()
                        }, function (data, _params) {
                            result['options']['tags'] = data;
                        });
                    }
                    result['options'].confirm = $("#confirm-" + option.id).is(':checked') || undefined;
                    result['options'].secure = $("#secure-" + option.id).is(':checked') || undefined;
                    result['options'].pwd = $("#pwd-" + option.id).is(':checked') || undefined;

                }
            }
            else if (option.category == "string") {
                if ($("#" + option.id + "-input").val() == '' & option.required) {
                    throw 'La commande ' + option.name + ' est obligatoire';
                }
                if ($("#" + option.id + "-input").prop('type') == 'number') {
                    let itemVal = parseFloat($("#" + option.id + "-input").val());
                    var min = parseFloat($("#" + option.id + "-input").attr('min'));
                    var max = parseFloat($("#" + option.id + "-input").attr('max'));
                    var defaultValue = parseFloat($("#" + option.id + "-input").attr('default'));
                    if (isNaN(itemVal) && !isNaN(defaultValue)) {
                        itemVal = defaultValue;
                    } else if (!isNaN(min) && !isNaN(itemVal) && min > itemVal) {
                        itemVal = min;
                    } else if (!isNaN(max) && !isNaN(itemVal) && max < itemVal) {
                        itemVal = max;
                    }
                    result[option.id] = parseInt(itemVal);
                }
                else {
                    result[option.id] = parseString($("#" + option.id + "-input").val(), infoCmd);
                }
            }
            else if (option.category == "binary") {
                result[option.id] = $("#" + option.id + "-input").is(':checked');
            }
            else if (option.category == "color") {
                let itemVal = $("#" + option.id + "-input").val();
                if (itemVal != '') result[option.id] = itemVal;
            }
            else if (option.category == "stringList") {
                if ($("#" + option.id + "-input").val() == 'none' & option.required) {
                    throw 'La commande ' + option.name + ' est obligatoire';
                }

                if ($("#" + option.id + "-input").val() != 'none') {
                    if (option.id == 'subtitle') {
                        result[option.id] = parseString($("#subtitle-input-value").val(), infoCmd);
                    }
                    else {
                        result[option.id] = $("#" + option.id + "-input").val();
                    }
                }
                else {
                    result[option.id] = undefined;
                }
            }
            else if (option.category == "widgets") {
                if (widgetsOption.length == 0 & option.required) {
                    throw 'La commande ' + option.name + ' est obligatoire';
                }
                result[option.id] = widgetsOption;
            }
            else if (option.category == "cmdList") {
                if (cmdCat.length == 0 & option.required) {
                    throw 'La commande ' + option.name + ' est obligatoire';
                }

                // ---- Start cmdCat.forEach
                cmdCat.forEach(item => {
                    if (option.options.hasImage | option.options.hasIcon) {
                        item.image = htmlToIcon($('.jcCmdListOptions[data-id="icon-' + item.id + '"][data-index="' + item.index + '"]').children().first());
                        if (item.image == {}) { delete item.image; }
                    }
                    if (option.options.type == 'action') {
                        item['confirm'] = $('.jcCmdListOptions[data-id="confirm-' + item.id + '"][data-index="' + item.index + '"]').is(':checked') || undefined;
                        item['secure'] = $('.jcCmdListOptions[data-id="secure-' + item.id + '"][data-index="' + item.index + '"]').is(':checked') || undefined;
                        item['pwd'] = $('.jcCmdListOptions[data-id="pwd-' + item.id + '"][data-index="' + item.index + '"]').is(':checked') || undefined;
                        item['name'] = $('.jcCmdListOptions[data-id="custom-name-' + item.id + '"][data-index="' + item.index + '"]').val() || "";

                        if (item.subtype != undefined && item.subtype != 'other') {
                            var optionsForSubtype = { 'message': ['title', 'message', 'displayTitle', 'keepLastMsg'], 'slider': ['slider'], 'color': ['color'] };

                            item['options'] = {};

                            if (item.subtype == 'select') {
                                item['options']['select'] = $('.jcCmdListOptions[data-id="select-' + item.id + '"][data-index="' + item.index + '"] option:selected').val();
                            }
                            else {

                                var currentArray = optionsForSubtype[item.subtype];
                                currentArray.forEach(key => {
                                    if ($.inArray(key, ['displayTitle', 'keepLastMsg']) != -1) {
                                        item['options'][key] = $('.jcCmdListOptions[data-id="' + key + '-' + item.id + '"][data-index="' + item.index + '"]').is(':checked');
                                    }
                                    else {
                                        var tmpData = $('.jcCmdListOptions[data-id="' + key + '-' + item.id + '"][data-index="' + item.index + '"]').val();

                                        if (tmpData != '') {
                                            getCmdIdFromHumanName({ alert: '#widget-alert', stringData: tmpData, subtype: key }, function (result, _params) {
                                                item['options'][_params.subtype] = result;
                                            });
                                        }
                                        else {
                                            item['options'][key] = '';
                                        }
                                    }
                                });

                            }

                        }
                    }
                });
                // ---- END cmdCat.forEach
                result[option.id] = cmdCat;

            }
            else if (option.category == "ifImgs") {
                if (imgCat.length == 0 & option.required) {
                    throw 'La commande ' + option.name + ' est obligatoire';
                }

                imgCat.forEach(item => {
                    item.image = htmlToIcon($("#icon-div-" + item.index).children().first());
                    getCmdIdFromHumanName({ alert: '#widget-alert', stringData: $("#imglist-cond-" + item.index).val() }, function (result, _params) {
                        item.condition = result;
                    });
                });

                result[option.id] = imgCat;
            }
            else if (option.category == "img") {
                let icon = htmlToIcon($("#icon-div-" + option.id).children().first());
                if (icon.source == undefined & option.required) {
                    throw "L'image est obligatoire";
                }
                result[option.id] = icon.source != undefined ? icon : undefined;
            }
            else if (option.category == "choicesList") {
                option.choices.forEach(v => {
                    result[v.id] = $("#" + v.id + "-jc-checkbox").prop('checked');
                });
            } else if (option.category == "security") {

                result[option.id] = $('.jcSecurityDiv-' + option.id).getValues('.jcItemAttr-' + option.id)[0]['security'] || [];

            } else if (option.category == "actionList") {
                var tmp = []
                $('#actionList-div .actionList').each((index, elt) => {
                    var action = $(elt).getValues('.actionListAttr')[0];
                    action.action = $(elt).attr('data-type');
                    action.index = index;
                    // console.log('item => ', action);
                    tmp.push(action);
                });
                result[option.id] = tmp
                // console.log('result => ', result);

            } else if (widgetOld?.[option.id]) { // Keep options with no supported category
                result[option.id] = widgetOld[option.id];
            }
        });
        // ----- END forEach ----

        if (itemType == 'widget') {
            result.type = $("#widgetsList-select").val();
            result.blockDetail = $("#blockDetail-input").is(':checked');

        }
        else {
            result.type = 'component';
            result.component = $("#widgetsList-select").val();
        }

        visibilityCondData = $('#widgetModal #visibility-cond-input').val();
        if (visibilityCondData != '') {
            getCmdIdFromHumanName({ alert: '#widget-alert', stringData: visibilityCondData }, function (cmdResult, _params) {
                result.visibilityCond = cmdResult;
            });
        }

        widgetType = $("#widgetsList-select").val();

        widgetEnable = $('#enable-input').is(":checked");
        result.enable = widgetEnable;

        widgetRoom = $('#room-input :selected').val();
        widgetRoomName = $('#room-input :selected').text();
        if (widgetRoom != 'none') {
            if (widgetRoom == 'global') {
                result.room = 'global';
            }
            else {
                result.room = parseInt(widgetRoom);
            }
        }

        if (moreInfos.length > 0) {
            result.moreInfos = [];
            moreInfos.forEach(info => {
                info.name = $("#" + info.id + "-name-input").val();
                info.unit = $("#" + info.id + "-unit-input").val();
                info.type = 'cmd';
                delete info.human;
                result.moreInfos.push(info);
            });
        }
        toSave = JSON.stringify(result)

        widgetImg = $("#widgetImg").attr("src");

        widgetName = $("#name-input").val();
        widgetId = $("#widgetOptions").attr('widget-id');


        if (toSave !== null) {
            $.ajax({
                type: "POST",
                url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
                data: {
                    action: "saveWidgetConfig",
                    eqId: widgetId,
                    widgetJC: toSave,
                    imgPath: widgetImg
                },
                dataType: 'json',
                error: function (error) {
                    $('#div_alert').showAlert({ message: error.message, level: 'danger' });
                },
                success: function (data) {
                    if (data.state != 'ok') {
                        $('#div_alert').showAlert({
                            message: data.result,
                            level: 'danger'
                        });
                    }
                    else {

                        if (keepOpen == 'false') {
                            console.log('on reste open');
                            return;
                        }

                        if ($('.widgetMenu .saveWidget').attr('exit-attr') == 'true') {
                            var vars = getUrlVars()
                            var url = 'index.php?'
                            delete vars['id']
                            delete vars['saveSuccessFull']
                            delete vars['removeSuccessFull']
                            vars['saveSuccessFull'] = "1";

                            url = getCustomParamUrl(url, vars);
                            modifyWithoutSave = false
                            jeedomUtils.loadPage(url)
                        }
                        else {

                            if ($("#selWidgetDetail").length > 0) {
                                refreshWidgetDetails();
                                refreshWidgetsContent();

                                //if it's a new widget
                                if (widgetId == undefined || widgetId == '') {

                                    if (widgetRoomName != '') widgetRoomName = ' (' + widgetRoomName.replace(/(?:^[\s\u00a0]+)|(?:[\s\u00a0]+$)/g, '') + ')';
                                    widgetId = parseInt(data.result.id);
                                    $('#selWidgetDetail')
                                        .append($("<option></option>")
                                            .attr("value", widgetId)
                                            .attr("data-widget-id", widgetId)
                                            .attr("data-type", widgetType)
                                            .text(widgetName + widgetRoomName));
                                }
                                else {  //if it's just an update
                                    $("#selWidgetDetail option[data-widget-id=" + widgetId + "]").text(widgetName);
                                }
                            }
                            $("#widgetModal").dialog('destroy').remove();
                        }
                    }
                }
            })

        }
    } catch (error) {
        $('#widget-alert').showAlert({ message: error, level: 'danger' });
        console.error(error);
    }

});

//------------------------ END SAVE WIDGET

$(".widgetMenu .removeWidget").click(function () {
    var warning = "<i source='md' name='alert-outline' style='color:#ff0000' class='mdi mdi-alert-outline'></i>";

    var allName = $('#widgetExistInEquipement').text();

    var msg = '';
    if (allName.length == 0 || allName == '' || allName == undefined) {
        msg = '(Ce widget n\'est utilisé dans aucun équipement)';
    }
    else {
        var count = (allName.match(/,/g) || []).length;
        var eq = (count == 0) ? 'de l\'équipement' : 'des équipements';
        msg = warning + '  La suppression retirera ce widget ' + eq + ' suivant : ' + allName + '  ' + warning;
    }

    getSimpleModal({
        title: "Confirmation", fields: [{
            type: "string",
            value: "Voulez-vous supprimer ce widget ?<br /><br />" + msg
        }]
    }, function (result) {
        $('#widget-alert').hideAlert();
        widgetId = $("#widgetOptions").attr('widget-id');

        $.ajax({
            type: "POST",
            url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
            data: {
                action: "removeWidgetConfig",
                eqId: widgetId
            },
            dataType: 'json',
            error: function (error) {
                $('#div_alert').showAlert({ message: error.message, level: 'danger' });
            },
            success: function (data) {
                if (data.state != 'ok') {
                    $('#div_alert').showAlert({
                        message: data.result,
                        level: 'danger'
                    });
                }
                else {
                    var vars = getUrlVars()
                    var url = 'index.php?'
                    for (var i in vars) {
                        if (i != 'id' && i != 'saveSuccessFull' && i != 'removeSuccessFull') {
                            url += i + '=' + vars[i].replace('#', '') + '&'
                        }
                    }
                    modifyWithoutSave = false
                    url += '&saveSuccessFull=1'
                    jeedomUtils.loadPage(url)
                }
            }
        })
    });

});


$(".widgetMenu .hideWidget").click(function () {
    $("#widgetModal").dialog('destroy').remove();
});

$(".widgetMenu .duplicateWidget").click(function () {

    $('#widget-alert').hideAlert();

    $('#widgetOptions').attr('widget-id', '');

    $(this).hide()
    $('.widgetMenu .removeWidget').hide()
    showAutoFillWidgetCmds();
    $('#widget-alert').showAlert({ message: 'Vous êtes sur le widget dupliqué, réalisez (ou non) vos modifications. Dans tous les cas, pensez à sauvegarder !', level: 'success' });
    // $('.widgetMenu .saveWidget').attr('exit-attr', 'true');

});