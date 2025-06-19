<?php

class JeedomConnectAutomations {

    public static function addAutomation($eqLogicId, $automation) {
        $trigger = $automation['triggers'][0]; //only one trigger for the moment
        $type = $trigger['type'];
        $id = $automation["id"];
        $cron = self::getCron($eqLogicId, $id);
        $listener = self::getListener($eqLogicId, $id);

        // Automation trigger type has changed
        if (is_object($cron) && $type == "event") {
            self::removeCron($eqLogicId, $id);
        }
        if (is_object($listener) && $type == "cron") {
            self::removeListener($eqLogicId, $id);
        }


        if ($type == "cron") {
            self::addCron($eqLogicId, $automation);
        } elseif ($type == "event") {
            self::addEvent($eqLogicId, $automation);
        }

        return self::getAutomations($eqLogicId);
    }

    public static function setAutomationStatus($eqLogicId, $type, $id, $status) {

        if ($type == "cron") {
            $automation = self::getCron($eqLogicId, $id);
            $options = $automation->getOption();
            $options["disabled"] = $status == 0 ? false : true;
            self::addCron($eqLogicId, $options);
        } elseif ($type == "event") {
            $automation = self::getListener($eqLogicId, $id);
            $options = $automation->getOption();
            $options["disabled"] = $status == 0 ? false : true;
            self::addEvent($eqLogicId, $options);
        }

        return true;
    }

    public static function removeAutomation($eqLogicId, $type, $id) {
        if ($type == "cron") {
            self::removeCron($eqLogicId, $id);
        } elseif ($type == "event") {
            self::removeListener($eqLogicId, $id);
        }

        return self::getAutomations($eqLogicId);
    }

    public static function removeAllAutomation($eqLogicId) {
        foreach (cron::searchClassAndFunction('JeedomConnect', 'jobExecutor') as $c) {
            $options = $c->getOption();
            if ($options['eqLogicId'] == $eqLogicId) {
                $c->remove();
            }
        }

        foreach (listener::searchClassFunctionOption('JeedomConnect', 'jobExecutor') as $listener) {
            $options = $listener->getOption();
            if ($options['eqLogicId'] == $eqLogicId) {
                $listener->remove();
            }
        }
        return self::getAutomations($eqLogicId);
    }

    public static function getAutomations($eqLogicId, $withKey = false) {
        $crons = self::getAllCrons($eqLogicId);
        JCLog::debug('Get all crons for eqLogic ' . $eqLogicId . ', found ' . count($crons) . ' crons : ' . json_encode($crons));
        $events = self::getAllListners($eqLogicId);
        JCLog::debug('Get all listeners for eqLogic ' . $eqLogicId . ', found ' . count($events) . ' events : ' . json_encode($events));
        if ($withKey) {
            return array('crons' => $crons, 'events' => $events);
        }

        return array_merge($crons, $events);
    }

    // Cron functions
    private static function addCron($eqLogicId, $automation) {
        $trigger = $automation['triggers'][0]; //only one trigger for the moment
        $id = $automation["id"];
        $unique = $trigger["options"]["unique"];
        $once = $trigger["options"]["once"];
        $unique = $trigger["options"]["unique"];

        $schedule = $trigger["options"]["cron"];

        $automation["eqLogicId"] = $eqLogicId;

        $cron = self::getCron($eqLogicId, $id);
        if (!is_object($cron)) {
            $cron = new cron();
            $cron->setClass('JeedomConnect');
            $cron->setFunction('jobExecutor');
        }
        $cron->setOption($automation);
        $cron->setOnce($once && $unique ? 1 : 0);
        $cron->setEnable(1);
        $cron->setDeamon(0);
        $cron->setSchedule($schedule);
        $cron->setTimeout(5);
        $cron->save();
    }

    private static function removeCron($eqLogicId, $id) {
        $cron = self::getCron($eqLogicId, $id);
        if (is_object($cron)) {
            $cron->remove();
        }
    }

    private static function getCron($eqLogicId, $id) {
        foreach (cron::searchClassAndFunction('JeedomConnect', 'jobExecutor') as $c) {
            $options = $c->getOption();
            if ($options['id'] == $id && $options['eqLogicId'] == $eqLogicId) {
                return $c;
            }
        }
        return null;
    }

    private static function getAllCrons($eqLogicId) {
        $res = array();
        foreach (cron::searchClassAndFunction('JeedomConnect', 'jobExecutor') as $c) {
            $options = $c->getOption();
            if ($options['eqLogicId'] == $eqLogicId) {
                array_push($res, array_merge($options, array('jeedomId' => $c->getId())));
            }
        }
        return $res;
    }

    // Event functions
    private static function addEvent($eqLogicId, $automation) {
        $trigger = $automation['triggers'][0]; //only one trigger for the moment
        $id = $automation["id"];
        $event = $trigger["options"]["event"];

        $automation["eqLogicId"] = $eqLogicId;

        $cmds = JeedomConnectUtils::getCmdIdFromText($event);

        if (count($cmds) > 0) {
            $listener = self::getListener($eqLogicId, $id);
            if (!is_object($listener)) {
                $listener = new listener();
                $listener->setClass('JeedomConnect');
                $listener->setFunction('jobExecutor');
            }
            $listener->setOption($automation);
            foreach ($cmds as $cmdId) {
                $cmd = cmd::byId($cmdId);
                if (is_object(($cmd))) {
                    $listener->addEvent($cmd->getId());
                }
            }
            $listener->save();
        }
    }

    private static function getListener($eqLogicId, $id) {
        foreach (listener::searchClassFunctionOption('JeedomConnect', 'jobExecutor') as $listener) {
            $options = $listener->getOption();
            if ($options['id'] == $id && $options['eqLogicId'] == $eqLogicId) {
                return $listener;
            }
        }
        return null;
    }

    private static function getAllListners($eqLogicId) {
        $res = array();
        foreach (listener::searchClassFunctionOption('JeedomConnect', 'jobExecutor') as $listener) {
            $options = $listener->getOption();
            if ($options['eqLogicId'] == $eqLogicId) {
                array_push($res, array_merge($options, array('jeedomId' => $listener->getId())));
            }
        }
        return $res;
    }

    private static function removeListener($eqLogicId, $id) {
        $listener = self::getListener($eqLogicId, $id);
        if (is_object($listener)) {
            $listener->remove();
        }
    }

    //Job executor

    public static function jobExecutor($options) {
        JCLog::info('Execute job with options ' . json_encode($options));

        if ($options["disabled"]) {
            JCLog::info("Do not execute job cause automation is disabled");
            return;
        }

        $trigger = $options['triggers'][0];

        if ($trigger["type"] == "cron") {
            self::executeActions($options);
        } elseif ($trigger["type"] == "event") {
            $event = $trigger["options"]["event"];
            $cmds = JeedomConnectUtils::getCmdIdFromText($event);

            if (count($cmds) == 1 && $event == "#" . $cmds[0] . "#") {
                //cmd only, handle any event
                self::executeActions($options);
                if ($trigger["options"]["once"]) {
                    self::removeListener(eqLogic::byId($options["eqLogicId"]), $options["id"]);
                }
                return;
            }

            foreach ($cmds as $cmdId) {
                $cmd = cmd::byId($cmdId);
                if (is_object(($cmd))) {
                    $event = str_replace("#" . $cmdId . "#", $cmd->execCmd(), $event);
                }
            }
            JCLog::debug('Execute job, evaluate condition ' . $event);
            $conditionResult = false;
            try {
                $conditionResult = eval("return $event;");
                if ($conditionResult) {
                    self::executeActions($options);
                    if ($trigger["options"]["once"]) {
                        self::removeListener(eqLogic::byId($options["eqLogicId"]), $options["id"]);
                    }
                }
            } catch (ParseError  $e) {
                JCLog::debug('Error evaluating condition, ' . $e->getMessage());
            }
        }
    }

    private static function executeActions($options) {
        $actions = $options["actions"];

        JCLog::debug('Execute actions ' . json_encode($actions));
        foreach ($actions as $action) {
            switch ($action["action"]) {
                case 'cmd':
                    apiHelper::execCmd($action["options"]['id'], $action['options']['options'] ?? null);
                    break;
                case 'scenario':
                    apiHelper::execSc($action["options"]['scenario_id'], $action["options"]);
                    break;
                case 'notif':
                    /** @var JeedomConnect  $eqLogic */
                    $eqLogic = eqLogic::byId($options["eqLogicId"]);
                    $trigger = $options['triggers'][0];
                    $type = $trigger['type'];
                    $actions = $options['actions'];

                    if (count($actions) > 1) {
                        $msgNotif = 'Action(s) déclenchée(s) : ';
                        foreach ($actions as $action) {
                            if ($action['action'] == 'scenario') {
                                $msgNotif .= 'scénario "' . $action['options']['name'] . '", ';
                            }
                            if ($action['action'] == 'cmd') {
                                $msgNotif .= 'commande ' . cmd::byId($action['options']['id'])->getHumanName() . ",";
                            }
                        }
                        $msgNotif = rtrim($msgNotif, ', ');
                    }
                    // JCLog::debug('Message for notification: ' . $msgNotif);

                    switch ($type) {
                        case "cron":
                            $message = "L'heure de la programmation est arrivée.";
                            break;
                        case "event":
                            $event = $trigger["options"]["event"];
                            $triggerCmd = cmd::byId($options["event_id"]);
                            if ($event == "#" . $options["event_id"] . "#") {
                                $message = "La commande " . $triggerCmd->getHumanName() . " a changé d'état.";
                            } else {
                                $message = "La condition " . jeedom::toHumanReadable($event) . " est vérifiée.";
                            }
                            break;
                    }
                    $message .= !empty($msgNotif) ? "<br><br>$msgNotif" : '';

                    $title = "Programmation JeedomConnect";

                    $data = array(
                        'type' => 'DISPLAY_NOTIF',
                        'payload' => array(
                            'title' => $title,
                            'message' => $message,
                            'notificationId' => round(microtime(true) * 10000)
                        )
                    );
                    $notifId = $action["options"]["id"] ?? 'defaultNotif';
                    $eqLogic->sendNotif($notifId, $data);
                    break;
            }
        }
    }
}
