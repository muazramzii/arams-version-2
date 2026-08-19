<?php

namespace App\Enums;

enum DatePrecision: string
{
    case Day = 'DAY';
    case Month = 'MONTH';
    case Year = 'YEAR';
    case Unknown = 'UNKNOWN';
}
