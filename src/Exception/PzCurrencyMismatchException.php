<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Exception;

final class PzCurrencyMismatchException extends PzCurrException
{
    /**
     * Cria a exceção para operação entre moedas incompatíveis.
     */
    public static function between(string $a, string $b): self
    {
        return new self(sprintf(
            'Invalid operation between different currencies: "%s" and "%s".',
            $a,
            $b
        ));
    }
}
