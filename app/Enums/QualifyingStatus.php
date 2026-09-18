<?php

namespace App\Enums;

enum QualifyingStatus: string
{
    case NotStarted = 'not_started';
    case Running = 'running';
    case Completed = 'completed';
    case Invalid = 'invalid';
    case ManuallyCorrected = 'manually_corrected';
}