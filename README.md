# PzCurr

Biblioteca de valores monetários com **precisão exata** (BCMath) para PHP e Laravel.
Zero conversão para `float` em todo o ciclo de vida — criação, operações, persistência e formatação.

---

## Instalação

```bash
composer require puzl/pzcurr
```

O **auto-discovery** do Laravel registra o `PzCurrServiceProvider` automaticamente.
Nenhuma configuração manual é obrigatória.

Para publicar o arquivo de configuração:

```bash
php artisan vendor:publish --tag=pzcurr-config
```

---

## Uso Principal

> **Moeda padrão:** se você **não** informar a moeda, o PzCurr assume **`BRL`** automaticamente.
> Ou seja, `->of('25.00')` é equivalente a `->of('25.00', 'BRL')`.

```php
use Puzl\PzCurr\Factory\PzCurrFactory;

// Sem informar a moeda → assume BRL por padrão
$result = PzCurrFactory::make()
    ->of('25.00')   // equivale a ->of('25.00', 'BRL')
    ->add('4.99')
    ->format();

// 'R$ 29,99'
echo $result;

// Outros exemplos
$money = PzCurrFactory::make()->of('100.00', 'BRL');
$money->subtract('10.00')->multiply('2'); // R$ 180,00

// Serialização
$money->toArray();      // ['amount' => '180.00', 'currency' => 'BRL', 'scale' => 2]
$money->getAmount();    // '180.00'  (string, nunca float)
$money->getMinorAmount(); // 18000  (int de centavos)
```

### Moeda via Enum e escala opcional

O segundo parâmetro de `of()`/`ofMinor()`/`zero()` aceita o **enum** `PzCurrCurrencyEnum`
(recomendado), uma `string` (inclusive moedas customizadas) ou pode ser **omitido** — nesse
caso usa a moeda padrão **BRL**.

O `of()` (e o `zero()`) aceita ainda um **terceiro parâmetro opcional `$scale`**:

- Quando **não informado**, a escala vem da moeda (BRL = 2, JPY = 0, ...).
- Quando **informado**, prevalece sobre a escala da moeda e passa a valer para todas as
  operações subsequentes (`add`, `subtract`, `multiply`, `divide`).

```php
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Factory\PzCurrFactory;

// Moeda padrão (BRL), escala da moeda (2)
PzCurrFactory::make()->of('1.50');                       // '1.50', scale 2

// Moeda via enum
PzCurrFactory::make()->of('10.00', PzCurrCurrencyEnum::USD);

// Escala explícita: ignora a escala da moeda
PzCurrFactory::make()->of('1.1234567', PzCurrCurrencyEnum::BRL, scale: 7);
// getAmount() => '1.1234567', getScale() => 7

// Operações respeitam a escala definida na instância
PzCurrFactory::make()
    ->of('1.0000000', PzCurrCurrencyEnum::BRL, scale: 7)
    ->add('0.0000001')
    ->multiply('2');
// getAmount() => '2.0000002'
```

> A `string` continua válida (`of('10.00', 'BRL')`), o que mantém moedas customizadas
> registradas via `PzCurrCurrencyRegistry` e a reidratação pelo `PzCurrCast` funcionando.
> Escala negativa lança `PzCurrInvalidScaleException`.

#### Exemplo: valor unitário × quantidade (NF-e)

Calcular com alta precisão e só no fim reduzir o total para 2 casas (ex.: `vProd`):

```php
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Factory\PzCurrFactory;

$vProd = PzCurrFactory::make()
    ->of('1.0640000000', PzCurrCurrencyEnum::BRL, scale: 10) // unitário (até 10 casas)
    ->multiply('39680.1234')                                  // quantidade (até 4 casas)
    ->withScale(2);                                           // total em 2 casas

echo $vProd->getAmount(); // '42219.65'
```

### Múltiplos operandos (`add` e `subtract`)

Os métodos `add()` e `subtract()` aceitam **um ou mais valores** na mesma chamada (parâmetros variádicos).
Cada argumento pode ser `string`, `int` ou outra instância de `PzCurrInterface` (mesma moeda).
O arredondamento é aplicado **uma única vez** ao final da operação, o que preserva precisão em somas/subtrações com vários termos.

```php
use Puzl\PzCurr\Factory\PzCurrFactory;

$factory = PzCurrFactory::make();

// add() — somar vários valores de uma vez
$total = $factory
    ->of('25.00', 'BRL')
    ->add('4.99', '10.00', '3.50');
// getAmount() => '43.49'

// subtract() — subtrair vários valores de uma vez
$saldo = $factory
    ->of('100.00', 'BRL')
    ->subtract('10.00', '5.50', '2.00');
// getAmount() => '82.50'

// Valores vindos de um array (spread)
$itens = ['4.99', '10.00', '3.50'];

$carrinho = $factory
    ->of('0.00', 'BRL')
    ->add(...$itens);
// getAmount() => '18.49'

// Misturando tipos: string, int e outra instância PzCurr
$frete = $factory->of('12.90', 'BRL');

$pedido = $factory
    ->of('50.00', 'BRL')
    ->add('9.99', 5, $frete);
// getAmount() => '77.89'
```

---

## Persistência com Eloquent Cast

O `PzCurrCast` persiste o valor em **duas colunas** — `*_amount` (string decimal) e `*_currency` (código ISO 4217) — sem nenhuma conversão para `float`.

### Migration de exemplo

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->decimal('price_amount', 20, 8); // string decimal, precisão adequada
    $table->char('price_currency', 3);
    $table->timestamps();
});
```

### Model

```php
use Puzl\PzCurr\Laravel\PzCurrCast;

class Order extends Model
{
    protected $casts = [
        'price' => PzCurrCast::class,
    ];
}
```

### Uso no model

```php
// Salvar
$order = new Order();
$order->price = PzCurrFactory::make()->of('19.90', 'BRL');
$order->save();
// Colunas: price_amount = '19.90', price_currency = 'BRL'

// Ler — reconstrói automaticamente via Factory
$order = Order::find(1);
echo $order->price->format();         // 'R$ 19,90'
echo $order->price->getAmount();      // '19.90'  (string, zero float)
echo $order->price->getCurrency()->code; // 'BRL'
```

---

## Configuração (`config/pzcurr.php`)

```php
return [
    'adapter'          => env('PZCURR_ADAPTER', 'BCMATH'),
    'default_currency' => env('PZCURR_DEFAULT_CURRENCY', 'BRL'),
    'rounding_mode'    => env('PZCURR_ROUNDING_MODE', 'HALF_UP'),
    'strict_floats'    => env('PZCURR_STRICT_FLOATS', false),
    'formatting' => [
        'thousands_separator' => '.',
        'decimal_separator'   => ',',
        'symbol_before'       => true,
    ],
];
```

---

## Entrada de `float` e modo STRICT

Os métodos `of()`, `add()`, `subtract()`, `multiply()`, `divide()`, `mod()` e as comparações
aceitam `string`, `int` e também **`float`**. O ideal é sempre passar **string** (ex.: `'19.90'`),
pois o `float` já chega com a imprecisão do IEEE 754. Mas, para conveniência, o `float` é aceito por
padrão e convertido para string **da forma mais determinística possível**.

**Como o float é convertido:** via `number_format($valor, $escalaDaInstancia, '.', '')` — ou seja,
arredondado pela **escala da instância**, sem notação científica e sem depender de
`serialize_precision`. A escala manda; o resultado é previsível.

```php
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Factory\PzCurrFactory;

// float aceito (STRICT desativado, padrão)
PzCurrFactory::make()->of(0.1, PzCurrCurrencyEnum::BRL)->add(0.2)->getAmount(); // '0.30'

PzCurrFactory::make()->of('10.00', PzCurrCurrencyEnum::BRL)->multiply(2.5)->getAmount(); // '25.00'
```

### Modo STRICT (proibir `float`)

Quando `strict_floats` é **`true`**, qualquer `float` passado a esses métodos lança
`PzCurrFloatNotAllowedException` — útil para garantir que só strings/ints entrem no domínio.
O padrão é **`false`** (aceita float).

```php
// .env
PZCURR_STRICT_FLOATS=true
```

```php
// Com STRICT ativado:
PzCurrFactory::make()->of(19.90, PzCurrCurrencyEnum::BRL); // lança PzCurrFloatNotAllowedException
PzCurrFactory::make()->of('19.90', PzCurrCurrencyEnum::BRL); // OK (string)
```

> Fora do Laravel (sem helper `config()`), o modo é `false` por padrão; é possível ativá-lo
> manualmente instanciando o adapter com o segundo argumento do construtor.

---

## Requisitos

- PHP `^8.1`
- Extensão `bcmath` (obrigatória)
- Extensão `intl` (opcional — formatação por locale)
- Laravel / Illuminate `^10|^11|^12` (opcional — integração ServiceProvider + Cast)

---

## Testes

```bash
./vendor/bin/phpunit
```
