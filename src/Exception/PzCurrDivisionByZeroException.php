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
            'Divisão por zero não é permitida na operação "%s".',
            $operation
        ));
    }

    /**
     * Cria a exceção quando a soma dos ratios informados a allocate() é zero.
     */
    public static function zeroRatios(): self
    {
        return new self(
            'A soma dos ratios informados a allocate() é zero; não é possível distribuir o valor.'
        );
    }
}
