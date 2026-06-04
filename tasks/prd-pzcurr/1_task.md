# Task 1.0: Fundação do Pacote e Hierarquia de Exceções

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Alta
**Dependências**: Nenhuma
**Assignee**: Não atribuído

---

## Objetivo

Criar o esqueleto do pacote Composer `puzl/pzcurr` (greenfield) com autoload PSR-4, configuração de auto-discovery Laravel, configuração do PHPUnit e a hierarquia completa de exceções tipadas. Ao final, o pacote deve ser instalável (`composer install`) e a suíte de testes deve rodar (`./vendor/bin/phpunit`), entregando a base sobre a qual todas as demais tarefas serão construídas.

---

## Contexto

O PzCurr replica o padrão arquitetural já consolidado em `PzRequest`/`PzMail`/`PzPdf`. Esta primeira tarefa não implementa lógica monetária; ela estabelece a estrutura do pacote e o sistema de erros que será usado por todas as outras camadas (validação de entrada, mismatch de moeda, arredondamento, extensão ausente).

**Referências**:
- PRD: `tasks/prd-pzcurr/prd.md` - Seção 4 (RNF-03/RNF-04), Seção 9 (Restrições Técnicas)
- Tech Spec: `tasks/prd-pzcurr/techspec.md` - Seção 1.3 (Stack), Seção 9.3 (hierarquia de exceções), Seção 10 (arquivos), Seção 11.1 (Fase 1)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `composer.json` (PSR-4 `Puzl\PzCurr\`, autoload-dev `Puzl\PzCurr\Tests\`, require `php ^8.1`, `ext-bcmath`, `illuminate/support ^10|^11|^12`, suggest `ext-intl`, dev `phpunit/phpunit ^10.5`, `illuminate/config`, `illuminate/container`, `vlucas/phpdotenv`, `extra.laravel.providers`)
- ✅ `phpunit.xml.dist` com suítes `Unit` e `Features`, `source` apontando para `src`
- ✅ `PzCurrException` (classe base abstrata/estendível)
- ✅ `PzCurrInvalidAmountException`, `PzCurrInvalidCurrencyException`, `PzCurrencyMismatchException`, `PzCurrRoundingNecessaryException`, `PzCurrMissingExtensionException`
- ✅ Métodos de fábrica nas exceções para mensagens claras e contextuais
- ✅ Estrutura de diretórios `src/` e `tests/`

### O que NÃO está no escopo:
- ❌ Lógica de cálculo, moeda, formatação (tasks 2.0+)
- ❌ ServiceProvider e config (Task 8.0)
- ❌ Lançamento real das exceções pela lógica de negócio (apenas as classes)

---

## Subtarefas

### 1. Configuração do pacote Composer
**Descrição**: Criar o `composer.json` seguindo o padrão Puzl, com autoload PSR-4, dependências e auto-discovery do provider (que será implementado na Task 8.0, mas já declarado).
**Arquivos afetados**:
- `composer.json`

**Implementação**:
```json
{
    "name": "puzl/pzcurr",
    "description": "Biblioteca de valores monetários com precisão exata (BCMath) para Laravel.",
    "type": "library",
    "license": "proprietary",
    "require": {
        "php": "^8.1",
        "ext-bcmath": "*",
        "illuminate/support": "^10.0|^11.0|^12.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5",
        "illuminate/config": "^10.0|^11.0|^12.0",
        "illuminate/container": "^10.0|^11.0|^12.0",
        "vlucas/phpdotenv": "^5.6"
    },
    "suggest": {
        "ext-intl": "Necessário para formatação por locale (NumberFormatter)."
    },
    "autoload": {
        "psr-4": { "Puzl\\PzCurr\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Puzl\\PzCurr\\Tests\\": "tests/" }
    },
    "extra": {
        "laravel": {
            "providers": [ "Puzl\\PzCurr\\Laravel\\PzCurrServiceProvider" ]
        }
    },
    "config": { "sort-packages": true },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

---

### 2. Configuração do PHPUnit
**Descrição**: Criar `phpunit.xml.dist` espelhando o `PzRequest`, com duas suítes e cobertura sobre `src`.
**Arquivos afetados**:
- `phpunit.xml.dist`

**Implementação**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Features">
            <directory>tests/Features</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

---

### 3. Hierarquia de exceções
**Descrição**: Criar a exceção base e as 5 especializadas, todas com `declare(strict_types=1)`. Adicionar construtores estáticos (named constructors) para mensagens contextuais e acionáveis.
**Arquivos afetados**:
- `src/Exception/PzCurrException.php`
- `src/Exception/PzCurrInvalidAmountException.php`
- `src/Exception/PzCurrInvalidCurrencyException.php`
- `src/Exception/PzCurrencyMismatchException.php`
- `src/Exception/PzCurrRoundingNecessaryException.php`
- `src/Exception/PzCurrMissingExtensionException.php`

**Implementação**:
```php
<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Exception;

class PzCurrException extends \RuntimeException
{
}
```

```php
<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Exception;

final class PzCurrencyMismatchException extends PzCurrException
{
    public static function between(string $a, string $b): self
    {
        return new self(sprintf(
            'Operação inválida entre moedas diferentes: "%s" e "%s".',
            $a,
            $b
        ));
    }
}
```
> Aplicar o mesmo padrão (named constructor + mensagem contextual) às demais:
> - `PzCurrInvalidAmountException::forValue(string $value)`
> - `PzCurrInvalidCurrencyException::forCode(string $code)`
> - `PzCurrRoundingNecessaryException::forScale(string $amount, int $scale)`
> - `PzCurrMissingExtensionException::bcmath()`

---

### 4. Testes Unitários
**Descrição**: Validar que cada exceção estende `PzCurrException`, é capturável de forma genérica, e que os named constructors produzem a mensagem esperada.

**Arquivos de teste**:
- `tests/Unit/Exception/PzCurrExceptionHierarchyTest.php`

**Casos de teste obrigatórios**:
1. **Hierarquia comum**
   - **Cenário**: Instanciar cada exceção especializada.
   - **Expectativa**: Todas são `instanceof PzCurrException` e `instanceof \Throwable`.
2. **Captura genérica**
   - **Cenário**: Lançar uma especializada e capturar como `PzCurrException`.
   - **Expectativa**: O `catch (PzCurrException $e)` captura corretamente.
3. **Mensagens contextuais**
   - **Cenário**: Chamar `PzCurrencyMismatchException::between('BRL','USD')` etc.
   - **Expectativa**: Mensagem contém as moedas/valores informados.

**Cobertura mínima**: 100% das classes de exceção.

---

### 5. Testes de Integração
**Descrição**: Garantir que o autoload PSR-4 resolve as classes do pacote e que o bootstrap de teste funciona.

**Arquivos de teste**:
- `tests/Features/Bootstrap/PackageBootstrapTest.php`

**Cenários de teste obrigatórios**:
1. **Autoload do pacote**
   - **Fluxo**: `composer dump-autoload` → instanciar uma exceção via FQCN.
   - **Expectativa**: Classe é carregada sem erro de autoload.
2. **Extensão bcmath presente no ambiente de teste**
   - **Fluxo**: Verificar `extension_loaded('bcmath')` no setup.
   - **Expectativa**: Retorna `true` (pré-requisito do ambiente).

---

## Critérios de Aceitação

- [ ] `composer install` executa sem erros
- [ ] `./vendor/bin/phpunit` roda as duas suítes (mesmo que com poucos testes)
- [ ] `declare(strict_types=1)` em todos os arquivos `.php`
- [ ] Todas as exceções estendem `PzCurrException`
- [ ] Tipagem estrita, sem uso de `mixed`
- [ ] Todos os testes unitários passando
- [ ] Todos os testes de integração passando
- [ ] PHPDoc nos named constructors
- [ ] Sem erros de lint/type-check

---

## Entregáveis

**Arquivos Criados**:
- [ ] `composer.json`
- [ ] `phpunit.xml.dist`
- [ ] `src/Exception/PzCurrException.php`
- [ ] `src/Exception/PzCurrInvalidAmountException.php`
- [ ] `src/Exception/PzCurrInvalidCurrencyException.php`
- [ ] `src/Exception/PzCurrencyMismatchException.php`
- [ ] `src/Exception/PzCurrRoundingNecessaryException.php`
- [ ] `src/Exception/PzCurrMissingExtensionException.php`
- [ ] `tests/Unit/Exception/PzCurrExceptionHierarchyTest.php`
- [ ] `tests/Features/Bootstrap/PackageBootstrapTest.php`

**Arquivos Modificados**:
- [ ] Nenhum

**Documentação**:
- [ ] PHPDoc nos named constructors das exceções

---

## Guia de Implementação

### Passo 1: Criar o composer.json e instalar
Criar o `composer.json` conforme a subtarefa 1 e rodar:

```bash
composer install
```

### Passo 2: Configurar PHPUnit
Criar `phpunit.xml.dist` (subtarefa 2) e os diretórios `tests/Unit` e `tests/Features`.

### Passo 3: Implementar as exceções
Criar a base `PzCurrException` e as 5 especializadas com named constructors. Manter mensagens em pt-BR e contextuais.

### Passo 4: Escrever e rodar os testes
```bash
./vendor/bin/phpunit
```

---

## Validação

### Checklist de Validação Manual:
- [ ] `composer dump-autoload` não acusa erro de PSR-4
- [ ] Cada exceção pode ser capturada como `PzCurrException`
- [ ] `phpunit` verde nas duas suítes

### Comandos de Validação:
```bash
composer install
./vendor/bin/phpunit
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Ambiente sem `ext-bcmath` | Alto | Baixa | Documentar requisito; teste de bootstrap verifica `extension_loaded` |
| Divergência de versões Illuminate | Médio | Baixa | Constraint `^10|^11|^12` alinhado às libs Puzl |

---

## Notas Adicionais

O `PzCurrServiceProvider` é apenas **declarado** no `extra.laravel.providers` nesta task; sua implementação ocorre na Task 8.0. Garanta que o FQCN declarado bata exatamente com o que será criado depois (`Puzl\PzCurr\Laravel\PzCurrServiceProvider`).

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-06-04 | Equipe Puzl | Criação da tarefa |
