# Task 3.0: Contract Público e Base Abstrata

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Alta
**Dependências**: Task 2.0
**Assignee**: Não atribuído

---

## Objetivo

Definir o contrato público estável `PzCurrInterface` (a "linguagem" do pacote) e implementar a classe abstrata `PzCurrBase`, que mantém o estado fluente (amount, currency, scale, rounding mode default) e as validações de moeda — **sem conhecer BCMath**. Entregável: contrato completo + base testável de forma isolada via stub concreto, preservando o desacoplamento (RNF-02).

---

## Contexto

O desacoplamento é central no PzCurr: consumidores dependem apenas de `PzCurrInterface`, nunca do motor de cálculo. A `PzCurrBase` concentra o estado fluente e as regras independentes de tecnologia (validação de moeda compatível, `copy()`), enquanto o cálculo concreto fica no adapter (Task 4.0). Um `PzCurrBaseStub` permite testar a fluência sem precisar do BCMath.

**Referências**:
- PRD: `tasks/prd-pzcurr/prd.md` - RF-02, RF-04, RNF-02, RNF-05, RN-02
- Tech Spec: `tasks/prd-pzcurr/techspec.md` - Seção 2.1 (diagrama), 2.3 (Base não conhece bcmath), 3.2 (interface), 8.1 (stub), 11.1 (Fase 3)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `PzCurrInterface` com todos os métodos (criação, aritmética, escala/arredondamento, comparação/sinal, extração/formatação, serialização, `copy`)
- ✅ `PzCurrBase` abstrata: propriedades de estado, construtor, `getCurrency`/`getScale`/`getAmount`/`toDecimal`, `withRoundingMode`, `copy`, validação de moeda (`assertSameCurrency`)
- ✅ Declaração de métodos abstratos para o cálculo (a serem implementados pelo adapter)
- ✅ `PzCurrBaseStub` (teste) — concretiza os abstratos com implementação trivial/fake
- ✅ Lançamento de `PzCurrencyMismatchException` em operação entre moedas distintas

### O que NÃO está no escopo:
- ❌ Implementação real de `add`/`subtract`/etc com `bc*` (Task 4.0)
- ❌ Allocate/split (Task 5.0), formatação (Task 6.0), Factory (Task 7.0)

---

## Subtarefas

### 1. Interface PzCurrInterface
**Descrição**: Definir o contrato público estável conforme a Tech Spec §3.2, estendendo `\JsonSerializable`.
**Arquivos afetados**:
- `src/Contract/PzCurrInterface.php`

**Implementação**:
```php
<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Contract;

use Puzl\PzCurr\Currency\PzCurrCurrency;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;

interface PzCurrInterface extends \JsonSerializable
{
    public function of(string|int $amount, string $currency): self;
    public function ofMinor(int $minorAmount, string $currency): self;
    public function zero(string $currency): self;

    public function add(self|string|int ...$values): self;
    public function subtract(self|string|int ...$values): self;
    public function multiply(string|int $factor, ?PzCurrRoundingModeEnum $mode = null): self;
    public function divide(string|int $divisor, ?PzCurrRoundingModeEnum $mode = null): self;
    public function mod(string|int $divisor): self;
    public function absolute(): self;
    public function negated(): self;

    /** @param array<int|string, int|float|string> $ratios @return array<int|string, self> */
    public function allocate(array $ratios): array;
    /** @return array<int, self> */
    public function split(int $parts): array;
    public function ratioOf(self $other): string;

    public function withScale(int $scale, ?PzCurrRoundingModeEnum $mode = null): self;
    public function withRoundingMode(PzCurrRoundingModeEnum $mode): self;

    public function compareTo(self|string|int $other): int;
    public function isEqualTo(self|string|int $other): bool;
    public function isGreaterThan(self|string|int $other): bool;
    public function isGreaterThanOrEqualTo(self|string|int $other): bool;
    public function isLessThan(self|string|int $other): bool;
    public function isLessThanOrEqualTo(self|string|int $other): bool;
    public function isZero(): bool;
    public function isPositive(): bool;
    public function isPositiveOrZero(): bool;
    public function isNegative(): bool;
    public function isNegativeOrZero(): bool;
    public function getSign(): int;
    public function isSameValueAs(self $other): bool;

    public function getAmount(): string;
    public function toDecimal(): string;
    public function getMinorAmount(): int;
    public function getCurrency(): PzCurrCurrency;
    public function getScale(): int;
    public function format(?string $locale = null): string;

    /** @return array{amount: string, currency: string, scale: int} */
    public function toArray(): array;
    public function jsonSerialize(): array;

    public function copy(): self;
}
```

---

### 2. Classe abstrata PzCurrBase
**Descrição**: Implementar o estado fluente e os métodos independentes de tecnologia. Métodos que exigem cálculo `bc*` são declarados `abstract` para o adapter implementar.
**Arquivos afetados**:
- `src/Adapter/PzCurrBase.php`

**Implementação**:
```php
abstract class PzCurrBase implements PzCurrInterface
{
    protected string $amount = '0';            // nunca float
    protected ?PzCurrCurrency $currency = null;
    protected int $scale = 0;
    protected PzCurrRoundingModeEnum $roundingMode;

    // Métodos concretos (sem bcmath):
    public function getAmount(): string { return $this->amount; }
    public function toDecimal(): string { return $this->amount; }
    public function getCurrency(): PzCurrCurrency { /* ... */ }
    public function getScale(): int { return $this->scale; }
    public function withRoundingMode(PzCurrRoundingModeEnum $mode): self { $this->roundingMode = $mode; return $this; }
    public function copy(): self { return clone $this; }
    public function toArray(): array { return ['amount' => $this->amount, 'currency' => $this->currency->code, 'scale' => $this->scale]; }
    public function jsonSerialize(): array { return $this->toArray(); }

    // Helper de validação de moeda (RN-02):
    protected function assertSameCurrency(PzCurrInterface $other): void
    {
        if ($other->getCurrency()->code !== $this->currency->code) {
            throw PzCurrencyMismatchException::between($this->currency->code, $other->getCurrency()->code);
        }
    }

    // Métodos que dependem de cálculo → abstratos (implementados pelo adapter):
    abstract public function of(string|int $amount, string $currency): self;
    abstract public function add(self|string|int ...$values): self;
    // ... demais métodos de cálculo abstratos
}
```
> Definir como `abstract` apenas o que exige `bc*`; manter concreto o que é puramente estado/estrutura.

---

### 3. Stub concreto para testes
**Descrição**: Criar `PzCurrBaseStub` que estende a base e implementa os métodos abstratos de forma trivial (sem `bc*` real), permitindo testar o estado fluente isoladamente.
**Arquivos afetados**:
- `tests/Unit/Adapter/PzCurrBaseStub.php` (helper de teste, não vai em `src`)

**Implementação**:
```php
final class PzCurrBaseStub extends PzCurrBase
{
    public function of(string|int $amount, string $currency): self
    {
        $this->currency = PzCurrCurrencyRegistry::of($currency);
        $this->scale = $this->currency->scale;
        $this->amount = (string) $amount;
        return $this;
    }
    // implementações fake mínimas dos demais abstratos para exercitar fluência
}
```

---

### 4. Testes Unitários
**Descrição**: Validar estado fluente, `copy`, `withRoundingMode`, `toArray`/`jsonSerialize` e a validação de moeda.

**Arquivos de teste**:
- `tests/Unit/Adapter/PzCurrBaseTest.php`

**Casos de teste obrigatórios**:
1. **Fluência retorna a mesma instância**
   - **Cenário**: `$pz->of('1','BRL')->withRoundingMode(HALF_UP)`.
   - **Expectativa**: cada método retorna `$this` (mesma referência via `assertSame`).
2. **copy() gera nova instância independente**
   - **Cenário**: `$b = $a->copy()` e alterar `$b`.
   - **Expectativa**: `$a` permanece inalterado; `$a !== $b`.
3. **Mismatch de moeda**
   - **Cenário**: `assertSameCurrency` entre BRL e USD (via método público de teste ou operação stub).
   - **Expectativa**: lança `PzCurrencyMismatchException`.
4. **toArray/jsonSerialize**
   - **Cenário**: objeto em `'19.90' BRL`.
   - **Expectativa**: `['amount'=>'19.90','currency'=>'BRL','scale'=>2]`.

**Cobertura mínima**: 95% da `PzCurrBase`.

---

### 5. Testes de Integração
**Descrição**: Exercitar Base + Registry + Enum juntos via stub.

**Arquivos de teste**:
- `tests/Features/Adapter/BaseDomainIntegrationTest.php`

**Cenários de teste obrigatórios**:
1. **Base resolve moeda do Registry e escala**
   - **Fluxo**: `stub->of('100','JPY')`.
   - **Expectativa**: `getScale()` = 0; `getCurrency()->code` = 'JPY'.

---

## Critérios de Aceitação

- [ ] `declare(strict_types=1)` em todos os arquivos
- [ ] `PzCurrBase` não referencia nenhuma função `bc*` nem terceiros (verificável)
- [ ] Todos os métodos fluentes retornam `$this`
- [ ] `copy()` produz instância independente
- [ ] Mismatch de moeda lança `PzCurrencyMismatchException`
- [ ] Tipagem estrita, sem `mixed`
- [ ] Todos os testes unitários e de integração passando
- [ ] PHPDoc no contrato e nos métodos não óbvios
- [ ] Sem erros de lint/type-check

---

## Entregáveis

**Arquivos Criados**:
- [ ] `src/Contract/PzCurrInterface.php`
- [ ] `src/Adapter/PzCurrBase.php`
- [ ] `tests/Unit/Adapter/PzCurrBaseStub.php`
- [ ] `tests/Unit/Adapter/PzCurrBaseTest.php`
- [ ] `tests/Features/Adapter/BaseDomainIntegrationTest.php`

**Arquivos Modificados**:
- [ ] Nenhum

**Documentação**:
- [ ] PHPDoc no contrato `PzCurrInterface`

---

## Guia de Implementação

### Passo 1: Contrato
Transcrever a `PzCurrInterface` da Tech Spec §3.2 com os imports corretos.

### Passo 2: Base abstrata
Implementar estado + métodos concretos; declarar abstratos os que exigem cálculo.

### Passo 3: Stub + testes
Criar o stub e os testes de fluência/copy/mismatch.

```bash
./vendor/bin/phpunit tests/Unit/Adapter tests/Features/Adapter
```

---

## Validação

### Checklist de Validação Manual:
- [ ] Nenhuma chamada `bc*` aparece em `src/Adapter/PzCurrBase.php`
- [ ] Encadeamento fluente compila e roda no stub

### Comandos de Validação:
```bash
./vendor/bin/phpunit --testsuite Unit
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Vazamento de BCMath para a Base | Médio | Baixa | Manter cálculo abstrato; teste de convenção verifica ausência de `bc*` na Base |
| Modelo mutável causar efeito colateral | Médio | Média | `copy()` testado garantindo independência |

---

## Notas Adicionais

A divisão entre "estado na Base" e "cálculo no adapter" é o que permite, no futuro, plugar `BRICK_MONEY`/`MONEYPHP` sem reescrever a Base. Resista à tentação de colocar `bc*` aqui.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-06-04 | Equipe Puzl | Criação da tarefa |
