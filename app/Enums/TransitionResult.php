<?php

namespace App\Enums;

enum TransitionResult: string
{
    case Updated = 'updated';
    case NoChange = 'no_change';
    case NotFound = 'not_found';
    case InvalidStatus = 'invalid_status';
}
