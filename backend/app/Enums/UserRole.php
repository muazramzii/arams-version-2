<?php

namespace App\Enums;

enum UserRole: string
{
    case Lecturer = 'Lecturer';
    case Tdpp = 'TDPP';
    case Admin = 'Admin';
}
