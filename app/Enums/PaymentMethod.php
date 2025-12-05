<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case CreditCard = 'credit_card';
    case Check = 'check';
}
