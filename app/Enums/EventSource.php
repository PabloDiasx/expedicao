<?php

namespace App\Enums;

enum EventSource: string
{
    case Manual = 'manual';
    case Scanner = 'scanner';
    case ScannerExpedicao = 'scanner_expedicao';
}
