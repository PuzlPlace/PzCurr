<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Adapter\BcMath;

use Puzl\PzCurr\Adapter\PzCurrBase;
use Puzl\PzCurr\Contract\PzCurrInterface;
use Puzl\PzCurr\Currency\PzCurrCurrencyRegistry;
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Exception\PzCurrDivisionByZeroException;
use Puzl\PzCurr\Exception\PzCurrFloatNotAllowedException;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;
use Puzl\PzCurr\Exception\PzCurrMissingExtensionException;
use Puzl\PzCurr\Support\PzCurrAllocator;
use Puzl\PzCurr\Support\PzCurrFormatter;
use Puzl\PzCurr\Support\PzCurrRoundingHelper;

/**
 * BCMath adapter — all arithmetic is performed via bc* functions on string
 * representations, ensuring zero float conversions at every stage.
 *
 * workingScale() strategy: intermediate bc* calls use (currency scale + MARGIN)
 * extra digits of precision. The result is then reduced to the target scale via
 * PzCurrRoundingHelper as a single, explicit rounding step per operation.
 */
final class PzCurrBcMath extends PzCurrBase
{
    /** Extra digits kept during intermediate calculations to avoid precision loss. */
    private const WORKING_SCALE_MARGIN = 10;

    /** Accepts optional sign, integer digits, optional dot-separated decimal digits. */
    private const NUMERIC_PATTERN = '/^-?\d+(\.\d+)?$/';

    public function __construct(
        ?PzCurrRoundingModeEnum $defaultMode = null,
        private readonly bool $strictFloats = false,
    ) {
        if (!extension_loaded('bcmath')) {
            throw PzCurrMissingExtensionException::bcmath();
        }

        $this->roundingMode = $defaultMode ?? PzCurrRoundingModeEnum::HALF_UP;
    }

    // -------------------------------------------------------------------------
    // Working scale
    // -------------------------------------------------------------------------

    /**
     * Scale used for intermediate bc* operations.
     * Adds a fixed margin so that no precision is silently dropped before the
     * explicit rounding step at the end of each operation.
     */
    private function workingScale(): int
    {
        return $this->scale + self::WORKING_SCALE_MARGIN;
    }

    // -------------------------------------------------------------------------
    // Input validation
    // -------------------------------------------------------------------------

    /**
     * Validates that $value is a well-formed numeric string and returns it.
     *
     * @throws PzCurrInvalidAmountException for non-numeric input.
     */
    private function assertNumericString(string $value): string
    {
        if (!preg_match(self::NUMERIC_PATTERN, $value)) {
            throw PzCurrInvalidAmountException::forValue($value);
        }

        return $value;
    }

    /**
     * Normaliza um valor escalar (string|int|float) para string numérica validada.
     *
     * Float é tratado conforme o modo STRICT:
     *  - STRICT ativado  → lança PzCurrFloatNotAllowedException.
     *  - STRICT desativado (padrão) → converte para string da forma mais segura/determinística
     *    possível via floatToString(), arredondando para a escala atual da instância. Isso
     *    evita notação científica e dependência de `serialize_precision`, alinhado à recomendação
     *    de "nunca confiar no último dígito de um float".
     */
    private function scalarToString(string|int|float $value): string
    {
        if (is_float($value)) {
            if ($this->strictFloats) {
                throw PzCurrFloatNotAllowedException::forValue($value);
            }

            $value = $this->floatToString($value);
        }

        return $this->assertNumericString((string) $value);
    }

    /**
     * Converte um float para string decimal de forma determinística e sem notação científica.
     *
     * Usa number_format com a escala atual da instância e separador decimal '.' (locale-safe).
     * O float já chega com a imprecisão inerente do IEEE 754; arredondar para a escala do
     * objeto produz o resultado previsível e adequado ao domínio (a escala manda).
     */
    private function floatToString(float $value): string
    {
        return number_format($value, $this->scale, '.', '');
    }

    /**
     * Resolves a variadic operand to its string representation.
     *
     * When the operand is a PzCurrInterface instance, currency compatibility is
     * validated via assertSameCurrency() before extracting the amount string.
     * Plain string/int/float operands are normalized (float respeita o modo STRICT).
     */
    private function operandToString(PzCurrInterface|string|int|float $value): string
    {
        if ($value instanceof PzCurrInterface) {
            $this->assertSameCurrency($value);

            return $value->getAmount();
        }

        return $this->scalarToString($value);
    }

    /**
     * Guards against division/modulo by zero, raising a typed PzCurr exception
     * instead of leaking PHP's native DivisionByZeroError.
     *
     * @throws PzCurrDivisionByZeroException when $value is numerically zero.
     */
    private function assertNonZero(string $value, string $operation): void
    {
        if (bccomp($value, '0', $this->workingScale()) === 0) {
            throw PzCurrDivisionByZeroException::forDivisor($operation);
        }
    }

    // -------------------------------------------------------------------------
    // Creation — RF-01
    // -------------------------------------------------------------------------

    public function of(string|int|float $amount, PzCurrCurrencyEnum|string|null $currency = null, ?int $scale = null): self
    {
        $this->currency = PzCurrCurrencyRegistry::of($this->resolveCurrencyCode($currency));

        if ($scale !== null) {
            $this->assertValidScale($scale);
        }

        // Escala definida antes de normalizar o amount: floatToString() arredonda pela escala.
        $this->scale  = $scale ?? $this->currency->scale;
        $normalized   = $this->scalarToString($amount);
        $this->amount = PzCurrRoundingHelper::round($normalized, $this->scale, $this->roundingMode);

        return $this;
    }

    public function ofMinor(int $minorAmount, PzCurrCurrencyEnum|string|null $currency = null): self
    {
        $this->currency = PzCurrCurrencyRegistry::of($this->resolveCurrencyCode($currency));
        $this->scale    = $this->currency->scale;
        $divisor        = bcpow('10', (string) $this->scale);
        $this->amount   = bcdiv((string) $minorAmount, $divisor, $this->scale);

        return $this;
    }

    public function zero(PzCurrCurrencyEnum|string|null $currency = null, ?int $scale = null): self
    {
        return $this->of('0', $currency, $scale);
    }

    // -------------------------------------------------------------------------
    // Arithmetic — RF-02
    // -------------------------------------------------------------------------

    /**
     * Adds one or more values using bcadd, accumulating at working scale.
     * A single rounding step is applied after the full loop.
     */
    public function add(PzCurrInterface|string|int|float ...$values): self
    {
        foreach ($values as $v) {
            $operand      = $this->operandToString($v);
            $this->amount = bcadd($this->amount, $operand, $this->workingScale());
        }

        $this->amount = PzCurrRoundingHelper::round($this->amount, $this->scale, $this->roundingMode);

        return $this;
    }

    /**
     * Subtracts one or more values using bcsub, accumulating at working scale.
     * A single rounding step is applied after the full loop.
     */
    public function subtract(PzCurrInterface|string|int|float ...$values): self
    {
        foreach ($values as $v) {
            $operand      = $this->operandToString($v);
            $this->amount = bcsub($this->amount, $operand, $this->workingScale());
        }

        $this->amount = PzCurrRoundingHelper::round($this->amount, $this->scale, $this->roundingMode);

        return $this;
    }

    public function multiply(string|int|float $factor, ?PzCurrRoundingModeEnum $mode = null): self
    {
        $validated    = $this->scalarToString($factor);
        $this->amount = bcmul($this->amount, $validated, $this->workingScale());
        $this->amount = PzCurrRoundingHelper::round($this->amount, $this->scale, $mode ?? $this->roundingMode);

        return $this;
    }

    public function divide(string|int|float $divisor, ?PzCurrRoundingModeEnum $mode = null): self
    {
        $validated = $this->scalarToString($divisor);
        $this->assertNonZero($validated, 'divide');

        $this->amount = bcdiv($this->amount, $validated, $this->workingScale());
        $this->amount = PzCurrRoundingHelper::round($this->amount, $this->scale, $mode ?? $this->roundingMode);

        return $this;
    }

    public function mod(string|int|float $divisor): self
    {
        $validated = $this->scalarToString($divisor);
        $this->assertNonZero($validated, 'mod');

        $this->amount = bcmod($this->amount, $validated, $this->scale);

        return $this;
    }

    public function absolute(): self
    {
        if (bccomp($this->amount, '0', $this->scale) < 0) {
            $this->amount = substr($this->amount, 1);
        }

        return $this;
    }

    public function negated(): self
    {
        $this->amount = bcmul($this->amount, '-1', $this->scale);

        return $this;
    }

    /**
     * Returns the ratio of $this to $other as a high-precision decimal string.
     * Uses working scale to maximise available digits.
     */
    public function ratioOf(PzCurrInterface $other): string
    {
        $this->assertSameCurrency($other);
        $this->assertNonZero($other->getAmount(), 'ratioOf');

        return bcdiv($this->amount, $other->getAmount(), $this->workingScale());
    }

    // -------------------------------------------------------------------------
    // Scale / rounding — RF-03
    // -------------------------------------------------------------------------

    public function withScale(int $scale, ?PzCurrRoundingModeEnum $mode = null): self
    {
        $this->assertValidScale($scale);

        $this->amount = PzCurrRoundingHelper::round($this->amount, $scale, $mode ?? $this->roundingMode);
        $this->scale  = $scale;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Comparisons / sign — RF-04
    // -------------------------------------------------------------------------

    public function compareTo(PzCurrInterface|string|int|float $other): int
    {
        $operand = $this->operandToString($other);

        return bccomp($this->amount, $operand, $this->workingScale());
    }

    public function isEqualTo(PzCurrInterface|string|int|float $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function isGreaterThan(PzCurrInterface|string|int|float $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isGreaterThanOrEqualTo(PzCurrInterface|string|int|float $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function isLessThan(PzCurrInterface|string|int|float $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isLessThanOrEqualTo(PzCurrInterface|string|int|float $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', $this->scale) === 0;
    }

    public function isPositive(): bool
    {
        return $this->getSign() > 0;
    }

    public function isPositiveOrZero(): bool
    {
        return $this->getSign() >= 0;
    }

    public function isNegative(): bool
    {
        return $this->getSign() < 0;
    }

    public function isNegativeOrZero(): bool
    {
        return $this->getSign() <= 0;
    }

    public function getSign(): int
    {
        return bccomp($this->amount, '0', $this->scale);
    }

    /**
     * Compares value and currency without throwing PzCurrencyMismatchException.
     * Returns false when currencies differ.
     */
    public function isSameValueAs(PzCurrInterface $other): bool
    {
        return $other->getCurrency()->code === $this->getCurrency()->code
            && bccomp($this->amount, $other->getAmount(), $this->scale) === 0;
    }

    // -------------------------------------------------------------------------
    // Extraction — partial implementation (full output in Task 6.0)
    // -------------------------------------------------------------------------

    public function getMinorAmount(): int
    {
        $multiplier = bcpow('10', (string) $this->scale);
        $minor      = bcmul($this->amount, $multiplier, 0);

        // Guard against silent int overflow: bcmul keeps arbitrary precision as a
        // string, but casting beyond PHP_INT_MAX/MIN would yield a garbage value.
        if (bccomp($minor, (string) PHP_INT_MAX, 0) > 0
            || bccomp($minor, (string) PHP_INT_MIN, 0) < 0) {
            throw PzCurrInvalidAmountException::minorAmountOverflow($minor);
        }

        return (int) $minor;
    }

    // -------------------------------------------------------------------------
    // Not yet implemented — deferred to future tasks
    // -------------------------------------------------------------------------

    /** @param array<int|string, int|float|string> $ratios @return array<int|string, self> */
    public function allocate(array $ratios): array
    {
        $parts = PzCurrAllocator::allocate($this->amount, $ratios, $this->scale);

        return array_map(fn (string $p) => $this->copy()->setAmountInternal($p), $parts);
    }

    /** @return array<int, self> */
    public function split(int $parts): array
    {
        $values = PzCurrAllocator::split($this->amount, $parts, $this->scale);

        return array_map(fn (string $p) => $this->copy()->setAmountInternal($p), $values);
    }

    /**
     * Formats the monetary value for human display, delegating to PzCurrFormatter.
     *
     * When $locale is provided and the intl extension is available, locale-aware
     * formatting is used. Otherwise, manual mode applies using separators and symbol
     * position read from `config('pzcurr.formatting')` when available, falling back
     * to pt-BR defaults (e.g. 'R$ 1.234,56').
     */
    public function format(?string $locale = null): string
    {
        return self::buildFormatter()->format($this->amount, $this->getCurrency(), $locale);
    }

    /**
     * Builds a PzCurrFormatter using formatting options from config when available.
     * Falls back gracefully to pt-BR defaults outside of a Laravel context.
     */
    private static function buildFormatter(): PzCurrFormatter
    {
        if (!function_exists('config')) {
            return new PzCurrFormatter();
        }

        /** @var mixed $cfg */
        $cfg = config('pzcurr.formatting');

        if (!is_array($cfg)) {
            return new PzCurrFormatter();
        }

        return new PzCurrFormatter(
            thousandsSeparator: is_string($cfg['thousands_separator'] ?? null) ? $cfg['thousands_separator'] : '.',
            decimalSeparator:   is_string($cfg['decimal_separator']   ?? null) ? $cfg['decimal_separator']   : ',',
            symbolBefore:       isset($cfg['symbol_before']) ? (bool) $cfg['symbol_before'] : true,
        );
    }
}
