<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Support;

use Puzl\PzCurr\Currency\PzCurrCurrency;

/**
 * Formats a monetary amount for human display.
 *
 * ## Manual mode (default / pt-BR)
 * Uses configurable thousands separator (default '.'), decimal separator (default ','),
 * and places the currency symbol before the number (e.g. 'R$ 1.234,56').
 * No float conversion occurs at any stage; the amount string is parsed directly.
 *
 * ## Intl mode (optional)
 * When a non-null $locale is provided AND the PHP `intl` extension is loaded,
 * formatting is delegated to {@see \NumberFormatter}::formatCurrency.
 *
 * ⚠ **Important (RN-01)**: NumberFormatter::formatCurrency() requires a PHP float.
 * This conversion is performed for **display purposes only** and must never be used
 * for calculation, comparison, or persistence. The canonical representation of any
 * monetary value is always the string stored in the adapter, not the float passed
 * here. For values within typical monetary ranges (up to ~PHP_INT_MAX / 100) the
 * double-precision representation is exact or differs only in trailing digits beyond
 * the currency's scale, so the displayed string is correct. Any consumer that needs
 * to persist or compute with the formatted value must use getAmount()/getMinorAmount()
 * instead.
 *
 * When $locale is provided but `intl` is unavailable, the formatter silently falls
 * back to manual mode without throwing an exception (graceful degradation — RF-06).
 */
final class PzCurrFormatter
{
    /**
     * @param string $thousandsSeparator  Separator inserted every 3 integer digits (default '.').
     * @param string $decimalSeparator    Separator between integer and decimal parts (default ',').
     * @param bool   $symbolBefore        When true the currency symbol precedes the number.
     * @param bool|null $intlAvailable    Override intl detection (null = auto via extension_loaded).
     *                                   Pass false in tests to simulate absence of intl.
     */
    public function __construct(
        private readonly string $thousandsSeparator = '.',
        private readonly string $decimalSeparator   = ',',
        private readonly bool   $symbolBefore       = true,
        private readonly ?bool  $intlAvailable      = null,
    ) {}

    /**
     * Formats $amount (a decimal string such as '1234.56') for human display.
     *
     * @param string          $amount   Canonical decimal string from the adapter.
     * @param PzCurrCurrency  $currency Currency metadata (code, symbol, scale).
     * @param string|null     $locale   BCP-47 locale tag (e.g. 'pt_BR'). When provided
     *                                  and intl is available, locale-aware formatting is used.
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
    // Manual formatting
    // -------------------------------------------------------------------------

    /**
     * Builds a human-readable string using configured separators and the currency
     * symbol, without any float conversion.
     *
     * Example output for BRL defaults: 'R$ 1.234,56'
     */
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

    /**
     * Inserts the thousands separator every 3 digits from the right.
     *
     * Examples (default separator '.'):
     *   '1234'    → '1.234'
     *   '1234567' → '1.234.567'
     *   '123'     → '123'
     */
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
    // Intl formatting
    // -------------------------------------------------------------------------

    /**
     * Delegates formatting to {@see \NumberFormatter}::formatCurrency.
     *
     * The $amount string is cast to float solely for passing it to the intl API.
     * This is a display-only operation — see class-level PHPDoc for the full rationale.
     *
     * Falls back to manual mode if the formatter fails (e.g. unknown currency code).
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
