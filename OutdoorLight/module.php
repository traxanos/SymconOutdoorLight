<?php

declare(strict_types=1);

class OutdoorLight extends IPSModule
{
    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        $this->RegisterPropertyInteger('BrightnessId', 0);
        $this->RegisterPropertyBoolean('MorningActive', true);
        $this->RegisterPropertyInteger('MorningLux', 50);
        $this->RegisterPropertyInteger('MorningDuration', 120);
        $this->RegisterPropertyString('MorningStartTime', '{"hour":5,"minute":0,"second":0}');
        $this->RegisterPropertyString('MorningEndTime', '{"hour":9,"minute":0,"second":0}');
        $this->RegisterPropertyBoolean('EveningActive', true);
        $this->RegisterPropertyInteger('EveningLux', 50);
        $this->RegisterPropertyInteger('EveningDuration', 180);
        $this->RegisterPropertyString('EveningStartTime', '{"hour":17,"minute":0,"second":0}');
        $this->RegisterPropertyString('EveningEndTime', '{"hour":0,"minute":0,"second":0}');

        // Variables
        $this->RegisterVariableBoolean('Status', $this->Translate('Status'), '~Switch', false);
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();

        if ($this->ReadPropertyInteger('BrightnessId') == 0) {
            $this->SetStatus(201);

            $luxDependendEvents = [
                'MorningStartEvent',
                'MorningLearnEvent',
                'EveningLuxEvent',
                'EveningStartEvent'
            ];
            foreach ($luxDependendEvents as $eventName) {
                if ($event = @$this->GetIDForIdent($eventName)) {
                    IPS_SetEventActive($event, false);
                }
            }

            return;
        }

        $this->CreateDurationEvent();
        $this->CreateMorningLearnEvent();
        $this->CreateMorningStartEvent();
        $this->CreateMorningEndEvent();
        $this->CreateEveningLuxEvent();
        $this->CreateEveningStartEvent();
        $this->CreateEveningEndEvent();

        $this->SetStatus(102);
    }

    public function ExecuteEvent(string $type)
    {
        if ($type == 'MorningOn' || $type == 'EveningOn') {
            $duration = $this->ReadPropertyInteger(substr($type, 0, 7) . 'Duration');
            $this->TurnOn($duration);
        } elseif ($type == 'MorningLearn') {
            $this->CalcMoringStartTime();
        } else {
            $this->TurnOff();
        }
    }

    protected function TurnOn(int $duration = 1)
    {
        $event = $this->GetIDForIdent('DurationEvent');
        IPS_SetEventActive($event, true);

        $time = new DateTime("+ $duration minutes");
        IPS_SetEventCyclicTimeFrom(
            $event,
            $time->format('H'),
            $time->format('i'),
            $time->format('s')
        );

        SetValueBoolean($this->GetIDForIdent('Status'), true);
    }

    protected function TurnOff()
    {
        $event = $this->GetIDForIdent('DurationEvent');
        IPS_SetEventActive($event, false);

        SetValueBoolean($this->GetIDForIdent('Status'), false);
    }

    protected function CalcMoringStartTime()
    {
        $event = $this->GetIDForIdent('MorningStartEvent');
        $duration = $this->ReadPropertyInteger('MorningDuration');
        $time = $this->ReadPropertyTime('MorningStartTime');
        $calcTime = new DateTime("- $duration minutes");
        if ($time < $calcTime) {
            $time = $calcTime;
        }
        IPS_SetEventCyclicTimeFrom(
            $event,
            $time->format('H'),
            $time->format('i'),
            $time->format('s')
        );

        $nextDay = new DateTime('+ 1 day');
        $event = $this->GetIDForIdent('MorningLearnEvent');
        IPS_SetEventConditionDateRule($event, 0, 1, 3,
            $nextDay->format('d'), $nextDay->format('m'), $nextDay->format('Y')
        );
    }

    protected function CreateDurationEvent()
    {
        $instance = $this->InstanceID;
        $status = $this->GetIDForIdent('Status');
        $event = $this->CreateEvent('DurationEvent', 1);
        IPS_SetEventScript($event, "OL_ExecuteEvent($instance, 'Off');");
    }

    protected function CreateMorningLearnEvent()
    {
        $found = @$this->GetIDForIdent('MorningLearnEvent');
        $instance = $this->InstanceID;
        $status = $this->GetIDForIdent('Status');
        $event = $this->CreateEvent('MorningLearnEvent', 0);
        IPS_SetEventActive($event, true);
        IPS_SetEventTrigger($event, 2, $this->ReadPropertyInteger('BrightnessId'));
        IPS_SetEventTriggerValue($event, $this->ReadPropertyInteger('MorningLux'));
        IPS_SetEventTriggerSubsequentExecution($event, false);

        IPS_SetEventCondition($event, 0, 0, 0);
        if (!$found) { // New created
            $time = new DateTime('+ 1 day');
            IPS_SetEventConditionDateRule(
                $event, 0, 1, 3,
                $time->format('d'),
                $time->format('m'),
                $time->format('Y')
            );
        }

        IPS_SetEventScript($event, "OL_ExecuteEvent($instance, 'MorningLearn');");
    }

    protected function CreateEveningLuxEvent()
    {
        $instance = $this->InstanceID;
        $status = $this->GetIDForIdent('Status');
        $event = $this->CreateEvent('EveningLuxEvent', 0);
        $time = $this->ReadPropertyTime('EveningStartTime');
        IPS_SetEventActive($event, $this->ReadPropertyBoolean('EveningActive'));
        IPS_SetEventTrigger($event, 3, $this->ReadPropertyInteger('BrightnessId'));
        IPS_SetEventTriggerValue($event, $this->ReadPropertyInteger('EveningLux'));
        IPS_SetEventTriggerSubsequentExecution($event, false);
        IPS_SetEventCondition($event, 0, 0, 0);
        IPS_SetEventConditionVariableRule($event, 0, 1, $status, 0, false);
        IPS_SetEventConditionTimeRule($event, 0, 2, 2,
            $time->format('H'), $time->format('i'), $time->format('s')
        );
        IPS_SetEventScript($event, "OL_ExecuteEvent($instance, 'EveningOn');");
    }

    protected function CreateMorningStartEvent()
    {
        $instance = $this->InstanceID;
        $found = @$this->GetIDForIdent('MorningStartEvent');
        $status = $this->GetIDForIdent('Status');
        $event = $this->CreateEvent('MorningStartEvent', 1);
        $eventData = IPS_GetEvent($event);
        $currentTime = $eventData['CyclicTimeFrom'];
        if (!$found) { // New created
            $this->SetEventCyclicTime($event, 'MorningStartTime');
        }
        IPS_SetEventActive($event, $this->ReadPropertyBoolean('MorningActive'));
        IPS_SetEventCondition($event, 0, 0, 0);
        IPS_SetEventConditionVariableRule($event, 0, 1, $status, 0, false);
        IPS_SetEventScript($event, "OL_ExecuteEvent($instance, 'MorningOn');");
    }

    protected function CreateEveningStartEvent()
    {
        $instance = $this->InstanceID;
        $status = $this->GetIDForIdent('Status');
        $event = $this->CreateEvent('EveningStartEvent', 1);
        $brightness_id = $this->ReadPropertyInteger('BrightnessId');
        $lux = $this->ReadPropertyInteger('EveningLux');
        $this->SetEventCyclicTime($event, 'EveningStartTime');
        IPS_SetEventActive($event, $this->ReadPropertyBoolean('EveningActive'));
        IPS_SetEventCondition($event, 0, 0, 0);
        IPS_SetEventConditionVariableRule($event, 0, 1, $status, 0, false);
        IPS_SetEventConditionVariableRule($event, 0, 2, $brightness_id, 5, $lux);
        IPS_SetEventScript($event, "OL_ExecuteEvent($instance, 'EveningOn');");
    }

    protected function CreateMorningEndEvent()
    {
        $instance = $this->InstanceID;
        $event = $this->CreateEvent('MorningEndEvent', 1);
        $this->SetEventCyclicTime($event, 'MorningEndTime');
        IPS_SetEventActive($event, $this->ReadPropertyBoolean('MorningActive'));
        IPS_SetEventScript($event, "OL_ExecuteEvent($instance, 'Off');");
    }

    protected function CreateEveningEndEvent()
    {
        $instance = $this->InstanceID;
        $event = $this->CreateEvent('EveningEndEvent', 1);
        $this->SetEventCyclicTime($event, 'EveningEndTime');
        IPS_SetEventActive($event, $this->ReadPropertyBoolean('EveningActive'));
        IPS_SetEventScript($event, "OL_ExecuteEvent($instance, 'Off');");
    }

    protected function CreateEvent(string $ident, int $type)
    {
        if (!$event = @$this->GetIDForIdent($ident)) {
            $event = IPS_CreateEvent($type);
            IPS_SetIdent($event, $ident);
            IPS_SetParent($event, $this->InstanceID);
            IPS_SetHidden($event, true);
        }

        return $event;
    }

    protected function SetEventCyclicTime(int $event, string $property)
    {
        $time = $this->ReadPropertyTime($property);
        IPS_SetEventCyclicTimeFrom(
            $event,
            $time->format('H'),
            $time->format('i'),
            $time->format('s')
        );
    }

    protected function ReadPropertyTime($name)
    {
        $timeValues = json_decode($this->ReadPropertyString($name));
        return new DateTime($timeValues->hour . ':' . $timeValues->minute . ':' . $timeValues->second);
    }
}
