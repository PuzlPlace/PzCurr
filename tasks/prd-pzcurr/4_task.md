# Task 4.0: Adapter BCMath — Criação, Aritmética e Comparações

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Alta
**Dependências**: Task 3.0
**Assignee**: Não atribuído

---

## Objetivo

Implementar o adapter concreto `PzCurrBcMath`, que estende `PzCurrBase` e provê o motor de cálculo exato via funções `bc*`: criação (`of`/`ofMinor`/`zero`), aritmética fluente (`add`/`subtract`/`multiply`/`divide`/`mod`/`absolute`/`negated`/`ratioOf`) e comparações/sinal (`compareTo`, `is*`, `getSign`, `isSameValueAs`). Entregável: valores monetários com cálculo 100% exato (zero `float`), encadeáveis, com não-regressão garantida por testes.

---

## Contexto

Este é o coração funcional do pacote. Toda matemática usa `bcadd`/`bcsub`/`bcmul`/`bcdiv`/`bcmod`/`bccomp` sobre strings, com escala de trabalho (escala da moeda + margem) e arredondamento final único via `PzCurrRoundingHelper` (Task 2.0). As operações são mutáveis e retornam `$this`. Allocate/split ficam para a Task 5.0; formatação/serialização para a 6.0.

**Referências**:
- PRD: `tasks/prd-pzcurr/prd.md` - RF-01, RF-02, RF-03, RF-04, RN-01, RN-02, RN-04, RN-05
- Tech Spec: `tasks/prd-pzcurr/techspec.md` - Seção 2.2 (fluxo), 6.2 (checagem extensão), 7.2 (otimizações), 11.1 (Fase 4)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `PzCurrBcMath` (extends `PzCurrBase`)
- ✅ Checagem de `ext-bcmath` no construtor (`PzCurrMissingExtensionException`)
- ✅ Criação: `of` (valida moeda, normaliza string, aplica escala), `ofMinor` (`bcdiv` por `10^scale`), `zero`
- ✅ Aritmética: `add`/`subtract` (variádicos), `multiply`, `divide`, `mod`, `absolute`, `negated`, `ratioOf`
- ✅ Escala/arredondamento por operação (`withScale`, parâmetro `?PzCurrRoundingModeEnum`)
- ✅ Comparações/sinal: `compareTo`, `isEqualTo`, `isGreaterThan(OrEqualTo)`, `isLessThan(OrEqualTo)`, `isZero`, `isPositive(OrZero)`, `isNegative(OrZero)`, `getSign`, `isSameValueAs`
- ✅ Validação estrita de entrada numérica (regex) → `PzCurrInvalidAmountException`

### O que NÃO está no escopo:
- ❌ `allocate`/`split` (Task 5.0)
- ❌ `getMinorAmount` extração final/formatação/serialização (Task 6.0 — exceto o necessário internamente)
- ❌ Factory/config (Task 7.0)

---

## Subtarefas

### 1. Construtor e checagem de extensão
**Descrição**: Garantir `ext-bcmath` cedo, com mensagem clara.
**Arquivos afetados**:
- `src/Adapter/BcMath/PzCurrBcMath.php`

**Implementação**:
```php
final class PzCurrBcMath extends PzCurrBase
{
    public function __construct(?PzCurrRoundingModeEnum $defaultMode = null)
    {
        if (!extension_loaded('bcmath')) {
            throw PzCurrMissingExtensionException::bcmath();
        }
        $this->roundingMode = $defaultMode ?? PzCurrRoundingModeEnum::HALF_UP;
    }
}
```

---

### 2. Criação de valores
**Descrição**: Implementar `of`, `ofMinor`, `zero` com validação e normalização sem `float`.
**Arquivos afetados**:
- `src/Adapter/BcMath/PzCurrBcMath.php`

**Implementação**:
```php
public function of(string|int $amount, string $currency): self
{
    $this->currency = PzCurrCurrencyRegistry::of($currency);
    $this->scale = $this->currency->scale;
    $normalized = $this->assertNumericString((string) $amount); // regex; lança PzCurrInvalidAmountException
    $this->amount = PzCurrRoundingHelper::round($normalized, $this->scale, $this->roundingMode);
    return $this;
}

public function ofMinor(int $minorAmount, string $currency): self
{
    $this->currency = PzCurrCurrencyRegistry::of($currency);
    $this->scale = $this->currency->scale;
    $divisor = bcpow('10', (string) $this->scale);
    $this->amount = bcdiv((string) $minorAmount, $divisor, $this->scale);
    return $this;
}

public function zero(string $currency): self { return $this->of('0', $currency); }
```

---

### 3. Aritmética fluente
**Descrição**: Operações `bc*` com escala de trabalho e arredondamento final. `add`/`subtract` variádicos acumulam em loop único. Operações com outro `PzCurr` validam moeda via `assertSameCurrency`.
**Arquivos afetados**:
- `src/Adapter/BcMath/PzCurrBcMath.php`

**Implementação**:
```php
public function add(self|string|int ...$values): self
{
    foreach ($values as $v) {
        $operand = $this->operandToString($v); // se PzCurr → assertSameCurrency + getAmount
        $this->amount = bcadd($this->amount, $operand, $this->workingScale());
    }
    $this->amount = PzCurrRoundingHelper::round($this->amount, $this->scale, $this->roundingMode);
    return $this;
}

public function multiply(string|int $factor, ?PzCurrRoundingModeEnum $mode = null): self
{
    $this->amount = bcmul($this->amount, (string) $factor, $this->workingScale());
    $this->amount = PzCurrRoundingHelper::round($this->amount, $this->scale, $mode ?? $this->roundingMode);
    return $this;
}

public function divide(string|int $divisor, ?PzCurrRoundingModeEnum $mode = null): self { /* bcdiv + round */ }
public function mod(string|int $divisor): self { /* bcmod */ }
public function absolute(): self { /* remove sinal via bccomp/strip */ }
public function negated(): self { /* bcmul por -1 */ }
public function ratioOf(self $other): string { /* assertSameCurrency + bcdiv com escala alta */ }
```
> `workingScale()` = escala da moeda + margem fixa (ex.: +10) para precisão intermediária; arredondamento final reaplica `$this->scale`.

---

### 4. Comparações e sinal
**Descrição**: Implementar comparações com `bccomp` na maior escala entre operandos; `isSameValueAs` não lança em mismatch.
**Arquivos afetados**:
- `src/Adapter/BcMath/PzCurrBcMath.php`

**Implementação**:
```php
public function compareTo(self|string|int $other): int
{
    $operand = $this->operandToString($other); // valida moeda se PzCurr
    return bccomp($this->amount, $operand, $this->workingScale());
}
public function isZero(): bool { return bccomp($this->amount, '0', $this->scale) === 0; }
public function getSign(): int { return bccomp($this->amount, '0', $this->scale); }
public function isSameValueAs(self $other): bool
{
    return $other->getCurrency()->code === $this->currency->code
        && bccomp($this->amount, $other->getAmount(), $this->scale) === 0;
}
// isEqualTo/isGreaterThan(OrEqualTo)/isLessThan(OrEqualTo)/isPositive(OrZero)/isNegative(OrZero) derivam de compareTo/getSign
```

---

### 5. withScale
**Descrição**: Permitir escala customizada por instância, reaplicando arredondamento.
**Arquivos afetados**:
- `src/Adapter/BcMath/PzCurrBcMath.php`

**Implementação**:
```php
public function withScale(int $scale, ?PzCurrRoundingModeEnum $mode = null): self
{
    $this->amount = PzCurrRoundingHelper::round($this->amount, $scale, $mode ?? $this->roundingMode);
    $this->scale = $scale;
    return $this;
}
```

---

### 6. Testes Unitários
**Descrição**: Não-regressão exaustiva da criação, aritmética, comparação, arredondamento por operação e mismatch.

**Arquivos de teste**:
- `tests/Unit/Adapter/BcMath/PzCurrBcMathCreationTest.php`
- `tests/Unit/Adapter/BcMath/PzCurrBcMathArithmeticTest.php`
- `tests/Unit/Adapter/BcMath/PzCurrBcMathComparisonTest.php`

**Casos de teste obrigatórios**:
1. **Criação válida e inválida**
   - **Cenário**: `of('19.90','BRL')`, `ofMinor(1990,'BRL')`, `zero('BRL')`, e `of('abc','BRL')`.
   - **Expectativa**: 3 primeiros corretos; o último lança `PzCurrInvalidAmountException`.
2. **Precisão clássica (sem float)**
   - **Cenário**: `of('0.1','BRL')->add('0.2')`.
   - **Expectativa**: `getAmount()` === `'0.30'` (não `0.30000000004`).
3. **add/subtract variádicos**
   - **Cenário**: `add('1','2','3')`.
   - **Expectativa**: acumula corretamente; retorna `$this`.
4. **multiply/divide com modo**
   - **Cenário**: divisão que gera dízima com cada modo de arredondamento.
   - **Expectativa**: resultado bate com o modo passado.
5. **Mismatch de moeda**
   - **Cenário**: `of('1','BRL')->add(of('1','USD'))`.
   - **Expectativa**: lança `PzCurrencyMismatchException`.
6. **Comparações e sinal**
   - **Cenário**: tabela com `compareTo`, todos os `is*`, `getSign`.
   - **Expectativa**: booleanos/int corretos; `isSameValueAs` não lança em mismatch (retorna `false`).
7. **Encadeamento fluente**
   - **Cenário**: `of('25.00','BRL')->add('4.99')->subtract('2.50')->multiply(2)`.
   - **Expectativa**: `'54.98'` e cada etapa retorna a mesma instância.

**Cobertura mínima**: 95% do adapter (exceto allocate/split/format).

---

### 7. Testes de Integração
**Descrição**: Caso de uso principal fim-a-fim (sem Laravel ainda).

**Arquivos de teste**:
- `tests/Features/Adapter/BcMathUseCaseTest.php`

**Cenários de teste obrigatórios**:
1. **Cálculo de pedido**
   - **Fluxo**: `of('25.00','BRL')->add('4.99')->subtract('2.50')->multiply(2)`.
   - **Expectativa**: `getAmount()` === `'54.98'`.
2. **Divisão com arredondamento default da config**
   - **Fluxo**: `of('10.00','BRL')->divide(3)`.
   - **Expectativa**: `'3.33'` com HALF_UP.

---

## Critérios de Aceitação

- [ ] `declare(strict_types=1)` em todos os arquivos
- [ ] **Zero `float`**: nenhuma conversão para float em qualquer caminho (verificável)
- [ ] Toda aritmética usa `bc*`
- [ ] `ext-bcmath` ausente lança `PzCurrMissingExtensionException` no construtor
- [ ] Operações entre moedas diferentes lançam `PzCurrencyMismatchException`
- [ ] Arredondamento delega sempre ao `PzCurrRoundingHelper`
- [ ] Tipagem estrita, sem `mixed`
- [ ] Todos os testes unitários e de integração passando
- [ ] PHPDoc nos métodos de cálculo não triviais
- [ ] Sem erros de lint/type-check

---

## Entregáveis

**Arquivos Criados**:
- [ ] `src/Adapter/BcMath/PzCurrBcMath.php`
- [ ] `tests/Unit/Adapter/BcMath/PzCurrBcMathCreationTest.php`
- [ ] `tests/Unit/Adapter/BcMath/PzCurrBcMathArithmeticTest.php`
- [ ] `tests/Unit/Adapter/BcMath/PzCurrBcMathComparisonTest.php`
- [ ] `tests/Features/Adapter/BcMathUseCaseTest.php`

**Arquivos Modificados**:
- [ ] `src/Adapter/PzCurrBase.php` (se ajustes de assinatura abstrata forem necessários)

**Documentação**:
- [ ] PHPDoc explicando `workingScale()` e estratégia de arredondamento final

---

## Guia de Implementação

### Passo 1: Construtor + criação (TDD)
Implementar checagem de extensão e `of`/`ofMinor`/`zero`, com testes de criação e validação.

### Passo 2: Aritmética
Implementar operações com escala de trabalho + arredondamento final. Cobrir variádicos e mismatch.

### Passo 3: Comparações
Implementar `compareTo` e derivar os `is*`/`getSign`/`isSameValueAs`.

```bash
./vendor/bin/phpunit tests/Unit/Adapter/BcMath tests/Features/Adapter
```

---

## Validação

### Checklist de Validação Manual:
- [ ] `0.1 + 0.2 === 0.30` (string)
- [ ] Divisão com cada modo de arredondamento confere
- [ ] Encadeamento longo mantém precisão

### Comandos de Validação:
```bash
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Features
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Perda de precisão por escala de trabalho insuficiente | Alto | Média | Margem fixa generosa na `workingScale`; testes de dízima |
| `float` acidental em casts | Alto | Média | Revisão + teste comparando strings exatas |
| Mismatch não detectado em operandos string | Médio | Baixa | `operandToString` só valida moeda quando operando é `PzCurr` |

---

## Notas Adicionais

`getMinorAmount` final, formatação e serialização ficam na Task 6.0, mas a representação interna `string` já viabiliza ambas. Não implemente allocate/split aqui (Task 5.0).

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-06-04 | Equipe Puzl | Criação da tarefa |
