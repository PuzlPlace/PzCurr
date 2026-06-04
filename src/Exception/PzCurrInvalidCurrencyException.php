<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Exception;

final class PzCurrInvalidCurrencyException extends PzCurrException
{
    /**
     * Cria a exceção para um código de moeda desconhecido ou inválido.
     */
    public static function forCode(string $code): self
    {
        return new self(sprintf(
            'Invalid or unsupported currency code: "%s". Use a valid ISO 4217 code (e.g. "BRL", "USD").',
            $code
        ));
    }
}
