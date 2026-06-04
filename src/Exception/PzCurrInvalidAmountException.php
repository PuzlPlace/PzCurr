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
            'Valor monetário inválido: "%s". Informe uma string numérica válida (ex.: "19.90").',
            $value
        ));
    }

    /**
     * Cria a exceção quando o valor em unidades menores excede o intervalo seguro de int do PHP.
     */
    public static function minorAmountOverflow(string $minorAmount): self
    {
        return new self(sprintf(
            'O valor em unidades menores ("%s") excede o intervalo seguro de inteiros do PHP. '
            . 'Use getAmount() para obter a representação decimal completa sem perda de precisão.',
            $minorAmount
        ));
    }
}
