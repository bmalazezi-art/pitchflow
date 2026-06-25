<?php

namespace App\Enums;

enum ReliabilityStatus: string
{
    case Reliable = 'reliable';
    case NeedsAttention = 'needs_attention';
    case HighRisk = 'high_risk';
}
