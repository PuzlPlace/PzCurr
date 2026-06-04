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
            'Float values are not allowed in STRICT mode: %s. Float (IEEE 754) may lose '
            . 'precision; use a decimal string (e.g. "19.90") or an int. '
            . 'Disable STRICT mode (config pzcurr.strict_floats = false) to accept floats.',
            var_export($value, true)
        ));
    }
}
