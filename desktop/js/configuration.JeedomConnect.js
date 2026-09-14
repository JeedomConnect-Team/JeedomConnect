
// Affiche uniquement la section correspondant au mode TURN sélectionné
// ('cloudflare' = offre gratuite BYO, 'managed' = offre payante).
function JC_toggleTurnModeSections() {
    var mode = $('#turnModeSelect').val();
    $('.turnModeSection').each(function () {
        $(this).toggle($(this).data('mode') === mode);
    });
}
$('.jeedomConnect').off('change', '#turnModeSelect').on('change', '#turnModeSelect', JC_toggleTurnModeSections);
JC_toggleTurnModeSections();

// Formate un nombre d'octets en Mo (2 décimales) tant que la valeur reste
// sous 1000 Mo, puis bascule en Go au-delà - une conso de quelques dizaines
// de Mo affichée en Go (ex: "0.02 Go") est illisible, un utilisateur pense
// tout de suite à une erreur d'affichage.
function JC_fmtBytes(bytes) {
    var mo = bytes / 1048576;
    if (mo >= 1000) {
        return (mo / 1024).toFixed(2) + ' Go';
    }
    return mo.toFixed(2) + ' Mo';
}

// Charge et affiche le statut essai/licence managé (lecture seule, ne
// consomme aucun quota côté service de mint) - appelé au chargement de la
// page et après toute action susceptible de changer ce statut (démarrage
// d'essai, activation/test de licence).
function JC_loadManagedTurnStatus() {
    var $row = $('#managedTurnStatusRow');
    var $box = $('#managedTurnStatusBox');
    var $trialRow = $('#startManagedTrialRow');

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'getManagedTurnStatus'
        },
        cache: false,
        dataType: 'json',
        success: function (data) {
            if (data.state != 'ok' || !data.result) {
                // Ni essai démarré, ni licence activée - le bouton de
                // démarrage d'essai garde tout son sens, on le laisse visible.
                $trialRow.show();
                $row.hide();
                return;
            }
            var s = data.result;
            // L'essai est démarré (quel que soit son état - actif/expiré) ou
            // une licence est active : redémarrer l'essai n'a plus de sens,
            // on masque le bouton.
            $trialRow.toggle(s.mode !== 'trial' && s.mode !== 'license');
            var html = '';
            var alertClass = 'alert-info';

            // Si le service VPS a pu synchroniser la conso REELLE (octets,
            // via l'Analytics Cloudflare - voir Go2rtc::getManagedTurnStatus),
            // on l'affiche de préférence ; sinon repli sur l'ancien comptage
            // approximatif par nombre de sessions (les deux champs ne sont
            // jamais présents que l'un ou l'autre selon la config du VPS).
            var hasByteUsage = (typeof s.bytesUsed === 'number' && typeof s.bytesMax === 'number');

            if (s.mode === 'trial') {
                if (!s.started) {
                    html = '<i class="fas fa-hourglass-half"></i> Essai gratuit démarré - pas encore utilisé (ouvrez une caméra hors LAN pour l\'activer).';
                } else if (s.expired) {
                    alertClass = 'alert-warning';
                    html = '<i class="fas fa-exclamation-triangle"></i> Essai gratuit expiré - abonnez-vous pour continuer à utiliser ce mode.';
                } else {
                    var usageTxt = hasByteUsage
                        ? JC_fmtBytes(s.bytesUsed) + ' / ' + (s.bytesMax / 1073741824).toFixed(0) + ' Go utilisés'
                        : s.sessionsUsed + '/' + s.sessionsMax + ' sessions utilisées (~4 Go)';
                    html = '<i class="fas fa-hourglass-half"></i> Essai gratuit en cours : <b>' + s.daysRemaining + ' jour(s) restant(s)</b>'
                        + ' - ' + usageTxt + '.';
                }
            } else if (s.mode === 'license') {
                var usageTxt;
                if (hasByteUsage) {
                    var remainingBytes = Math.max(0, s.bytesMax - s.bytesUsed);
                    usageTxt = JC_fmtBytes(s.bytesUsed) + ' / ' + (s.bytesMax / 1073741824).toFixed(0)
                        + ' Go utilisés ce mois, ' + JC_fmtBytes(remainingBytes) + ' restants';
                } else {
                    var remaining = Math.max(0, s.sessionsMax - s.sessionsUsed);
                    usageTxt = s.sessionsUsed + '/' + s.sessionsMax + ' sessions utilisées ce mois (~10 Go), ' + remaining + ' restantes';
                }
                // expiresAt : date de renouvellement normal, OU date de fin
                // de periode de grace si l'abonnement a ete resilie entre-
                // temps (Lemon Squeezy garde la licence "active" jusque-la -
                // voir _validate_license cote VPS) - on ne peut pas
                // distinguer les deux cas depuis ce seul champ, d'ou une
                // formulation neutre ("valide jusqu'au") plutot que
                // d'annoncer un renouvellement qui n'aura peut-etre pas lieu.
                var expiresTxt = '';
                if (s.expiresAt) {
                    var expDate = new Date(s.expiresAt);
                    if (!isNaN(expDate.getTime())) {
                        expiresTxt = ' Accès valide jusqu\'au ' + expDate.toLocaleDateString('fr-FR') + '.';
                    }
                }
                html = '<i class="fas fa-check-circle"></i> Abonnement actif - ' + usageTxt + '.' + expiresTxt;
            } else {
                $row.hide();
                return;
            }

            $box.removeClass('alert-info alert-warning').addClass(alertClass).html(html);
            $row.show();
        },
        error: function () {
            $row.hide();
        }
    });
}
if ($('#turnModeSelect').length) JC_loadManagedTurnStatus();

$('.jeedomConnect').off('click', '#startManagedTrial').on('click', '#startManagedTrial', function () {
    var $btn = $(this);
    var $result = $('#startManagedTrialResult');
    $result.removeClass('text-success text-danger').text('');

    bootbox.confirm("Le délai de 7 jours démarre dès maintenant et ne peut pas être suspendu ni réinitialisé. Continuer ?", function (confirmed) {
        if (!confirmed) return;
        $btn.prop('disabled', true);
        $.post({
            url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
            data: {
                action: 'startManagedTrial'
            },
            cache: false,
            dataType: 'json',
            success: function (data) {
                $btn.prop('disabled', false);
                if (data.state != 'ok') {
                    $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> ' + data.result);
                } else {
                    $result.addClass('text-success').html('<i class="fas fa-check-circle"></i> Essai démarré - vous avez 7 jours.');
                    JC_loadManagedTurnStatus();
                }
            },
            error: function () {
                $btn.prop('disabled', false);
                $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> Erreur inattendue, vérifiez les logs du plugin.');
            }
        });
    });
})

$('.jeedomConnect').off('click', '#activateManagedTurn').on('click', '#activateManagedTurn', function () {
    var $btn = $(this);
    var $result = $('#activateManagedTurnResult');
    $result.removeClass('text-success text-danger').text('');
    $btn.prop('disabled', true);

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'activateLicense'
        },
        cache: false,
        dataType: 'json',
        success: function (data) {
            $btn.prop('disabled', false);
            if (data.state != 'ok') {
                $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> ' + data.result);
            } else {
                $result.addClass('text-success').html('<i class="fas fa-check-circle"></i> Licence activée avec succès.');
                JC_loadManagedTurnStatus();
            }
        },
        error: function () {
            $btn.prop('disabled', false);
            $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> Erreur inattendue, vérifiez les logs du plugin.');
        }
    });
})

$('.jeedomConnect').off('click', '#testManagedTurn').on('click', '#testManagedTurn', function () {
    var $btn = $(this);
    var $result = $('#testManagedTurnResult');
    $result.removeClass('text-success text-danger').text('');
    $btn.prop('disabled', true);

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'testManagedTurn'
        },
        cache: false,
        dataType: 'json',
        success: function (data) {
            $btn.prop('disabled', false);
            if (data.state != 'ok') {
                $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> ' + data.result);
            } else {
                $result.addClass('text-success').html('<i class="fas fa-check-circle"></i> Licence valide, credentials TURN obtenus avec succès.');
                JC_loadManagedTurnStatus();
            }
        },
        error: function () {
            $btn.prop('disabled', false);
            $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> Erreur inattendue, vérifiez les logs du plugin.');
        }
    });
})

$('.jeedomConnect').off('click', '#restartGo2rtc').on('click', '#restartGo2rtc', function () {
    var $btn = $(this);
    var $result = $('#restartGo2rtcResult');
    $result.removeClass('text-success text-danger').text('');
    $btn.prop('disabled', true);

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'restartGo2rtc'
        },
        cache: false,
        dataType: 'json',
        success: function (data) {
            $btn.prop('disabled', false);
            if (data.state != 'ok') {
                $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> ' + data.result);
            } else {
                $result.addClass('text-success').html('<i class="fas fa-check-circle"></i> go2rtc redémarré.');
            }
        },
        error: function () {
            $btn.prop('disabled', false);
            $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> Erreur inattendue, vérifiez les logs du plugin.');
        }
    });
})

$('.jeedomConnect').off('click', '#testCloudflareTurn').on('click', '#testCloudflareTurn', function () {
    var $btn = $(this);
    var $result = $('#testCloudflareTurnResult');
    $result.removeClass('text-success text-danger').text('');
    $btn.prop('disabled', true);

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'testCloudflareTurn'
        },
        cache: false,
        dataType: 'json',
        success: function (data) {
            $btn.prop('disabled', false);
            if (data.state != 'ok') {
                $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> ' + data.result);
            } else {
                $result.addClass('text-success').html('<i class="fas fa-check-circle"></i> Identifiants valides, credentials TURN obtenus avec succès.');
            }
        },
        error: function () {
            $btn.prop('disabled', false);
            $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> Erreur inattendue, vérifiez les logs du plugin.');
        }
    });
    // Rappel : ce test porte sur les identifiants ENREGISTRÉS, pas sur une
    // saisie en cours non sauvegardée - la sauvegarde de la page est
    // nécessaire avant de tester une modification.
})

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