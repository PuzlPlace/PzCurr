<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Currency;

final class PzCurrCurrency
{
    public function __construct(
        public readonly string $code,
        public readonly int $numericCode,
        public readonly int $scale,
        public readonly string $symbol,
    ) {}
}
