<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Currency;

/** Value object imutável com metadados de uma moeda (código, escala, símbolo). */
final class PzCurrCurrency
{
    public function __construct(
        public readonly string $code,
        public readonly int $numericCode,
        public readonly int $scale,
        public readonly string $symbol,
    ) {}
}
