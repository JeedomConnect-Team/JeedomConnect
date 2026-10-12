
// Gestion TURN hors LAN + tunnel webview : voir
// streamingServices.JeedomConnect.js (modale "Services de streaming",
// page Gestion).

$('.jeedomConnect').off('click', '#removeAllWidgets').on('click', '#removeAllWidgets', function () {
    $('.actions-detail').hideAlert();
    var warning = "<i source='md' name='alert-outline' style='color:#ff0000' class='mdi mdi-alert-outline'></i>";
    var msg = " Vous allez supprimer l'ensemble des widgets sauvegardés ainsi que remettre à 0 la configuration de tous vos équipements.<br>"
    msg += warning + " <b> Le retour arrière n'est pas possible.</b> " + warning
    msg += "<br>Voulez-vous continuer ? "

    bootbox.confirm(msg, function (result) {
        if (result) {
            $.post({
                url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
                data: {
                    action: 'removeWidgetConfig',
                    all: true
                },
                cache: false,
                dataType: 'json',
                success: function (data) {
                    if (data.state != 'ok') {
                        $('.actions-detail').showAlert({
                            message: data.result,
                            level: 'danger'
                        });
                    }
                    else {
                        $('.actions-detail').showAlert({
                            message: data.result.widget + ' widgets ont été supprimés <br>Ainsi que ' + data.result.eqLogic + ' équipements réinitialisé(s)',
                            level: 'success'
                        });
                    }
                }
            });
        }
    });

})

$('.jeedomConnect').off('click', '#reinitBin').on('click', '#reinitBin', function () {

    $('.actions-detail').hideAlert();
    var warning = "<i source='md' name='alert-outline' style='color:#ff0000' class='mdi mdi-alert-outline'></i>";
    var msg = "Vous allez réinstaller des packages nécessaire à l'envoie des notifications.<br>";
    msg += warning + " <b>Le retour arrière n'est pas possible.</b> " + warning;
    msg += "<br>Voulez-vous continuer ? ";
    bootbox.confirm(msg, function (result) {
        if (result) {
            $.post({
                url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
                data: {
                    action: 'reinitBin'
                },
                cache: false,
                dataType: 'json',
                success: function (data) {
                    if (data.state != 'ok') {
                        $('.actions-detail').showAlert({
                            message: data.result,
                            level: 'danger'
                        });
                    }
                    else {
                        $('.actions-detail').showAlert({
                            message: 'Action réalisée. Rafraichissez cette page.',
                            level: 'success'
                        });
                    }
                }
            });
        }
    });

})

$('.jeedomConnect').off('click', '#reinitAllEq').on('click', '#reinitAllEq', function () {

    $('.actions-detail').hideAlert();
    var warning = "<i source='md' name='alert-outline' style='color:#ff0000' class='mdi mdi-alert-outline'></i>";
    var msg = "Vous allez remettre à 0 la configuration de tous vos équipements.<br>";
    msg += warning + " <b>Le retour arrière n'est pas possible.</b> " + warning;
    msg += "<br>Voulez-vous continuer ? ";
    bootbox.confirm(msg, function (result) {
        if (result) {
            $.post({
                url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
                data: {
                    action: 'reinitEquipement'
                },
                cache: false,
                dataType: 'json',
                success: function (data) {
                    if (data.state != 'ok') {
                        $('.actions-detail').showAlert({
                            message: data.result,
                            level: 'danger'
                        });
                    }
                    else {
                        $('.actions-detail').showAlert({
                            message: data.result.eqLogic + ' équipements ont été réinitialisés',
                            level: 'success'
                        });
                    }
                }
            });
        }
    });

})


$('.jeedomConnect').off('click', '#listWidget').on('click', '#listWidget', function () {

    var optionSelected = $('input[name=filter]:checked').attr('id');

    $('.actions-detail').hideAlert();
    $('.resultListWidget').hideAlert();

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'countWigdetUsage'
        },
        cache: false,
        dataType: 'json',
        success: function (data) {
            console.log('result : ', data)
            if (data.state != 'ok') {
                $('.actions-detail').showAlert({
                    message: data.result,
                    level: 'danger'
                });
            }
            else {

                var resultUnused = data.result.unused;
                var resultUnexisting = data.result.unexisting;
                var resultCount = data.result.count;
                var typeAlert = 'success';
                if (optionSelected == 'unusedOnly') {
                    if (resultUnused.length > 0) {
                        msgFinal = 'Voici les widget non utilisés <br>' + data.result.unused.join(", ");
                    }
                    else {
                        msgFinal = 'Tous les widgets sont utilisés !';
                    }
                }
                else if (optionSelected == 'unexistingOnly') {
                    if (resultUnexisting.length > 0) {
                        msgFinal = '<u>Voici des widgets inexistants mais présents dans vos fichiers de configuration </u><br><br>' + resultUnexisting.join(", ");
                        typeAlert = 'warning';
                    }
                    else {
                        msgFinal = 'Tous les widgets utilisés dans les équipements sont bien existants dans la configuration :)';
                    }
                }
                else {
                    var msgUnexisting = '';
                    var msgCount = '';

                    if (!jQuery.isEmptyObject(resultCount)) {
                        msgCount = '<u>Voici le compte de chaque widget </u><br><br>' + JSON.stringify(data.result.count);

                        var html = "<table><thead><tr><th>Id</th><th>Count</th></thead><tbody>";
                        $.each(data.result.count, function (id, count) {
                            html += "<tr><td>" + id + "</td><td>" + count + "</td></tr>"
                        })
                        //msgCount += html ;

                    }

                    if (resultUnexisting.length > 0) {
                        msgUnexisting = '<u>Voici des widgets inexistants mais présents dans vos fichiers de configuration </u><br><br>' + resultUnexisting.join(", ");
                        typeAlert = 'warning';
                    }

                    if (msgCount == '' && msgUnexisting != '') {
                        msgFinal = msgUnexisting;
                    }
                    else if (msgCount != '' && msgUnexisting == '') {
                        msgFinal = msgCount;
                    }
                    else {
                        msgFinal = msgCount + '<br><br>' + "-".repeat(30) + '<br><br>' + msgUnexisting;
                    }

                }


                $('.resultListWidget').showAlert({
                    message: msgFinal,
                    level: typeAlert
                });
            }
        }
    });

})


$('.jeedomConnect').off('click', '.exportConf').on('click', '.exportConf', function () {

    $('.resultListWidget').hideAlert();

    var typeExport = $(this).data('type');
    var fileName = (typeExport == 'exportWidgets') ? 'generic_' : 'custom_data_';

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'generateFile',
            type: typeExport,
            import: 'genericConfig'
        },
        dataType: 'json',
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alertPluginConfiguration').showAlert({
                    message: data.result,
                    level: 'danger'
                });
            }
            else {
                var fileName2 = 'export_' + fileName + 'Widgets.json';
                download(fileName2, JSON.stringify(data.result), true);
            }
        }
    });

});

$('.jeedomConnect').off('click', '#importWidgetConf').on('click', '#importWidgetConf', function () {
    $('.resultListWidget').hideAlert();
    $("#importConfig-input").click();
    // importFile();
});

$('.jeedomConnect').off('change', '#importConfig-input').on('change', '#importConfig-input', function () {

    // var files = $(this).prop('files');
    var files = document.getElementById('importConfig-input').files;
    console.log(files);
    if (files.length <= 0) {
        return false;
    }

    var fr = new FileReader();

    fr.onload = function (e) {

        var dataUploaded = e.target.result;
        // var dataUploaded = JSON.parse(e.target.result);
        console.log('dataUploaded ', dataUploaded);

        $.post({
            url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
            data: {
                action: 'uploadWidgets',
                data: dataUploaded,
                import: 'genericConfig'
            },
            dataType: 'json',
            success: function (data) {
                if (data.state != 'ok') {
                    $('.resultListWidget').showAlert({
                        message: data.result,
                        level: 'danger'
                    });
                }
                else {
                    $('.resultListWidget').showAlert({
                        message: data.result,
                        level: 'success'
                    });
                }
            }
        });
    }
    fr.readAsText(files.item(0));
    $(this).prop("value", "");

});


var JCdataChange = ''
$('.needJCRefresh').on('focusin', function () {
    JCdataChange = $(this).val();
    // console.log('focus in', JCdataChange);
});

$('.needJCRefresh').on('focusout', function () {
    JCdataChangeOut = $(this).val();
    // console.log('focus out', JCdataChangeOut);
    if (JCdataChange != JCdataChangeOut) {
        $('.customJCObject').attr('data-needrefresh', true);
        $('.infoRefresh').show();
    }
});

function JeedomConnect_postSaveConfiguration() {

    var refreshRequired = $('.customJCObject').attr('data-needrefresh') == 'true';
    if (refreshRequired) {
        $.post({
            url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
            data: {
                action: 'generateQRcode'
            },
            dataType: 'json'
        });
        $('.infoRefresh').hide();
        $('.customJCObject').removeAttr('data-needrefresh');

        $.post({
            url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
            data: {
                action: 'restartDaemon'
            },
            dataType: 'json'
        });
    }
}