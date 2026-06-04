<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Exception;

final class PzCurrInvalidScaleException extends PzCurrException
{
    /**
     * Cria a exceção quando a escala informada é negativa.
     */
    public static function negative(int $scale): self
    {
        return new self(sprintf(
            'Invalid scale: %d. Scale (number of decimal places) must be greater than or equal to 0.',
            $scale
        ));
    }
}
