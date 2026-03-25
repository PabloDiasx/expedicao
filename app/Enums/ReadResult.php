<?php

namespace App\Enums;

enum ReadResult: string
{
    case Matched = 'matched';
    case NoChange = 'no_change';
    case NotFound = 'not_found';
}
