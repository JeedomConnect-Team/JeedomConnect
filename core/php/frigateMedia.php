<?php

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

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
require_once dirname(__FILE__) . "/../class/JeedomConnectWidget.class.php";
require_once dirname(__FILE__) . "/../class/FrigateClient.class.php";

$apiKey = init('apiKey');
/** @var \JeedomConnect */
$eqLogic = eqLogic::byLogicalId($apiKey, 'JeedomConnect');

if (!is_object($eqLogic)) {
	JCLog::debug("Can't find eqLogic");
	throw new Exception(__("Can't find eqLogic", __FILE__), -32699);
}

ob_clean();

// Modelé sur snapshot.php (même convention de nom "id" pour le widget) -
// media ∈ {thumbnail, snapshot, clip, vod, live, clipRange}, voir
// FrigateClient::proxyMedia()/proxyVod()/exportClip(). Le Content-Type est
// posé par FrigateClient (image/jpeg bufferisé pour thumbnail/snapshot/live,
// relayé depuis Frigate pour clip/vod/clipRange - voir streamMedia()),
// jamais ici.
$widgetId = init('id');
$eventId = init('eventId');
$media = init('media');

if ($media === 'vod') {
	// file : nom de fichier relatif demandé par le lecteur (sous-playlist,
	// segment init ou .m4s) - réécrit par FrigateClient::rewriteVodPlaylist()
	// à chaque playlist servie, vide seulement pour la toute première requête
	// (master.m3u8, point d'entrée).
	$file = init('file', '');
	FrigateClient::proxyVod($widgetId, $eventId, $file, $apiKey, $_SERVER['HTTP_RANGE'] ?? null);
} else if ($media === 'clipRange') {
	// Extrait sur une plage horaire arbitraire (pas liée à un event détecté,
	// contrairement à media=clip qui prend un eventId) - start/duration en
	// secondes unix, voir FrigateClient::exportClip().
	$start = intval(init('start'));
	$duration = intval(init('duration'));
	FrigateClient::exportClip($widgetId, $start, $duration, $_SERVER['HTTP_RANGE'] ?? null);
} else {
	// h/quality : uniquement pris en compte pour media=live (aperçu basse
	// qualité pendant le chargement du flux, voir FrigateClient::proxyMedia).
	FrigateClient::proxyMedia($widgetId, $eventId, $media, $_SERVER['HTTP_RANGE'] ?? null, intval(init('h', 0)), intval(init('quality', 0)));
}
