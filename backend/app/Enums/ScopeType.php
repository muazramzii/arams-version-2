<?php

namespace App\Enums;

enum ScopeType: string
{
    case Institution = 'INSTITUTION';
    case Faculty = 'FACULTY';
    case Staff = 'STAFF';
}
