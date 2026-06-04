# Tasks - PzCurr (Biblioteca de Valores Monetários)

## Visão Geral

**PRD**: `tasks/prd-pzcurr/prd.md`
**Tech Spec**: `tasks/prd-pzcurr/techspec.md`

**Resumo**: Construir o pacote Composer `puzl/pzcurr`, uma biblioteca PHP/Laravel para representar e operar valores monetários com precisão exata via BCMath. A arquitetura segue o padrão Puzl (Contract → Base abstrata → Adapter → Factory → Enum → ServiceProvider), isolando o motor de cálculo atrás de uma interface pública fluente. As tarefas abaixo entregam a biblioteca de forma incremental, cada uma com sua própria suíte de testes (unit + integração), garantindo não-regressão das regras de cálculo, arredondamento, alocação, comparação, formatação e persistência.

---

## Lista de Tarefas

### Legenda
- ✅ Concluída
- 🚧 Em Progresso
- ⏳ Aguardando
- 📦 Pronta para Iniciar

---

### Task 1.0: Fundação do Pacote e Hierarquia de Exceções
**Status**: ✅
**Dependências**: Nenhuma
**Descrição**: Criar o esqueleto do pacote Composer `puzl/pzcurr` (autoload PSR-4, auto-discovery Laravel), configurar o PHPUnit e implementar toda a hierarquia de exceções tipadas. Entregável: pacote instalável e testável (`composer install` + `phpunit` rodando).

**Entregáveis**:
- [x] `composer.json` com PSR-4 `Puzl\PzCurr\`, `ext-bcmath`, `illuminate/support`, `extra.laravel.providers`
- [x] `phpunit.xml.dist` com suítes `Unit` e `Features`, `source` = `src`
- [x] `PzCurrException` (base) + 5 exceções especializadas
- [x] Testes unitários (hierarquia e mensagens das exceções)
- [x] Testes de integração (autoload + bootstrap do pacote)

**Arquivo**: `tasks/prd-pzcurr/1_task.md`

---

### Task 2.0: Domínio de Moeda e Arredondamento
**Status**: ✅
**Dependências**: Task 1.0
**Descrição**: Implementar o value object `PzCurrCurrency`, o catálogo `PzCurrCurrencyRegistry` (ISO 4217 + moedas customizadas), o enum `PzCurrRoundingModeEnum` e o `PzCurrRoundingHelper` com todos os modos de arredondamento compatíveis com PHP 8.1+. Entregável: domínio numérico/moeda funcional e testado de forma exaustiva.

**Entregáveis**:
- [x] `PzCurrCurrency` (VO imutável)
- [x] `PzCurrCurrencyRegistry` (BRL, USD, EUR, JPY + registro customizado)
- [x] `PzCurrRoundingModeEnum` (8 modos, incl. `UNNECESSARY`)
- [x] `PzCurrRoundingHelper` (arredondamento via string, sem `float`/`bcround`)
- [x] Testes unitários (tabela de casos por modo + resolução de moeda)
- [x] Testes de integração (registro de moeda customizada usada pelo helper)

**Arquivo**: `tasks/prd-pzcurr/2_task.md`

---

### Task 3.0: Contract Público e Base Abstrata
**Status**: ✅
**Dependências**: Task 2.0
**Descrição**: Definir o contrato público estável `PzCurrInterface` e implementar a `PzCurrBase` abstrata, que mantém o estado fluente (amount, currency, scale, rounding mode) e as validações de moeda — sem conhecer BCMath. Entregável: contrato + base testáveis via stub concreto.

**Entregáveis**:
- [x] `PzCurrInterface` (todos os métodos do RF-01 a RF-07)
- [x] `PzCurrBase` (estado fluente, validação de moeda, `copy()`)
- [x] `PzCurrBaseStub` (stub concreto para testes)
- [x] Testes unitários (estado fluente retorna `$this`, mismatch de moeda)
- [x] Testes de integração (Base + Registry + Enum em conjunto)

**Arquivo**: `tasks/prd-pzcurr/3_task.md`

---

### Task 4.0: Adapter BCMath — Criação, Aritmética e Comparações
**Status**: ✅
**Dependências**: Task 3.0
**Descrição**: Implementar o adapter concreto `PzCurrBcMath` com criação (`of`/`ofMinor`/`zero`), aritmética fluente (`add`/`subtract`/`multiply`/`divide`/`mod`/`absolute`/`negated`/`ratioOf`) e comparações/sinal — toda matemática via funções `bc*`. Entregável: motor de cálculo exato funcional (exceto allocate/split, na Task 5.0).

**Entregáveis**:
- [x] `PzCurrBcMath` (criação + aritmética + comparações)
- [x] Checagem de `ext-bcmath` no construtor
- [x] Aplicação de escala/arredondamento por operação
- [x] Testes unitários (não-regressão: aritmética, modos de arredondamento, mismatch)
- [x] Testes de integração (encadeamento fim-a-fim do caso de uso principal)

**Arquivo**: `tasks/prd-pzcurr/4_task.md`

---

### Task 5.0: Allocate / Split (Conservação de Centavos)
**Status**: ✅
**Dependências**: Task 4.0
**Descrição**: Implementar o `PzCurrAllocator` (algoritmo de maior resto fracionário) e os métodos `allocate(array $ratios)` e `split(int $parts)` no adapter, garantindo que a soma das partes seja exatamente igual ao total (RN-03). Entregável: distribuição justa e sem perda de centavos.

**Entregáveis**:
- [x] `PzCurrAllocator` (largest-remainder)
- [x] `allocate()` e `split()` no adapter
- [x] Testes unitários (`Σ partes == total` para vários ratios/partes/sinais)
- [x] Testes de integração (allocate/split encadeado com aritmética)

**Arquivo**: `tasks/prd-pzcurr/5_task.md`

---

### Task 6.0: Saída — Formatação, Extração e Serialização
**Status**: ✅
**Dependências**: Task 4.0
**Descrição**: Implementar o `PzCurrFormatter` (manual pt-BR + `intl` opcional com degradação graciosa), a extração `getMinorAmount()`/`getAmount()`/`toDecimal()` e a serialização `toArray()`/`jsonSerialize()`. Entregável: valor exibível e serializável sem `float`.

**Entregáveis**:
- [x] `PzCurrFormatter` (manual + `intl` opcional)
- [x] `getMinorAmount`, `getAmount`, `toDecimal`, `getScale`, `getCurrency`
- [x] `toArray`/`jsonSerialize` (estrutura `{amount, currency, scale}`)
- [x] Testes unitários (formatação pt-BR, degradação sem `intl`, serialização)
- [x] Testes de integração (format + serialize após cadeia de operações)

**Arquivo**: `tasks/prd-pzcurr/6_task.md`

---

### Task 7.0: Factory e Enum de Adapters
**Status**: ✅
**Dependências**: Task 4.0
**Descrição**: Implementar `PzCurrAdapterEnum` (BCMATH/BRICK_MONEY/MONEYPHP + `tryFromName`) e `PzCurrFactory::make()` com resolução de adapter (arg → `config('pzcurr.adapter')` → env `PZCURR_ADAPTER` → BCMATH) e fallback silencioso. Entregável: ponto de entrada único funcional.

**Entregáveis**:
- [x] `PzCurrAdapterEnum` (3 casos + `tryFromName`)
- [x] `PzCurrFactory::make()` (cadeia de resolução + fallback silencioso)
- [x] Testes unitários (default BCMATH, resolução por arg/config/env, fallback)
- [x] Testes de integração (Factory cria adapter e executa operação)

**Arquivo**: `tasks/prd-pzcurr/7_task.md`

---

### Task 8.0: Integração Laravel — ServiceProvider, Config e Eloquent Cast
**Status**: ✅
**Dependências**: Task 6.0, Task 7.0
**Descrição**: Implementar `PzCurrServiceProvider` (auto-discovery, merge de config, publish apenas em console), `config/pzcurr.php` e `PzCurrCast` (2 colunas: `*_amount` + `*_currency`). Atualizar README. Entregável: pacote plug-and-play em Laravel, com persistência segura e smoke test E2E.

**Entregáveis**:
- [x] `PzCurrServiceProvider` (auto-discovery + merge/publish)
- [x] `config/pzcurr.php` (adapter, moeda, escala/arredondamento, formatação)
- [x] `PzCurrCast` (round-trip 2 colunas, sem `float`)
- [x] `README.md` atualizado (caso de uso principal)
- [x] Testes de integração (`tests/Features/Laravel` + Cast + smoke E2E)

**Arquivo**: `tasks/prd-pzcurr/8_task.md`

---

## Sequenciamento e Dependências

```
Task 1.0 (Fundação + Exceções)
  ↓
Task 2.0 (Moeda + Arredondamento)
  ↓
Task 3.0 (Contract + Base)
  ↓
Task 4.0 (Adapter BCMath: aritmética + comparações)
  ├─→ Task 5.0 (Allocate/Split)          [paralelo com 6.0 e 7.0]
  ├─→ Task 6.0 (Saída/Serialização)      [paralelo com 5.0 e 7.0]
  └─→ Task 7.0 (Factory + Enum)          [paralelo com 5.0 e 6.0]
                                  ↓
Task 8.0 (Laravel + Cast) — depende de 6.0 e 7.0
```

---

## Cronograma Sugerido

| Task | Descrição | Dependências | Pode Iniciar |
|------|-----------|--------------|--------------|
| 1.0 | Fundação do pacote + exceções | - | Imediato |
| 2.0 | Moeda + arredondamento | 1.0 | Após 1.0 |
| 3.0 | Contract + Base abstrata | 2.0 | Após 2.0 |
| 4.0 | Adapter BCMath (aritmética/comparação) | 3.0 | Após 3.0 |
| 5.0 | Allocate/Split | 4.0 | Após 4.0 (paralelo) |
| 6.0 | Saída/Serialização | 4.0 | Após 4.0 (paralelo) |
| 7.0 | Factory + Enum | 4.0 | Após 4.0 (paralelo) |
| 8.0 | Laravel + Cast | 6.0, 7.0 | Após 6.0 e 7.0 |

---

## Notas Importantes

- **Zero `float`**: nenhuma tarefa pode introduzir conversão para `float` em cálculo ou persistência (RN-01/RNF-01). Toda aritmética é `bc*` sobre strings.
- **Compatibilidade PHP 8.1+**: não usar `bcround()`/enum nativos de arredondamento (8.4+). O `PzCurrRoundingHelper` (Task 2.0) é a fonte única de arredondamento.
- **Desacoplamento**: consumidores dependem só de `PzCurrInterface`. A `PzCurrBase` não conhece BCMath; o cálculo vive no adapter.
- **Testes acompanham cada fase**: cada task entrega seu próprio conjunto de testes (TDD onde viável, especialmente nas regras de cálculo/arredondamento/alocação).
- **Paralelização**: 5.0, 6.0 e 7.0 podem ser desenvolvidas em paralelo após a 4.0, mas todas convergem na 8.0.

---

## Checklist de Conclusão da Funcionalidade

- [ ] Todas as tarefas individuais concluídas
- [ ] Testes unitários passando (cobertura ampla das regras de negócio)
- [ ] Testes de integração passando (ServiceProvider, Cast, smoke E2E)
- [ ] `declare(strict_types=1)` em todos os arquivos
- [ ] Zero uso de `float` confirmado por revisão/teste
- [ ] Code review aprovado
- [ ] README atualizado com exemplos do caso de uso principal

---

## Histórico de Versões

| Versão | Data | Autor | Descrição |
|--------|------|-------|-----------|
| 1.0 | 2026-06-04 | Equipe Puzl | Lista inicial de tarefas |
