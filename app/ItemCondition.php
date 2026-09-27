<?php

namespace App;

enum ItemCondition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case Good = 'good';
    case Fair = 'fair';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::LikeNew => 'Seperti Baru',
            self::Good => 'Baik',
            self::Fair => 'Cukup Baik',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $condition): array => [$condition->value => $condition->label()])
            ->all();
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
