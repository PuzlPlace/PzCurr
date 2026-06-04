<?php

declare(strict_types=1);

return [
    'adapter' => env('PZCURR_ADAPTER', 'BCMATH'),

    'default_currency' => env('PZCURR_DEFAULT_CURRENCY', 'BRL'),

    'rounding_mode' => env('PZCURR_ROUNDING_MODE', 'HALF_UP'),

    /*
     * Modo STRICT para entrada de floats.
     *
     * false (padrão): os métodos (of, add, subtract, multiply, divide, mod e comparações)
     *                 aceitam float, convertendo-o para string de forma determinística
     *                 (arredondado pela escala da instância) antes de qualquer cálculo.
     * true:           qualquer float informado a esses métodos lança
     *                 PzCurrFloatNotAllowedException, forçando o uso de string/int.
     */
    'strict_floats' => env('PZCURR_STRICT_FLOATS', false),

    'formatting' => [
        'thousands_separator' => '.',
        'decimal_separator'   => ',',
        'symbol_before'       => true,
    ],
];
