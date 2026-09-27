<?php

namespace App;

enum PaymentMethodType: string
{
    case Dana = 'dana';
    case Gopay = 'gopay';
    case Ovo = 'ovo';
    case Bank = 'bank';
    case Qris = 'qris';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Dana => 'DANA',
            self::Gopay => 'GoPay',
            self::Ovo => 'OVO',
            self::Bank => 'Transfer Bank',
            self::Qris => 'QRIS',
            self::Other => 'Metode Lainnya',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
