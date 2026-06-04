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
            'Código de moeda inválido ou não suportado: "%s". Use um código ISO 4217 válido (ex.: "BRL", "USD").',
            $code
        ));
    }
}
