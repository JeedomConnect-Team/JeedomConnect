<?php

/**
 * Car integration (Android Auto, Android Automotive, CarPlay)
 *
 * The car clients are native (no React Native runtime in the car), so all the
 * "what can be done with this widget while driving" logic lives here and is
 * shared by every platform. It reuses the Device Controls mapping
 * (JeedomConnectDeviceControl::getDeviceConfig) without its side effects
 * (activeControlIds / activeControlTime are never touched).
 */
class JeedomConnectCar {

    /** Widget types never shown in the car (video/images are not allowed by Android Auto & CarPlay) */
    private static $_excludedTypes = array('camera');

    /**
     * Car configuration, used by the app settings screen
     *
     * @param JeedomConnect $eqLogic
     * @return array favorites : ordered widget ids, available : every widget usable in the car
     */
    public static function getConfig($eqLogic) {
        if (!is_object($eqLogic)) {
            throw new Exception('No equipment found');
        }
        $available = array();
        foreach (self::getWidgets($eqLogic) as $widget) {
            $device = self::getCarDevice($widget, null);
            if ($device == null) continue;

            $available[] = array(
                'id' => $device['id'],
                'widgetType' => $device['widgetType'],
                'title' => $device['title'],
                'subtitle' => $device['subtitle'],
                'mode' => $device['mode']
            );
        }

        // sort by room, then name
        usort($available, function ($a, $b) {
            if (strtolower($a['subtitle']) ==  strtolower($b['subtitle'])) {
                return strcmp(strtolower($a['title']),  strtolower($b['title']));
            }
            return strcmp(strtolower($a['subtitle']),  strtolower($b['subtitle']));
        });

        return array(
            'favorites' => self::getFavorites($eqLogic),
            'navigation' => self::getNavigation($eqLogic),
            'available' => $available
        );
    }

    /**
     * Save the ordered list of car favorites
     *
     * @param JeedomConnect $eqLogic
     * @param array|null $favorites widget ids
     * @return array the new configuration (same as getConfig)
     */
    public static function setFavorites($eqLogic, $favorites) {
        if (!is_object($eqLogic)) {
            throw new Exception('No equipment found');
        }
        if (!is_array($favorites)) {
            throw new Exception('favorites must be an array');
        }

        $existingIds = array_column(self::getWidgets($eqLogic), 'id');
        $existingIds = array_map('strval', $existingIds);

        $res = array();
        foreach ($favorites as $id) {
            $id = strval($id);
            if (in_array($id, $existingIds) && !in_array($id, $res)) {
                $res[] = $id;
            }
        }

        JCLog::debug('car favorites for ' . $eqLogic->getName() . ' : ' . json_encode($res));
        $eqLogic->setConfiguration('carFavorites', $res);
        $eqLogic->save(true);

        return self::getConfig($eqLogic);
    }

    /**
     * Car favorites with their current state, in the user order
     *
     * @param JeedomConnect $eqLogic
     * @return array
     */
    public static function getDevices($eqLogic) {
        if (!is_object($eqLogic)) {
            throw new Exception('No equipment found');
        }
        $favorites = self::getFavorites($eqLogic);
        $displayed = self::getDisplayed($eqLogic);
        $widgets = $displayed['widgets'];

        $cmdIds = array();
        foreach ($displayed['ids'] as $id) {
            $cmdIds = array_merge($cmdIds, self::getCarInfoCmdIds($widgets[$id]), self::getIconCmdIds($widgets[$id]));
        }
        $cmdIds = array_unique(array_filter($cmdIds, 'strlen'));
        $cmdData = JeedomConnectUtils::getCmdValues($cmdIds)['data'];

        // folders (widget groups, summaries) last: they count and drive their members
        $built = array();
        foreach ($displayed['ids'] as $id) {
            if (in_array($widgets[$id]['type'], self::$_folderTypes)) continue;
            $device = self::getCarDevice($widgets[$id], $cmdData);
            if ($device != null) $built[$id] = $device;
        }
        foreach (array_reverse($displayed['ids']) as $id) {
            if (!in_array($widgets[$id]['type'], self::$_folderTypes)) continue;
            $device = self::getCarDevice($widgets[$id], $cmdData, array('members' => $displayed['members'][$id] ?? array(), 'built' => $built));
            if ($device != null) $built[$id] = $device;
        }

        $devices = array();
        $topDevices = array();
        foreach ($displayed['ids'] as $id) {
            if (!isset($built[$id])) continue;
            $device = $built[$id];
            if (!in_array($id, $favorites, true)) {
                // only reachable from its group: not a tile of the home screen / navigation
                $device['hidden'] = true;
            } else {
                $topDevices[] = $device;
            }
            $devices[] = $device;
        }

        $navigation = self::getNavigation($eqLogic);
        $result = array(
            'hasFavorites' => count($favorites) > 0,
            'devices' => $devices,
            'navigation' => $navigation
        );
        if ($navigation != 'none') {
            $result['groups'] = self::getGroups($eqLogic, $navigation, $topDevices, $widgets);
        }
        return $result;
    }

    /** widget groups and widget containers: shown in the car as a folder of their members */
    private static $_folderTypes = array(
        'group-light', 'group-plug', 'group-shutter', 'group-door', 'group-window', 'group-pir',
        'group-alarm', 'group-generic-info-binary', 'widgets-summary', 'favorites'
    );

    /** types the Device Controls mapping doesn't know, handled by the car only */
    private static $_carOnlyTypes = array('mode', 'choices-list', 'media-player', 'event');

    /**
     * Widgets the car displays: the favorites, then the members of the favorite folders (2 levels:
     * a summary holding a group of lights), in that order.
     * @return array ids, widgets (id => widget), members (folder id => member ids)
     */
    private static function getDisplayed($eqLogic) {
        $widgets = array();
        foreach (self::getWidgets($eqLogic) as $widget) {
            $widgets[strval($widget['id'])] = $widget;
        }
        $allWidgets = $eqLogic->getGeneratedConfigFile()['payload']['widgets'] ?? array();

        $ids = array();
        $members = array();
        $add = function ($id, $depth) use (&$add, &$ids, &$members, $widgets, $allWidgets) {
            if (!isset($widgets[$id]) || in_array($id, $ids, true)) return;
            $ids[] = $id;
            if ($depth >= 2 || !in_array($widgets[$id]['type'], self::$_folderTypes)) return;
            $members[$id] = array_values(array_filter(self::getMemberIds($widgets[$id], $allWidgets), function ($memberId) use ($widgets) {
                return isset($widgets[$memberId]);
            }));
            foreach ($members[$id] as $memberId) $add($memberId, $depth + 1);
        };
        foreach (self::getFavorites($eqLogic) as $id) $add($id, 0);
        return array('ids' => $ids, 'widgets' => $widgets, 'members' => $members);
    }

    /** same rule as the app (getWidgetData): enabled widgets placed in the group, or listed by it */
    private static function getMemberIds($widget, $allWidgets) {
        $listed = array_map('strval', array_column($widget['widgets'] ?? array(), 'id'));
        $res = array();
        foreach ($allWidgets as $w) {
            if (empty($w['enable']) || intval($w['widgetId'] ?? -1) < 0) continue;
            $id = strval($w['id']);
            $inGroup = isset($w['parentId'], $widget['widgetId']) && strval($w['parentId']) === strval($widget['widgetId']);
            if (($inGroup || in_array($id, $listed, true)) && $id !== strval($widget['id']) && !in_array($id, $res, true)) {
                $res[] = $id;
            }
        }
        return $res;
    }

    /** every info cmd of the widget (statusInfo, setpointInfo, playInfo...) + those of its name */
    private static function getCarInfoCmdIds($widget) {
        $ids = array();
        foreach ($widget as $key => $value) {
            if (substr($key, -4) === 'Info' && is_array($value) && !empty($value['id'])) $ids[] = strval($value['id']);
        }
        return array_merge($ids, JeedomConnectUtils::getCmdIdFromText($widget['name'] ?? ''));
    }

    /**
     * Long polling used by the car screens instead of polling GET_CAR_DEVICES: answers as soon as
     * what the car displays differs from what it has, at most EVENTS_MAX_WAIT seconds later, with
     * the same payload as getDevices + datetime and hash to send back on the next call.
     *
     * With lastHash (ETag-like, current clients): the payload is compared with the car's one at the
     * start, so a change can't slip between two calls (with event timestamps alone, a state changed
     * while the previous answer was being built was only seen at the next event or the timeout:
     * actions looked 10-25 s slow), then recomputed on every favorite cmd::update event and every
     * 5 s for what events don't cover (favorites edited in the app, names with expressions).
     * Without lastHash (builds before 2026-11-03): previous behaviour, wait for a favorite event.
     * Without lastReadTimestamp (first call): immediate answer.
     *
     * @param JeedomConnect $eqLogic
     * @param float|null $lastReadTimestamp datetime returned by the previous call
     * @param string|null $lastHash hash returned by the previous call
     * @return array
     */
    public static function getEvents($eqLogic, $lastReadTimestamp, $lastHash = null) {
        if (!is_object($eqLogic)) {
            throw new Exception('No equipment found');
        }

        $datetime = $lastReadTimestamp == null ? getmicrotime() : $lastReadTimestamp;
        $result = null;
        if ($lastReadTimestamp != null) {
            set_time_limit(self::EVENTS_MAX_WAIT + 30);
            $cmdIds = self::getFavoritesInfoCmdIds($eqLogic);
            $end = time() + self::EVENTS_MAX_WAIT;
            if ($lastHash !== null) {
                $result = self::getDevices($eqLogic);
                $lastCheck = time();
                while (self::payloadHash($result) === $lastHash && time() < $end) {
                    sleep(1);
                    $events = event::changes($datetime);
                    $datetime = $events['datetime'];
                    if (!self::hasCmdUpdate($events['result'], $cmdIds) && time() - $lastCheck < 5) continue;
                    $lastCheck = time();
                    $result = self::getDevices($eqLogic);
                }
            } else {
                while (true) {
                    $events = event::changes($datetime);
                    $datetime = $events['datetime'];
                    if (self::hasCmdUpdate($events['result'], $cmdIds) || time() >= $end) break;
                    sleep(1);
                }
            }
        }

        if ($result === null) $result = self::getDevices($eqLogic);
        $result['hash'] = self::payloadHash($result);
        $result['datetime'] = $datetime;
        return $result;
    }

    /** what the car displays: everything but datetime / hash */
    private static function payloadHash($result) {
        unset($result['datetime'], $result['hash']);
        return md5(json_encode($result));
    }

    const EVENTS_MAX_WAIT = 25;

    private static function getFavoritesInfoCmdIds($eqLogic) {
        $displayed = self::getDisplayed($eqLogic);
        $cmdIds = array();
        foreach ($displayed['ids'] as $id) {
            $widget = $displayed['widgets'][$id];
            $cmdIds = array_merge($cmdIds, self::getCarInfoCmdIds($widget), self::getIconCmdIds($widget));
        }
        return array_map('strval', array_unique(array_filter($cmdIds, 'strlen')));
    }

    private static function hasCmdUpdate($events, $cmdIds) {
        foreach ($events as $event) {
            if (($event['name'] ?? '') == 'cmd::update' && in_array(strval($event['option']['cmd_id'] ?? ''), $cmdIds)) {
                return true;
            }
        }
        return false;
    }

    /** how the car organizes the favorites (setting of the app, shared by every car client) */
    const NAVIGATION_MODES = array('none', 'room', 'type', 'menu');

    /**
     * @param JeedomConnect $eqLogic
     * @param string $navigation one of NAVIGATION_MODES
     * @return array the new configuration (same as getConfig)
     */
    public static function setNavigation($eqLogic, $navigation) {
        if (!is_object($eqLogic)) {
            throw new Exception('No equipment found');
        }
        if (!in_array($navigation, self::NAVIGATION_MODES, true)) {
            throw new Exception('unknown navigation mode ' . json_encode($navigation));
        }
        $eqLogic->setConfiguration('carNavigation', $navigation);
        $eqLogic->save(true);
        return self::getConfig($eqLogic);
    }

    private static function getNavigation($eqLogic) {
        $navigation = $eqLogic->getConfiguration('carNavigation', 'none');
        return in_array($navigation, self::NAVIGATION_MODES, true) ? $navigation : 'none';
    }

    /** most recent car tokens kept per equipment (several cars can sign in with the same equipment) */
    const MAX_CAR_TOKENS = 5;

    /**
     * FCM token of an Android Automotive app signed in with this equipment. Kept apart from the phone's
     * token (configuration 'token'): the car shares the phone's equipment, overwriting it would stop the
     * phone notifications.
     *
     * @param JeedomConnect $eqLogic
     * @param string|null $token
     * @param bool $remove the car signs out
     * @return array count: tokens registered
     */
    public static function setCarToken($eqLogic, $token, $remove = false) {
        if (!is_object($eqLogic)) {
            throw new Exception('No equipment found');
        }
        if (!is_string($token) || $token === '') {
            throw new Exception('token missing');
        }
        $tokens = array_values(array_filter(self::getCarTokens($eqLogic), function ($item) use ($token) {
            return $item['token'] !== $token;
        }));
        if (!$remove) {
            $tokens[] = array('token' => $token, 'time' => time());
            $tokens = array_slice($tokens, -self::MAX_CAR_TOKENS);
        }
        JCLog::debug('car - ' . ($remove ? 'removing' : 'registering') . ' a car notification token for ' . $eqLogic->getName() . ', ' . count($tokens) . ' registered');
        $eqLogic->setConfiguration('carTokens', $tokens);
        $eqLogic->save(true);
        return array('count' => count($tokens));
    }

    /** @return array [{token, time}] */
    private static function getCarTokens($eqLogic) {
        $tokens = $eqLogic->getConfiguration('carTokens', array());
        return is_array($tokens) ? array_values(array_filter($tokens, function ($item) {
            return is_array($item) && !empty($item['token']);
        })) : array();
    }

    public static function hasCarTokens($eqLogic) {
        return count(self::getCarTokens($eqLogic)) > 0;
    }

    /**
     * Sends a notification marked "Dans la voiture" (option car of its config) to the Android Automotive
     * apps signed in with this equipment, same binary and payload as the phone (JeedomConnect::sendNotif).
     * The phone shows it in Android Auto itself. Tokens FCM reports as unregistered (app removed,
     * data cleared) are dropped.
     *
     * @param JeedomConnect $eqLogic
     * @param string $binPath the sendNotif binary
     * @param array $data {type, payload} as built by sendNotif
     */
    public static function sendNotif($eqLogic, $binPath, $data) {
        $tokens = self::getCarTokens($eqLogic);
        $payload = json_encode($data["payload"], JSON_HEX_APOS);
        $stale = array();
        foreach ($tokens as $item) {
            $cmd = $binPath . " -token='" . $item['token'] . "' -os='android' -type='" . $data["type"] . "' -payload='" . $payload . "' 2>&1";
            $output = shell_exec($cmd);
            $outputJson = preg_replace('/.*response:( )/', '', strval($output));
            $result = is_json($outputJson) ? json_decode($outputJson, true) : null;
            if ($result !== null && ($result['SuccessCount'] ?? 0) == 1) {
                JCLog::debug('car - notification sent to a car');
                continue;
            }
            JCLog::warning('car - notification not delivered to a car: ' . strval($output));
            if (preg_match('/not.?registered|unregistered|registration-token|invalid.?argument/i', strval($output))) {
                $stale[] = $item['token'];
            }
        }
        if (count($stale) > 0) {
            $eqLogic->setConfiguration('carTokens', array_values(array_filter($tokens, function ($item) use ($stale) {
                return !in_array($item['token'], $stale, true);
            })));
            $eqLogic->save(true);
        }
    }

    /**
     * Tree of the favorites for the room / type / menu navigation, only with branches holding favorites:
     * [{id, title, key?, icon?, devices: [device ids], groups: [same]}]
     * key: a group the clients name themselves (types, "other"), title is the French fallback.
     */
    private static function getGroups($eqLogic, $navigation, $devices, $widgets) {
        switch ($navigation) {
            case 'room':
                return self::getRoomGroups($devices, $widgets);
            case 'type':
                return self::getTypeGroups($devices);
            case 'menu':
                return self::getMenuGroups($eqLogic, $devices);
        }
        return array();
    }

    /** rooms in the order of their first favorite, favorites without room last in "other" */
    private static function getRoomGroups($devices, $widgets) {
        $groups = array();
        $other = array();
        foreach ($devices as $device) {
            $roomId = $widgets[$device['id']]['room'] ?? null;
            $room = ($roomId !== null && $roomId !== '') ? jeeObject::byId(intval($roomId)) : null;
            if (!is_object($room)) {
                $other[] = $device['id'];
                continue;
            }
            $key = 'room' . $room->getId();
            if (!isset($groups[$key])) {
                $groups[$key] = array(
                    'id' => $key,
                    'title' => $room->getName(),
                    'icon' => self::normalizeIcon(JeedomConnectUtils::getIconAndColor($room->getDisplay('icon'))),
                    'devices' => array(),
                    'groups' => array()
                );
            }
            $groups[$key]['devices'][] = $device['id'];
        }
        $groups = array_values($groups);
        if (count($other) > 0) $groups[] = self::getOtherGroup($other);
        return $groups;
    }

    private static $_typeGroups = array(
        'lights' => array('Lumières', array('single-light-switch', 'single-light-dim', 'single-light-color', 'group-light')),
        'switches' => array('Switch', array('plug', 'generic-switch', 'group-plug')),
        'covers' => array('Volets', array('shutter', 'group-shutter')),
        'openings' => array('Ouvertures', array('frontgate', 'door', 'window', 'group-door', 'group-window')),
        'security' => array('Sécurité', array('alarm', 'pir', 'group-alarm', 'group-pir')),
        'climate' => array('Climat', array('thermostat', 'air-con', 'temperature', 'humidity')),
        'scenarios' => array('Actions', array('scenario', 'generic-action-other')),
        'sensors' => array('Capteurs', array('generic-info-binary', 'generic-info-numeric', 'generic-info-string', 'power', 'brightness')),
    );

    /** fixed type order, favorites order inside a type, the remaining types last in "other" */
    private static function getTypeGroups($devices) {
        $groups = array();
        $other = array();
        foreach ($devices as $device) {
            $found = false;
            foreach (self::$_typeGroups as $key => $typeGroup) {
                if (in_array($device['widgetType'], $typeGroup[1])) {
                    $groups[$key][] = $device['id'];
                    $found = true;
                    break;
                }
            }
            if (!$found) $other[] = $device['id'];
        }

        $result = array();
        foreach (self::$_typeGroups as $key => $typeGroup) {
            if (empty($groups[$key])) continue;
            $result[] = array('id' => $key, 'key' => $key, 'title' => $typeGroup[0], 'devices' => $groups[$key], 'groups' => array());
        }
        if (count($other) > 0) $result[] = self::getOtherGroup($other);
        return $result;
    }

    /**
     * The app menus: bottom menus (tabs) > top menus (sections), widgets in their app order.
     * Widget groups are merged into their parent menu, and a bottom menu holding a single top menu
     * and no widget of its own shows that top menu's widgets directly (one step less while driving).
     * Favorites placed in no (enabled) menu go to "other".
     */
    private static function getMenuGroups($eqLogic, $devices) {
        $payload = $eqLogic->getGeneratedConfigFile()['payload'] ?? array();
        $deviceIds = array_map('strval', array_column($devices, 'id'));

        $widgetGroupParents = array();
        foreach ($payload['groups'] ?? array() as $group) {
            $widgetGroupParents[strval($group['id'])] = isset($group['parentId']) ? strval($group['parentId']) : null;
        }

        // menu id -> [{id, index}] of the favorites placed in it
        $byMenu = array();
        foreach ($payload['widgets'] ?? array() as $widget) {
            $id = strval($widget['id']);
            if (!in_array($id, $deviceIds, true) || !isset($widget['parentId'])) continue;
            $parent = strval($widget['parentId']);
            if (array_key_exists($parent, $widgetGroupParents)) $parent = $widgetGroupParents[$parent];
            if ($parent === null) continue;
            $byMenu[$parent][] = array('id' => $id, 'index' => intval($widget['index'] ?? 0));
        }
        $placed = array();
        $menuDevices = function ($menuId) use ($byMenu, &$placed) {
            $items = $byMenu[strval($menuId)] ?? array();
            usort($items, function ($a, $b) {
                return $a['index'] - $b['index'];
            });
            $ids = array_values(array_unique(array_column($items, 'id')));
            foreach ($ids as $id) $placed[$id] = true;
            return $ids;
        };
        $enabledSorted = function ($items) {
            $items = array_values(array_filter($items, function ($item) {
                return ($item['enable'] ?? true) !== false;
            }));
            usort($items, function ($a, $b) {
                return intval($a['index'] ?? 0) - intval($b['index'] ?? 0);
            });
            return $items;
        };

        $result = array();
        $sections = $enabledSorted($payload['sections'] ?? array());
        foreach ($enabledSorted($payload['tabs'] ?? array()) as $tab) {
            $node = array(
                'id' => 'menu' . $tab['id'],
                'title' => $tab['name'] ?? '',
                'icon' => self::normalizeIcon($tab['icon'] ?? null),
                'devices' => $menuDevices($tab['id']),
                'groups' => array()
            );
            foreach ($sections as $section) {
                if (strval($section['parentId'] ?? '') !== strval($tab['id'])) continue;
                $sectionDevices = $menuDevices($section['id']);
                if (count($sectionDevices) == 0) continue;
                $node['groups'][] = array(
                    'id' => 'menu' . $section['id'],
                    'title' => $section['name'] ?? '',
                    'icon' => self::normalizeIcon($section['icon'] ?? null),
                    'devices' => $sectionDevices,
                    'groups' => array()
                );
            }
            if (count($node['devices']) == 0 && count($node['groups']) == 1) {
                $node['devices'] = $node['groups'][0]['devices'];
                $node['groups'] = array();
            }
            if (count($node['devices']) > 0 || count($node['groups']) > 0) $result[] = $node;
        }

        $other = array_values(array_filter($deviceIds, function ($id) use ($placed) {
            return !isset($placed[$id]);
        }));
        if (count($other) > 0) $result[] = self::getOtherGroup($other);
        return $result;
    }

    private static function getOtherGroup($deviceIds) {
        return array('id' => 'other', 'key' => 'other', 'title' => 'Autres', 'devices' => array_values($deviceIds), 'groups' => array());
    }

    /**
     * @param JeedomConnect $eqLogic
     * @return array<string>
     */
    private static function getFavorites($eqLogic) {
        $favorites = $eqLogic->getConfiguration('carFavorites', array());
        return is_array($favorites) ? array_map('strval', $favorites) : array();
    }

    /**
     * Widgets of the equipment generated config, without duplicates
     *
     * @param JeedomConnect $eqLogic
     * @return array
     */
    private static function getWidgets($eqLogic) {
        $conf = $eqLogic->getGeneratedConfigFile();
        $widgetsAll = $conf['payload']['widgets'] ?? array();

        $widgets = array();
        $idList = array();
        foreach ($widgetsAll as $widget) {
            if (in_array($widget['id'], $idList)) continue;
            if (in_array($widget['type'], self::$_excludedTypes)) continue;
            $widgets[] = $widget;
            $idList[] = $widget['id'];
        }
        return $widgets;
    }

    /**
     * Build the car payload of a widget
     *
     * mode :
     *  - toggle   : tap sends offAction if status is 'on', onAction otherwise
     *  - exec     : tap sends action
     *  - menu     : tap opens a grid with one button per item of actions (key : up, stop, down, open, partial,
     *               close, position; label when the client can't name it, e.g. "50 %")
     *  - range    : tap opens -/+/presets, sends rangeAction with options.slider (range : value, min, max, increment, unit)
     *  - panel    : tap opens a list of sections [{key?, title?, items}] (modes, setpoint -/+, playback...),
     *               item = {key?, label?, text?, selected?, actions: [action]}; the clients name the known keys
     *  - folder   : widget group / container: tap opens its members (children: device ids, sent as hidden
     *               devices) and folderActions (items, e.g. everything on / off)
     *  - readonly : information only (also used when every action needs a PIN, with protected = true)
     * Each action keeps its own 'challenge' : ACK = confirm before sending, PIN = refused in the car
     * (no keyboard while driving)
     *
     * @param array $widget widget config
     * @param array|null $cmdData null if states are not needed
     * @param array|null $context folders: members (ids) and built (id => device of the members)
     * @return array|null
     */
    private static function getCarDevice($widget, $cmdData, $context = null) {
        $type = $widget['type'];
        if (!in_array($type, self::$_folderTypes) && !in_array($type, self::$_carOnlyTypes)) {
            $control = JeedomConnectDeviceControl::getDeviceConfig($widget, $cmdData);
            if ($control == null) return null;
        } else {
            $control = self::getBaseControl($widget);
        }

        $device = array(
            'id' => $control['id'],
            'widgetId' => $control['widgetId'],
            'widgetType' => $type,
            'title' => $control['title'],
            'subtitle' => $control['subtitle'],
            'deviceStatus' => $control['deviceStatus'],
            'status' => $control['status'] ?? null,
            'statusText' => $control['statusText'] ?? null,
            'icon' => $control['icon'] ?? null,
            'iconColor' => $control['iconColor'] ?? null,
            'mode' => 'readonly'
        );
        if ($cmdData !== null) {
            $device['carIcon'] = self::getCarIcon($widget, $cmdData);
        }
        $val = function ($key) use ($widget, $cmdData) {
            $id = $widget[$key]['id'] ?? null;
            return ($id !== null && $id !== '' && $cmdData !== null) ? ($cmdData[$id] ?? null) : null;
        };

        // sliders (and other range-only widgets) only have a rangeStatus in the Device Controls mapping
        $isHour = !empty($widget['hourFormat']);
        if (($device['statusText'] ?? '') === '' && ($control['rangeStatus'] ?? '') !== '') {
            $device['statusText'] = $isHour ? self::numericToHour($control['rangeStatus'])
                : $control['rangeStatus'] . ($control['rangeUnit'] ?? '');
        }

        $actions = array();
        switch ($type) {
            case 'shutter':
                $actions = self::getMenuActions($widget, array('up' => 'upAction', 'stop' => 'stopAction', 'down' => 'downAction'));
                if (self::hasCmd($widget['positionAction'] ?? null)) {
                    $bounds = self::getBounds($widget['positionAction'], $widget['statusInfo'] ?? array());
                    foreach (array(0, 50, 100) as $pct) {
                        $target = self::round($bounds['min'] + ($bounds['max'] - $bounds['min']) * $pct / 100);
                        $actions[] = array_merge(array('key' => 'position', 'label' => $pct . ' %'), self::withSlider($widget['positionAction'], $target));
                    }
                }
                if (count($actions) > 0) {
                    $device['mode'] = 'menu';
                    $device['actions'] = $actions;
                }
                // numeric state: the position rather than open / closed
                $position = $val('statusInfo');
                if (($widget['statusInfo']['subType'] ?? '') == 'numeric' && is_numeric($position)) {
                    $bounds = self::getBounds($widget['positionAction'] ?? array(), $widget['statusInfo']);
                    $device['statusText'] = self::percent($position, $bounds) . ' %';
                    $device['status'] = $position > $bounds['min'] ? 'on' : 'off';
                }
                break;

            case 'frontgate':
                $menu = self::getMenuActions($widget, array('open' => 'openAction', 'partial' => 'partialAction', 'close' => 'closeAction'));
                $hasStatus = ($widget['statusInfo']['id'] ?? null) != null;
                // with a known state, one tap opens or closes (the main use case : arriving home)
                if ($hasStatus && self::hasCmd($control['onAction'] ?? null) && self::hasCmd($control['offAction'] ?? null)) {
                    $device['mode'] = 'toggle';
                    $actions = array($control['onAction'], $control['offAction']);
                } else if (count($menu) == 1) {
                    $device['mode'] = 'exec';
                    $device['action'] = $menu[0];
                    $actions = $menu;
                } else if (count($menu) > 1) {
                    $device['mode'] = 'menu';
                    $device['actions'] = $menu;
                    $actions = $menu;
                }
                break;

            case 'single-light-dim':
            case 'single-light-color':
                $bright = $val('brightInfo');
                $bounds = self::getBounds($widget['brightAction'] ?? array(), $widget['brightInfo'] ?? array());
                $level = is_numeric($bright) ? self::percent($bright, $bounds) : null;
                if ($device['status'] == 'on' && $level !== null) {
                    $device['statusText'] = 'ON · ' . $level . ' %';
                }
                if (!self::hasCmd($widget['brightAction'] ?? null)) {
                    $actions = self::getDefaultActions($control, $isHour, $widget, $device);
                    break;
                }
                $sections = array();
                $power = self::powerItems($widget, $device['status'] == 'on');
                if (count($power) > 0) $sections[] = array('key' => 'power', 'items' => $power);
                $items = array();
                foreach (array(25, 50, 75, 100) as $pct) {
                    $target = self::round($bounds['min'] + ($bounds['max'] - $bounds['min']) * $pct / 100);
                    $items[] = self::panelItem(null, $pct . ' %', self::withSlider($widget['brightAction'], $target), array(
                        'selected' => $device['status'] == 'on' && $level === $pct
                    ));
                }
                $section = array('key' => 'brightness', 'items' => $items);
                if ($device['status'] == 'on' && $level !== null) $section['title'] = $level . ' %';
                $sections[] = $section;
                $device['mode'] = 'panel';
                $device['panel'] = $sections;
                // for the "all on / all off" of a group of lights
                if (self::hasCmd($widget['onAction'] ?? null)) $device['onAction'] = JeedomConnectUtils::getActionCmd($widget['onAction']);
                if (self::hasCmd($widget['offAction'] ?? null)) $device['offAction'] = JeedomConnectUtils::getActionCmd($widget['offAction']);
                break;

            case 'generic-action-other':
                $list = array_values(array_filter(self::sortByIndex($widget['actions'] ?? array()), function ($a) {
                    return self::hasCmd($a);
                }));
                if (count($list) == 1) {
                    $device['mode'] = 'exec';
                    $device['action'] = JeedomConnectUtils::getActionCmd($list[0]);
                    $actions = array($device['action']);
                } else if (count($list) > 1) {
                    $items = array();
                    foreach ($list as $a) {
                        $items[] = self::panelItem(null, self::cmdLabel($a), JeedomConnectUtils::getActionCmd($a));
                    }
                    $device['mode'] = 'panel';
                    $device['panel'] = array(array('items' => $items));
                }
                break;

            case 'mode':
                $current = $val('statusInfo');
                $items = array();
                foreach (self::sortByIndex($widget['modes'] ?? array()) as $mode) {
                    if (!self::hasCmd($mode)) continue;
                    $items[] = self::panelItem(null, self::cmdLabel($mode), JeedomConnectUtils::getActionCmd($mode), array(
                        'selected' => self::isCurrentMode($mode, $current)
                    ));
                }
                $device['statusText'] = ($current === null || $current === '') ? null : strval($current);
                if (count($items) > 0) {
                    $device['mode'] = 'panel';
                    $device['panel'] = array(array('items' => $items));
                }
                break;

            case 'choices-list':
                $current = $val('statusInfo');
                $label = null;
                $items = self::selectItems($widget['listAction'] ?? null, $current, $label);
                if ($label !== null) {
                    $device['statusText'] = $label;
                } else if ($current !== null && $current !== '') {
                    $device['statusText'] = strval($current);
                }
                if (count($items) > 0) {
                    $device['mode'] = 'panel';
                    $device['panel'] = array(array('items' => $items));
                }
                break;

            case 'alarm':
                $modes = array_values(array_filter(self::sortByIndex($widget['modes'] ?? array()), function ($m) {
                    return self::hasCmd($m);
                }));
                if (count($modes) == 0) {
                    $actions = self::getDefaultActions($control, $isHour, $widget, $device);
                    break;
                }
                $current = $val('modeInfo');
                $armed = $device['status'] == 'on';
                $items = array();
                if (self::hasCmd($widget['offAction'] ?? null)) {
                    $items[] = self::panelItem('disarm', null, JeedomConnectUtils::getActionCmd($widget['offAction']), array('selected' => !$armed));
                }
                foreach ($modes as $mode) {
                    $items[] = self::panelItem(null, self::cmdLabel($mode), JeedomConnectUtils::getActionCmd($mode), array(
                        'selected' => $armed && self::isCurrentMode($mode, $current)
                    ));
                }
                $device['mode'] = 'panel';
                $device['panel'] = array(array('key' => 'modes', 'items' => $items));
                break;

            case 'thermostat':
            case 'air-con':
                $sections = array();
                $unit = $widget['setpointInfo']['unit'] ?? $widget['tempInfo']['unit'] ?? '°C';
                $setpoint = $val('setpointInfo');
                $temp = $val('tempInfo');
                $current = $val('modeInfo');
                $texts = array();
                // modes: a list of commands, or on an air-con a select command (modeSelect), like the app
                $modes = array();
                foreach (self::sortByIndex($widget['modes'] ?? array()) as $mode) {
                    if (!self::hasCmd($mode)) continue;
                    $modes[] = self::panelItem(null, self::cmdLabel($mode), JeedomConnectUtils::getActionCmd($mode), array(
                        'selected' => self::isCurrentMode($mode, $current)
                    ));
                }
                if (count($modes) == 0 && $type == 'air-con') {
                    $label = null;
                    $modes = self::selectItems($widget['modeSelect'] ?? null, $current, $label);
                    if ($label !== null) $current = $label;
                }
                if ($type == 'air-con') {
                    $texts[] = $device['status'] == 'on' ? 'ON' : 'OFF';
                    $power = self::powerItems($widget, $device['status'] == 'on');
                    if (count($power) > 0) $sections[] = array('key' => 'power', 'items' => $power);
                }
                $temps = array();
                if (is_numeric($temp)) $temps[] = self::round($temp) . $unit;
                if (is_numeric($setpoint)) $temps[] = self::round($setpoint) . $unit;
                if (count($temps) > 0) $texts[] = implode(' → ', $temps);
                if ($current !== null && $current !== '') $texts[] = strval($current);
                $device['statusText'] = count($texts) > 0 ? implode(' · ', $texts) : null;

                if (self::hasCmd($widget['setpointAction'] ?? null)) {
                    $bounds = self::getBounds($widget['setpointAction'], $widget['setpointInfo'] ?? array());
                    $base = is_numeric($setpoint) ? min(max(floatval($setpoint), $bounds['min']), $bounds['max']) : $bounds['min'];
                    $items = array();
                    foreach (array('minus' => -1, 'plus' => 1) as $key => $sign) {
                        $target = self::round(min(max($base + $sign * $bounds['increment'], $bounds['min']), $bounds['max']));
                        $items[] = self::panelItem($key, ($sign < 0 ? '−' : '+') . self::round($bounds['increment']), self::withSlider($widget['setpointAction'], $target), array(
                            'text' => '→ ' . $target . $unit
                        ));
                    }
                    $section = array('key' => 'setpoint', 'items' => $items);
                    if (is_numeric($setpoint)) $section['title'] = self::round($setpoint) . $unit;
                    $sections[] = $section;
                }
                if (count($modes) > 0) $sections[] = array('key' => 'modes', 'items' => $modes);
                if ($type == 'air-con') {
                    // ventilation: a select command or a slider; oscillations: select commands
                    $fan = $widget['fanSpeedAction'] ?? null;
                    if (self::hasCmd($fan)) {
                        $label = null;
                        $fanItems = ($fan['subType'] ?? '') == 'slider' ? self::stepItems($fan, $widget['fanSpeedInfo'] ?? array(), $val('fanSpeedInfo'))
                            : self::selectItems($fan, $val('fanSpeedInfo'), $label);
                        if (count($fanItems) > 0) $sections[] = array('key' => 'fan', 'items' => $fanItems);
                    }
                    foreach (array('swing' => 'swing', 'swing_h' => 'horizontalSwing') as $key => $name) {
                        $label = null;
                        $swingItems = self::selectItems($widget[$name . 'Action'] ?? null, $val($name . 'Info'), $label);
                        if (count($swingItems) > 0) $sections[] = array('key' => $key, 'items' => $swingItems);
                    }
                }
                if (count($sections) > 0) {
                    $device['mode'] = 'panel';
                    $device['panel'] = $sections;
                }
                // the Device Controls status of a thermostat means nothing (no on / off)
                if ($type == 'thermostat') $device['status'] = null;
                break;

            case 'media-player':
                $playing = $val('playInfo');
                $isPlaying = is_numeric($playing) && $playing > 0;
                $title = implode(' – ', array_filter(array($val('titleInfo'), $val('artistInfo')), function ($t) {
                    return $t !== null && trim(strval($t)) !== '';
                }));
                $device['status'] = !empty($widget['statusInfo']['id']) ? self::onOff($val('statusInfo')) : ($isPlaying ? 'on' : 'off');
                $device['statusText'] = $title !== '' ? $title : null;
                $sections = array();

                $playback = array();
                if ($isPlaying && self::hasCmd($widget['pauseAction'] ?? null)) {
                    $playback[] = self::panelItem('pause', null, JeedomConnectUtils::getActionCmd($widget['pauseAction']));
                } else if (self::hasCmd($widget['playAction'] ?? null)) {
                    $playback[] = self::panelItem('play', null, JeedomConnectUtils::getActionCmd($widget['playAction']));
                } else if (self::hasCmd($widget['pauseAction'] ?? null)) {
                    $playback[] = self::panelItem('pause', null, JeedomConnectUtils::getActionCmd($widget['pauseAction']));
                }
                foreach (array('previous' => 'previousAction', 'next' => 'nextAction', 'stop' => 'stopAction') as $key => $widgetKey) {
                    if (self::hasCmd($widget[$widgetKey] ?? null)) $playback[] = self::panelItem($key, null, JeedomConnectUtils::getActionCmd($widget[$widgetKey]));
                }
                if (count($playback) > 0) $sections[] = array('key' => 'playback', 'items' => $playback);

                $volume = array();
                if (self::hasCmd($widget['volumeAction'] ?? null)) {
                    $bounds = self::getBounds($widget['volumeAction'], $widget['volumeInfo'] ?? array());
                    $step = max($bounds['step'], ($bounds['max'] - $bounds['min']) / 20);
                    $level = $val('volumeInfo');
                    $base = is_numeric($level) ? floatval($level) : $bounds['min'];
                    foreach (array('volume_down' => -1, 'volume_up' => 1) as $key => $sign) {
                        $target = self::round(min(max($base + $sign * $step, $bounds['min']), $bounds['max']));
                        $volume[] = self::panelItem($key, null, self::withSlider($widget['volumeAction'], $target), array('text' => self::percent($target, $bounds) . ' %'));
                    }
                }
                $muted = $val('muteInfo');
                if (is_numeric($muted) && $muted > 0 && self::hasCmd($widget['unmuteAction'] ?? null)) {
                    $volume[] = self::panelItem('unmute', null, JeedomConnectUtils::getActionCmd($widget['unmuteAction']));
                } else if (self::hasCmd($widget['muteAction'] ?? null)) {
                    $volume[] = self::panelItem('mute', null, JeedomConnectUtils::getActionCmd($widget['muteAction']));
                }
                if (count($volume) > 0) $sections[] = array('key' => 'volume', 'items' => $volume);

                $power = self::powerItems($widget, $device['status'] == 'on');
                if (count($power) > 0) $sections[] = array('key' => 'power', 'items' => $power);

                if (count($sections) > 0) {
                    $device['mode'] = 'panel';
                    $device['panel'] = $sections;
                }
                break;

            case 'event':
                $event = $val('eventInfo');
                $device['statusText'] = ($event === null || $event === '') ? null : strval($event);
                break;

            case 'widgets-summary':
            case 'favorites':
            case 'group-light':
            case 'group-plug':
            case 'group-shutter':
            case 'group-door':
            case 'group-window':
            case 'group-pir':
            case 'group-alarm':
            case 'group-generic-info-binary':
                $device['mode'] = 'folder';
                if ($context === null) break;   // configuration listing: no member states
                $children = array();
                foreach ($context['members'] as $memberId) {
                    if (isset($context['built'][$memberId])) $children[] = $context['built'][$memberId];
                }
                if (count($children) == 0) return null;
                $device['children'] = array_column($children, 'id');
                self::fillFolder($device, $children);
                break;

            default:
                $actions = self::getDefaultActions($control, $isHour, $widget, $device);
                break;
        }

        if ($device['mode'] == 'toggle') {
            $device['onAction'] = $control['onAction'] ?? $actions[0];
            $device['offAction'] = $control['offAction'] ?? $actions[1];
        } else if ($device['mode'] == 'exec' && !key_exists('action', $device)) {
            $device['action'] = $control['action'];
        } else if ($device['mode'] == 'panel') {
            foreach ($device['panel'] as $section) {
                foreach ($section['items'] as $item) $actions = array_merge($actions, $item['actions'] ?? array());
            }
        }

        // challenges are handled per action by the clients (ON can need a confirmation while OFF doesn't)
        // only when every action needs a PIN is there nothing left to do from the car (a folder keeps
        // its members, which have their own actions)
        $pinCount = count(array_keys(array_column($actions, 'challenge'), 'PIN'));
        if ($device['mode'] != 'folder' && count($actions) > 0 && $pinCount == count($actions)) {
            $device['mode'] = 'readonly';
            $device['protected'] = true;
            unset($device['onAction'], $device['offAction'], $device['action'], $device['actions'], $device['rangeAction'], $device['range'], $device['panel']);
        }

        if ($control['deviceStatus'] == 'DISABLED') {
            $device['mode'] = 'readonly';
            unset($device['onAction'], $device['offAction'], $device['action'], $device['actions'], $device['rangeAction'], $device['range'], $device['panel'], $device['folderActions'], $device['children']);
        }

        return $device;
    }

    /** toggle / exec / range from the Device Controls mapping (the types without car-specific handling) */
    private static function getDefaultActions($control, $isHour, $widget, &$device) {
        if (self::hasCmd($control['onAction'] ?? null) && self::hasCmd($control['offAction'] ?? null)) {
            $device['mode'] = 'toggle';
            return array($control['onAction'], $control['offAction']);
        }
        if (key_exists('action', $control) && (self::hasCmd($control['action']) || ($control['action']['action'] ?? '') == 'execSc')) {
            $device['mode'] = 'exec';
            return array($control['action']);
        }
        if (self::hasCmd($control['rangeAction'] ?? null) && !$isHour) {
            // sliders, setpoint only devices (an "hour" slider holds HHMM, +/- makes no sense: display only)
            $device['mode'] = 'range';
            $device['rangeAction'] = $control['rangeAction'];
            $device['range'] = self::getRange($widget, $control);
            return array($control['rangeAction']);
        }
        return array();
    }

    /** id, title, room... for the types the Device Controls mapping doesn't know */
    private static function getBaseControl($widget) {
        try {
            $title = JeedomConnectUtils::getExpressionEvaluated($widget['name'], $widget)['result'];
        } catch (Exception $e) {
            $title = $widget['name'];
        }
        return array(
            'id' => strval($widget['id']),
            'widgetId' => strval($widget['widgetId'] ?? ''),
            'title' => $title,
            'subtitle' => JeedomConnectUtils::getRoomName($widget),
            'deviceStatus' => !empty($widget['enable']) ? 'OK' : 'DISABLED'
        );
    }

    /**
     * Folder tile: how many members are on / open / armed, and the actions on all of them at once
     * (lights / plugs: on, off; shutters: up, down) from the members' own car actions.
     */
    private static function fillFolder(&$device, $children) {
        $withStatus = array_values(array_filter($children, function ($child) {
            return ($child['status'] ?? null) !== null;
        }));
        $onCount = count(array_filter($withStatus, function ($child) {
            return $child['status'] == 'on';
        }));
        if (count($withStatus) > 0) {
            $device['statusText'] = $onCount . '/' . count($withStatus);
            $device['status'] = $onCount > 0 ? 'on' : 'off';
        } else {
            $device['statusText'] = strval(count($children));
        }

        $collect = function ($pick) use ($children) {
            $res = array();
            foreach ($children as $child) {
                $action = $pick($child);
                if ($action != null) $res[] = $action;
            }
            return $res;
        };
        $byKey = function ($key) {
            return function ($child) use ($key) {
                foreach ($child['actions'] ?? array() as $action) {
                    if (($action['key'] ?? '') == $key) return $action;
                }
                return null;
            };
        };
        $items = array();
        switch ($device['widgetType']) {
            case 'group-light':
            case 'group-plug':
                $items[] = self::folderItem('all_on', $collect(function ($child) {
                    return $child['onAction'] ?? null;
                }));
                $items[] = self::folderItem('all_off', $collect(function ($child) {
                    return $child['offAction'] ?? null;
                }));
                break;
            case 'group-shutter':
                $items[] = self::folderItem('all_up', $collect($byKey('up')));
                $items[] = self::folderItem('all_down', $collect($byKey('down')));
                break;
        }
        $items = array_values(array_filter($items));
        if (count($items) > 0) $device['folderActions'] = $items;
    }

    /**
     * One action on several devices: those needing a PIN are left out (no keyboard in the car),
     * the item asks for a confirmation when one of the others does. Null when nothing is left.
     */
    private static function folderItem($key, $actions) {
        $actions = array_values(array_filter($actions, function ($action) {
            return ($action['challenge'] ?? '') != 'PIN';
        }));
        if (count($actions) == 0) return null;
        $item = array('key' => $key, 'actions' => array_map(function ($action) {
            unset($action['challenge'], $action['key'], $action['label']);
            return $action;
        }, $actions));
        if (in_array('ACK', array_column($actions, 'challenge'), true)) $item['challenge'] = 'ACK';
        return $item;
    }

    /** Marche / Arrêt rows of a panel, the current state checked */
    private static function powerItems($widget, $isOn) {
        $items = array();
        if (self::hasCmd($widget['onAction'] ?? null)) {
            $items[] = self::panelItem('on', null, JeedomConnectUtils::getActionCmd($widget['onAction']), array('selected' => $isOn));
        }
        if (self::hasCmd($widget['offAction'] ?? null)) {
            $items[] = self::panelItem('off', null, JeedomConnectUtils::getActionCmd($widget['offAction']), array('selected' => !$isOn));
        }
        return $items;
    }

    /**
     * Panel rows for the choices of a select cmd (choices-list, air-con mode / fan / swing), sent with
     * options.select. The active one is the choice whose id is the info value, like the app.
     *
     * @param string|null $currentLabel out: name of the active choice
     */
    private static function selectItems($widgetAction, $current, &$currentLabel = null) {
        $items = array();
        if (!self::hasCmd($widgetAction)) return $items;
        $choices = $widgetAction['choices'] ?? JeedomConnect::getChoiceData($widgetAction['id']);
        foreach ($choices as $choice) {
            $action = JeedomConnectUtils::getActionCmd($widgetAction);
            $action['options'] = array_merge(is_array($action['options'] ?? null) ? $action['options'] : array(), array('select' => $choice['id']));
            $selected = $current !== null && strval($choice['id']) === strval($current);
            if ($selected) $currentLabel = strval($choice['value']);
            $items[] = self::panelItem(null, strval($choice['value']), $action, array('selected' => $selected));
        }
        return $items;
    }

    /** - / + rows for a slider cmd, each showing the value it will send (setpoint-like) */
    private static function stepItems($widgetAction, $info, $value) {
        $bounds = self::getBounds($widgetAction, $info);
        $unit = $info['unit'] ?? '';
        $base = is_numeric($value) ? min(max(floatval($value), $bounds['min']), $bounds['max']) : $bounds['min'];
        $items = array();
        foreach (array('minus' => -1, 'plus' => 1) as $key => $sign) {
            $target = self::round(min(max($base + $sign * $bounds['increment'], $bounds['min']), $bounds['max']));
            $items[] = self::panelItem($key, ($sign < 0 ? '−' : '+') . self::round($bounds['increment']), self::withSlider($widgetAction, $target), array(
                'text' => '→ ' . $target . $unit
            ));
        }
        return $items;
    }

    /** a panel row: key = named by the clients (on, off, play...), label = the text otherwise */
    private static function panelItem($key, $label, $action, $extra = array()) {
        $item = array('actions' => array($action));
        if ($key !== null) $item['key'] = $key;
        if ($label !== null) $item['label'] = $label;
        return array_merge($item, $extra);
    }

    /** name of a mode / action of a cmd list: the one given in the widget, else the Jeedom cmd name */
    private static function cmdLabel($item) {
        if (!empty($item['name'])) return strval($item['name']);
        $cmd = cmd::byId($item['id'] ?? null);
        return is_object($cmd) ? $cmd->getName() : strval($item['id'] ?? '');
    }

    /** same as the app (mode widget): the mode whose cmd name is the current mode info value */
    private static function isCurrentMode($mode, $current) {
        if ($current === null || $current === '') return false;
        $cmd = cmd::byId($mode['id'] ?? null);
        $names = array(is_object($cmd) ? $cmd->getName() : null, $mode['name'] ?? null);
        foreach ($names as $name) {
            if ($name !== null && mb_strtolower(trim($name)) === mb_strtolower(trim(strval($current)))) return true;
        }
        return false;
    }

    private static function sortByIndex($items) {
        if (!is_array($items)) return array();
        usort($items, function ($a, $b) {
            return intval($a['index'] ?? 0) - intval($b['index'] ?? 0);
        });
        return $items;
    }

    /** an action cmd sent with a slider value (whole numbers as integers: "#slider#" would become "87.0") */
    private static function withSlider($widgetAction, $value) {
        $action = JeedomConnectUtils::getActionCmd($widgetAction);
        $options = is_array($action['options'] ?? null) ? $action['options'] : array();
        $options['slider'] = floor($value) == $value ? intval($value) : floatval($value);
        $action['options'] = $options;
        return $action;
    }

    /** bounds of a slider action, same priority as getRange (action, info, then the Jeedom cmd) */
    private static function getBounds($action, $info) {
        $cmd = !empty($action['id']) ? cmd::byId($action['id']) : null;
        $cmdConfig = function ($key) use ($cmd) {
            return is_object($cmd) ? $cmd->getConfiguration($key, null) : null;
        };
        $min = self::firstNumeric(array($action['minValue'] ?? null, $info['minValue'] ?? null, $cmdConfig('minValue')), 0);
        $max = self::firstNumeric(array($action['maxValue'] ?? null, $info['maxValue'] ?? null, $cmdConfig('maxValue')), 100);
        $step = self::firstNumeric(array($action['step'] ?? null, $info['step'] ?? null), 1);
        if ($max <= $min) {
            $min = 0;
            $max = 100;
        }
        if ($step <= 0) $step = 1;
        return array(
            'min' => $min,
            'max' => $max,
            'step' => $step,
            // the car has no slider: keep the widget step only when it gives a reasonable number of taps
            'increment' => (($max - $min) / $step <= 50) ? $step : ($max - $min) / 10
        );
    }

    private static function percent($value, $bounds) {
        return intval(round((floatval($value) - $bounds['min']) / ($bounds['max'] - $bounds['min']) * 100));
    }

    /** 19.5 -> 19.5, 20.0 -> 20 */
    private static function round($value) {
        $value = round(floatval($value), 2);
        return floor($value) == $value ? intval($value) : $value;
    }

    private static function onOff($value) {
        return (is_numeric($value) && $value > 0) ? 'on' : 'off';
    }

    /**
     * Bounds of a 'range' device. The car has no slider: it sends value +/- increment or a preset,
     * so the increment is the widget step only when it gives a reasonable number of taps.
     */
    private static function getRange($widget, $control) {
        // same priority as the app slider (SliderCmd): action cmd, info cmd, widget slider options,
        // then the Jeedom cmd configuration, and 0-100 / 1 only as a last resort
        $action = $widget['sliderAction'] ?? $widget['setpointAction'] ?? array();
        $info = $widget['statusInfo'] ?? $widget['setpointInfo'] ?? array();
        $options = $widget['sliderOptions'] ?? array();
        $cmd = !empty($action['id']) ? cmd::byId($action['id']) : null;
        $cmdConfig = function ($key) use ($cmd) {
            return is_object($cmd) ? $cmd->getConfiguration($key, null) : null;
        };

        $min = self::firstNumeric(array($action['minValue'] ?? null, $info['minValue'] ?? null, $options['minimumValue'] ?? null, $cmdConfig('minValue')), 0);
        $max = self::firstNumeric(array($action['maxValue'] ?? null, $info['maxValue'] ?? null, $options['maximumValue'] ?? null, $cmdConfig('maxValue')), 100);
        $step = self::firstNumeric(array($action['step'] ?? null, $info['step'] ?? null, $options['stepValue'] ?? null), 1);
        if ($max <= $min) {
            JCLog::debug('car - invalid range [' . $min . ',' . $max . '] for widget ' . $widget['id'] . ', using [0,100]');
            $min = 0;
            $max = 100;
        }
        if ($step <= 0) $step = 1;
        // the car has no slider: keep the widget step only when it gives a reasonable number of taps
        $increment = (($max - $min) / $step <= 50) ? $step : ($max - $min) / 10;
        $value = $control['rangeStatus'] ?? null;

        return array(
            'value' => is_numeric($value) ? floatval($value) : null,
            'min' => $min,
            'max' => $max,
            'increment' => $increment,
            'unit' => $control['rangeUnit'] ?? ''
        );
    }

    /**
     * Icon of a device as the app shows it (getStatusImage in the app): the first statusImages entry
     * whose condition is true, else the widget image. Null: the car uses its own icon for the widget type.
     * Normalized for the native car apps:
     *  - {source: md|fa|jeedom, name, color?}  icon fonts bundled in the app, color as #RRGGBB[AA]
     *  - {source: jc, name, path?}             image bundled in the app; path = user image to try otherwise
     *  - {source: url, path|url}               user image, path relative to the Jeedom url
     *  + grayscale: true for the Grayscale filter / shadow
     */
    private static function getCarIcon($widget, $cmdData) {
        $image = null;
        $statusImages = $widget['statusImages'] ?? array();
        if (is_array($statusImages) && count($statusImages) > 0) {
            usort($statusImages, function ($a, $b) {
                return intval($a['index'] ?? 0) - intval($b['index'] ?? 0);
            });
            foreach ($statusImages as $item) {
                if (!is_array($item) || empty($item['condition'])) continue;
                if (self::evaluateIconCondition($item['condition'], $widget, $cmdData) === true) {
                    $image = $item['image'] ?? null;
                    break;
                }
            }
        }
        if ($image == null) $image = $widget['image'] ?? null;
        return self::normalizeIcon($image);
    }

    /**
     * Same substitution as the app: #value# = status info, #id# = cmd value (strings quoted).
     * A variable that can't be resolved makes the condition false, like the failing eval() in the app.
     */
    private static function evaluateIconCondition($condition, $widget, $cmdData) {
        $valueId = self::getValueCmdId($widget);
        if ($valueId != null) $condition = str_replace('#value#', '#' . $valueId . '#', $condition);
        $condition = preg_replace_callback('/#([^#]*)#/', function ($match) use ($cmdData) {
            if (!array_key_exists($match[1], $cmdData)) return $match[0];
            $value = $cmdData[$match[1]];
            return is_numeric($value) ? $value : '"' . str_replace('"', '\"', strval($value)) . '"';
        }, trim($condition));
        if (strpos($condition, '#') !== false) return false;
        try {
            return evaluate($condition);
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function getValueCmdId($widget) {
        return $widget['statusInfo']['id'] ?? $widget['eventInfo']['id'] ?? null;
    }

    /** cmd ids the icon depends on: the #value# one and those used in the statusImages conditions */
    private static function getIconCmdIds($widget) {
        $statusImages = $widget['statusImages'] ?? array();
        if (!is_array($statusImages) || count($statusImages) == 0) return array();
        $ids = array(self::getValueCmdId($widget));
        foreach ($statusImages as $item) {
            preg_match_all('/#(\d+)#/', $item['condition'] ?? '', $matches);
            $ids = array_merge($ids, $matches[1]);
        }
        return array_filter($ids, 'strlen');
    }

    private static function normalizeIcon($icon) {
        if (empty($icon)) return null;
        $userImgPath = config::byKey('userImgPath', 'JeedomConnect');

        // legacy format: a plain string (see getIcon in the app)
        if (is_string($icon)) {
            if (strpos($icon, 'http') === 0) return array('source' => 'url', 'url' => $icon);
            if ($userImgPath != '' && strpos($icon, '/var/www/html/' . $userImgPath) === 0) {
                return array('source' => 'url', 'path' => substr($icon, strlen('/var/www/html/')));
            }
            // other server files go through downloadFile.php with the user hash: not for the car
            if (strpos($icon, '/var/www/html/') === 0) return null;
            if ($userImgPath != '' && strpos($icon, $userImgPath) === 0) return array('source' => 'url', 'path' => $icon);
            return array('source' => 'jc', 'name' => self::withoutExtension($icon), 'path' => $userImgPath . $icon);
        }
        if (!is_array($icon)) return null;

        $result = null;
        switch ($icon['source'] ?? '') {
            case 'md':
            case 'fa':
            case 'jeedom':
                if (empty($icon['name'])) return null;
                $result = array('source' => $icon['source'], 'name' => $icon['name']);
                $color = $icon['effect']['color'] ?? $icon['color'] ?? null;
                // theme color names (primary...) mean nothing outside the app
                if (is_string($color) && preg_match('/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $color)) {
                    $result['color'] = $color;
                }
                break;

            case 'jc':
                if (empty($icon['name'])) return null;
                $result = array('source' => 'jc', 'name' => self::withoutExtension($icon['name']));
                break;

            case 'user':
                if (empty($icon['name'])) return null;
                $name = str_replace('/var/www/html/' . $userImgPath, '', $icon['name']);
                $result = array('source' => 'url', 'path' => ($userImgPath != '' && strpos($name, $userImgPath) === 0) ? $name : $userImgPath . $name);
                break;

            default:
                if (!empty($icon['uri'])) $result = array('source' => 'url', 'url' => $icon['uri']);
                break;
        }
        if ($result == null) return null;

        if (($icon['effect']['filter'] ?? '') == 'Grayscale' || ($icon['shadow'] ?? false) === true || ($icon['shadow'] ?? '') === 'true') {
            $result['grayscale'] = true;
        }
        return $result;
    }

    private static function withoutExtension($name) {
        return preg_replace('/\.[^\/.]+$/', '', $name);
    }

    /** same as numericToHour in the app generic-slider widget (730 -> "07 : 30") */
    private static function numericToHour($value) {
        $str = str_pad(strval($value), 4, '0', STR_PAD_LEFT);
        if (strlen($str) != 4 || intval(substr($str, 0, 2)) > 23 || intval(substr($str, 2)) > 60) return '?? : ??';
        return substr($str, 0, 2) . ' : ' . substr($str, 2);
    }

    private static function firstNumeric($values, $default) {
        foreach ($values as $value) {
            if (is_numeric($value)) return floatval($value);
        }
        return $default;
    }

    private static function getMenuActions($widget, $keys) {
        $res = array();
        foreach ($keys as $key => $widgetKey) {
            if (self::hasCmd($widget[$widgetKey] ?? null)) {
                $res[] = array_merge(array('key' => $key), JeedomConnectUtils::getActionCmd($widget[$widgetKey]));
            }
        }
        return $res;
    }

    /**
     * @param array|null $action a widget action ('id') or a control action ('cmdId')
     */
    private static function hasCmd($action) {
        if (!is_array($action)) return false;
        return ($action['cmdId'] ?? $action['id'] ?? null) != null;
    }
}
