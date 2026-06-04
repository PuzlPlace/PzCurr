<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Exception\PzCurrDivisionByZeroException;
use Puzl\PzCurr\Exception\PzCurrException;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;
use Puzl\PzCurr\Exception\PzCurrInvalidCurrencyException;
use Puzl\PzCurr\Exception\PzCurrMissingExtensionException;
use Puzl\PzCurr\Exception\PzCurrRoundingNecessaryException;
use Puzl\PzCurr\Exception\PzCurrencyMismatchException;

final class PzCurrExceptionHierarchyTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Hierarquia comum
    // -----------------------------------------------------------------------

    public function test_all_exceptions_extend_pzcurr_exception(): void
    {
        $exceptions = [
            PzCurrInvalidAmountException::forValue('abc'),
            PzCurrInvalidCurrencyException::forCode('XXX'),
            PzCurrencyMismatchException::between('BRL', 'USD'),
            PzCurrRoundingNecessaryException::forScale('19.999', 2),
            PzCurrMissingExtensionException::bcmath(),
            PzCurrDivisionByZeroException::forDivisor('divide'),
            PzCurrDivisionByZeroException::zeroRatios(),
        ];

        foreach ($exceptions as $exception) {
            $this->assertInstanceOf(PzCurrException::class, $exception);
            $this->assertInstanceOf(\Throwable::class, $exception);
        }
    }

    public function test_pzcurr_exception_extends_runtime_exception(): void
    {
        $exception = new PzCurrException('test');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    // -----------------------------------------------------------------------
    // Captura genérica
    // -----------------------------------------------------------------------

    public function test_specialized_exception_is_caught_as_pzcurr_exception(): void
    {
        $caught = null;

        try {
            throw PzCurrencyMismatchException::between('BRL', 'USD');
        } catch (PzCurrException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(PzCurrencyMismatchException::class, $caught);
        $this->assertInstanceOf(PzCurrException::class, $caught);
    }

    public function test_invalid_amount_exception_is_caught_as_pzcurr_exception(): void
    {
        $caught = null;

        try {
            throw PzCurrInvalidAmountException::forValue('not-a-number');
        } catch (PzCurrException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(PzCurrInvalidAmountException::class, $caught);
    }

    public function test_missing_extension_exception_is_caught_as_pzcurr_exception(): void
    {
        $caught = null;

        try {
            throw PzCurrMissingExtensionException::bcmath();
        } catch (PzCurrException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(PzCurrMissingExtensionException::class, $caught);
    }

    // -----------------------------------------------------------------------
    // Mensagens contextuais
    // -----------------------------------------------------------------------

    public function test_currency_mismatch_message_contains_both_currencies(): void
    {
        $exception = PzCurrencyMismatchException::between('BRL', 'USD');

        $this->assertStringContainsString('BRL', $exception->getMessage());
        $this->assertStringContainsString('USD', $exception->getMessage());
    }

    public function test_invalid_amount_message_contains_the_value(): void
    {
        $exception = PzCurrInvalidAmountException::forValue('not-a-number');

        $this->assertStringContainsString('not-a-number', $exception->getMessage());
    }

    public function test_invalid_currency_message_contains_the_code(): void
    {
        $exception = PzCurrInvalidCurrencyException::forCode('INVALID');

        $this->assertStringContainsString('INVALID', $exception->getMessage());
    }

    public function test_rounding_necessary_message_contains_amount_and_scale(): void
    {
        $exception = PzCurrRoundingNecessaryException::forScale('19.999', 2);

        $this->assertStringContainsString('19.999', $exception->getMessage());
        $this->assertStringContainsString('2', $exception->getMessage());
    }

    public function test_missing_extension_message_mentions_bcmath(): void
    {
        $exception = PzCurrMissingExtensionException::bcmath();

        $this->assertStringContainsString('bcmath', $exception->getMessage());
    }

    // -----------------------------------------------------------------------
    // Named constructors retornam instância correta
    // -----------------------------------------------------------------------

    public function test_currency_mismatch_named_constructor_returns_correct_type(): void
    {
        $exception = PzCurrencyMismatchException::between('EUR', 'GBP');

        $this->assertInstanceOf(PzCurrencyMismatchException::class, $exception);
    }

    public function test_invalid_amount_named_constructor_returns_correct_type(): void
    {
        $exception = PzCurrInvalidAmountException::forValue('');

        $this->assertInstanceOf(PzCurrInvalidAmountException::class, $exception);
    }

    public function test_invalid_currency_named_constructor_returns_correct_type(): void
    {
        $exception = PzCurrInvalidCurrencyException::forCode('ZZZ');

        $this->assertInstanceOf(PzCurrInvalidCurrencyException::class, $exception);
    }

    public function test_rounding_necessary_named_constructor_returns_correct_type(): void
    {
        $exception = PzCurrRoundingNecessaryException::forScale('1.005', 2);

        $this->assertInstanceOf(PzCurrRoundingNecessaryException::class, $exception);
    }

    public function test_missing_extension_named_constructor_returns_correct_type(): void
    {
        $exception = PzCurrMissingExtensionException::bcmath();

        $this->assertInstanceOf(PzCurrMissingExtensionException::class, $exception);
    }

    public function test_division_by_zero_named_constructors_return_correct_type(): void
    {
        $this->assertInstanceOf(
            PzCurrDivisionByZeroException::class,
            PzCurrDivisionByZeroException::forDivisor('divide'),
        );
        $this->assertInstanceOf(
            PzCurrDivisionByZeroException::class,
            PzCurrDivisionByZeroException::zeroRatios(),
        );
    }

    public function test_division_by_zero_message_contains_operation(): void
    {
        $exception = PzCurrDivisionByZeroException::forDivisor('mod');

        $this->assertStringContainsString('mod', $exception->getMessage());
    }
}
