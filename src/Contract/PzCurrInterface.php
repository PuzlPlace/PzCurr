<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Contract;

use Puzl\PzCurr\Currency\PzCurrCurrency;
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Enum\PzCurrLocaleEnum;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;

/**
 * Contrato público estável do pacote PzCurr.
 *
 * Consumidores da biblioteca dependem APENAS desta interface, nunca do adapter concreto.
 * O modelo é mutável e fluente: métodos de operação alteram o objeto e retornam $this.
 * Use copy() para obter um snapshot independente antes de aplicar operações destrutivas.
 */
interface PzCurrInterface extends \JsonSerializable
{
    // -------------------------------------------------------------------------
    // Criação — RF-01
    // -------------------------------------------------------------------------

    /**
     * Inicializa o valor a partir de uma string ou inteiro decimal.
     *
     * @param  PzCurrCurrencyEnum|string|null  $currency  Moeda (enum recomendado);
     *         quando null, usa a moeda padrão (BRL).
     * @param  int|null  $scale  Escala (casas decimais) da instância. Quando null, usa
     *         a escala da moeda; quando informado, prevalece sobre a escala da moeda.
     */
    public function of(string|int|float $amount, PzCurrCurrencyEnum|string|null $currency = null, ?int $scale = null): self;

    /**
     * Inicializa a partir do valor em unidade menor (centavos).
     *
     * @param  PzCurrCurrencyEnum|string|null  $currency  Moeda (enum recomendado);
     *         quando null, usa a moeda padrão (BRL).
     */
    public function ofMinor(int $minorAmount, PzCurrCurrencyEnum|string|null $currency = null): self;

    /**
     * Inicializa com valor zero na moeda informada.
     *
     * @param  PzCurrCurrencyEnum|string|null  $currency  Moeda (enum recomendado);
     *         quando null, usa a moeda padrão (BRL).
     * @param  int|null  $scale  Escala opcional da instância (ver of()).
     */
    public function zero(PzCurrCurrencyEnum|string|null $currency = null, ?int $scale = null): self;

    // -------------------------------------------------------------------------
    // Aritmética fluente — RF-02
    // -------------------------------------------------------------------------

    /** Soma um ou mais valores ao objeto atual (mutável, retorna $this). */
    public function add(self|string|int|float ...$values): self;

    /** Subtrai um ou mais valores do objeto atual (mutável, retorna $this). */
    public function subtract(self|string|int|float ...$values): self;

    /** Multiplica pelo fator informado, com modo de arredondamento opcional. */
    public function multiply(string|int|float $factor, ?PzCurrRoundingModeEnum $mode = null): self;

    /** Divide pelo divisor informado, com modo de arredondamento opcional. */
    public function divide(string|int|float $divisor, ?PzCurrRoundingModeEnum $mode = null): self;

    /** Retorna o resto da divisão pelo divisor (mutável, retorna $this). */
    public function mod(string|int|float $divisor): self;

    /** Transforma em valor absoluto (mutável, retorna $this). */
    public function absolute(): self;

    /** Nega o sinal do valor (mutável, retorna $this). */
    public function negated(): self;

    /**
     * Distribui o valor entre os ratios informados, garantindo conservação de centavos.
     *
     * @param  array<int|string, int|float|string> $ratios
     * @return array<int|string, self>
     */
    public function allocate(array $ratios): array;

    /**
     * Divide o valor em partes iguais, garantindo conservação de centavos.
     *
     * @return array<int, self>
     */
    public function split(int $parts): array;

    /** Retorna a proporção deste valor em relação ao outro como string decimal. */
    public function ratioOf(self $other): string;

    // -------------------------------------------------------------------------
    // Escala / arredondamento — RF-03
    // -------------------------------------------------------------------------

    /** Altera a escala (casas decimais) com arredondamento opcional. */
    public function withScale(int $scale, ?PzCurrRoundingModeEnum $mode = null): self;

    /** Define o modo de arredondamento padrão para operações subsequentes. */
    public function withRoundingMode(PzCurrRoundingModeEnum $mode): self;

    // -------------------------------------------------------------------------
    // Comparações / sinal — RF-04
    // -------------------------------------------------------------------------

    /** Compara com outro valor; retorna -1, 0 ou 1. */
    public function compareTo(self|string|int|float $other): int;

    public function isEqualTo(self|string|int|float $other): bool;

    public function isGreaterThan(self|string|int|float $other): bool;

    public function isGreaterThanOrEqualTo(self|string|int|float $other): bool;

    public function isLessThan(self|string|int|float $other): bool;

    public function isLessThanOrEqualTo(self|string|int|float $other): bool;

    public function isZero(): bool;

    public function isPositive(): bool;

    public function isPositiveOrZero(): bool;

    public function isNegative(): bool;

    public function isNegativeOrZero(): bool;

    /** Retorna -1, 0 ou 1 representando o sinal do valor. */
    public function getSign(): int;

    /**
     * Compara valor e moeda sem lançar PzCurrencyMismatchException.
     * Retorna false quando as moedas diferem.
     */
    public function isSameValueAs(self $other): bool;

    // -------------------------------------------------------------------------
    // Extração / formatação — RF-06
    // -------------------------------------------------------------------------

    /** Retorna o valor como string decimal (ex.: '19.90'). */
    public function getAmount(): string;

    /** Alias semântico de getAmount(). */
    public function toDecimal(): string;

    /**
     * Converte o valor decimal para float.
     *
     * ATENÇÃO: float (IEEE 754) não representa exatamente a maioria dos decimais.
     * Use apenas para exibição ou interoperabilidade com APIs que exigem `number`.
     * NUNCA use o resultado em cálculo, comparação ou persistência — isso reintroduz
     * erros de precisão. Para essas finalidades use getAmount()/toDecimal() (string)
     * ou getMinorAmount() (int). Valores com muitas casas decimais ou muito grandes
     * podem perder dígitos já na conversão.
     */
    public function toFloat(): float;

    /** Retorna o valor em unidade menor (centavos) como inteiro. */
    public function getMinorAmount(): int;

    public function getCurrency(): PzCurrCurrency;

    public function getScale(): int;

    /** Formata o valor para exibição humana, com locale opcional. */
    public function format(?PzCurrLocaleEnum $locale = null): string;

    // -------------------------------------------------------------------------
    // Serialização — RF-07
    // -------------------------------------------------------------------------

    /**
     * @return array{amount: string, currency: string, scale: int}
     */
    public function toArray(): array;

    /** @return array{amount: string, currency: string, scale: int} */
    public function jsonSerialize(): array;

    // -------------------------------------------------------------------------
    // Snapshot — mutabilidade controlada
    // -------------------------------------------------------------------------

    /** Retorna uma cópia independente do objeto atual (clone). */
    public function copy(): self;
}
