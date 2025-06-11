<?php

class JeedomConnectAutomations {

    public static function addAutomation($eqLogic, $automation) {
        $trigger = $automation['triggers'][0]; //only one trigger for the moment
        $type = $trigger['type'];
        if ($type == "cron") {
            self::addCron($eqLogic, $automation);
        }
        if ($type == "event") {
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
        } else {
            self::removeListener($eqLogic, $id);
        }

        return self::getAutomations($eqLogic);
    }

    public static function getAutomations($eqLogic) {
        $crons = self::getAllCrons($eqLogic);
        $events = self::getAllListners($eqLogic);
        return array_merge($crons, $events);
    }

    // Cron functions

    private static function addCron($eqLogic, $automation) {
        $trigger = $automation['triggers'][0]; //only one trigger for the moment
        $id = $automation["id"];
        $once = $trigger["options"]["once"];
        $schedule = $trigger["options"]["cron"];

        $automation["eqLogicId"] = $eqLogic->getId();

        $cron = self::getCron($eqLogic, $id);
        if (!is_object($cron)) {
            $cron = new cron();
            $cron->setClass('JeedomConnect');
            $cron->setFunction('jobExecutor');
        }
        $cron->setOption($automation);
        $cron->setOnce($once ? 1 : 0);
        $cron->setEnable(1);
        $cron->setDeamon(0);
        $cron->setSchedule($schedule);
        $cron->setTimeout(15); // increase this value ?
        $cron->save();
    }

    private static function removeCron($eqLogic, $id) {
        $cron = self::getCron($eqLogic, $id);
        if (is_object($cron)) {
            $cron->remove();
        }
    }

    private static function getCron($eqLogic, $id) {
        foreach (cron::all() as $c) {
            if ($c->getClass() == 'JeedomConnect' && $c->getFunction() == "jobExecutor") {
                $options = $c->getOption();
                if ($options['id'] == $id && $options['eqLogicId'] == $eqLogic->getId()) {
                    return $c;
                }
            }
        }
        return null;
    }

    private static function getAllCrons($eqLogic) {
        $res = array();
        foreach (cron::all() as $c) {
            if ($c->getClass() == 'JeedomConnect' && $c->getFunction() == "jobExecutor") {
                $options = $c->getOption();
                if ($options['eqLogicId'] == $eqLogic->getId()) {
                    array_push($res, $options);
                }
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
        foreach (listener::all() as $listener) {
            if ($listener->getClass() == 'JeedomConnect' && $listener->getFunction() == 'jobExecutor') {
                $options = $listener->getOption();
                if ($options['id'] == $id && $options['eqLogicId'] == $eqLogic->getId()) {
                    return $listener;
                }
            }
        }
        return null;
    }

    private static function getAllListners($eqLogic) {
        $res = array();
        foreach (listener::all() as $listener) {
            if ($listener->getClass() == 'JeedomConnect' && $listener->getFunction() == 'jobExecutor') {
                $options = $listener->getOption();
                if ($options['eqLogicId'] == $eqLogic->getId()) {
                    array_push($res, $options);
                }
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

        $trigger = $options['triggers'][0];

        if ($trigger["type"] == "cron") {
            self::executeActions($options);
        } else {
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
        $trigger = $options['triggers'][0];
        $actions = $options["actions"];
        $eqLogic = eqLogic::byId($options["eqLogicId"]);

        JCLog::debug('Execute actions ' . json_encode($actions));
        foreach ($actions as $action) {
            if ($action["action"] == "cmd") {
                apiHelper::execCmd($action["options"]['id'], $action['options'] ?? null);
            }
            if ($action["action"] == "scenario") {
                apiHelper::execSc($action["options"]['scenario_id'], $action["options"]);
            }
            if ($action["action"] == "notif") {
                // TODO : choose a notif cmd and execute it with a message related to the trigger
            }
        }
    }
}
