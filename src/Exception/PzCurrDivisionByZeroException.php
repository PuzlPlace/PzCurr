<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Exception;

final class PzCurrDivisionByZeroException extends PzCurrException
{
    /**
     * Cria a exceção para uma divisão (ou módulo) por zero.
     */
    public static function forDivisor(string $operation): self
    {
        return new self(sprintf(
            'Division by zero is not allowed in operation "%s".',
            $operation
        ));
    }

    /**
     * Cria a exceção quando a soma dos ratios informados a allocate() é zero.
     */
    public static function zeroRatios(): self
    {
        return new self(
            'The sum of ratios passed to allocate() is zero; cannot distribute the amount.'
        );
    }
}
