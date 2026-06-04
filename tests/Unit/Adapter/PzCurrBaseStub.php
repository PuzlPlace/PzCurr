<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Adapter;

use Puzl\PzCurr\Adapter\PzCurrBase;
use Puzl\PzCurr\Contract\PzCurrInterface;
use Puzl\PzCurr\Currency\PzCurrCurrencyRegistry;
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;


/**
 * Stub concreto de PzCurrBase para uso exclusivo em testes.
 *
 * Implementa os métodos abstratos com comportamentos triviais/fake, permitindo
 * exercitar o estado fluente, copy() e validações de moeda sem depender de bc*.
 */
final class PzCurrBaseStub extends PzCurrBase
{
    public function of(string|int|float $amount, PzCurrCurrencyEnum|string|null $currency = null, ?int $scale = null): self
    {
        $this->currency = PzCurrCurrencyRegistry::of($this->resolveCurrencyCode($currency));

        if ($scale !== null) {
            $this->assertValidScale($scale);
        }

        $this->scale  = $scale ?? $this->currency->scale;
        $this->amount = (string) $amount;

        return $this;
    }

    public function ofMinor(int $minorAmount, PzCurrCurrencyEnum|string|null $currency = null): self
    {
        $this->currency = PzCurrCurrencyRegistry::of($this->resolveCurrencyCode($currency));
        $this->scale    = $this->currency->scale;
        $this->amount   = (string) $minorAmount;

        return $this;
    }

    public function zero(PzCurrCurrencyEnum|string|null $currency = null, ?int $scale = null): self
    {
        $this->currency = PzCurrCurrencyRegistry::of($this->resolveCurrencyCode($currency));

        if ($scale !== null) {
            $this->assertValidScale($scale);
        }

        $this->scale  = $scale ?? $this->currency->scale;
        $this->amount = '0';

        return $this;
    }

    public function add(PzCurrInterface|string|int|float ...$values): self
    {
        return $this;
    }

    public function subtract(PzCurrInterface|string|int|float ...$values): self
    {
        return $this;
    }

    public function multiply(string|int|float $factor, ?PzCurrRoundingModeEnum $mode = null): self
    {
        return $this;
    }

    public function divide(string|int|float $divisor, ?PzCurrRoundingModeEnum $mode = null): self
    {
        return $this;
    }

    public function mod(string|int|float $divisor): self
    {
        return $this;
    }

    public function absolute(): self
    {
        return $this;
    }

    public function negated(): self
    {
        return $this;
    }

    /** @param array<int|string, int|float|string> $ratios @return array<int|string, self> */
    public function allocate(array $ratios): array
    {
        return [];
    }

    /** @return array<int, self> */
    public function split(int $parts): array
    {
        return [];
    }

    public function ratioOf(PzCurrInterface $other): string
    {
        return '1';
    }

    public function withScale(int $scale, ?PzCurrRoundingModeEnum $mode = null): self
    {
        $this->scale = $scale;

        return $this;
    }

    public function compareTo(PzCurrInterface|string|int|float $other): int
    {
        return 0;
    }

    public function isEqualTo(PzCurrInterface|string|int|float $other): bool
    {
        return true;
    }

    public function isGreaterThan(PzCurrInterface|string|int|float $other): bool
    {
        return false;
    }

    public function isGreaterThanOrEqualTo(PzCurrInterface|string|int|float $other): bool
    {
        return false;
    }

    public function isLessThan(PzCurrInterface|string|int|float $other): bool
    {
        return false;
    }

    public function isLessThanOrEqualTo(PzCurrInterface|string|int|float $other): bool
    {
        return false;
    }

    public function isZero(): bool
    {
        return $this->amount === '0';
    }

    public function isPositive(): bool
    {
        return false;
    }

    public function isPositiveOrZero(): bool
    {
        return false;
    }

    public function isNegative(): bool
    {
        return false;
    }

    public function isNegativeOrZero(): bool
    {
        return false;
    }

    public function getSign(): int
    {
        return 0;
    }

    public function isSameValueAs(PzCurrInterface $other): bool
    {
        return $this->amount === $other->getAmount()
            && $this->getCurrency()->code === $other->getCurrency()->code;
    }

    public function getMinorAmount(): int
    {
        return (int) $this->amount;
    }

    public function format(?string $locale = null): string
    {
        return $this->amount;
    }

    /**
     * Expõe assertSameCurrency() para cobertura de teste.
     */
    public function testAssertSameCurrency(PzCurrInterface $other): void
    {
        $this->assertSameCurrency($other);
    }
}
