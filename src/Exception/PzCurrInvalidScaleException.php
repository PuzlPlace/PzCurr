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
            'Escala inválida: %d. A escala (número de casas decimais) deve ser maior ou igual a 0.',
            $scale
        ));
    }
}
