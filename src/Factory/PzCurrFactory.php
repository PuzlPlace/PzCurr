<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Factory;

use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Contract\PzCurrInterface;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;

/**
 * Ponto de entrada único do pacote.
 *
 * Cadeia de resolução do adapter:
 *   1. Argumento explícito `$adapter`
 *   2. `config('pzcurr.adapter')` (apenas quando o helper Laravel estiver disponível)
 *   3. Variável de ambiente `PZCURR_ADAPTER`
 *   4. Default: BCMATH
 *
 * Fallback silencioso: adapters previstos mas não implementados (BRICK_MONEY, MONEYPHP)
 * retornam BCMath sem lançar exceção (RNF-02).
 */
final class PzCurrFactory
{
    /**
     * Resolve e instancia o adapter correto conforme a cadeia de prioridade.
     * O modo de arredondamento default é lido de `config('pzcurr.rounding_mode')` quando disponível.
     */
    public static function make(?PzCurrAdapterEnum $adapter = null): PzCurrInterface
    {
        $resolved     = $adapter
            ?? self::resolveFromConfig()
            ?? self::resolveFromEnv()
            ?? PzCurrAdapterEnum::BCMATH;

        $roundingMode = self::resolveRoundingModeFromConfig();
        $strictFloats = self::resolveStrictFloatsFromConfig();

        return match ($resolved) {
            PzCurrAdapterEnum::BCMATH => new PzCurrBcMath($roundingMode, $strictFloats),
            // Fallback silencioso: previstos mas não implementados → BCMATH
            PzCurrAdapterEnum::BRICK_MONEY,
            PzCurrAdapterEnum::MONEYPHP => new PzCurrBcMath($roundingMode, $strictFloats),
        };
    }

    /**
     * Lê o adapter a partir de `config('pzcurr.adapter')`.
     * Retorna null quando o helper Laravel não está disponível ou o valor é inválido.
     */
    private static function resolveFromConfig(): ?PzCurrAdapterEnum
    {
        if (!function_exists('config')) {
            return null;
        }

        /** @var mixed $name */
        $name = config('pzcurr.adapter');

        return is_string($name) ? PzCurrAdapterEnum::tryFromName($name) : null;
    }

    /**
     * Lê o adapter a partir da variável de ambiente `PZCURR_ADAPTER`.
     * Retorna null quando a variável não está definida ou o valor é inválido.
     */
    private static function resolveFromEnv(): ?PzCurrAdapterEnum
    {
        $name = getenv('PZCURR_ADAPTER');

        return is_string($name) && $name !== '' ? PzCurrAdapterEnum::tryFromName($name) : null;
    }

    /**
     * Lê o modo de arredondamento padrão de `config('pzcurr.rounding_mode')`.
     * Retorna null quando o helper Laravel não está disponível ou o valor é inválido,
     * deixando o adapter usar seu próprio default (HALF_UP).
     */
    private static function resolveRoundingModeFromConfig(): ?PzCurrRoundingModeEnum
    {
        if (!function_exists('config')) {
            return null;
        }

        /** @var mixed $name */
        $name = config('pzcurr.rounding_mode');

        return is_string($name) ? PzCurrRoundingModeEnum::tryFrom($name) : null;
    }

    /**
     * Lê a flag de modo STRICT de `config('pzcurr.strict_floats')`.
     * Quando true, os métodos do adapter rejeitam valores float com exceção.
     * Default false (aceita float, convertendo para string de forma determinística).
     */
    private static function resolveStrictFloatsFromConfig(): bool
    {
        if (!function_exists('config')) {
            return false;
        }

        return (bool) config('pzcurr.strict_floats', false);
    }
}
