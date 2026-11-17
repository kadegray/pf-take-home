<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Purchase = 'purchase';
    case Hold = 'hold';
    case Settlement = 'settlement';
}
