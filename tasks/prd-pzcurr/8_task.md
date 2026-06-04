# Task 8.0: Integração Laravel — ServiceProvider, Config e Eloquent Cast

## Informações Gerais

**Status**: ✅ Concluída
**Prioridade**: Alta
**Dependências**: Task 6.0, Task 7.0
**Assignee**: Não atribuído

---

## Objetivo

Entregar a integração Laravel "plug-and-play": `PzCurrServiceProvider` (auto-discovery, merge de config, publish apenas em console), o arquivo `config/pzcurr.php` (adapter, moeda, escala/arredondamento, formatação) e o `PzCurrCast` (Eloquent Cast em duas colunas `*_amount` + `*_currency`). Atualizar o README com o caso de uso principal. Entregável: pacote utilizável em um app Laravel sem configuração manual obrigatória, com persistência segura (sem `float`) e smoke test fim-a-fim.

---

## Contexto

Esta tarefa fecha o ciclo, conectando o motor de cálculo (Tasks 4–7) ao framework. O ServiceProvider segue exatamente o padrão de `PzRequest` (publish só em `runningInConsole()`), e o Cast persiste o valor em duas colunas reconstruindo o objeto via `PzCurrFactory`, sem nenhuma conversão para `float` (RNF-01/§12.4).

**Referências**:
- PRD: `tasks/prd-pzcurr/prd.md` - RF-07, RF-09, RNF-01, RNF-03
- Tech Spec: `tasks/prd-pzcurr/techspec.md` - Seção 2.2 (persistência), 7.1 (publish em console), 8.3 (testes integração), 11.1 (Fase 7/8), 12.4 (deploy)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `PzCurrServiceProvider` (`register` merge config; `boot`/`publishes` só em console com tag `pzcurr-config`)
- ✅ `config/pzcurr.php` (adapter padrão, moeda padrão, escala/arredondamento padrão, opções de formatação)
- ✅ `PzCurrCast` (implementa `CastsAttributes`; `get`/`set` em 2 colunas; reconstrói via Factory; zero `float`)
- ✅ Auto-discovery validado (já declarado na Task 1.0)
- ✅ Wiring da config nas opções default do `PzCurrFormatter`/rounding mode
- ✅ `README.md` atualizado com instalação, caso de uso principal e exemplo de migration

### O que NÃO está no escopo:
- ❌ Conversão de câmbio / múltiplas moedas
- ❌ Implementação de adapters BRICK_MONEY/MONEYPHP

---

## Subtarefas

### 1. config/pzcurr.php
**Descrição**: Arquivo de configuração publicável.
**Arquivos afetados**:
- `config/pzcurr.php`

**Implementação**:
```php
<?php

declare(strict_types=1);

return [
    'adapter' => env('PZCURR_ADAPTER', 'BCMATH'),
    'default_currency' => env('PZCURR_DEFAULT_CURRENCY', 'BRL'),
    'rounding_mode' => env('PZCURR_ROUNDING_MODE', 'HALF_UP'),
    'formatting' => [
        'thousands_separator' => '.',
        'decimal_separator' => ',',
        'symbol_before' => true,
    ],
];
```

---

### 2. PzCurrServiceProvider
**Descrição**: Provider com auto-discovery, merge e publish (apenas console), espelhando `PzRequestServiceProvider`.
**Arquivos afetados**:
- `src/Laravel/PzCurrServiceProvider.php`

**Implementação**:
```php
final class PzCurrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/pzcurr.php', 'pzcurr');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/pzcurr.php' => config_path('pzcurr.php'),
            ], 'pzcurr-config');
        }
    }
}
```

---

### 3. PzCurrCast (Eloquent)
**Descrição**: Cast de duas colunas. `get` reconstrói o `PzCurr` a partir de `{attribute}_amount` + `{attribute}_currency`; `set` devolve o array das duas colunas. Sem `float`.
**Arquivos afetados**:
- `src/Laravel/PzCurrCast.php`

**Implementação**:
```php
final class PzCurrCast implements CastsAttributes
{
    /** @param array<string,mixed> $attributes */
    public function get($model, string $key, $value, array $attributes): ?PzCurrInterface
    {
        $amount = $attributes["{$key}_amount"] ?? null;
        $currency = $attributes["{$key}_currency"] ?? null;
        if ($amount === null || $currency === null) {
            return null;
        }
        return PzCurrFactory::make()->of((string) $amount, (string) $currency);
    }

    /** @return array<string,string> */
    public function set($model, string $key, $value, array $attributes): array
    {
        if (!$value instanceof PzCurrInterface) {
            throw PzCurrInvalidAmountException::forValue((string) $value);
        }
        return [
            "{$key}_amount" => $value->getAmount(),       // string decimal, nunca float
            "{$key}_currency" => $value->getCurrency()->code,
        ];
    }
}
```

---

### 4. Wiring da config
**Descrição**: Fazer a Factory/Formatter respeitarem a config (rounding mode default e separadores). Ajuste mínimo para ler `config('pzcurr.*')` quando disponível.
**Arquivos afetados**:
- `src/Factory/PzCurrFactory.php` (default rounding a partir da config, se presente)
- `src/Support/PzCurrFormatter.php` (defaults a partir da config, se presente)

---

### 5. README
**Descrição**: Documentar instalação, caso de uso principal e migration de exemplo (2 colunas).
**Arquivos afetados**:
- `README.md`

**Conteúdo mínimo**:
- Instalação via Composer + auto-discovery
- `php artisan vendor:publish --tag=pzcurr-config`
- Exemplo: `PzCurrFactory::make()->of('25.00','BRL')->add('4.99')->format()`
- Migration de exemplo com colunas `price_amount` (decimal/string) + `price_currency`
- Uso do `PzCurrCast` no model (`protected $casts = ['price' => PzCurrCast::class]`)

---

### 6. Testes de Integração
**Descrição**: ServiceProvider, Cast round-trip e smoke E2E, no padrão `tests/Features/Laravel`.

**Arquivos de teste**:
- `tests/Features/Laravel/PzCurrServiceProviderTest.php`
- `tests/Features/Laravel/PzCurrCastTest.php`
- `tests/Features/Bootstrap/SmokeTest.php`

**Cenários de teste obrigatórios**:
1. **register() faz merge da config**
   - **Fluxo**: bootar provider em container de teste → `config('pzcurr.adapter')`.
   - **Expectativa**: valor default `'BCMATH'` disponível.
2. **publishes apenas em console com a tag**
   - **Fluxo**: inspecionar `ServiceProvider::$publishGroups`/`pathsToPublish` com tag `pzcurr-config`.
   - **Expectativa**: `config/pzcurr.php` mapeado para `config_path('pzcurr.php')`.
3. **Auto-discovery**
   - **Fluxo**: ler `composer.json` → `extra.laravel.providers`.
   - **Expectativa**: contém `Puzl\PzCurr\Laravel\PzCurrServiceProvider`.
4. **Cast round-trip**
   - **Fluxo**: `set` de `of('19.90','BRL')` → 2 colunas → `get` reconstrói.
   - **Expectativa**: `getAmount()` === `'19.90'`, moeda BRL; **sem `float`** no caminho.
5. **Smoke E2E**
   - **Fluxo**: `PzCurrFactory::make()->of('25.00','BRL')->add('4.99')->format()`.
   - **Expectativa**: `'R$ 29,99'`.

---

## Critérios de Aceitação

- [ ] `declare(strict_types=1)` em todos os arquivos
- [ ] Pacote funciona sem registro manual do provider (auto-discovery)
- [ ] `vendor:publish --tag=pzcurr-config` publica `config/pzcurr.php`
- [ ] `publishes` registrado apenas em `runningInConsole()`
- [ ] `PzCurrCast` faz round-trip sem `float`
- [ ] Config define adapter, moeda, escala/arredondamento e formatação
- [ ] Tipagem estrita, sem `mixed` (salvo assinaturas exigidas pelo contrato `CastsAttributes`)
- [ ] Todos os testes de integração passando
- [ ] README atualizado com exemplos
- [ ] Sem erros de lint/type-check

---

## Entregáveis

**Arquivos Criados**:
- [ ] `config/pzcurr.php`
- [ ] `src/Laravel/PzCurrServiceProvider.php`
- [ ] `src/Laravel/PzCurrCast.php`
- [ ] `tests/Features/Laravel/PzCurrServiceProviderTest.php`
- [ ] `tests/Features/Laravel/PzCurrCastTest.php`
- [ ] `tests/Features/Bootstrap/SmokeTest.php`

**Arquivos Modificados**:
- [ ] `src/Factory/PzCurrFactory.php` (default rounding via config)
- [ ] `src/Support/PzCurrFormatter.php` (defaults via config)
- [ ] `README.md`

**Documentação**:
- [ ] README com instalação, caso de uso principal e migration de exemplo

---

## Guia de Implementação

### Passo 1: Config + Provider
Criar `config/pzcurr.php` e o provider (merge + publish em console).

### Passo 2: Cast
Implementar `PzCurrCast` com 2 colunas e reconstrução via Factory.

### Passo 3: Wiring + README + testes
Conectar config aos defaults, escrever README e os testes de integração.

```bash
./vendor/bin/phpunit tests/Features/Laravel tests/Features/Bootstrap
```

---

## Validação

### Checklist de Validação Manual:
- [ ] Instalar em app Laravel de teste sem registrar provider → funciona
- [ ] `vendor:publish` cria `config/pzcurr.php`
- [ ] Salvar/ler model com `PzCurrCast` preserva precisão
- [ ] Smoke `make()->of()->add()->format()` retorna esperado

### Comandos de Validação:
```bash
./vendor/bin/phpunit --testsuite Features
composer install --no-dev --optimize-autoloader
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Cast convertendo para `float` ao persistir | Alto | Média | Persistir `getAmount()` (string); teste de round-trip verifica string exata |
| Publish rodando em runtime web (custo) | Baixo | Baixa | `publishes` somente em `runningInConsole()` |
| Coluna decimal truncando precisão | Médio | Média | Documentar tipo de coluna (decimal com escala adequada) na migration de exemplo |

---

## Notas Adicionais

Com esta tarefa concluída, todos os RFs e RNFs do PRD estão cobertos. Recomenda-se rodar a suíte completa (`Unit` + `Features`) e revisar manualmente a ausência de `float` em todo o `src/` antes do release v1.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-06-04 | Equipe Puzl | Criação da tarefa |
