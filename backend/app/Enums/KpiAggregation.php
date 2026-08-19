<?php

namespace App\Enums;

enum KpiAggregation: string
{
    case Count = 'COUNT';
    case Sum = 'SUM';
    case Avg = 'AVG';
    case Max = 'MAX';
}
