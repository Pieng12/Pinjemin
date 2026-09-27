<?php

namespace App;

enum PaymentPlan: string
{
    case FullTransfer = 'full_transfer';
    case DepositTransfer = 'deposit_transfer';
    case DepositCash = 'deposit_cash';

    public function label(): string
    {
        return match ($this) {
            self::FullTransfer => 'Bayar lunas',
            self::DepositTransfer => 'DP, lalu transfer pelunasan',
            self::DepositCash => 'DP, lalu tunai saat pengambilan',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
