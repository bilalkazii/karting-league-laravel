<?php

namespace App\Enums;

enum RaceEventType: string
{
    case QualifyingStart = 'qualifying_start';
    case QualifyingStop = 'qualifying_stop';
    case QualifyingInvalid = 'qualifying_invalid';
    case QualifyingRestore = 'qualifying_restore';
    case QualifyingReset = 'qualifying_reset';
    case QualifyingCorrected = 'qualifying_corrected';
    case QualifyingReplace = 'qualifying_replace';
    case QualifyingPromoted = 'qualifying_promoted';
    case Ready = 'ready';
    case NotReady = 'not_ready';
    case RaceStart = 'race_start';
    case RaceFinish = 'race_finish';
    case Dnf = 'dnf';
    case Dns = 'dns';
    case Retired = 'retired';
    case Withdrawn = 'withdrawn';
    case Penalty = 'penalty';
    case PenaltyCancelled = 'penalty_cancelled';
    case GridChange = 'grid_change';
    case GridPenalty = 'grid_penalty';
    case Complete = 'complete';
    case Note = 'note';
    case StatusChange = 'status_change';
}