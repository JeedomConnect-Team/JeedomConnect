var originalPwd = null;
$("#actionPwd").focusin(function () {
    if (originalPwd === null) {
        originalPwd = $(this).val();
    }
});


function saveEqLogic(_eqLogic) {
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {};
    }

    currentPwd = $("#actionPwd").val();
    if (originalPwd !== null && originalPwd != currentPwd) {
        _eqLogic.configuration.pwdChanged = 'true';
    }

    return _eqLogic;

}

$('body').off('click', '.toggle-password').on('click', '.toggle-password', function () {
    $(this).toggleClass("fa-eye fa-eye-slash");
    var input = $("#actionPwd");
    if (input.attr("type") === "password") {
        input.attr("type", "text");
    } else {
        input.attr("type", "password");
    }

});

$('.eqLogicAttr.checkJcConnexionOption').on('change', function () {

    var useWs = $('.eqLogicAttr[data-l1key=configuration][data-l2key=useWs]');
    var polling = $('.eqLogicAttr[data-l1key=configuration][data-l2key=polling]');
    disableCheckboxWsPolling(useWs, polling);


});


$('.eqLogicAttr[data-l1key=configuration][data-l2key=apiKey]').on('change', function () {
    var key = $('.eqLogicAttr[data-l1key=configuration][data-l2key=apiKey]').value();
    $('#img_config').attr("src", 'plugins/JeedomConnect/data/qrcodes/' + key + '.png');

});

$("#assistant-btn").click(function () {
    openAssistantWidgetModal($('.eqLogicAttr[data-l1key=id]').value());

});

$("#notifConfig-btn").click(function () {
    openAssistantNotificationModal($('.eqLogicAttr[data-l1key=id]').value());
});

$('.jeedomConnect').off('click', '#export-btn').on('click', '#export-btn', function () {

    var apiKeyVal = $('.eqLogicAttr[data-l1key=configuration][data-l2key=apiKey]').value();

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'generateFile',
            type: 'exportEqConf',
            apiKey: apiKeyVal
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
                download(apiKeyVal + '.json', JSON.stringify(data.result), true);
            }
        }
    });

})


$('.jeedomConnect').off('click', '#exportAll-btn').on('click', '#exportAll-btn', function () {
    var apiKey = $('.eqLogicAttr[data-l1key=configuration][data-l2key=apiKey]').value();

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            'action': 'getConfig',
            'apiKey': apiKey,
            all: true
        },
        dataType: 'json',
        success: function (data) {
            //console.log("roomList ajax received : ", data) ;
            if (data.state != 'ok') {
                $('#div_alert').showAlert({
                    message: data.result,
                    level: 'danger'
                });
            }
            else {
                download(apiKey + '_DEBUG.json', JSON.stringify(data.result), true);
            }
        }
    });


});

$('.jeedomConnect').off('click', '#copy-btn').on('click', '#copy-btn', function () {
    var from = $('.eqLogicAttr[data-l1key=configuration][data-l2key=apiKey]').text();

    allJCEquipmentsWithoutCurrent = allJCEquipments.filter(function (obj) {
        return obj.apiKey !== from;
    }).map(item => {
        return {
            id: item.apiKey,
            name: item.name
        };
    });
    getSimpleModal({
        title: "Recopier vers quel(s) appareil(s)",
        fields: [
            { title: "Choix", type: "checkboxes", choices: allJCEquipmentsWithoutCurrent },
            { type: "line" },
            { title: "Inclure les perso", type: "radios", choices: [{ id: 'yes', name: 'Oui' }, { id: 'no', name: 'Non', selected: 'checked' },] },
            { type: 'description', text: 'Attention, lors de la recopie vous perdez la configuration présente actuelle sur les équipements "cibles"' }
        ]
    }, function (result) {
        if (result.checkboxes.length === 0) {
            throw "Il faut sélectionner au moins un appareil ! ";
        }
        $.post({
            url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
            data: {
                'action': 'copyConfig',
                'from': from,
                'to': result.checkboxes,
                'withCustom': (result.radio == 'yes')
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
                    $('#div_alert').showAlert({
                        message: "C'est fait !",
                        level: 'success'
                    });
                }
            }
        });
    });
});

$("#import-btn").click(function () {
    $("#import-input").click();
});

$("#import-input").change(function () {
    var key = $('.eqLogicAttr[data-l1key=configuration][data-l2key=apiKey]').value();
    if ($(this).prop('files').length > 0) {
        file = $(this).prop('files')[0];
        var reader = new FileReader();
        reader.onload = (function (theFile) {
            return function (e) {
                config = e.target.result;
                $.post({
                    url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
                    data: {
                        action: 'uploadWidgets',
                        import: 'eqConfig',
                        data: config,
                        apiKey: key
                    },
                    dataType: 'json',
                    error: function (error) {
                        $('#div_alert').showAlert({ message: data.result, level: 'danger' });
                    },
                    success: function (data) {
                        var levelType = (data.state != 'ok') ? 'danger' : 'success';
                        $('#div_alert').showAlert({ message: data.result, level: levelType });

                    },

                });
            };
        })(file);
        reader.readAsText(file);
        $(this).prop("value", "");
    }
});


$(".btRegenerateApiKey").click(function () {
    var warning = "<i source='md' name='alert-outline' style='color:#ff0000' class='mdi mdi-alert-outline'></i>";

    getSimpleModal({
        title: "Confirmation",
        fields: [
            {
                type: "string",
                value: "Avant de réaliser cette opération, assurez-vous que l'application n'est pas lancée sur votre appareil.<br><br>Voulez-vous vraiment regénérer la clé API de cet équipement ?"
            }]
    }, function (result) {
        $.post({
            url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
            data: {
                'action': 'regenerateApiKey',
                'eqId': $('.eqLogicAttr[data-l1key=id]').value(),
                'apiKey': $('.eqLogicAttr[data-l1key=configuration][data-l2key=apiKey]').value()
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
                    // console.log('btRegenerateApiKey data :', data);
                    var ApiKey = data.result.newapikey;
                    // console.log('new api key', ApiKey);
                    $('.eqLogicAttr[data-l1key=configuration][data-l2key=apiKey]').text(ApiKey)
                    $('#img_config').attr("src", 'plugins/JeedomConnect/data/qrcodes/' + ApiKey + '.png');
                }
            },
            error: function (error) {
                console.log("error while regenerating the api key", error)
            }
        });
    });
})

$("#qrcode-regenerate").click(function () {
    var key = $('.eqLogicAttr[data-l1key=configuration][data-l2key=apiKey]').value();
    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            'action': 'generateQRcode',
            'id': $('.eqLogicAttr[data-l1key=id]').value()
        },
        success: function () {
            $('#img_config').attr("src", 'plugins/JeedomConnect/data/qrcodes/' + key + '.png?' + new Date().getTime());
        },
        error: function (error) {
            console.log("error while generating qr code ", error)
        }
    });
});

$("#removeDevice").click(function () {
    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            'action': 'removeDevice',
            'id': $('.eqLogicAttr[data-l1key=id]').value()
        },
        success: function () {
            $('.eqLogicAttr[data-l1key=configuration][data-l2key=deviceName]').html('');
        },
        error: function (error) {
            console.log("error to remove device ", error);
        }
    });
});


$('.eqLogicAttr[data-l1key=name]').change(function () {
    $('.eqNameQrCode').text($('.eqLogicAttr[data-l1key=name]').val());
})