<?php

namespace App\Enums;

enum KpiSourceKind: string
{
    case ResearchRecord = 'RESEARCH_RECORD';
    case MetricSnapshot = 'METRIC_SNAPSHOT';
}
