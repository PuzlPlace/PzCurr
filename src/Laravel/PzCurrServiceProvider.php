<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Laravel;

use Illuminate\Support\ServiceProvider;

/**
 * Service Provider Laravel do PzCurr.
 *
 * Mantém o pacote "plug-and-play" via auto-discovery (declarado em
 * `composer.json` `extra.laravel.providers`):
 *
 * - `register()`: mescla `config/pzcurr.php` em `config('pzcurr.*')`.
 * - `boot()`: registra o `publishes` com a tag `pzcurr-config` apenas
 *   quando rodando em console (otimização de memória em runtime web).
 */
final class PzCurrServiceProvider extends ServiceProvider
{
    public const CONFIG_PATH = __DIR__ . '/../../config/pzcurr.php';

    public const PUBLISH_TAG = 'pzcurr-config';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'pzcurr');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::CONFIG_PATH => $this->configPath('pzcurr.php'),
            ], self::PUBLISH_TAG);
        }
    }

    /**
     * Resolve o destino do publish. Em ambiente Laravel completo usa o
     * helper `config_path()`; em testes sob `Illuminate\Container\Container`
     * puro, faz fallback para `basePath('config/...')`.
     */
    protected function configPath(string $file): string
    {
        return function_exists('config_path')
            ? config_path($file)
            : $this->app->basePath('config/' . $file);
    }
}
