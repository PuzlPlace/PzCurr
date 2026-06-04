<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Factory;

enum PzCurrAdapterEnum: int
{
    case BCMATH = 0;
    case BRICK_MONEY = 1;  // previsto, sem implementação (fallback → BCMATH)
    case MONEYPHP = 2;     // previsto, sem implementação (fallback → BCMATH)

    /** Resolve case pelo nome (ex.: 'BCMATH'); retorna null se inválido. */
    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }
        return null;
    }
}
