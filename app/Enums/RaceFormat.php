<?php

namespace App\Enums;

enum RaceFormat: string
{
    case Sprint = 'sprint';
    case Feature = 'feature';
    case Custom = 'custom';
}