<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Laravel\Fixtures;

use Illuminate\Container\Container;

/**
 * Stub mínimo do `Application` Laravel para passar ao Service Provider
 * sem subir o framework completo. Implementa apenas o que o provider
 * realmente chama: `runningInConsole()` e `basePath()`.
 */
final class AppStub extends Container
{
    private bool $console;

    public function __construct(bool $runningInConsole = true)
    {
        $this->console = $runningInConsole;
    }

    public function runningInConsole(): bool
    {
        return $this->console;
    }

    public function basePath(string $path = ''): string
    {
        return sys_get_temp_dir() . '/pzcurr-test/' . ltrim($path, '/');
    }
}
