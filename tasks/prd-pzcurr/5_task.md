# Task 5.0: Allocate / Split (Conservação de Centavos)

## Informações Gerais

**Status**: ✅ Concluída
**Prioridade**: Alta
**Dependências**: Task 4.0
**Assignee**: Não atribuído

---

## Objetivo

Implementar o `PzCurrAllocator` (algoritmo de maior resto fracionário — *largest remainder*) e os métodos `allocate(array $ratios)` e `split(int $parts)` no adapter `PzCurrBcMath`, garantindo que a soma das partes seja **exatamente** igual ao valor original, sem perder ou criar centavos (RN-03). Entregável: distribuição justa, determinística e independente da ordem dos ratios.

---

## Contexto

Dividir dinheiro raramente é exato (ex.: R$ 10,00 / 3). O algoritmo de maior resto distribui o "piso" de cada parte e depois aloca os centavos restantes às partes com maior resíduo fracionário, garantindo conservação total. Esse comportamento espelha MoneyPHP/Brick e é exigido pelo PRD (RN-03) e pelo caso de uso alternativo (5.1).

**Referências**:
- PRD: `tasks/prd-pzcurr/prd.md` - RF-02 (allocate/split), RN-03, Caso de uso 5.1 (fluxo alternativo)
- Tech Spec: `tasks/prd-pzcurr/techspec.md` - Seção 2.3 (allocate por maior resto), 8.2 (testes de conservação), 11.1 (Fase 4)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `PzCurrAllocator::allocate(string $amount, array $ratios, int $scale): array<int|string,string>`
- ✅ `PzCurrAllocator::split(string $amount, int $parts, int $scale): array<int,string>`
- ✅ `PzCurrBcMath::allocate(array $ratios): array<int|string, self>`
- ✅ `PzCurrBcMath::split(int $parts): array<int, self>`
- ✅ Conservação exata `Σ partes == total` (incl. valores negativos)
- ✅ Preservação das chaves dos ratios (associativo) em `allocate`

### O que NÃO está no escopo:
- ❌ Formatação das partes (Task 6.0)
- ❌ Conversão de câmbio / múltiplas moedas
- ❌ `MoneyBag` (fora do escopo da v1)

---

## Subtarefas

### 1. PzCurrAllocator (largest-remainder)
**Descrição**: Algoritmo puro sobre strings: calcula o piso de cada parte por ratio, soma os pisos, e distribui o resto (em unidades da menor escala) às partes com maior resíduo fracionário, do maior para o menor.
**Arquivos afetados**:
- `src/Support/PzCurrAllocator.php`

**Implementação**:
```php
final class PzCurrAllocator
{
    /**
     * @param array<int|string, int|float|string> $ratios
     * @return array<int|string, string> partes na escala informada; Σ partes == amount
     */
    public static function allocate(string $amount, array $ratios, int $scale): array
    {
        // 1. Converte cada ratio para string; soma total de ratios (bcadd).
        // 2. Trabalha em unidades menores: total minor = amount * 10^scale (bcmul → int string).
        // 3. Para cada ratio: piso = floor(totalMinor * ratio / totalRatios) via bc* (sem float).
        // 4. Resto = totalMinor - Σ pisos.
        // 5. Distribui 1 unidade menor por vez às partes com maior resíduo (desempate por ordem estável).
        // 6. Converte cada parte de volta para decimal na escala (bcdiv).
    }

    /** @return array<int, string> */
    public static function split(string $amount, int $parts, int $scale): array
    {
        // ratios = array_fill(0, $parts, 1); delega para allocate.
    }
}
```
> Toda a matemática é `bc*`/inteiros-string. Nenhum `float`, mesmo recebendo `float` em `$ratios` (converter via formatação controlada para string antes).

---

### 2. Métodos allocate/split no adapter
**Descrição**: Expor os métodos no `PzCurrBcMath`, retornando novas instâncias `PzCurr` (cópias) por parte, preservando moeda e escala.
**Arquivos afetados**:
- `src/Adapter/BcMath/PzCurrBcMath.php`

**Implementação**:
```php
public function allocate(array $ratios): array
{
    $parts = PzCurrAllocator::allocate($this->amount, $ratios, $this->scale);
    return array_map(fn (string $p) => $this->copy()->setAmountInternal($p), $parts);
}

public function split(int $parts): array
{
    $values = PzCurrAllocator::split($this->amount, $parts, $this->scale);
    return array_map(fn (string $p) => $this->copy()->setAmountInternal($p), $values);
}
```
> `setAmountInternal` é um helper protegido para gravar o amount já calculado sem re-arredondar.

---

### 3. Testes Unitários
**Descrição**: Conservação para vários cenários; independência de ordem; negativos.

**Arquivos de teste**:
- `tests/Unit/Support/PzCurrAllocatorTest.php`

**Casos de teste obrigatórios**:
1. **Split exato**
   - **Cenário**: `split('10.00', 2, 2)`.
   - **Expectativa**: `['5.00','5.00']`; soma = `'10.00'`.
2. **Split com sobra**
   - **Cenário**: `split('10.00', 3, 2)`.
   - **Expectativa**: `['3.34','3.33','3.33']` (ou distribuição equivalente); **soma === '10.00'**.
3. **Allocate por ratios**
   - **Cenário**: `allocate('100.00', [1,1,1], 2)` e `allocate('0.05', [7,3], 2)`.
   - **Expectativa**: conservação exata; centavo extra vai à maior fração.
4. **Independência de ordem**
   - **Cenário**: mesmos ratios em ordens diferentes.
   - **Expectativa**: multiconjunto de resultados idêntico; soma idêntica.
5. **Valores negativos**
   - **Cenário**: `split('-10.00', 3, 2)`.
   - **Expectativa**: conservação `Σ == '-10.00'`.
6. **Chaves associativas preservadas**
   - **Cenário**: `allocate('10.00', ['a'=>1,'b'=>1], 2)`.
   - **Expectativa**: retorna chaves `'a'` e `'b'`.
7. **Escala 0 (JPY)**
   - **Cenário**: `split('100', 3, 0)`.
   - **Expectativa**: `['34','33','33']`, soma `'100'`.

**Cobertura mínima**: 100% do allocator.

---

### 4. Testes de Integração
**Descrição**: allocate/split encadeado com aritmética via adapter completo.

**Arquivos de teste**:
- `tests/Features/Adapter/AllocateIntegrationTest.php`

**Cenários de teste obrigatórios**:
1. **Pedido rateado**
   - **Fluxo**: `of('100.00','BRL')->add('0.01')` → `split(3)`.
   - **Expectativa**: 3 instâncias `PzCurr`, mesma moeda, soma dos `getAmount()` === `'100.01'`.
2. **Allocate retorna PzCurr válidos**
   - **Fluxo**: `of('0.05','BRL')->allocate([7,3])`.
   - **Expectativa**: cada parte é `PzCurrInterface`, moeda BRL, soma `'0.05'`.

---

## Critérios de Aceitação

- [ ] `declare(strict_types=1)` em todos os arquivos
- [ ] **Conservação exata** `Σ partes == total` em todos os cenários testados
- [ ] Independência da ordem dos ratios
- [ ] Funciona com valores negativos e escala 0
- [ ] Zero `float` (ratios `float` convertidos para string com segurança)
- [ ] Partes retornadas são instâncias independentes (cópias) na mesma moeda
- [ ] Tipagem estrita, sem `mixed`
- [ ] Todos os testes unitários e de integração passando
- [ ] PHPDoc no algoritmo do allocator
- [ ] Sem erros de lint/type-check

---

## Entregáveis

**Arquivos Criados**:
- [ ] `src/Support/PzCurrAllocator.php`
- [ ] `tests/Unit/Support/PzCurrAllocatorTest.php`
- [ ] `tests/Features/Adapter/AllocateIntegrationTest.php`

**Arquivos Modificados**:
- [ ] `src/Adapter/BcMath/PzCurrBcMath.php` (métodos `allocate`/`split` + helper interno)

**Documentação**:
- [ ] PHPDoc explicando o algoritmo de maior resto

---

## Guia de Implementação

### Passo 1: Allocator puro (TDD)
Escrever os testes de conservação primeiro; implementar o algoritmo trabalhando em unidades menores (inteiros-string).

### Passo 2: Integrar no adapter
Expor `allocate`/`split` retornando cópias `PzCurr`.

```bash
./vendor/bin/phpunit tests/Unit/Support/PzCurrAllocatorTest.php tests/Features/Adapter/AllocateIntegrationTest.php
```

---

## Validação

### Checklist de Validação Manual:
- [ ] `split('10.00',3)` soma exatamente `'10.00'`
- [ ] Reordenar ratios não muda a soma nem o conjunto
- [ ] Negativos conservam

### Comandos de Validação:
```bash
./vendor/bin/phpunit --filter Allocat
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Centavo perdido/criado em divisões | Alto | Média | Trabalhar em unidades menores inteiras; teste de conservação exaustivo |
| `float` em ratios introduzindo imprecisão | Médio | Média | Converter ratios para string controlada antes de qualquer cálculo |
| Desempate instável mudando resultado | Baixo | Média | Ordenação estável documentada e testada por independência de ordem |

---

## Notas Adicionais

O algoritmo deve operar em **unidades menores inteiras** internamente para evitar qualquer ambiguidade de escala, e só converter de volta para decimal no final. Isso simplifica a prova de conservação.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-06-04 | Equipe Puzl | Criação da tarefa |
