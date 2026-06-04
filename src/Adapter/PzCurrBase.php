<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Adapter;

use Puzl\PzCurr\Contract\PzCurrInterface;
use Puzl\PzCurr\Currency\PzCurrCurrency;
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Exception\PzCurrencyMismatchException;
use Puzl\PzCurr\Exception\PzCurrInvalidScaleException;

/**
 * Base abstrata do PzCurr.
 *
 * Responsabilidades desta classe:
 * - Manter o estado fluente (amount, currency, scale, roundingMode).
 * - Implementar os métodos que NÃO dependem de cálculo bc* (getters, copy, serialização).
 * - Declarar como abstract todos os métodos que exigem bc* (delegados ao adapter concreto).
 * - Validar compatibilidade de moeda entre operandos (RN-02).
 *
 * Restrição arquitetural (RNF-02): esta classe NÃO deve importar nem chamar nenhuma
 * função bc* (bcadd, bcsub, bcmul, bcdiv, bcmod, bccomp, bcscale, bcpow, bcsqrt).
 * O cálculo concreto vive exclusivamente no adapter (ex.: PzCurrBcMath).
 */
abstract class PzCurrBase implements PzCurrInterface
{
    protected string $amount = '0';

    protected ?PzCurrCurrency $currency = null;

    protected int $scale = 0;

    protected PzCurrRoundingModeEnum $roundingMode;

    public function __construct()
    {
        $this->roundingMode = PzCurrRoundingModeEnum::HALF_UP;
    }

    // -------------------------------------------------------------------------
    // Métodos concretos — sem dependência de bc*
    // -------------------------------------------------------------------------

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function toDecimal(): string
    {
        return $this->amount;
    }

    /**
     * Converte o valor decimal para float.
     *
     * ATENÇÃO: conversão apenas para exibição/interoperabilidade. float (IEEE 754)
     * pode perder precisão; nunca use o resultado em cálculo, comparação ou
     * persistência. Use getAmount()/toDecimal() (string) ou getMinorAmount() (int)
     * para essas finalidades.
     */
    public function toFloat(): float
    {
        return (float) $this->amount;
    }

    public function getCurrency(): PzCurrCurrency
    {
        if ($this->currency === null) {
            throw new \LogicException('Currency not initialized. Call of() first.');
        }

        return $this->currency;
    }

    public function getScale(): int
    {
        return $this->scale;
    }

    public function withRoundingMode(PzCurrRoundingModeEnum $mode): self
    {
        $this->roundingMode = $mode;

        return $this;
    }

    public function copy(): self
    {
        return clone $this;
    }

    /**
     * Writes a pre-computed amount string directly, bypassing validation and rounding.
     * Intended for internal use by allocate/split implementations that already hold
     * exact minor-unit results.
     */
    protected function setAmountInternal(string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return array{amount: string, currency: string, scale: int}
     */
    public function toArray(): array
    {
        return [
            'amount'   => $this->amount,
            'currency' => $this->getCurrency()->code,
            'scale'    => $this->scale,
        ];
    }

    /**
     * @return array{amount: string, currency: string, scale: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // -------------------------------------------------------------------------
    // Validação de moeda (RN-02)
    // -------------------------------------------------------------------------

    /**
     * Garante que $other compartilha a mesma moeda que $this.
     *
     * @throws PzCurrencyMismatchException quando as moedas diferem.
     */
    protected function assertSameCurrency(PzCurrInterface $other): void
    {
        if ($other->getCurrency()->code !== $this->getCurrency()->code) {
            throw PzCurrencyMismatchException::between(
                $this->getCurrency()->code,
                $other->getCurrency()->code,
            );
        }
    }

    // -------------------------------------------------------------------------
    // Normalização de moeda / escala
    // -------------------------------------------------------------------------

    /**
     * Resolve o parâmetro de moeda para o código ISO em maiúsculas.
     *
     * Aceita o enum PzCurrCurrencyEnum (recomendado), uma string (inclusive moedas
     * customizadas fora do enum) ou null (usa a moeda padrão definida no enum).
     */
    protected function resolveCurrencyCode(PzCurrCurrencyEnum|string|null $currency): string
    {
        if ($currency === null) {
            return PzCurrCurrencyEnum::default()->value;
        }

        if ($currency instanceof PzCurrCurrencyEnum) {
            return $currency->value;
        }

        return strtoupper($currency);
    }

    /**
     * Garante que a escala informada é válida (>= 0).
     *
     * @throws PzCurrInvalidScaleException quando a escala é negativa.
     */
    protected function assertValidScale(int $scale): void
    {
        if ($scale < 0) {
            throw PzCurrInvalidScaleException::negative($scale);
        }
    }

    // -------------------------------------------------------------------------
    // Métodos abstratos — delegados ao adapter concreto (requerem bc*)
    // -------------------------------------------------------------------------

    abstract public function of(string|int|float $amount, PzCurrCurrencyEnum|string|null $currency = null, ?int $scale = null): self;

    abstract public function ofMinor(int $minorAmount, PzCurrCurrencyEnum|string|null $currency = null): self;

    abstract public function zero(PzCurrCurrencyEnum|string|null $currency = null, ?int $scale = null): self;

    abstract public function add(PzCurrInterface|string|int|float ...$values): self;

    abstract public function subtract(PzCurrInterface|string|int|float ...$values): self;

    abstract public function multiply(string|int|float $factor, ?PzCurrRoundingModeEnum $mode = null): self;

    abstract public function divide(string|int|float $divisor, ?PzCurrRoundingModeEnum $mode = null): self;

    abstract public function mod(string|int|float $divisor): self;

    abstract public function absolute(): self;

    abstract public function negated(): self;

    /** @param array<int|string, int|float|string> $ratios @return array<int|string, self> */
    abstract public function allocate(array $ratios): array;

    /** @return array<int, self> */
    abstract public function split(int $parts): array;

    abstract public function ratioOf(PzCurrInterface $other): string;

    abstract public function withScale(int $scale, ?PzCurrRoundingModeEnum $mode = null): self;

    abstract public function compareTo(PzCurrInterface|string|int|float $other): int;

    abstract public function isEqualTo(PzCurrInterface|string|int|float $other): bool;

    abstract public function isGreaterThan(PzCurrInterface|string|int|float $other): bool;

    abstract public function isGreaterThanOrEqualTo(PzCurrInterface|string|int|float $other): bool;

    abstract public function isLessThan(PzCurrInterface|string|int|float $other): bool;

    abstract public function isLessThanOrEqualTo(PzCurrInterface|string|int|float $other): bool;

    abstract public function isZero(): bool;

    abstract public function isPositive(): bool;

    abstract public function isPositiveOrZero(): bool;

    abstract public function isNegative(): bool;

    abstract public function isNegativeOrZero(): bool;

    abstract public function getSign(): int;

    abstract public function isSameValueAs(PzCurrInterface $other): bool;

    abstract public function getMinorAmount(): int;

    abstract public function format(?string $locale = null): string;
}
