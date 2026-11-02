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

        $widgets = array();
        foreach (self::getWidgets($eqLogic) as $widget) {
            $widgets[strval($widget['id'])] = $widget;
        }

        $cmdIds = array();
        foreach ($favorites as $id) {
            if (isset($widgets[$id])) {
                $cmdIds = array_merge($cmdIds, JeedomConnectUtils::getInfosCmdIds($widgets[$id]), self::getIconCmdIds($widgets[$id]));
            }
        }
        $cmdIds = array_unique(array_filter($cmdIds, 'strlen'));
        $cmdData = JeedomConnectUtils::getCmdValues($cmdIds)['data'];

        $devices = array();
        foreach ($favorites as $id) {
            if (!isset($widgets[$id])) continue;
            $device = self::getCarDevice($widgets[$id], $cmdData);
            if ($device != null) {
                $devices[] = $device;
            }
        }

        $navigation = self::getNavigation($eqLogic);
        $result = array(
            'hasFavorites' => count($favorites) > 0,
            'devices' => $devices,
            'navigation' => $navigation
        );
        if ($navigation != 'none') {
            $result['groups'] = self::getGroups($eqLogic, $navigation, $devices, $widgets);
        }
        return $result;
    }

    /**
     * Long polling used by the car screens instead of polling GET_CAR_DEVICES.
     * Waits until an info command used by a favorite changes (or EVENTS_MAX_WAIT seconds),
     * then returns the same payload as getDevices + the datetime to send back on the next call.
     * Without lastReadTimestamp (first call) it answers immediately.
     *
     * @param JeedomConnect $eqLogic
     * @param float|null $lastReadTimestamp datetime returned by the previous call
     * @return array
     */
    public static function getEvents($eqLogic, $lastReadTimestamp) {
        if (!is_object($eqLogic)) {
            throw new Exception('No equipment found');
        }

        if ($lastReadTimestamp == null) {
            $datetime = getmicrotime();
        } else {
            set_time_limit(self::EVENTS_MAX_WAIT + 30);
            $cmdIds = self::getFavoritesInfoCmdIds($eqLogic);
            $datetime = $lastReadTimestamp;
            $end = time() + self::EVENTS_MAX_WAIT;
            while (true) {
                $events = event::changes($datetime);
                $datetime = $events['datetime'];
                if (self::hasCmdUpdate($events['result'], $cmdIds) || time() >= $end) break;
                sleep(1);
            }
        }

        // the full payload also covers what events don't (favorites edited in the app, names with expressions)
        $result = self::getDevices($eqLogic);
        $result['datetime'] = $datetime;
        return $result;
    }

    const EVENTS_MAX_WAIT = 25;

    private static function getFavoritesInfoCmdIds($eqLogic) {
        $favorites = self::getFavorites($eqLogic);
        $cmdIds = array();
        foreach (self::getWidgets($eqLogic) as $widget) {
            if (in_array(strval($widget['id']), $favorites)) {
                $cmdIds = array_merge($cmdIds, JeedomConnectUtils::getInfosCmdIds($widget), self::getIconCmdIds($widget));
            }
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
        'switches' => array('Prises et interrupteurs', array('plug', 'generic-switch', 'group-plug')),
        'covers' => array('Volets', array('shutter', 'group-shutter')),
        'openings' => array('Ouvertures', array('frontgate', 'door', 'window', 'group-door', 'group-window')),
        'security' => array('Sécurité', array('alarm', 'pir', 'group-alarm', 'group-pir')),
        'climate' => array('Climat', array('thermostat', 'air-con', 'temperature', 'humidity')),
        'scenarios' => array('Scénarios et actions', array('scenario', 'generic-action-other')),
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
     *  - menu     : tap opens a screen with one button per item of actions (key : up, stop, down, open, partial, close)
     *  - range    : tap opens -/+/presets, sends rangeAction with options.slider (range : value, min, max, increment, unit)
     *  - readonly : information only (also used when every action needs a PIN, with protected = true)
     * Each action keeps its own 'challenge' : ACK = confirm before sending, PIN = refused in the car
     * (no keyboard while driving)
     *
     * @param array $widget widget config
     * @param array|null $cmdData null if states are not needed
     * @return array|null
     */
    private static function getCarDevice($widget, $cmdData) {
        $control = JeedomConnectDeviceControl::getDeviceConfig($widget, $cmdData);
        if ($control == null) return null;

        $device = array(
            'id' => $control['id'],
            'widgetId' => $control['widgetId'],
            'widgetType' => $widget['type'],
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

        // sliders (and other range-only widgets) only have a rangeStatus in the Device Controls mapping
        $isHour = !empty($widget['hourFormat']);
        if (($device['statusText'] ?? '') === '' && ($control['rangeStatus'] ?? '') !== '') {
            $device['statusText'] = $isHour ? self::numericToHour($control['rangeStatus'])
                : $control['rangeStatus'] . ($control['rangeUnit'] ?? '');
        }

        $actions = array();
        switch ($widget['type']) {
            case 'shutter':
                $actions = self::getMenuActions($widget, array('up' => 'upAction', 'stop' => 'stopAction', 'down' => 'downAction'));
                if (count($actions) > 0) {
                    $device['mode'] = 'menu';
                    $device['actions'] = $actions;
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

            default:
                if (self::hasCmd($control['onAction'] ?? null) && self::hasCmd($control['offAction'] ?? null)) {
                    $device['mode'] = 'toggle';
                    $actions = array($control['onAction'], $control['offAction']);
                } else if (key_exists('action', $control) && (self::hasCmd($control['action']) || ($control['action']['action'] ?? '') == 'execSc')) {
                    $device['mode'] = 'exec';
                    $actions = array($control['action']);
                } else if (self::hasCmd($control['rangeAction'] ?? null) && !$isHour) {
                    // sliders, thermostat setpoint (an "hour" slider holds HHMM, +/- makes no sense: display only)
                    $device['mode'] = 'range';
                    $device['rangeAction'] = $control['rangeAction'];
                    $device['range'] = self::getRange($widget, $control);
                    $actions = array($control['rangeAction']);
                }
                break;
        }

        if ($device['mode'] == 'toggle') {
            $device['onAction'] = $control['onAction'] ?? $actions[0];
            $device['offAction'] = $control['offAction'] ?? $actions[1];
        } else if ($device['mode'] == 'exec' && !key_exists('action', $device)) {
            $device['action'] = $control['action'];
        }

        // challenges are handled per action by the clients (ON can need a confirmation while OFF doesn't)
        // only when every action needs a PIN is there nothing left to do from the car
        $pinCount = count(array_keys(array_column($actions, 'challenge'), 'PIN'));
        if (count($actions) > 0 && $pinCount == count($actions)) {
            $device['mode'] = 'readonly';
            $device['protected'] = true;
            unset($device['onAction'], $device['offAction'], $device['action'], $device['actions'], $device['rangeAction'], $device['range']);
        }

        if ($control['deviceStatus'] == 'DISABLED') {
            $device['mode'] = 'readonly';
            unset($device['onAction'], $device['offAction'], $device['action'], $device['actions'], $device['rangeAction'], $device['range']);
        }

        return $device;
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
