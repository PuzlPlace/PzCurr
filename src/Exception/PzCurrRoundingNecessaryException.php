<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Exception;

final class PzCurrRoundingNecessaryException extends PzCurrException
{
    /**
     * Cria a exceção quando o arredondamento é necessário mas não permitido na escala definida.
     */
    public static function forScale(string $amount, int $scale): self
    {
        return new self(sprintf(
            'Rounding is required to represent "%s" at scale %d, but UNNECESSARY mode was configured.',
            $amount,
            $scale
        ));
    }
}
