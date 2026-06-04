<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Exception;

final class PzCurrMissingExtensionException extends PzCurrException
{
    /**
     * Cria a exceção para a extensão bcmath ausente no ambiente PHP.
     */
    public static function bcmath(): self
    {
        return new self(
            'A extensão PHP "bcmath" é obrigatória para o PzCurr mas não está disponível neste ambiente. '
            . 'Habilite-a no php.ini ou instale o pacote correspondente.'
        );
    }
}
