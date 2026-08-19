<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Open = 'OPEN';
    case Met = 'MET';
    case MetLate = 'MET_LATE';
    case Missed = 'MISSED';
    case Cancelled = 'CANCELLED';
}
