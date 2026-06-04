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
            'The PHP "bcmath" extension is required for PzCurr but is not available in this environment. '
            . 'Enable it in php.ini or install the corresponding package.'
        );
    }
}
