<?php

class JeedomConnectAutomations {

    public static function addAutomation($eqLogic, $automation) {
        $trigger = $automation['triggers'][0]; //only one trigger for the moment
        $type = $trigger['type'];
        $id = $automation["id"];
        $cron = self::getCron($eqLogic, $id);
        $listener = self::getListener($eqLogic, $id);

        // Automation trigger type has changed
        if (is_object($cron) && $type == "event") {
            self::removeCron($eqLogic, $id);
        }
        if (is_object($listener) && $type == "cron") {
            self::removeListener($eqLogic, $id);
        }


        if ($type == "cron") {
            self::addCron($eqLogic, $automation);
        } elseif ($type == "event") {
            self::addEvent($eqLogic, $automation);
        }

        return self::getAutomations($eqLogic);
    }

    public static function removeAutomation($eqLogic, $automation) {
        $trigger = $automation['triggers'][0]; //only one trigger for the moment
        $id = $automation["id"];
        $type = $trigger['type'];
        if ($type == "cron") {
            self::removeCron($eqLogic, $id);
        } elseif ($type == "event") {
            self::removeListener($eqLogic, $id);
        }

        return self::getAutomations($eqLogic);
    }

    public static function removeAllAutomation($eqLogic) {
        foreach (cron::searchClassAndFunction('JeedomConnect', 'jobExecutor') as $c) {
            $options = $c->getOption();
            if ($options['eqLogicId'] == $eqLogic->getId()) {
                $c->remove();
            }
        }

        foreach (listener::searchClassFunctionOption('JeedomConnect', 'jobExecutor') as $listener) {
            $options = $listener->getOption();
            if ($options['eqLogicId'] == $eqLogic->getId()) {
                $listener->remove();
            }
        }
        return self::getAutomations($eqLogic);
    }

    public static function getAutomations($eqLogic) {
        $crons = self::getAllCrons($eqLogic);
        JCLog::debug('Get all crons for eqLogic ' . $eqLogic->getName() . ', found ' . count($crons) . ' crons : ' . json_encode($crons));
        $events = self::getAllListners($eqLogic);
        JCLog::debug('Get all listeners for eqLogic ' . $eqLogic->getName() . ', found ' . count($events) . ' events : ' . json_encode($events));
        return array_merge($crons, $events);
    }

    // Cron functions
    private static function addCron($eqLogic, $automation) {
        $trigger = $automation['triggers'][0]; //only one trigger for the moment
        $id = $automation["id"];
        $once = $trigger["options"]["once"];
        $keep = $trigger["options"]["keep"];
        $schedule = $trigger["options"]["cron"];

        $automation["eqLogicId"] = $eqLogic->getId();

        $cron = self::getCron($eqLogic, $id);
        if (!is_object($cron)) {
            $cron = new cron();
            $cron->setClass('JeedomConnect');
            $cron->setFunction('jobExecutor');
        }
        $cron->setOption($automation);
        $cron->setOnce($once && !$keep ? 1 : 0);
        $cron->setEnable(1);
        $cron->setDeamon(0);
        $cron->setSchedule($schedule);
        $cron->setTimeout(5);
        $cron->save();
    }

    private static function removeCron($eqLogic, $id) {
        $cron = self::getCron($eqLogic, $id);
        if (is_object($cron)) {
            $cron->remove();
        }
    }

    private static function getCron($eqLogic, $id) {
        foreach (cron::searchClassAndFunction('JeedomConnect', 'jobExecutor') as $c) {
            $options = $c->getOption();
            if ($options['id'] == $id && $options['eqLogicId'] == $eqLogic->getId()) {
                return $c;
            }
        }
        return null;
    }

    private static function getAllCrons($eqLogic) {
        $res = array();
        foreach (cron::searchClassAndFunction('JeedomConnect', 'jobExecutor') as $c) {
            $options = $c->getOption();
            if ($options['eqLogicId'] == $eqLogic->getId()) {
                array_push($res, $options);
            }
        }
        return $res;
    }

    // Event functions
    private static function addEvent($eqLogic, $automation) {
        $trigger = $automation['triggers'][0]; //only one trigger for the moment
        $id = $automation["id"];
        $event = $trigger["options"]["event"];

        $automation["eqLogicId"] = $eqLogic->getId();

        $cmds = JeedomConnectUtils::getCmdIdFromText($event);

        if (count($cmds) > 0) {
            $listener = self::getListener($eqLogic, $id);
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

    private static function getListener($eqLogic, $id) {
        foreach (listener::searchClassFunctionOption('JeedomConnect', 'jobExecutor') as $listener) {
            $options = $listener->getOption();
            if ($options['id'] == $id && $options['eqLogicId'] == $eqLogic->getId()) {
                return $listener;
            }
        }
        return null;
    }

    private static function getAllListners($eqLogic) {
        $res = array();
        foreach (listener::searchClassFunctionOption('JeedomConnect', 'jobExecutor') as $listener) {
            $options = $listener->getOption();
            if ($options['eqLogicId'] == $eqLogic->getId()) {
                array_push($res, $options);
            }
        }
        return $res;
    }

    private static function removeListener($eqLogic, $id) {
        $listener = self::getListener($eqLogic, $id);
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
                    $message .= "<br><br>" . $msgNotif;

                    $title = "Programmation JeedomConnect";

                    $data = array(
                        'type' => 'DISPLAY_NOTIF',
                        'payload' => array(
                            'title' => $title,
                            'message' => $message,
                            'notificationId' => round(microtime(true) * 10000)
                        )
                    );
                    $eqLogic->sendNotif("defaultNotif", $data);
                    break;
            }
        }
    }
}
