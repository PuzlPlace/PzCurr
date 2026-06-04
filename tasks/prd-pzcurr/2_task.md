# Task 2.0: Domínio de Moeda e Arredondamento

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Alta
**Dependências**: Task 1.0
**Assignee**: Não atribuído

---

## Objetivo

Implementar o núcleo de domínio numérico do pacote: o value object imutável `PzCurrCurrency`, o catálogo `PzCurrCurrencyRegistry` (ISO 4217 + moedas customizadas), o enum `PzCurrRoundingModeEnum` (8 modos) e o `PzCurrRoundingHelper`, que aplica arredondamento sobre strings de forma compatível com PHP 8.1+ (sem `bcround`). Entregável: resolução de moeda e arredondamento exatos e exaustivamente testados, base para o adapter de cálculo.

---

## Contexto

Toda operação monetária precisa saber a escala da moeda (BRL/USD=2, JPY=0) e como arredondar casas excedentes. Como o `bcround()` nativo só existe no PHP 8.4+, o pacote implementa seu próprio helper operando sobre o dígito-guia da string decimal (RN-04/RN-05). O registry centraliza o catálogo ISO e permite moedas customizadas (ex.: cripto).

**Referências**:
- PRD: `tasks/prd-pzcurr/prd.md` - RF-03, RF-05, RN-04, RN-05
- Tech Spec: `tasks/prd-pzcurr/techspec.md` - Seções 1.3, 2.3 (helper próprio), 3.1 (PzCurrCurrency), 3.3 (RoundingModeEnum), 11.1 (Fase 2)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `PzCurrCurrency` (VO imutável: code, numericCode, scale, symbol)
- ✅ `PzCurrCurrencyRegistry` (catálogo ISO estático + registro de customizadas)
- ✅ `PzCurrRoundingModeEnum` (HALF_UP, HALF_DOWN, HALF_EVEN, UP, DOWN, CEILING, FLOOR, UNNECESSARY)
- ✅ `PzCurrRoundingHelper::round(string $amount, int $scale, PzCurrRoundingModeEnum $mode): string`
- ✅ Modo `UNNECESSARY` lança `PzCurrRoundingNecessaryException`
- ✅ Lookup case-insensitive de moeda (uppercase) e exceção para inválida

### O que NÃO está no escopo:
- ❌ Aritmética `bc*` (Task 4.0)
- ❌ Formatação/símbolo aplicado em saída (Task 6.0)
- ❌ Conversão de câmbio (fora do escopo da v1)

---

## Subtarefas

### 1. Value Object PzCurrCurrency
**Descrição**: Criar o VO imutável que representa uma moeda resolvida.
**Arquivos afetados**:
- `src/Currency/PzCurrCurrency.php`

**Implementação**:
```php
<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Currency;

final class PzCurrCurrency
{
    public function __construct(
        public readonly string $code,        // 'BRL'
        public readonly int $numericCode,    // 986
        public readonly int $scale,          // 2 (JPY = 0)
        public readonly string $symbol,      // 'R$'
    ) {}
}
```

---

### 2. Catálogo PzCurrCurrencyRegistry
**Descrição**: Catálogo ISO 4217 como mapa estático carregado uma vez por processo, com suporte a registro de moedas customizadas em runtime.
**Arquivos afetados**:
- `src/Currency/PzCurrCurrencyRegistry.php`

**Implementação**:
```php
final class PzCurrCurrencyRegistry
{
    /** @var array<string, PzCurrCurrency> */
    private static array $custom = [];

    /** @var array<string, array{numeric:int, scale:int, symbol:string}> */
    private const ISO = [
        'BRL' => ['numeric' => 986, 'scale' => 2, 'symbol' => 'R$'],
        'USD' => ['numeric' => 840, 'scale' => 2, 'symbol' => '$'],
        'EUR' => ['numeric' => 978, 'scale' => 2, 'symbol' => '€'],
        'JPY' => ['numeric' => 392, 'scale' => 0, 'symbol' => '¥'],
        // ... ampliar catálogo ISO conforme necessário
    ];

    public static function of(string $code): PzCurrCurrency { /* uppercase + lookup; lança PzCurrInvalidCurrencyException */ }

    public static function register(PzCurrCurrency $currency): void { /* grava em $custom */ }

    public static function has(string $code): bool { /* ... */ }
}
```
> A resolução deve normalizar para uppercase, consultar `$custom` antes do `ISO`, e lançar `PzCurrInvalidCurrencyException::forCode()` quando ausente.

---

### 3. Enum PzCurrRoundingModeEnum
**Descrição**: Enum string com os 8 modos de arredondamento.
**Arquivos afetados**:
- `src/Enum/PzCurrRoundingModeEnum.php`

**Implementação**:
```php
<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Enum;

enum PzCurrRoundingModeEnum: string
{
    case HALF_UP     = 'HALF_UP';
    case HALF_DOWN   = 'HALF_DOWN';
    case HALF_EVEN   = 'HALF_EVEN';
    case UP          = 'UP';
    case DOWN        = 'DOWN';
    case CEILING     = 'CEILING';
    case FLOOR       = 'FLOOR';
    case UNNECESSARY = 'UNNECESSARY';
}
```

---

### 4. Helper PzCurrRoundingHelper
**Descrição**: Implementar o arredondamento sobre string decimal, sem `float` e sem `bcround`. Decide o ajuste a partir do dígito imediatamente após a escala alvo e do sinal/paridade conforme o modo.
**Arquivos afetados**:
- `src/Support/PzCurrRoundingHelper.php`

**Implementação**:
```php
final class PzCurrRoundingHelper
{
    /**
     * Arredonda a string $amount para $scale casas conforme $mode.
     * Toda a lógica opera sobre strings; nenhum cast para float.
     */
    public static function round(
        string $amount,
        int $scale,
        PzCurrRoundingModeEnum $mode
    ): string {
        // 1. Se já está na escala (sem dígitos excedentes), retorna como está.
        // 2. UNNECESSARY: se houver dígito excedente != 0 → PzCurrRoundingNecessaryException.
        // 3. Demais modos: analisar dígito-guia e resto; truncar e somar/subtrair 1 ulp via bcadd/bcsub.
        // 4. HALF_EVEN: considerar paridade do último dígito mantido.
        // 5. CEILING/FLOOR/UP/DOWN: considerar sinal do número.
    }
}
```

---

### 5. Testes Unitários
**Descrição**: Tabela exaustiva de arredondamento por modo + resolução de moeda.

**Arquivos de teste**:
- `tests/Unit/Support/PzCurrRoundingHelperTest.php`
- `tests/Unit/Currency/PzCurrCurrencyRegistryTest.php`

**Casos de teste obrigatórios**:
1. **Tabela por modo (data provider)**
   - **Cenário**: valores como `'2.345'`, `'2.355'`, `'-2.345'`, `'2.5'` com escala 2/0 para cada modo.
   - **Expectativa**: resultado bate com a definição (ex.: HALF_UP de `2.345`→`2.35`; HALF_EVEN de `2.345`→`2.34`; HALF_EVEN de `2.355`→`2.36`).
2. **UNNECESSARY com resto**
   - **Cenário**: `round('2.345', 2, UNNECESSARY)`.
   - **Expectativa**: lança `PzCurrRoundingNecessaryException`.
3. **UNNECESSARY sem resto**
   - **Cenário**: `round('2.30', 2, UNNECESSARY)`.
   - **Expectativa**: retorna `'2.30'` sem lançar.
4. **Sinal em CEILING/FLOOR**
   - **Cenário**: negativos com CEILING e FLOOR.
   - **Expectativa**: CEILING tende a +inf, FLOOR a -inf.
5. **Resolução de moeda ISO**
   - **Cenário**: `of('brl')`, `of('JPY')`.
   - **Expectativa**: escala 2 e 0 respectivamente; uppercase aplicado.
6. **Moeda inválida**
   - **Cenário**: `of('XXX')` não registrada.
   - **Expectativa**: lança `PzCurrInvalidCurrencyException`.

**Cobertura mínima**: 95% do helper e do registry.

---

### 6. Testes de Integração
**Descrição**: Garantir que o registry e o helper operam em conjunto (moeda customizada com escala própria sendo arredondada).

**Arquivos de teste**:
- `tests/Features/Currency/CurrencyRoundingIntegrationTest.php`

**Cenários de teste obrigatórios**:
1. **Moeda customizada + arredondamento**
   - **Fluxo**: `register(new PzCurrCurrency('BTC', 0, 8, '₿'))` → `RoundingHelper::round('1.234567895', 8, HALF_UP)`.
   - **Expectativa**: resultado com 8 casas correto; moeda resolvível por `of('BTC')`.

---

## Critérios de Aceitação

- [ ] `declare(strict_types=1)` em todos os arquivos
- [ ] Nenhum cast para `float` no helper (verificável por revisão/teste)
- [ ] Todos os 8 modos de arredondamento implementados e testados
- [ ] `UNNECESSARY` lança exceção apenas quando há resto
- [ ] Registry resolve BRL/USD/EUR/JPY e moedas customizadas
- [ ] Tipagem estrita, sem `mixed`
- [ ] Todos os testes unitários e de integração passando
- [ ] PHPDoc no helper explicando a estratégia de string
- [ ] Sem erros de lint/type-check

---

## Entregáveis

**Arquivos Criados**:
- [ ] `src/Currency/PzCurrCurrency.php`
- [ ] `src/Currency/PzCurrCurrencyRegistry.php`
- [ ] `src/Enum/PzCurrRoundingModeEnum.php`
- [ ] `src/Support/PzCurrRoundingHelper.php`
- [ ] `tests/Unit/Support/PzCurrRoundingHelperTest.php`
- [ ] `tests/Unit/Currency/PzCurrCurrencyRegistryTest.php`
- [ ] `tests/Features/Currency/CurrencyRoundingIntegrationTest.php`

**Arquivos Modificados**:
- [ ] Nenhum

**Documentação**:
- [ ] PHPDoc detalhado em `PzCurrRoundingHelper::round`

---

## Guia de Implementação

### Passo 1: VO e Enum
Implementar `PzCurrCurrency` e `PzCurrRoundingModeEnum` (sem lógica, apenas estrutura).

### Passo 2: Registry
Implementar o catálogo ISO e os métodos `of`/`register`/`has`. Normalizar uppercase.

### Passo 3: Helper de arredondamento (TDD)
Escrever a tabela de testes primeiro (data provider), depois implementar `round` modo a modo, usando `bcadd`/`bcsub` para somar/subtrair 1 unidade na última posição (ulp).

```bash
./vendor/bin/phpunit --testsuite Unit
```

---

## Validação

### Checklist de Validação Manual:
- [ ] HALF_EVEN trata `.5` exato pela paridade
- [ ] Negativos com UP/DOWN/CEILING/FLOOR corretos
- [ ] Moeda customizada com escala 8 funciona

### Comandos de Validação:
```bash
./vendor/bin/phpunit tests/Unit/Support/PzCurrRoundingHelperTest.php
./vendor/bin/phpunit tests/Features/Currency
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Erro de borda no arredondamento `.5` | Alto | Média | Data provider exaustivo cobrindo todos os modos e sinais |
| Catálogo ISO incompleto | Baixo | Média | Iniciar com BRL/USD/EUR/JPY; arquitetura permite expandir sem quebra |

---

## Notas Adicionais

O helper é a **fonte única de verdade** de arredondamento de todo o pacote. Não duplicar lógica de arredondamento no adapter (Task 4.0); o adapter deve sempre delegar a este helper.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-06-04 | Equipe Puzl | Criação da tarefa |
