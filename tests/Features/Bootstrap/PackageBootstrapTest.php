<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Bootstrap;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Exception\PzCurrException;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;
use Puzl\PzCurr\Exception\PzCurrInvalidCurrencyException;
use Puzl\PzCurr\Exception\PzCurrMissingExtensionException;
use Puzl\PzCurr\Exception\PzCurrRoundingNecessaryException;
use Puzl\PzCurr\Exception\PzCurrencyMismatchException;

final class PackageBootstrapTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Extensão bcmath
    // -----------------------------------------------------------------------

    public function test_bcmath_extension_is_loaded(): void
    {
        $this->assertTrue(
            extension_loaded('bcmath'),
            'A extensão bcmath é obrigatória para o PzCurr e não está carregada neste ambiente.'
        );
    }

    // -----------------------------------------------------------------------
    // Autoload PSR-4 — todas as classes de exceção devem ser carregáveis
    // -----------------------------------------------------------------------

    public function test_autoload_resolves_base_exception_class(): void
    {
        $this->assertTrue(class_exists(PzCurrException::class));
    }

    public function test_autoload_resolves_invalid_amount_exception(): void
    {
        $this->assertTrue(class_exists(PzCurrInvalidAmountException::class));
    }

    public function test_autoload_resolves_invalid_currency_exception(): void
    {
        $this->assertTrue(class_exists(PzCurrInvalidCurrencyException::class));
    }

    public function test_autoload_resolves_currency_mismatch_exception(): void
    {
        $this->assertTrue(class_exists(PzCurrencyMismatchException::class));
    }

    public function test_autoload_resolves_rounding_necessary_exception(): void
    {
        $this->assertTrue(class_exists(PzCurrRoundingNecessaryException::class));
    }

    public function test_autoload_resolves_missing_extension_exception(): void
    {
        $this->assertTrue(class_exists(PzCurrMissingExtensionException::class));
    }

    // -----------------------------------------------------------------------
    // Instanciação via FQCN — garante que o autoload realmente carrega
    // -----------------------------------------------------------------------

    public function test_exception_can_be_instantiated_via_fqcn(): void
    {
        $exception = PzCurrInvalidAmountException::forValue('bad');

        $this->assertInstanceOf(PzCurrException::class, $exception);
        $this->assertNotEmpty($exception->getMessage());
    }

    public function test_all_exception_classes_are_instantiable(): void
    {
        $instances = [
            PzCurrInvalidAmountException::forValue('bad'),
            PzCurrInvalidCurrencyException::forCode('ZZZ'),
            PzCurrencyMismatchException::between('BRL', 'USD'),
            PzCurrRoundingNecessaryException::forScale('1.005', 2),
            PzCurrMissingExtensionException::bcmath(),
        ];

        foreach ($instances as $instance) {
            $this->assertInstanceOf(PzCurrException::class, $instance);
        }
    }
}
