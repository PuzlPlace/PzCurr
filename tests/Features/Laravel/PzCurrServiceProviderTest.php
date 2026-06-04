<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Factory\PzCurrFactory;
use Puzl\PzCurr\Laravel\PzCurrServiceProvider;
use Puzl\PzCurr\Tests\Features\Laravel\Fixtures\AppStub;

final class PzCurrServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('PZCURR_ADAPTER');
        ServiceProvider::$publishGroups = [];
        ServiceProvider::$publishes     = [];
    }

    protected function tearDown(): void
    {
        putenv('PZCURR_ADAPTER');
        ServiceProvider::$publishGroups = [];
        ServiceProvider::$publishes     = [];
        Container::setInstance(null);
        parent::tearDown();
    }

    private function makeApp(bool $runningInConsole = true): AppStub
    {
        $app = new AppStub($runningInConsole);
        $app->instance('config', new Repository());
        Container::setInstance($app);

        return $app;
    }

    // -------------------------------------------------------------------------
    // register() — merge de config
    // -------------------------------------------------------------------------

    public function test_register_merges_default_adapter_into_config(): void
    {
        $app      = $this->makeApp();
        $provider = new PzCurrServiceProvider($app);

        $provider->register();

        $config = $app->make('config');
        self::assertSame('BCMATH', $config->get('pzcurr.adapter'));
    }

    public function test_register_merges_default_currency_into_config(): void
    {
        $app      = $this->makeApp();
        $provider = new PzCurrServiceProvider($app);

        $provider->register();

        $config = $app->make('config');
        self::assertSame('BRL', $config->get('pzcurr.default_currency'));
    }

    public function test_register_merges_rounding_mode_into_config(): void
    {
        $app      = $this->makeApp();
        $provider = new PzCurrServiceProvider($app);

        $provider->register();

        $config = $app->make('config');
        self::assertSame('HALF_UP', $config->get('pzcurr.rounding_mode'));
    }

    public function test_register_merges_formatting_options_into_config(): void
    {
        $app      = $this->makeApp();
        $provider = new PzCurrServiceProvider($app);

        $provider->register();

        $config = $app->make('config');
        self::assertSame('.', $config->get('pzcurr.formatting.thousands_separator'));
        self::assertSame(',', $config->get('pzcurr.formatting.decimal_separator'));
        self::assertTrue($config->get('pzcurr.formatting.symbol_before'));
    }

    // -------------------------------------------------------------------------
    // boot() — publishes com tag pzcurr-config (apenas em console)
    // -------------------------------------------------------------------------

    public function test_boot_registers_publishes_under_pzcurr_config_tag(): void
    {
        $app      = $this->makeApp(true);
        $provider = new PzCurrServiceProvider($app);

        $provider->boot();

        $paths = ServiceProvider::pathsToPublish(PzCurrServiceProvider::class, 'pzcurr-config');
        self::assertNotEmpty($paths);

        $resolved   = realpath(PzCurrServiceProvider::CONFIG_PATH);
        $foundSource = false;
        foreach (array_keys($paths) as $source) {
            if (realpath($source) === $resolved) {
                $foundSource = true;
                break;
            }
        }
        self::assertTrue($foundSource, 'Publish source must point to the package config file.');
    }

    public function test_boot_does_not_register_publishes_outside_console(): void
    {
        $app      = $this->makeApp(false);
        $provider = new PzCurrServiceProvider($app);

        $provider->boot();

        $paths = ServiceProvider::pathsToPublish(PzCurrServiceProvider::class, 'pzcurr-config');
        self::assertSame([], $paths);
    }

    // -------------------------------------------------------------------------
    // Auto-discovery — composer.json
    // -------------------------------------------------------------------------

    public function test_composer_json_declares_provider_in_laravel_auto_discovery(): void
    {
        $composerPath = realpath(__DIR__ . '/../../../composer.json');
        self::assertNotFalse($composerPath);

        $raw = file_get_contents($composerPath);
        self::assertIsString($raw);

        $manifest = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        self::assertContains(
            'Puzl\\PzCurr\\Laravel\\PzCurrServiceProvider',
            $manifest['extra']['laravel']['providers'],
        );
    }

    // -------------------------------------------------------------------------
    // Resolução do adapter via config após register()
    // -------------------------------------------------------------------------

    public function test_factory_resolves_adapter_using_config_after_provider_registers(): void
    {
        $app      = $this->makeApp();
        $provider = new PzCurrServiceProvider($app);
        $provider->register();

        $app->make('config')->set('pzcurr.adapter', 'BCMATH');

        self::assertInstanceOf(PzCurrBcMath::class, PzCurrFactory::make());
    }
}
