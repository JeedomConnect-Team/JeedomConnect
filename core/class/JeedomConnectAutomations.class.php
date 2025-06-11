<?php

class JeedomConnectAutomations {

    public static function addAutomation($eqLogic, $automation) {
        $trigger = $automation['triggers'][0]; //only one trigger for the moment
        $type = $trigger['type'];
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
                    // TODO : choose a notif cmd and execute it with a message related to the trigger
                    break;
            }
        }
    }
}
