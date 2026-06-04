<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Support;

use Puzl\PzCurr\Currency\PzCurrCurrency;

/**
 * Formata valor monetário para exibição humana.
 *
 * ## Modo manual (padrão / pt-BR)
 * Usa separadores configuráveis e símbolo antes do número (ex.: 'R$ 1.234,56').
 * Sem conversão para float; a string amount é parseada diretamente.
 *
 * ## Modo Intl (opcional)
 * Com $locale e extensão intl, delega a NumberFormatter::formatCurrency.
 *
 * ⚠ **Importante (RN-01)**: formatCurrency exige float PHP — apenas para exibição.
 * A representação canônica permanece a string do adapter. Sem intl, cai no modo manual.
 */
final class PzCurrFormatter
{
    /**
     * @param string $thousandsSeparator Separador a cada 3 dígitos inteiros (padrão '.').
     * @param string $decimalSeparator   Separador entre parte inteira e decimal (padrão ',').
     * @param bool   $symbolBefore       Quando true, símbolo precede o número.
     * @param bool|null $intlAvailable   Sobrescreve detecção de intl (null = auto).
     */
    public function __construct(
        private readonly string $thousandsSeparator = '.',
        private readonly string $decimalSeparator   = ',',
        private readonly bool   $symbolBefore       = true,
        private readonly ?bool  $intlAvailable      = null,
    ) {}

    /**
     * Formata $amount (string decimal, ex.: '1234.56') para exibição.
     *
     * @param string|null $locale Tag BCP-47 (ex.: 'pt_BR'); com intl, formata por locale.
     */
    public function format(string $amount, PzCurrCurrency $currency, ?string $locale = null): string
    {
        $useIntl = $this->intlAvailable ?? extension_loaded('intl');

        if ($locale !== null && $useIntl) {
            return $this->formatWithIntl($amount, $currency, $locale);
        }

        return $this->formatManual($amount, $currency);
    }

    // -------------------------------------------------------------------------
    // Formatação manual
    // -------------------------------------------------------------------------

    /** Monta string legível com separadores e símbolo, sem conversão float. */
    private function formatManual(string $amount, PzCurrCurrency $currency): string
    {
        $negative = str_starts_with($amount, '-');
        $abs      = $negative ? substr($amount, 1) : $amount;

        $dotPos  = strpos($abs, '.');
        $intPart = $dotPos !== false ? substr($abs, 0, $dotPos) : $abs;
        $decPart = $dotPos !== false ? substr($abs, $dotPos + 1) : '';

        $intFormatted = $this->applyThousandsSeparator($intPart);

        $number = $intFormatted;
        if ($decPart !== '') {
            $number .= $this->decimalSeparator . $decPart;
        }

        if ($negative) {
            $number = '-' . $number;
        }

        return $this->symbolBefore
            ? $currency->symbol . ' ' . $number
            : $number . ' ' . $currency->symbol;
    }

    /** Insere separador de milhares a cada 3 dígitos da direita para a esquerda. */
    private function applyThousandsSeparator(string $intPart): string
    {
        if (strlen($intPart) <= 3) {
            return $intPart;
        }

        $chunks = [];
        $len    = strlen($intPart);

        for ($i = $len; $i > 0; $i -= 3) {
            $start    = max(0, $i - 3);
            $chunks[] = substr($intPart, $start, $i - $start);
        }

        return implode($this->thousandsSeparator, array_reverse($chunks));
    }

    // -------------------------------------------------------------------------
    // Formatação Intl
    // -------------------------------------------------------------------------

    /**
     * Delega formatação ao NumberFormatter.
     *
     * O cast para float é apenas para a API intl (exibição). Em falha, usa modo manual.
     */
    private function formatWithIntl(string $amount, PzCurrCurrency $currency, string $locale): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $result    = $formatter->formatCurrency((float) $amount, $currency->code);

        if ($result === false) {
            return $this->formatManual($amount, $currency);
        }

        return $result;
    }
}
