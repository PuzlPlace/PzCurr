# Task 6.0: Saída — Formatação, Extração e Serialização

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Média
**Dependências**: Task 4.0
**Assignee**: Não atribuído

---

## Objetivo

Implementar a camada de saída do pacote: o `PzCurrFormatter` (modo manual pt-BR + integração opcional com `intl`/`NumberFormatter` com degradação graciosa), a extração de valores (`getMinorAmount`, `getAmount`/`toDecimal`, `getScale`, `getCurrency`) e a serialização (`toArray`/`jsonSerialize`). Entregável: valor monetário exibível e serializável de forma estável e segura, sem `float` em nenhuma etapa.

---

## Contexto

Após o cálculo exato (Tasks 4.0/5.0), o consumidor precisa exibir, extrair e persistir o valor. A formatação foca em pt-BR (`R$ 1.234,56`) no modo manual, e usa `NumberFormatter` por locale quando `intl` estiver disponível, degradando para o modo manual caso contrário (RF-06). `getMinorAmount` retorna inteiro de centavos para persistência segura.

**Referências**:
- PRD: `tasks/prd-pzcurr/prd.md` - RF-06, RF-07, RN-01, RNF-01
- Tech Spec: `tasks/prd-pzcurr/techspec.md` - Seção 2.2 (saída), 6.3 (sanitização), 12.3 (i18n), 11.1 (Fase 5)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `PzCurrFormatter` (manual configurável + `intl` opcional)
- ✅ Detecção de `intl` (`extension_loaded('intl')`) com fallback manual
- ✅ `getMinorAmount(): int` (`bcmul` por `10^scale`, cast final para int seguro)
- ✅ `getAmount`/`toDecimal`/`getScale`/`getCurrency` (consolidar na Base/adapter)
- ✅ `format(?string $locale = null): string` no adapter delegando ao formatter
- ✅ `toArray`/`jsonSerialize` com estrutura estável `{amount, currency, scale}`

### O que NÃO está no escopo:
- ❌ Eloquent Cast (Task 8.0)
- ❌ Configuração via `config/pzcurr.php` (Task 8.0 — o formatter aceita opções por parâmetro/construtor)
- ❌ Conversão de câmbio

---

## Subtarefas

### 1. PzCurrFormatter
**Descrição**: Formatador com modo manual (separador de milhar/decimal e símbolo configuráveis, foco pt-BR) e modo `intl` por locale quando disponível.
**Arquivos afetados**:
- `src/Support/PzCurrFormatter.php`

**Implementação**:
```php
final class PzCurrFormatter
{
    public function __construct(
        private readonly string $thousandsSeparator = '.',
        private readonly string $decimalSeparator = ',',
        private readonly bool $symbolBefore = true,
    ) {}

    public function format(string $amount, PzCurrCurrency $currency, ?string $locale = null): string
    {
        if ($locale !== null && extension_loaded('intl')) {
            return $this->formatWithIntl($amount, $currency, $locale); // NumberFormatter::CURRENCY
        }
        return $this->formatManual($amount, $currency); // ex.: 'R$ 1.234,56'
    }

    private function formatManual(string $amount, PzCurrCurrency $currency): string
    {
        // Separa parte inteira/decimal pela string (sem float),
        // aplica separador de milhar na parte inteira e monta símbolo + valor.
    }

    private function formatWithIntl(string $amount, PzCurrCurrency $currency, string $locale): string
    {
        // NumberFormatter($locale, CURRENCY); formatCurrency exige float →
        // como alternativa segura, usar pattern/format sobre string ou documentar a borda.
    }
}
```
> **Atenção (RN-01)**: `NumberFormatter::formatCurrency` recebe `float`. Para respeitar "zero float", preferir `NumberFormatter` apenas para descobrir o *pattern*/símbolo do locale e montar a string manualmente, OU documentar claramente que o caminho `intl` é apenas de exibição (nunca de cálculo/persistência). Decisão a registrar no PHPDoc.

---

### 2. Extração de valores
**Descrição**: Consolidar os getters de saída; `getMinorAmount` converte para inteiro de unidades menores.
**Arquivos afetados**:
- `src/Adapter/BcMath/PzCurrBcMath.php` (ou `PzCurrBase` onde fizer sentido sem `bc*`)

**Implementação**:
```php
public function getMinorAmount(): int
{
    $minor = bcmul($this->amount, bcpow('10', (string) $this->scale), 0);
    return (int) $minor; // string inteira → int; sem passar por float
}
```

---

### 3. format() no adapter
**Descrição**: Delegar ao formatter, repassando moeda/escala e locale.
**Arquivos afetados**:
- `src/Adapter/BcMath/PzCurrBcMath.php`

**Implementação**:
```php
public function format(?string $locale = null): string
{
    return (new PzCurrFormatter())->format($this->amount, $this->currency, $locale);
}
```
> Na Task 8.0, as opções manuais do formatter passarão a vir da config; aqui aceitamos defaults pt-BR.

---

### 4. Serialização
**Descrição**: `toArray`/`jsonSerialize` com estrutura estável (já esboçados na Base na Task 3.0 — validar e finalizar).
**Arquivos afetados**:
- `src/Adapter/PzCurrBase.php`

**Implementação**:
```php
public function toArray(): array
{
    return ['amount' => $this->amount, 'currency' => $this->currency->code, 'scale' => $this->scale];
}
public function jsonSerialize(): array { return $this->toArray(); }
```

---

### 5. Testes Unitários
**Descrição**: Formatação manual pt-BR, degradação sem `intl`, extração e serialização.

**Arquivos de teste**:
- `tests/Unit/Support/PzCurrFormatterTest.php`
- `tests/Unit/Adapter/BcMath/PzCurrBcMathOutputTest.php`

**Casos de teste obrigatórios**:
1. **Formatação manual pt-BR**
   - **Cenário**: `format('1234.56', BRL)`.
   - **Expectativa**: `'R$ 1.234,56'`.
2. **JPY escala 0**
   - **Cenário**: `format('1234', JPY)`.
   - **Expectativa**: sem casas decimais (`'¥ 1.234'` ou conforme config).
3. **Degradação sem intl**
   - **Cenário**: simular ausência de `intl` (locale informado).
   - **Expectativa**: cai no modo manual sem erro.
4. **getMinorAmount**
   - **Cenário**: `of('19.90','BRL')->getMinorAmount()`.
   - **Expectativa**: `1990` (int).
5. **toArray/jsonSerialize estáveis**
   - **Cenário**: `of('19.90','BRL')`.
   - **Expectativa**: `['amount'=>'19.90','currency'=>'BRL','scale'=>2]`; `json_encode` produz JSON correspondente.
6. **Sem float na serialização**
   - **Cenário**: inspecionar tipos de `toArray`.
   - **Expectativa**: `amount` é `string`, `scale` é `int`.

**Cobertura mínima**: 95% do formatter e dos métodos de saída.

---

### 6. Testes de Integração
**Descrição**: format + serialize após cadeia de operações.

**Arquivos de teste**:
- `tests/Features/Adapter/OutputIntegrationTest.php`

**Cenários de teste obrigatórios**:
1. **Pipeline completo**
   - **Fluxo**: `of('25.00','BRL')->add('4.99')->subtract('2.50')->multiply(2)->format()`.
   - **Expectativa**: `'R$ 54,98'`.
2. **Round-trip de serialização**
   - **Fluxo**: `json_encode($pz)` → estrutura `{amount, currency, scale}`.
   - **Expectativa**: JSON estável e reconstruível.

---

## Critérios de Aceitação

- [ ] `declare(strict_types=1)` em todos os arquivos
- [ ] **Zero `float`** em extração/serialização (decisão sobre `intl` documentada)
- [ ] Formatação manual pt-BR correta (milhar `.`, decimal `,`, símbolo)
- [ ] Degradação graciosa quando `intl` ausente
- [ ] `getMinorAmount` retorna `int` correto
- [ ] `toArray`/`jsonSerialize` estáveis e tipados
- [ ] Tipagem estrita, sem `mixed`
- [ ] Todos os testes unitários e de integração passando
- [ ] PHPDoc registrando a decisão sobre o caminho `intl`
- [ ] Sem erros de lint/type-check

---

## Entregáveis

**Arquivos Criados**:
- [ ] `src/Support/PzCurrFormatter.php`
- [ ] `tests/Unit/Support/PzCurrFormatterTest.php`
- [ ] `tests/Unit/Adapter/BcMath/PzCurrBcMathOutputTest.php`
- [ ] `tests/Features/Adapter/OutputIntegrationTest.php`

**Arquivos Modificados**:
- [ ] `src/Adapter/BcMath/PzCurrBcMath.php` (`getMinorAmount`, `format`)
- [ ] `src/Adapter/PzCurrBase.php` (finalizar `toArray`/`jsonSerialize`)

**Documentação**:
- [ ] PHPDoc no formatter sobre modo manual vs intl

---

## Guia de Implementação

### Passo 1: Formatter manual (TDD)
Implementar e testar o modo manual pt-BR primeiro (sem depender de intl).

### Passo 2: Caminho intl opcional
Adicionar suporte por locale com guarda `extension_loaded('intl')` e fallback.

### Passo 3: Extração e serialização
Implementar `getMinorAmount` e finalizar serialização.

```bash
./vendor/bin/phpunit tests/Unit/Support/PzCurrFormatterTest.php tests/Features/Adapter/OutputIntegrationTest.php
```

---

## Validação

### Checklist de Validação Manual:
- [ ] `R$ 1.234,56` para `1234.56` BRL
- [ ] Sem `intl`, formatação por locale cai no manual
- [ ] `getMinorAmount('19.90' BRL)` = 1990

### Comandos de Validação:
```bash
./vendor/bin/phpunit --filter Output
./vendor/bin/phpunit --filter Formatter
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| `intl` introduzir `float` via `formatCurrency` | Alto | Média | Usar intl só para metadados de locale ou documentar como exibição; nunca no caminho de cálculo/persistência |
| Inconsistência de separadores entre locales | Baixo | Média | Modo manual com separadores explícitos configuráveis |
| Overflow em `getMinorAmount` para valores enormes | Baixo | Baixa | Documentar limite de `int`; manter representação string como fonte primária |

---

## Notas Adicionais

A representação interna `string` é sempre a fonte de verdade. `getMinorAmount` é derivado e deve ser usado para persistência em inteiro, mas a string decimal permanece disponível para colunas decimais.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-06-04 | Equipe Puzl | Criação da tarefa |
