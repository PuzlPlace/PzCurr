# Plano — Escala opcional no `of()` + Enum de moedas (`PzCurrCurrencyEnum`)

## 1. Objetivo

Permitir inicializar um valor com **escala explícita** independente da moeda, mantendo o catálogo
de moedas padrão como fonte default, e trocar o parâmetro de moeda de **string** para um **enum**
(`PzCurrCurrencyEnum`) com todas as moedas ISO já catalogadas.

### Comportamento desejado

```php
// scale não informado → usa a escala da moeda (BRL = 2)
PzCurrFactory::make()->of('1.1234567', PzCurrCurrencyEnum::BRL);
// amount = '1.12', scale = 2

// scale informado → IGNORA a escala da moeda e usa o scale passado
PzCurrFactory::make()->of('1.1234567', PzCurrCurrencyEnum::BRL, scale: 7);
// amount = '1.1234567', scale = 7

// moeda não informada → default BRL
PzCurrFactory::make()->of('1.1234567');
// equivalente a of('1.1234567', PzCurrCurrencyEnum::BRL)

// operações subsequentes respeitam a escala definida na instância
PzCurrFactory::make()->of('1.0000000', PzCurrCurrencyEnum::BRL, scale: 7)
    ->multiply('2.5')      // arredonda em 7 casas
    ->add('0.0000001')     // arredonda em 7 casas
    ->withScale(2);        // reduz para 2 (ex.: vProd da NF-e)
```

### Regras

1. `scale` é **opcional**. Quando `null`, lê da moeda (comportamento atual).
2. Quando `scale` é informado, **prevalece** sobre a escala da moeda na instância.
3. Moeda (2º parâmetro) é **opcional** e default = `BRL`.
4. Moeda passada como **enum** (`PzCurrCurrencyEnum`), não string (com fallback de compat — ver §6).
5. `add` / `subtract` / `multiply` / `divide` continuam arredondando para `$this->scale` (já é assim hoje).

---

## 2. Situação atual (referências)

- Contrato: `of(string|int $amount, string $currency)` em `src/Contract/PzCurrInterface.php` (linha ~24).
- Base abstrata declara os mesmos assinaturas abstratas — `src/Adapter/PzCurrBase.php` (linhas ~135-139).
- Implementação: `src/Adapter/BcMath/PzCurrBcMath.php`
  - `of()` linhas ~110-118 → `currency = PzCurrCurrencyRegistry::of($currency); scale = currency->scale;`
  - `ofMinor()` ~120-128 e `zero()` ~130-133 também recebem `string $currency`.
- Registry: `src/Currency/PzCurrCurrencyRegistry.php` — catálogo `ISO` (const) + customizadas.
- Cast Eloquent: `src/Laravel/PzCurrCast.php` (linha 44) chama `->of((string) $amount, (string) $currency)`.
- `operandToString()` / `assertSameCurrency()` comparam por `currency->code` (string) — **não muda**.

> Nada hoje aceita escala na criação; a única forma é `withScale()` depois (que perde casas se o `of` já arredondou).

---

## 3. Mudanças propostas

### 3.1 Novo enum `PzCurrCurrencyEnum`

Arquivo: `src/Enum/PzCurrCurrencyEnum.php` (string-backed, valor = código ISO).

```php
enum PzCurrCurrencyEnum: string
{
    case AED = 'AED';
    case ARS = 'ARS';
    // ... todas as 50 moedas do catálogo ISO atual ...
    case BRL = 'BRL';
    // ...
    case ZAR = 'ZAR';
}
```

- O enum **só lista os códigos**; `numeric`/`scale`/`symbol` permanecem no `PzCurrCurrencyRegistry`
  (fonte única de metadados; evita duplicação e divergência).
- Conveniência opcional: `PzCurrCurrencyEnum::default(): self => self::BRL`.

> **Decisão:** o enum NÃO duplica scale/symbol. Mantém o registry como autoridade e permite moedas
> customizadas (cripto/NF-e) que não existem no enum continuarem funcionando via string.

### 3.2 Contrato `PzCurrInterface`

Atualizar assinaturas (aceitando enum OU string para compat — ver §6):

```php
public function of(
    string|int $amount,
    PzCurrCurrencyEnum|string|null $currency = null,
    ?int $scale = null
): self;

public function ofMinor(
    int $minorAmount,
    PzCurrCurrencyEnum|string|null $currency = null
): self;

public function zero(PzCurrCurrencyEnum|string|null $currency = null): self;
```

### 3.3 Base abstrata `PzCurrBase`

- Espelhar as novas assinaturas abstratas.
- Adicionar helper protegido para normalizar o parâmetro de moeda:

```php
protected function resolveCurrencyCode(PzCurrCurrencyEnum|string|null $currency): string
{
    if ($currency === null) {
        return PzCurrCurrencyEnum::default()->value; // 'BRL'
    }
    return $currency instanceof PzCurrCurrencyEnum ? $currency->value : strtoupper($currency);
}
```

### 3.4 Adapter `PzCurrBcMath`

`of()` passa a:

```php
public function of(
    string|int $amount,
    PzCurrCurrencyEnum|string|null $currency = null,
    ?int $scale = null
): self {
    $code           = $this->resolveCurrencyCode($currency);
    $this->currency = PzCurrCurrencyRegistry::of($code);
    $this->scale    = $scale ?? $this->currency->scale;   // <- escala explícita prevalece
    $normalized     = $this->assertNumericString((string) $amount);
    $this->amount   = PzCurrRoundingHelper::round($normalized, $this->scale, $this->roundingMode);

    return $this;
}
```

- `ofMinor()` e `zero()` usam `resolveCurrencyCode()`; `ofMinor` mantém scale da moeda (ou aceitar
  `?int $scale` também — ver §5, item 4).
- `zero()` repassa default: `return $this->of('0', $currency, $scale ?? null);` (manter simples).

### 3.5 Validação de `scale`

Adicionar guarda em `of()`/`withScale()`:
- `scale >= 0` (lança `PzCurrInvalidAmountException` ou nova exceção `PzCurrInvalidScaleException`).
- Opcional: teto máximo configurável (ex.: 18) para evitar abusos. Decidir em §5, item 2.

### 3.6 Cast Eloquent `PzCurrCast`

- `get()` continua usando string: `->of((string) $amount, (string) $currency)` — **funciona** pois
  `of()` aceita `string`. Sem quebra.
- (Opcional) Persistir e reidratar `scale` (já está em `toArray()`), para reconstruir valores com
  escala não-padrão — ver §5, item 3.

---

## 4. Arquivos afetados

| Arquivo | Mudança |
|--------|---------|
| `src/Enum/PzCurrCurrencyEnum.php` | **Novo** enum com 50 moedas |
| `src/Contract/PzCurrInterface.php` | Assinaturas `of`/`ofMinor`/`zero` |
| `src/Adapter/PzCurrBase.php` | Assinaturas abstratas + `resolveCurrencyCode()` |
| `src/Adapter/BcMath/PzCurrBcMath.php` | `of()` com `$scale`; `ofMinor`/`zero` normalizados |
| `tests/Unit/Adapter/PzCurrBaseStub.php` | Ajustar stub às novas assinaturas |
| `src/Exception/PzCurrInvalidScaleException.php` | **Novo** (se optar por exceção dedicada) |
| `src/Laravel/PzCurrCast.php` | (Opcional) reidratar `scale` |
| `README.md` | Documentar `of(amount, Enum, scale)` |

---

## 5. Decisões em aberto (confirmar antes de implementar)

1. **Compatibilidade de assinatura**: aceitar `enum|string` (recomendado, não quebra `PzCurrCast`
   nem chamadas existentes) ou **forçar só enum** (quebra cast e exige refactor amplo — ver §6)?
2. **Teto de escala**: limitar (ex.: máx 18) ou livre?
3. **Persistência de escala** no cast (coluna extra `*_scale` ou derivar): implementar agora ou depois?
4. **`ofMinor` com escala custom**: aceitar `?int $scale`? (impacta `10^scale` e overflow de int).
5. **`getMinorAmount()` com escala > 2**: `× 10^scale` pode estourar `PHP_INT_MAX`; manter guarda
   atual (já existe) e documentar limite.

---

## 6. Sobre "passar enum e não string"

O pedido é usar enum no parâmetro de moeda. Recomendação: **aceitar `PzCurrCurrencyEnum|string`**.

- **Por quê não só-enum:** `PzCurrCast::get()` recebe a moeda como **string** vinda do banco
  (`*_currency`). Forçar só-enum exigiria `PzCurrCurrencyEnum::from($value)` no cast e quebraria
  moedas **customizadas** (cripto/NF-e) que não estão no enum.
- **Resultado:** API pública incentiva enum (`of($v, PzCurrCurrencyEnum::BRL)`), mas string continua
  válida internamente (cast, moedas custom). Se você quiser **só-enum na API pública**, dá para manter
  string apenas num método interno (`ofRaw(string)` usado pelo cast) — registrar como decisão #1.

---

## 7. Testes a adicionar/ajustar

- `of()` sem scale → usa scale da moeda (regressão).
- `of()` com `scale: 7` → mantém 7 casas, não arredonda para 2.
- `of()` sem moeda → default BRL.
- `of(PzCurrCurrencyEnum::JPY)` → scale 0; com `scale: 4` → 4.
- `multiply`/`add` após `of(scale:7)` respeitam 7 casas; `withScale(2)` reduz.
- Enum: todos os `cases()` resolvem no registry (`PzCurrCurrencyRegistry::of($case->value)`).
- `scale` negativo lança exceção.
- Cast: `get()` com string continua funcionando.
- Stub (`PzCurrBaseStub`) compila com novas assinaturas.

---

## 8. Roteiro de implementação (ordem sugerida)

1. Criar `PzCurrCurrencyEnum` (50 casos) + `default()`.
2. (Opcional) `PzCurrInvalidScaleException`.
3. Atualizar `PzCurrInterface` (`of`/`ofMinor`/`zero`).
4. Atualizar `PzCurrBase` (assinaturas + `resolveCurrencyCode`).
5. Implementar em `PzCurrBcMath` (`of` com `$scale`, normalização de moeda).
6. Ajustar `PzCurrBaseStub`.
7. Testes unitários (criação, escala, enum, exceções).
8. (Opcional) Persistência de scale no `PzCurrCast`.
9. Atualizar `README.md`.
10. Rodar suíte no container Laradock:
    `docker exec -it laradock-workspace-1 bash -lc 'cd <path> && ./vendor/bin/phpunit'`.

---

## 9. Exemplo final (NF-e, sem moeda fake `BRL_CALC`)

```php
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Factory\PzCurrFactory;

$vProd = PzCurrFactory::make()
    ->of('1.0640000000', PzCurrCurrencyEnum::BRL, scale: 10) // unitário com 10 casas
    ->multiply('39680.1234')                                  // qCom (até 4)
    ->withScale(2);                                           // vProd na NF-e (2 casas)

$vProd->getAmount(); // total do item, pronto para a tag vProd
```
