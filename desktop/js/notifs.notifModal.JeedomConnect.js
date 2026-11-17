var actionList = [];

function setNotifModalData(options) {
    items = [];
    actionList = [];
    var notif = options.notif;
    //Name
    var value = notif ? notif.name : '';
    var nameHtml = `<li><div class='form-group'>
    <label class='col-xs-3  required' >Nom</label>
    <div class='col-xs-9'><div class='input-group'><input style="width:250px;" id="mod-notifName-input" value="${value}"></div></div></div></li>`;
    items.push(nameHtml);


    if (platformOs == 'android') {
        //Channel
        var channelValue = notif ? notif.channel : '';
        var channelsHtml = `<li><div class='form-group'>
    <label class='col-xs-3 required'>Canal</label>
    <div class='col-xs-9'><div class='input-group'><select style="width:150px;" id="mod-channel-input" value=''>`;
        $('ul#channelsUL li').each(function (i, obj) {
            var itemName = $(this).find('a').text();
            var itemId = $(this).find('a').data('id');
            channelsHtml += `<option value="${itemId}">${itemName}</option>`;
        });
        channelsHtml += `</select></div></div></div></li>`;
        items.push(channelsHtml);

        //Update
        var updateValue = notif ? notif.update ? "checked" : "" : "";
        var updateHtml = `<li><div class='form-group'>
    <label class='col-xs-3'>Mettre à jour l'existante</label>
    <div class='col-xs-9'>
      <div class="description">Si une autre notification de cette catégorie existe, son contenu sera mis à jour</div>
    <div class='input-group'>
      <input type="checkbox" style="width:150px;" id="update-input" ${updateValue}>
    </div></div></div></li>`;
        items.push(updateHtml);

        //Ongoing
        var ongoingValue = notif ? notif.ongoing ? "checked" : "" : "";
        var ongoingHtml = `<li><div class='form-group'>
    <label class='col-xs-3'>Persistance</label>
    <div class='col-xs-9'>
      <div class="description">Notification non supprimable. Envoyez 'cancel' dans le message pour l'effacer</div>
    <div class='input-group'>
      <input type="checkbox" style="width:150px;" id="ongoing-input" ${ongoingValue}>
    </div></div></div></li>`;

        items.push(ongoingHtml);
    } else {
        //Critical alert
        var criticalValue = notif ? notif.critical ? "checked" : "" : "";
        var criticalHtml = `<li><div class='form-group'>
    <label class='col-xs-3'>Alerte critique</label>
    <div class='col-xs-9'>
      <div class="description">Surpasse les paramètres de son et le mode Ne pas déranger</div>
    <div class='input-group'>
      <input type="checkbox" style="width:150px;" id="critical-input" ${criticalValue}>
    </div></div></div></li>`;

        items.push(criticalHtml);

        //Volume
        var criticalVolumeValue = notif?.criticalVolume || '';
        var criticalVolumeHtml = `<li><div class='form-group'>
    <label class='col-xs-3' >Volume alerte</label>
    <div class='col-xs-9'>
    <div class="description">Volume pour les alertes critiques, entre 0 et 1 (par défaut 0.9 = 90%)</div>
    <div class='input-group'><input style="width:250px;" id="criticalVolume-input" value='${criticalVolumeValue}'></div></div></div></li>`;
        items.push(criticalVolumeHtml);
    }

    //Car (Android Auto, Android Automotive, CarPlay)
    var carValue = notif ? notif.car ? "checked" : "" : "";
    var carHtml = `<li><div class='form-group'>
    <label class='col-xs-3'>Dans la voiture</label>
    <div class='col-xs-9'>
      <div class="description">Affiche aussi cette notification sur l'écran de la voiture</div>
    <div class='input-group'>
      <input type="checkbox" style="width:150px;" id="car-input" ${carValue}>
    </div></div></div></li>`;
    items.push(carHtml);


    //Color
    var colorValue = notif ? notif.color || '' : '';
    var colorHtml = `<li><div class='form-group'>
    <label class='col-xs-3 ' >Couleur</label>
      <div class='col-xs-9'>
        <div class='input-group'>
          <input style="width:200px;" id="mod-color-input" class="inputJCColor" value='${colorValue}'>
          <input type="color" id="mod-color-picker" value='${colorValue}' class="changeJCColor">
        </div>
      </div>
    </div></li>`;
    items.push(colorHtml);

    //Integrated image
    var imageHtml = `<li><div class='form-group'>
      <label class='col-xs-3 ' >Image</label>
        <div class='col-xs-9'>
        <span class="input-group-btn">
            <a class="btn btn-success roundedRight" onclick="imagePicker()"><i class="fas fa-check-square">
            </i> Choisir </a>
            <a id="icon-div" onclick="removeImage()">${notif ? iconToHtml(notif.image) : ''}</a>
        </span></div></div></li>`;

    items.push(imageHtml);

    //Actions
    var actionsHtml = `<li><div class='form-group'>
      <label class='col-xs-3 ' >Actions</label>
        <div class='col-xs-9'>
        <div class="description">Configurez jusqu'à 3 actions. Elles seront ignorées en cas d'utilisation de Ask</div>
        <span class="input-group-btn">
            <a class="btn btn-default roundedRight" onclick="addCmd()"><i class="fas fa-plus-square">
            </i> Ajouter une commande</a>
            <a class="btn btn-default roundedRight" onclick="addScenario()"><i class="fas fa-plus-square">
            </i> Ajouter un scénario</a>
            </span><div id="actionList"></div>
            </span></div></div></li>`;

    items.push(actionsHtml);


    $("#notifOptions").html(items.join(""));
    if (notif) {
        $('#mod-channel-input option[value="' + notif.channel + '"]').prop('selected', true);
        actionList = notif.actions || [];
        if (notif.image) {
            $("#mod-img-img").attr("value", notif.image);
            $("#mod-img-img").attr("src", "plugins/JeedomConnect/data/img/" + notif.image);
            $("#mod-img-img").css("display", "");
            $("#mod-img-remove").css("display", "");
        }
    }

    refreshActionList();
}



function imagePicker() {
    getIconModal({
        title: "Choisir une image",
        withIcon: "0",
        withImg: "1",
        icon: htmlToIcon($("#icon-div").children().first())
    }, (result) => {
        $("#icon-div").html(iconToHtml(result));
    });
}

function removeImage() {
    $("#icon-div").empty();
}

function addCmd() {
    if (actionList.length > 2) {
        return;
    }
    actionList.forEach(item => {
        item.name = $("#" + item.id + "-name-input").val();
    });
    jeedom.cmd.getSelectModal({
        cmd: {
            type: 'action',
            subType: 'other'
        }
    }, function (result) {
        var name = result.human.replace(/#/g, '');
        name = name.split('[');
        name = name[name.length - 1].replace(/]/g, '');
        var maxIndex = getMaxIndex(actionList);
        actionList.push({
            type: 'cmd',
            id: result.cmd.id,
            name: name,
            index: maxIndex + 1
        });
        refreshActionList()
    })
}

function addScenario() {
    if (actionList.length > 2) {
        return;
    }
    actionList.forEach(item => {
        item.name = $("#" + item.id + "-name-input").val();
    });
    jeedom.scenario.getSelectModal({}, function (result) {
        var name = result.human.replace(/#/g, '');
        name = name.split('[');
        name = name[name.length - 1].replace(/]/g, '');
        var maxIndex = getMaxIndex(actionList);
        actionList.push({
            type: 'scenario',
            id: result.id,
            name: name,
            index: maxIndex + 1
        });
        refreshActionList();
    })
}

function refreshActionList() {
    actionList.sort(function (s, t) {
        return s.index - t.index;
    });
    var html = '';
    actionList.forEach(item => {
        html += `<div class='input-group' style="border-width:1px; border-style:dotted;" id="actionList-${item.id}">
						<input style="width:240px;" class='input-sm form-control roundedLeft' id="${item.id}-input" value='' disabled>
            <i class="mdi mdi-arrow-up-circle" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;margin-left:10px;" aria-hidden="true" onclick="upAction('${item.id}');"></i>
      			<i class="mdi mdi-arrow-down-circle" style="color:rgb(80, 120, 170);font-size:24px;margin-right:10px;" aria-hidden="true" onclick="downAction('${item.id}');"></i>
      			<i class="mdi mdi-minus-circle" style="color:rgb(185, 58, 62);font-size:24px;" aria-hidden="true" onclick="deleteAction('${item.id}');"></i>
            <div class='input-group'><label class="xs-col-3">Nom : </label><input style="width:170px;" id="${item.id}-name-input" value="${item.name || ''}"></div>
            </div></div>`;
        if (item.type == "cmd") {
            getHumanName({
                id: item.id,
                error: function (error) { },
                success: function (data) {
                    $("#" + item.id + "-input").val(data);
                }
            });
        } else if (item.type == "scenario") {
            getScenarioHumanName({
                id: item.id,
                error: function (error) { },
                success: function (data) {
                    data.forEach(sc => {
                        if (sc['id'] == item.id) {
                            $("#" + item.id + "-input").val(sc.humanName);
                        }
                    })
                }
            });
        }

    });
    $("#actionList").html(html);
}




function deleteAction(id) {
    var actionToDelete = actionList.find(i => i.id == id);
    var index = actionList.indexOf(actionToDelete);
    actionList.forEach(item => {
        if (item.index > actionToDelete.index) {
            item.index = item.index - 1;
        }
    });
    actionList.splice(index, 1);
    refreshActionList();
}

function upAction(id) {
    actionList.forEach(item => {
        item.name = $("#" + item.id + "-name-input").val();
    });
    var actionToMove = actionList.find(i => i.id == parseInt(id));
    var index = parseInt(actionToMove.index);
    if (index == 0) {
        return;
    }
    var otherAction = actionList.find(i => i.index == index - 1);
    actionToMove.index = index - 1;
    otherAction.index = index;
    refreshActionList();
}

function downAction(id) {
    actionList.forEach(item => {
        item.name = $("#" + item.id + "-name-input").val();
    });
    var actionToMove = actionList.find(i => i.id == parseInt(id));
    var index = parseInt(actionToMove.index);
    if (index == getMaxIndex(actionList)) {
        return;
    }
    var otherAction = actionList.find(i => i.index == index + 1);
    actionToMove.index = index + 1;
    otherAction.index = index;
    refreshActionList();
}



function getHumanName(_params) {
    var params = $.extend({}, jeedom.private.default_params, {}, _params || {});

    var paramsAJAX = jeedom.private.getParamsAJAX(params);
    paramsAJAX.url = 'core/ajax/cmd.ajax.php';
    paramsAJAX.data = {
        action: 'getHumanCmdName',
        id: _params.id
    };
    $.ajax(paramsAJAX);
}

function getScenarioHumanName(_params) {
    var params = $.extend({}, jeedom.private.default_params, {}, _params || {});

    var paramsAJAX = jeedom.private.getParamsAJAX(params);
    paramsAJAX.url = 'core/ajax/scenario.ajax.php';
    paramsAJAX.data = {
        action: 'all',
        id: _params.id
    };
    $.ajax(paramsAJAX);
}
