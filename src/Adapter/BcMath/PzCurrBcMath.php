<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Adapter\BcMath;

use Puzl\PzCurr\Adapter\PzCurrBase;
use Puzl\PzCurr\Contract\PzCurrInterface;
use Puzl\PzCurr\Currency\PzCurrCurrencyRegistry;
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Enum\PzCurrLocaleEnum;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Exception\PzCurrDivisionByZeroException;
use Puzl\PzCurr\Exception\PzCurrFloatNotAllowedException;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;
use Puzl\PzCurr\Exception\PzCurrMissingExtensionException;
use Puzl\PzCurr\Support\PzCurrAllocator;
use Puzl\PzCurr\Support\PzCurrFormatter;
use Puzl\PzCurr\Support\PzCurrRoundingHelper;

/**
 * Adapter BCMath — toda aritmética usa funções bc* sobre strings,
 * sem conversão para float em nenhuma etapa.
 *
 * Estratégia workingScale(): operações intermediárias usam (escala da moeda + MARGEM)
 * dígitos extras. O resultado é reduzido à escala alvo via PzCurrRoundingHelper
 * em um único arredondamento explícito por operação.
 */
final class PzCurrBcMath extends PzCurrBase
{
    /** Dígitos extras nas operações intermediárias para evitar perda de precisão. */
    private const WORKING_SCALE_MARGIN = 10;

    /** Aceita sinal opcional, dígitos inteiros e decimais separados por ponto. */
    private const NUMERIC_PATTERN = '/^-?\d+(\.\d+)?$/';

    /** @throws PzCurrMissingExtensionException quando bcmath não está carregada. */
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
    // Escala de trabalho
    // -------------------------------------------------------------------------

    /** Escala usada nas operações bc* intermediárias (escala atual + margem). */
    private function workingScale(): int
    {
        return $this->scale + self::WORKING_SCALE_MARGIN;
    }

    // -------------------------------------------------------------------------
    // Validação de entrada
    // -------------------------------------------------------------------------

    /**
     * Valida string numérica bem formada.
     *
     * @throws PzCurrInvalidAmountException para entrada não numérica.
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
     * Converte float para string decimal determinística, sem notação científica.
     *
     * Usa number_format com a escala atual e separador '.' (independente de locale).
     */
    private function floatToString(float $value): string
    {
        return number_format($value, $this->scale, '.', '');
    }

    /**
     * Resolve operando variádico para string.
     *
     * Instâncias PzCurrInterface passam por assertSameCurrency() antes da extração.
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
     * Impede divisão/módulo por zero com exceção tipada do pacote.
     *
     * @throws PzCurrDivisionByZeroException quando $value é numericamente zero.
     */
    private function assertNonZero(string $value, string $operation): void
    {
        if (bccomp($value, '0', $this->workingScale()) === 0) {
            throw PzCurrDivisionByZeroException::forDivisor($operation);
        }
    }

    // -------------------------------------------------------------------------
    // Criação — RF-01
    // -------------------------------------------------------------------------

    /** Inicializa valor a partir de amount decimal, moeda e escala opcionais. */
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

    /** Inicializa a partir do valor em unidade menor (centavos). */
    public function ofMinor(int $minorAmount, PzCurrCurrencyEnum|string|null $currency = null): self
    {
        $this->currency = PzCurrCurrencyRegistry::of($this->resolveCurrencyCode($currency));
        $this->scale    = $this->currency->scale;
        $divisor        = bcpow('10', (string) $this->scale);
        $this->amount   = bcdiv((string) $minorAmount, $divisor, $this->scale);

        return $this;
    }

    /** Inicializa com zero na moeda informada. */
    public function zero(PzCurrCurrencyEnum|string|null $currency = null, ?int $scale = null): self
    {
        return $this->of('0', $currency, $scale);
    }

    // -------------------------------------------------------------------------
    // Aritmética — RF-02
    // -------------------------------------------------------------------------

    /** Soma valores via bcadd na escala de trabalho; arredonda ao final. */
    public function add(PzCurrInterface|string|int|float ...$values): self
    {
        foreach ($values as $v) {
            $operand      = $this->operandToString($v);
            $this->amount = bcadd($this->amount, $operand, $this->workingScale());
        }

        $this->amount = PzCurrRoundingHelper::round($this->amount, $this->scale, $this->roundingMode);

        return $this;
    }

    /** Subtrai valores via bcsub na escala de trabalho; arredonda ao final. */
    public function subtract(PzCurrInterface|string|int|float ...$values): self
    {
        foreach ($values as $v) {
            $operand      = $this->operandToString($v);
            $this->amount = bcsub($this->amount, $operand, $this->workingScale());
        }

        $this->amount = PzCurrRoundingHelper::round($this->amount, $this->scale, $this->roundingMode);

        return $this;
    }

    /** Multiplica pelo fator informado, com arredondamento opcional. */
    public function multiply(string|int|float $factor, ?PzCurrRoundingModeEnum $mode = null): self
    {
        $validated    = $this->scalarToString($factor);
        $this->amount = bcmul($this->amount, $validated, $this->workingScale());
        $this->amount = PzCurrRoundingHelper::round($this->amount, $this->scale, $mode ?? $this->roundingMode);

        return $this;
    }

    /** Divide pelo divisor informado, com arredondamento opcional. */
    public function divide(string|int|float $divisor, ?PzCurrRoundingModeEnum $mode = null): self
    {
        $validated = $this->scalarToString($divisor);
        $this->assertNonZero($validated, 'divide');

        $this->amount = bcdiv($this->amount, $validated, $this->workingScale());
        $this->amount = PzCurrRoundingHelper::round($this->amount, $this->scale, $mode ?? $this->roundingMode);

        return $this;
    }

    /** Retorna o resto da divisão pelo divisor. */
    public function mod(string|int|float $divisor): self
    {
        $validated = $this->scalarToString($divisor);
        $this->assertNonZero($validated, 'mod');

        $this->amount = bcmod($this->amount, $validated, $this->scale);

        return $this;
    }

    /** Transforma o valor em absoluto. */
    public function absolute(): self
    {
        if (bccomp($this->amount, '0', $this->scale) < 0) {
            $this->amount = substr($this->amount, 1);
        }

        return $this;
    }

    /** Nega o sinal do valor. */
    public function negated(): self
    {
        $this->amount = bcmul($this->amount, '-1', $this->scale);

        return $this;
    }

    /** Retorna a proporção deste valor em relação a $other como string decimal. */
    public function ratioOf(PzCurrInterface $other): string
    {
        $this->assertSameCurrency($other);
        $this->assertNonZero($other->getAmount(), 'ratioOf');

        return bcdiv($this->amount, $other->getAmount(), $this->workingScale());
    }

    // -------------------------------------------------------------------------
    // Escala / arredondamento — RF-03
    // -------------------------------------------------------------------------

    /** Altera a escala com arredondamento opcional. */
    public function withScale(int $scale, ?PzCurrRoundingModeEnum $mode = null): self
    {
        $this->assertValidScale($scale);

        $this->amount = PzCurrRoundingHelper::round($this->amount, $scale, $mode ?? $this->roundingMode);
        $this->scale  = $scale;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Comparações / sinal — RF-04
    // -------------------------------------------------------------------------

    /** Compara com outro valor; retorna -1, 0 ou 1. */
    public function compareTo(PzCurrInterface|string|int|float $other): int
    {
        $operand = $this->operandToString($other);

        return bccomp($this->amount, $operand, $this->workingScale());
    }

    /** Verifica igualdade numérica. */
    public function isEqualTo(PzCurrInterface|string|int|float $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    /** Verifica se é maior que o outro valor. */
    public function isGreaterThan(PzCurrInterface|string|int|float $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /** Verifica se é maior ou igual ao outro valor. */
    public function isGreaterThanOrEqualTo(PzCurrInterface|string|int|float $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    /** Verifica se é menor que o outro valor. */
    public function isLessThan(PzCurrInterface|string|int|float $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    /** Verifica se é menor ou igual ao outro valor. */
    public function isLessThanOrEqualTo(PzCurrInterface|string|int|float $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    /** Verifica se o valor é zero. */
    public function isZero(): bool
    {
        return bccomp($this->amount, '0', $this->scale) === 0;
    }

    /** Verifica se o valor é estritamente positivo. */
    public function isPositive(): bool
    {
        return $this->getSign() > 0;
    }

    /** Verifica se o valor é positivo ou zero. */
    public function isPositiveOrZero(): bool
    {
        return $this->getSign() >= 0;
    }

    /** Verifica se o valor é estritamente negativo. */
    public function isNegative(): bool
    {
        return $this->getSign() < 0;
    }

    /** Verifica se o valor é negativo ou zero. */
    public function isNegativeOrZero(): bool
    {
        return $this->getSign() <= 0;
    }

    /** Retorna -1, 0 ou 1 representando o sinal do valor. */
    public function getSign(): int
    {
        return bccomp($this->amount, '0', $this->scale);
    }

    /**
     * Compara valor e moeda sem lançar PzCurrencyMismatchException.
     * Retorna false quando as moedas diferem.
     */
    public function isSameValueAs(PzCurrInterface $other): bool
    {
        return $other->getCurrency()->code === $this->getCurrency()->code
            && bccomp($this->amount, $other->getAmount(), $this->scale) === 0;
    }

    // -------------------------------------------------------------------------
    // Extração — RF-06
    // -------------------------------------------------------------------------

    /** Retorna o valor em unidade menor (centavos) como inteiro. */
    public function getMinorAmount(): int
    {
        $multiplier = bcpow('10', (string) $this->scale);
        $minor      = bcmul($this->amount, $multiplier, 0);

        // Evita overflow silencioso ao converter string grande para int.
        if (bccomp($minor, (string) PHP_INT_MAX, 0) > 0
            || bccomp($minor, (string) PHP_INT_MIN, 0) < 0) {
            throw PzCurrInvalidAmountException::minorAmountOverflow($minor);
        }

        return (int) $minor;
    }

    // -------------------------------------------------------------------------
    // Distribuição / formatação
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
     * Formata o valor para exibição humana via PzCurrFormatter.
     *
     * Com $locale e extensão intl, usa formatação por locale; caso contrário,
     * aplica separadores e símbolo de config('pzcurr.formatting') ou padrão pt-BR.
     */
    public function format(?PzCurrLocaleEnum $locale = null): string
    {
        return self::buildFormatter()->format($this->amount, $this->getCurrency(), $locale);
    }

    /** Monta PzCurrFormatter a partir de config ou defaults pt-BR. */
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
