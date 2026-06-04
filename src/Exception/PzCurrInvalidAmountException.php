<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Exception;

final class PzCurrInvalidAmountException extends PzCurrException
{
    /**
     * Cria a exceção para um valor monetário inválido.
     */
    public static function forValue(string $value): self
    {
        return new self(sprintf(
            'Invalid monetary amount: "%s". Provide a valid numeric string (e.g. "19.90").',
            $value
        ));
    }

    /**
     * Cria a exceção quando o valor em unidades menores excede o intervalo seguro de int do PHP.
     */
    public static function minorAmountOverflow(string $minorAmount): self
    {
        return new self(sprintf(
            'Minor unit amount ("%s") exceeds PHP\'s safe integer range. '
            . 'Use getAmount() for the full decimal representation without precision loss.',
            $minorAmount
        ));
    }
}
