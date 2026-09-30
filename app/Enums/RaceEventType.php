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

    public function label(): string
    {
        return match ($this) {
            self::QualifyingStart => 'Qualifying started',
            self::QualifyingStop => 'Qualifying lap recorded',
            self::QualifyingInvalid => 'Qualifying lap invalidated',
            self::QualifyingRestore => 'Qualifying lap restored',
            self::QualifyingReset => 'Qualifying lap cleared',
            self::QualifyingCorrected => 'Qualifying time corrected',
            self::QualifyingReplace => 'Qualifying lap improved',
            self::QualifyingPromoted => 'Qualifying promoted',
            self::Ready => 'Driver marked ready',
            self::NotReady => 'Driver no longer ready',
            self::RaceStart => 'Race started',
            self::RaceFinish => 'Driver finished',
            self::Dnf => 'Driver did not finish',
            self::Dns => 'Driver did not start',
            self::Retired => 'Driver retired',
            self::Withdrawn => 'Driver withdrawn',
            self::Penalty => 'Penalty issued',
            self::PenaltyCancelled => 'Penalty cancelled',
            self::GridChange => 'Grid position changed',
            self::GridPenalty => 'Grid penalty applied',
            self::Complete => 'Race completed',
            self::Note => 'Note added',
            self::StatusChange => 'Race status changed',
        };
    }
}
