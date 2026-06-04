# Task 7.0: Factory e Enum de Adapters

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Alta
**Dependências**: Task 4.0
**Assignee**: Não atribuído

---

## Objetivo

Implementar o `PzCurrAdapterEnum` (BCMATH/BRICK_MONEY/MONEYPHP + `tryFromName`) e o `PzCurrFactory::make()`, o ponto de entrada único do pacote. A Factory resolve o adapter na ordem: argumento explícito → `config('pzcurr.adapter')` → env `PZCURR_ADAPTER` → default `BCMATH`, com **fallback silencioso** para BCMATH quando o adapter previsto não tem implementação. Entregável: instanciação desacoplada que permite trocar o motor de cálculo sem alterar o código consumidor (RNF-02).

---

## Contexto

A Factory é o que viabiliza o desacoplamento prometido pelo PRD: o consumidor chama `PzCurrFactory::make()->of(...)` e nunca conhece o adapter concreto. `BRICK_MONEY`/`MONEYPHP` existem no enum por previsão de futuro, mas caem silenciosamente em BCMATH (igual ao padrão `PzRequest`). A leitura de `config()`/`env()` deve degradar graciosamente fora do Laravel (para uso/testes standalone).

**Referências**:
- PRD: `tasks/prd-pzcurr/prd.md` - RF-08, Caso de uso 5.2 (troca de adapter), RNF-02
- Tech Spec: `tasks/prd-pzcurr/techspec.md` - Seção 2.1 (resolução), 2.3 (fallback silencioso), 3.3 (enum), 11.1 (Fase 6)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `PzCurrAdapterEnum` (BCMATH=0, BRICK_MONEY=1, MONEYPHP=2) + `tryFromName(string): ?self`
- ✅ `PzCurrFactory::make(?PzCurrAdapterEnum $adapter = null): PzCurrInterface`
- ✅ Cadeia de resolução: arg → `config('pzcurr.adapter')` → env `PZCURR_ADAPTER` → BCMATH
- ✅ Fallback silencioso de BRICK_MONEY/MONEYPHP → BCMATH (sem lançar)
- ✅ Leitura segura de config/env quando o Laravel não está presente (guarda `function_exists('config')`)

### O que NÃO está no escopo:
- ❌ Implementação concreta de BRICK_MONEY/MONEYPHP (fora do escopo da v1)
- ❌ ServiceProvider / publish de config (Task 8.0 — a Factory apenas lê config se existir)

---

## Subtarefas

### 1. PzCurrAdapterEnum
**Descrição**: Enum int espelhando `PzRequestEnum`, com resolução por nome case-sensitive.
**Arquivos afetados**:
- `src/Factory/PzCurrAdapterEnum.php`

**Implementação**:
```php
<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Factory;

enum PzCurrAdapterEnum: int
{
    case BCMATH = 0;
    case BRICK_MONEY = 1;   // previsto, sem implementação (fallback → BCMATH)
    case MONEYPHP = 2;      // previsto, sem implementação (fallback → BCMATH)

    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }
        return null;
    }
}
```

---

### 2. PzCurrFactory
**Descrição**: Resolver o adapter pela cadeia definida e instanciar o concreto, com fallback silencioso.
**Arquivos afetados**:
- `src/Factory/PzCurrFactory.php`

**Implementação**:
```php
final class PzCurrFactory
{
    public static function make(?PzCurrAdapterEnum $adapter = null): PzCurrInterface
    {
        $resolved = $adapter ?? self::resolveFromConfig() ?? self::resolveFromEnv() ?? PzCurrAdapterEnum::BCMATH;

        return match ($resolved) {
            PzCurrAdapterEnum::BCMATH      => new PzCurrBcMath(),
            // Fallback silencioso: previstos mas não implementados → BCMATH
            PzCurrAdapterEnum::BRICK_MONEY,
            PzCurrAdapterEnum::MONEYPHP    => new PzCurrBcMath(),
        };
    }

    private static function resolveFromConfig(): ?PzCurrAdapterEnum
    {
        if (!function_exists('config')) {
            return null;
        }
        $name = config('pzcurr.adapter');
        return is_string($name) ? PzCurrAdapterEnum::tryFromName($name) : null;
    }

    private static function resolveFromEnv(): ?PzCurrAdapterEnum
    {
        $name = getenv('PZCURR_ADAPTER');
        return is_string($name) && $name !== '' ? PzCurrAdapterEnum::tryFromName($name) : null;
    }
}
```
> Valores desconhecidos em config/env retornam `null` em `tryFromName`, fazendo a cadeia cair no default BCMATH sem lançar (RF-08).

---

### 3. Testes Unitários
**Descrição**: Resolução em todos os caminhos + fallback.

**Arquivos de teste**:
- `tests/Unit/Factory/PzCurrAdapterEnumTest.php`
- `tests/Unit/Factory/PzCurrFactoryTest.php`

**Casos de teste obrigatórios**:
1. **make() default**
   - **Cenário**: `make()` sem argumento, sem config/env.
   - **Expectativa**: instância de `PzCurrBcMath` (`instanceof PzCurrInterface`).
2. **Argumento explícito**
   - **Cenário**: `make(PzCurrAdapterEnum::BCMATH)`.
   - **Expectativa**: BCMath.
3. **Fallback silencioso**
   - **Cenário**: `make(PzCurrAdapterEnum::BRICK_MONEY)` e `MONEYPHP`.
   - **Expectativa**: BCMath, **sem lançar**.
4. **Resolução por env**
   - **Cenário**: `putenv('PZCURR_ADAPTER=BCMATH')`.
   - **Expectativa**: BCMath; valor inválido (`FOO`) também cai em BCMath.
5. **tryFromName**
   - **Cenário**: `tryFromName('BCMATH')`, `tryFromName('bcmath')`, `tryFromName('FOO')`.
   - **Expectativa**: `BCMATH`, `null` (case-sensitive), `null`.

**Cobertura mínima**: 100% do enum e da Factory.

---

### 4. Testes de Integração
**Descrição**: Factory cria adapter e executa operação fim-a-fim; resolução por config simulada.

**Arquivos de teste**:
- `tests/Features/Factory/FactoryIntegrationTest.php`

**Cenários de teste obrigatórios**:
1. **Pipeline via Factory**
   - **Fluxo**: `PzCurrFactory::make()->of('25.00','BRL')->add('4.99')`.
   - **Expectativa**: `getAmount()` === `'29.99'`.
2. **Resolução por config (mock/helper)**
   - **Fluxo**: definir `config('pzcurr.adapter') = 'BRICK_MONEY'` (helper de teste) → `make()`.
   - **Expectativa**: BCMath por fallback; consumidor inalterado.

---

## Critérios de Aceitação

- [ ] `declare(strict_types=1)` em todos os arquivos
- [ ] `make()` sem argumentos retorna BCMath
- [ ] Cadeia arg → config → env → BCMATH respeitada
- [ ] Fallback silencioso de BRICK_MONEY/MONEYPHP (não lança)
- [ ] Funciona fora do Laravel (sem `config()`) sem erro
- [ ] Tipagem estrita, sem `mixed`
- [ ] Todos os testes unitários e de integração passando
- [ ] PHPDoc na cadeia de resolução
- [ ] Sem erros de lint/type-check

---

## Entregáveis

**Arquivos Criados**:
- [ ] `src/Factory/PzCurrAdapterEnum.php`
- [ ] `src/Factory/PzCurrFactory.php`
- [ ] `tests/Unit/Factory/PzCurrAdapterEnumTest.php`
- [ ] `tests/Unit/Factory/PzCurrFactoryTest.php`
- [ ] `tests/Features/Factory/FactoryIntegrationTest.php`

**Arquivos Modificados**:
- [ ] Nenhum

**Documentação**:
- [ ] PHPDoc explicando a ordem de resolução e o fallback

---

## Guia de Implementação

### Passo 1: Enum
Implementar o enum e `tryFromName` (case-sensitive) com testes.

### Passo 2: Factory
Implementar `make` e os resolvedores com guardas para ambiente standalone.

```bash
./vendor/bin/phpunit tests/Unit/Factory tests/Features/Factory
```

---

## Validação

### Checklist de Validação Manual:
- [ ] `make()` → BCMath
- [ ] `PZCURR_ADAPTER=FOO` não quebra (cai em BCMath)
- [ ] Funciona sem Laravel carregado

### Comandos de Validação:
```bash
./vendor/bin/phpunit --filter Factory
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| `config()` indisponível fora do Laravel quebra Factory | Médio | Média | Guarda `function_exists('config')`; teste standalone |
| Valor inválido em env/config lançar exceção | Médio | Baixa | `tryFromName` retorna `null` → cai no default |

---

## Notas Adicionais

Este é o ponto onde o desacoplamento se materializa: o caso de uso 5.2 (troca de adapter) só funciona se o consumidor sempre passar por `make()`. A documentação (Task 8.0) deve enfatizar isso.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-06-04 | Equipe Puzl | Criação da tarefa |
