<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Laravel;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Puzl\PzCurr\Contract\PzCurrInterface;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;
use Puzl\PzCurr\Factory\PzCurrFactory;

/**
 * Eloquent Cast para valores monetários PzCurr.
 *
 * Persiste o valor em duas colunas:
 *   - `{key}_amount`   → string decimal (ex.: '19.90'), nunca float
 *   - `{key}_currency` → código ISO 4217 (ex.: 'BRL')
 *
 * Exemplo de uso no model:
 *   protected $casts = ['price' => PzCurrCast::class];
 *
 * Exemplo de migration:
 *   $table->decimal('price_amount', 20, 8);
 *   $table->char('price_currency', 3);
 */
final class PzCurrCast implements CastsAttributes
{
    /**
     * Reconstrói o PzCurrInterface a partir das duas colunas persistidas.
     * Retorna null quando qualquer coluna estiver ausente/nula.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array<string, mixed>                 $attributes
     */
    public function get($model, string $key, $value, array $attributes): ?PzCurrInterface
    {
        $amount   = $attributes["{$key}_amount"]   ?? null;
        $currency = $attributes["{$key}_currency"] ?? null;

        if ($amount === null || $currency === null) {
            return null;
        }

        return PzCurrFactory::make()->of((string) $amount, (string) $currency);
    }

    /**
     * Decompõe o PzCurrInterface nas duas colunas de persistência.
     * Nenhuma conversão para float ocorre neste caminho (RNF-01 / §12.4).
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array<string, mixed>                 $attributes
     * @return array<string, string>
     *
     * @throws PzCurrInvalidAmountException quando $value não é uma instância de PzCurrInterface.
     */
    public function set($model, string $key, $value, array $attributes): array
    {
        if (!$value instanceof PzCurrInterface) {
            throw PzCurrInvalidAmountException::forValue((string) $value);
        }

        return [
            "{$key}_amount"   => $value->getAmount(),
            "{$key}_currency" => $value->getCurrency()->code,
        ];
    }
}
