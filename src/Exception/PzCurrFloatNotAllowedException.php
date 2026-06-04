<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Exception;

final class PzCurrFloatNotAllowedException extends PzCurrException
{
    /**
     * Criada quando um valor float é informado com o modo STRICT ativado.
     */
    public static function forValue(float $value): self
    {
        return new self(sprintf(
            'Valor float não é permitido no modo STRICT: %s. Float (IEEE 754) pode perder '
            . 'precisão; informe uma string decimal (ex.: "19.90") ou um int. '
            . 'Desative o modo STRICT (config pzcurr.strict_floats = false) para aceitar floats.',
            var_export($value, true)
        ));
    }
}
