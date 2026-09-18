// JS de la modale "Services de streaming" (TURN + tunnel webview) -
// chargé dynamiquement à chaque ouverture de la modale (voir
// JeedomConnect.js, data-action=showStreamingServices), donc pas de
// délégation nécessaire depuis un conteneur de page (le formulaire
// .jeedomConnect de la page Configuration n'existe pas ici) : liaison
// directe sur les éléments, déjà présents dans le DOM au moment où ce
// script s'exécute (chargé après le HTML via include_file()).

// ----- TURN hors LAN (déplacé depuis configuration.JeedomConnect.js) -----

function JC_toggleTurnModeSections() {
    var mode = $('#turnModeSelect').val();
    $('.turnModeSection').each(function () {
        $(this).toggle($(this).data('mode') === mode);
    });
}
$('#turnModeSelect').off('change').on('change', JC_toggleTurnModeSections);
JC_toggleTurnModeSections();

// La modale n'est pas chargée dans le conteneur #div_plugin_configuration
// que cible le bouton "Sauvegarder" générique du core (bt_savePluginConfig,
// voir plugin.js) - ce bouton dédié appelle donc directement jeedom.config.save
// (même API core que le générique) sur les seuls .configKey de ce panneau.
$('#saveTurnConfig').off('click').on('click', function () {
    var $btn = $(this);
    var $result = $('#saveTurnConfigResult');
    $result.removeClass('text-success text-danger').text('');
    $btn.prop('disabled', true);

    jeedom.config.save({
        configuration: document.getElementById('turnHorsLanPanel').getJeeValues('.configKey')[0],
        plugin: 'JeedomConnect',
        error: function (error) {
            $btn.prop('disabled', false);
            $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> ' + error.message);
        },
        success: function () {
            $btn.prop('disabled', false);
            $result.addClass('text-success').html('<i class="fas fa-check-circle"></i> Configuration enregistrée.');
        }
    });
})

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

// Charge et affiche le statut essai/abonnement managé (lecture seule, ne
// consomme aucun quota côté service de mint) - appelé au chargement de la
// page et après toute action susceptible de changer ce statut (démarrage
// ou test de l'essai gratuit).
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
                $trialRow.show();
                $row.hide();
                return;
            }
            var s = data.result;
            if (s.mode === 'trial' && !s.started) {
                // Ni essai démarré, ni abonnement store lié - le bouton de
                // démarrage d'essai garde tout son sens, on le laisse visible.
                $trialRow.show();
                $row.hide();
                return;
            }
            $trialRow.hide();
            var html = '';
            var alertClass = 'alert-info';

            // Si le service VPS a pu synchroniser la conso REELLE (octets,
            // via l'Analytics Cloudflare - voir Go2rtc::getManagedTurnStatus),
            // on l'affiche de préférence ; sinon repli sur l'ancien comptage
            // approximatif par nombre de sessions (les deux champs ne sont
            // jamais présents que l'un ou l'autre selon la config du VPS).
            var hasByteUsage = (typeof s.bytesUsed === 'number' && typeof s.bytesMax === 'number');

            if (s.mode === 'trial') {
                if (s.expired) {
                    alertClass = 'alert-warning';
                    html = '<i class="fas fa-exclamation-triangle"></i> Essai gratuit expiré - abonnez-vous depuis l\'application (Préférences &gt; Caméras hors LAN) pour continuer à utiliser ce mode.';
                } else {
                    var usageTxt = hasByteUsage
                        ? JC_fmtBytes(s.bytesUsed) + ' / ' + (s.bytesMax / 1073741824).toFixed(0) + ' Go utilisés'
                        : s.sessionsUsed + '/' + s.sessionsMax + ' sessions utilisées (~4 Go)';
                    html = '<i class="fas fa-hourglass-half"></i> Essai gratuit en cours : <b>' + s.daysRemaining + ' jour(s) restant(s)</b>'
                        + ' - ' + usageTxt + '.';
                }
            } else if (s.mode === 'subscription') {
                var usageTxt;
                if (hasByteUsage) {
                    var remainingBytes = Math.max(0, s.bytesMax - s.bytesUsed);
                    usageTxt = JC_fmtBytes(s.bytesUsed) + ' / ' + (s.bytesMax / 1073741824).toFixed(0)
                        + ' Go utilisés ce mois, ' + JC_fmtBytes(remainingBytes) + ' restants';
                } else {
                    var remaining = Math.max(0, s.sessionsMax - s.sessionsUsed);
                    usageTxt = s.sessionsUsed + '/' + s.sessionsMax + ' sessions utilisées ce mois (~10 Go), ' + remaining + ' restantes';
                }
                var statusTxt = 'Abonnement actif';
                if (s.status !== 'active') {
                    // grace_period/billing_retry : probleme de paiement en
                    // cours, encore dans la fenetre de tolerance Apple/Google
                    // (voir SUBSCRIPTION_ACTIVE_STATUSES cote VPS) - pas
                    // encore bloquant mais merite d'attirer l'oeil.
                    alertClass = 'alert-warning';
                    statusTxt = 'Abonnement actif (problème de paiement en cours)';
                }
                var platformTxt = s.platform === 'ios' ? ' via App Store' : (s.platform === 'android' ? ' via Google Play' : '');
                // expiresAt : date de renouvellement normal, OU date de fin
                // de periode de grace si l'abonnement a ete resilie entre-
                // temps (le statut Apple/Google reste actif jusque-la) - on
                // ne peut pas distinguer les deux cas depuis ce seul champ,
                // d'ou une formulation neutre ("valide jusqu'au") plutot que
                // d'annoncer un renouvellement qui n'aura peut-etre pas lieu.
                var expiresTxt = '';
                if (s.expiresAt) {
                    var expDate = new Date(s.expiresAt);
                    if (!isNaN(expDate.getTime())) {
                        expiresTxt = ' Accès valide jusqu\'au ' + expDate.toLocaleDateString('fr-FR') + '.';
                    }
                }
                html = '<i class="fas fa-check-circle"></i> ' + statusTxt + platformTxt + ' - ' + usageTxt + '.' + expiresTxt;
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

$('#startManagedTrial').off('click').on('click', function () {
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

$('#testManagedTurn').off('click').on('click', function () {
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
                $result.addClass('text-success').html('<i class="fas fa-check-circle"></i> Accès valide, identifiants TURN obtenus avec succès.');
                JC_loadManagedTurnStatus();
            }
        },
        error: function () {
            $btn.prop('disabled', false);
            $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> Erreur inattendue, vérifiez les logs du plugin.');
        }
    });
})

$('#restartGo2rtc').off('click').on('click', function () {
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

$('#testCloudflareTurn').off('click').on('click', function () {
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

// ----- Webview hors LAN (tunnel Cloudflare) -----

function JC_loadTunnelOverview() {
    var $daemonStatus = $('#tunnelDaemonStatus');
    var $quotaBox = $('#tunnelQuotaBox');
    var $tbody = $('#tunnelWidgetsTableBody');

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'getTunnelOverview'
        },
        cache: false,
        dataType: 'json',
        success: function (data) {
            if (data.state != 'ok' || !data.result) {
                $daemonStatus.html('<span class="label label-danger">Erreur</span>');
                $quotaBox.html('<span class="text-danger">' + (data.result || 'Erreur inattendue') + '</span>');
                $tbody.html('<tr><td colspan="2" style="text-align:center;">-</td></tr>');
                return;
            }
            var r = data.result;

            if (r.daemon && r.daemon.state === 'ok') {
                $daemonStatus.html('<span class="label label-success">En cours d\'exécution</span>');
            } else {
                $daemonStatus.html('<span class="label label-default">Arrêté</span> <span style="color:#888;">(démarre automatiquement à la prochaine sauvegarde d\'un widget webview concerné)</span>');
            }

            var used = r.widgets ? r.widgets.length : 0;
            if (r.quota < 0) {
                $quotaBox.html('<i class="fas fa-infinity"></i> ' + used + ' widget(s) tunnelé(s) - quota illimité');
            } else {
                var quotaClass = used >= r.quota ? 'text-danger' : 'text-success';
                $quotaBox.html('<span class="' + quotaClass + '">' + used + ' / ' + r.quota + ' widget(s) tunnelé(s)</span>');
            }

            if (!r.widgets || r.widgets.length === 0) {
                $tbody.html('<tr><td colspan="2" style="text-align:center;">Aucun widget webview tunnelé pour l\'instant.</td></tr>');
                return;
            }
            var rows = '';
            $.each(r.widgets, function (i, w) {
                var hostnameEsc = $('<div>').text(w.hostname).html();
                var hostnameUrl = $('<div>').text('https://' + w.hostname + '/').html();
                rows += '<tr><td>' + $('<div>').text(w.name).html() + ' <span style="color:#888;">(#' + w.id + ')</span></td>'
                    + '<td><a href="' + hostnameUrl + '" target="_blank"><code>' + hostnameEsc + '</code></a></td></tr>';
            });
            $tbody.html(rows);
        },
        error: function () {
            $daemonStatus.html('<span class="label label-danger">Erreur</span>');
            $quotaBox.html('<span class="text-danger">Erreur inattendue, vérifiez les logs du plugin.</span>');
            $tbody.html('<tr><td colspan="2" style="text-align:center;">-</td></tr>');
        }
    });
}
JC_loadTunnelOverview();

$('#restartCloudflaredTunnel').off('click').on('click', function () {
    var $btn = $(this);
    var $result = $('#restartCloudflaredTunnelResult');
    $result.removeClass('text-success text-danger').text('');
    $btn.prop('disabled', true);

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'restartCloudflaredTunnel'
        },
        cache: false,
        dataType: 'json',
        success: function (data) {
            $btn.prop('disabled', false);
            if (data.state != 'ok') {
                $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> ' + data.result);
            } else {
                $result.addClass('text-success').html('<i class="fas fa-check-circle"></i> Tunnel redémarré.');
                JC_loadTunnelOverview();
            }
        },
        error: function () {
            $btn.prop('disabled', false);
            $result.addClass('text-danger').html('<i class="fas fa-times-circle"></i> Erreur inattendue, vérifiez les logs du plugin.');
        }
    });
})
