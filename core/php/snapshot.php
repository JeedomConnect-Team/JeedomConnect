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

$apiKey = init('apiKey');
/** @var \JeedomConnect */
$eqLogic = eqLogic::byLogicalId($apiKey, 'JeedomConnect');

if (!is_object($eqLogic)) {
	JCLog::debug("Can't find eqLogic");
	throw new Exception(__("Can't find eqLogic", __FILE__), -32699);
}

ob_clean();
header('Content-Type: image/jpeg');

$camWidgetId = init('id');
$conf = JeedomConnectWidget::getConfiguration($camWidgetId);
$snapUrl = getUrl($conf);
// JCLog::debug('conf => ' . json_encode($conf));
$username = ($conf['username'] ?? null) ?: null;
$pwd = ($conf['password'] ?? null) ?: null;
$authent = ($conf['authent'] ?? null) ?: null;

if (!is_string($snapUrl)) {
	JCLog::debug("Can't find snapshot url");
	throw new Exception(__("Can't find snapshot url", __FILE__), -32699);
}


function getUrl($conf) {

	$url = $conf['snapshotUrl'] ?? '';

	if (isset($conf['snapshotUrlInfo'])) {
		$cmdId = $conf['snapshotUrlInfo']['id'];

		$cmd = cmd::byId($cmdId);
		if (is_object($cmd)) {
			$url = $cmd->execCmd();
			// JCLog::debug('Snapshot will use url comming from cmd info ['.$cmdId.'] => ' . $url);
		}
	}
	// JCLog::debug('url used :' . $url);
	return $url;
}

function getData($url, $username, $pwd, $authent) {
	$ch = curl_init();

	$replaceArr = array(
		'#username#' => urlencode($username),
		'#password#' => urlencode($pwd),
	);

	$url = str_replace(array_keys($replaceArr), $replaceArr, $url);

	if (!is_null($username) && !is_null($pwd)) {
		$userPwd = $username . ':' . $pwd;
		if (in_array($authent, array(null, 'basic'))) {
			JCLog::trace('authent method : basic');
			curl_setopt($ch, CURLOPT_USERPWD, $userPwd);
			$headers = array(
				'Content-Type:application/json',
				'Authorization: Basic ' . base64_encode($userPwd),
			);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		} elseif (in_array($authent, array('digest'))) {
			JCLog::trace('authent method : digest');
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
			curl_setopt($ch, CURLOPT_USERPWD, $userPwd);
		} else {
			JCLog::trace('no authent method');
		}
	} else {
		JCLog::trace('no login/pwd');
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	$data = curl_exec($ch);
	if ($error = curl_error($ch)) {
		JCLog::warning('Error taking snapshot => ' . $error);
	}
	curl_close($ch);
	JCLog::debug('Snapshot sent - data size => ' . strlen($data));
	return $data;
}

// JCLog::debug('args => ' . json_encode(array($username, $pwd, $authent)));
$data = getData($snapUrl, $username, $pwd, $authent);

if (!function_exists('imagecreatefromstring')) {
	echo $data;
	exit();
}

$compress = init('compress', 100);
$resize = init('resize', 100);


if ($compress >= 100 && $resize >= 100) {
	echo $data;
	exit();
}
if (empty($data) || $data == false || $data == '') {
	echo $data;
	exit();
}


$source = @imagecreatefromstring($data);
if ($source === false) {
	echo $data;
	exit();
}

if ($resize >= 100) {
	imagejpeg($source, null, $compress);
	exit();
}

$width = imagesx($source);
$height = imagesy($source);
$ratio = $width / $height;
$newwidth = round($width * $resize / 100);
$newheight = round($height * $resize / 100);

$result = imagecreatetruecolor($newwidth, $newheight);
imagecopyresized($result, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
imagejpeg($result, null, $compress);
exit();
