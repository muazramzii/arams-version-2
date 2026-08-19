<?php

namespace App\Enums;

enum AttributionBasis: string
{
    case EffectiveDate = 'EFFECTIVE_DATE';
    case SubmissionDateFallback = 'SUBMISSION_DATE_FALLBACK';
    case Manual = 'MANUAL';
}
