# Tech Spec - PzCurr (Biblioteca de Valores Monetários)

## 1. Visão Geral

### 1.1 Objetivo Técnico
Implementar a biblioteca `puzl/pzcurr` replicando o padrão arquitetural já consolidado em `PzRequest` (Contract → Base abstrata → Adapter concreto → Factory → Enum → ServiceProvider Laravel). O motor de cálculo monetário (`BCMATH` na v1) fica isolado atrás da interface pública `PzCurrInterface`. Toda aritmética é feita por funções `bc*` (`bcadd`, `bcsub`, `bcmul`, `bcdiv`, `bcmod`, `bccomp`) sobre **strings**, garantindo precisão arbitrária e zero uso de `float`. O modelo é **mutável e fluente**: setters e operações alteram o próprio objeto e retornam `$this`, com `copy()` explícito para snapshots imutáveis quando necessário.

### 1.2 Escopo Técnico
- **Novo pacote Composer** `puzl/pzcurr` (greenfield), autoload PSR-4 `Puzl\PzCurr\`.
- **Componentes**: Contract público, Base abstrata, adapter `PzCurrBcMath`, Factory + Enum de adapters, catálogo de moedas ISO 4217 + moedas customizadas, helper interno de arredondamento BCMath, formatador (manual + `intl` opcional), serialização (JSON/array) e Eloquent Cast.
- **Integração Laravel**: `PzCurrServiceProvider` com auto-discovery e config publicável `config/pzcurr.php`.
- **Sem impacto em código externo**: pacote independente; consumido via `PzCurrFactory::make()`.

### 1.3 Stack Tecnológica
- PHP `^8.1`, `declare(strict_types=1)` em todos os arquivos.
- Extensão **bcmath** (obrigatória); **intl** (opcional, formatação por locale).
- `illuminate/support ^10|^11|^12` (ServiceProvider, Eloquent Cast contract).
- Dev: `phpunit/phpunit ^10.5`, `illuminate/config`, `illuminate/container`.
- **Decisão**: NÃO usar `bcround()`/enum `RoundingMode` nativos (PHP 8.4+); implementar helper de arredondamento próprio compatível com 8.1+.

---

## 2. Arquitetura

### 2.1 Diagrama de Arquitetura
```
Consumidor (Laravel App)
        │
        ▼
PzCurrFactory::make(?PzCurrAdapterEnum)      ← ponto de entrada único
        │  resolve: arg → config('pzcurr.adapter') → env PZCURR_ADAPTER → BCMATH
        ▼
PzCurrInterface  (Contract público estável — "linguagem" do pacote)
        ▲
        │ implements
PzCurrBase (abstract)  ── estado: amount(string), PzCurrCurrency, scale, RoundingModeEnum default
        │  - operações fluentes que NÃO dependem de bcmath diretamente delegam ao adapter
        ▲
        │ extends
PzCurrBcMath (adapter concreto v1)  ── implementa cálculo via bc* + PzCurrRoundingHelper
        │
        ├── usa ► PzCurrCurrencyRegistry (ISO 4217 + customizadas)
        ├── usa ► PzCurrRoundingHelper (HALF_UP, HALF_EVEN, UP, DOWN, CEILING, FLOOR, ...)
        ├── usa ► PzCurrFormatter (manual pt-BR / intl opcional)
        └── usa ► PzCurrAllocator (largest-remainder, conservação de centavos)

Laravel:  PzCurrServiceProvider (auto-discovery) ─► merge/publish config/pzcurr.php
Eloquent: PzCurrCast (amount + currency em 2 colunas)
Erros:    PzCurrException ◄ PzCurrencyMismatchException, PzCurrInvalidCurrencyException,
                              PzCurrRoundingNecessaryException, PzCurrInvalidAmountException
```

### 2.2 Fluxo de Dados
1. **Criação**: `make()->of('19.90','BRL')`. `of()` valida a moeda no `PzCurrCurrencyRegistry`, normaliza a string (sem `float`) e aplica a escala da moeda via `PzCurrRoundingHelper`. `ofMinor(1990,'BRL')` divide por `10^scale` usando `bcdiv`. `zero('BRL')` cria `'0.00'`.
2. **Operação**: cada método (`add`, `subtract`, ...) valida compatibilidade de moeda (`PzCurrencyMismatchException` se divergir), executa a operação `bc*` na escala de trabalho, reaplica a escala/arredondamento configurado e grava de volta em `$this->amount`, retornando `$this`.
3. **Comparação**: `compareTo` usa `bccomp` na maior escala entre operandos; métodos de sinal derivam de `bccomp($amount,'0')`.
4. **Saída**: `getAmount()`/`toDecimal()` retorna a string decimal; `getMinorAmount()` faz `bcmul` por `10^scale` e converte para `int`; `format()` delega ao `PzCurrFormatter`; `jsonSerialize()`/`toArray()` produzem `{amount, currency, scale}`.
5. **Persistência**: `PzCurrCast` lê/escreve duas colunas (`*_amount`, `*_currency`) reconstruindo o objeto via Factory, sem `float` em nenhuma etapa.

### 2.3 Decisões Arquiteturais
- **Armazenamento interno como string decimal** (ex.: `'19.90'`) na escala da moeda: simplifica formatação/extração e comparação; `getMinorAmount()` é derivado quando necessário. Alternativa (minor int) rejeitada por complicar `format()` e escalas variáveis.
- **Mutável + `copy()`**: segue RF-02/RNF-05 (fluência prazerosa) e mitiga o risco de efeitos colaterais (Risco "modelo mutável") oferecendo snapshot explícito.
- **Helper de arredondamento próprio**: garante compatibilidade PHP 8.1+ (o `bcround` nativo é 8.4+). Implementa todos os modos do enum operando sobre o dígito-guia da string.
- **Allocate por maior resto fracionário** (estilo MoneyPHP/Hamilton): mais justo e independente de ordem dos ratios; garante `Σ partes == total` (RN-03).
- **Fallback silencioso de adapter** (igual a `PzRequest`): `BRICK_MONEY`/`MONEYPHP` previstos no enum, mas caem em `BCMATH` sem lançar (RF-08).
- **Base abstrata não conhece bcmath**: o estado fluente vive em `PzCurrBase`; o cálculo concreto fica no adapter, preservando o desacoplamento (RNF-02) e permitindo futuros adapters sem reescrever a Base.

---

## 3. Interfaces e Contratos

### 3.1 DTOs (Data Transfer Objects)
```php
// Value Object de moeda (imutável) — resultado do registry.
final class PzCurrCurrency
{
    public function __construct(
        public readonly string $code,        // 'BRL'
        public readonly int $numericCode,    // 986
        public readonly int $scale,          // 2 (JPY = 0)
        public readonly string $symbol,      // 'R$'
    ) {}
}

// Estrutura de serialização estável (toArray/jsonSerialize)
// ['amount' => '19.90', 'currency' => 'BRL', 'scale' => 2]
```

### 3.2 Interfaces de Serviços
```php
namespace Puzl\PzCurr\Contract;

interface PzCurrInterface extends \JsonSerializable
{
    // Criação (também expostas como estáticas na Factory/adapter)
    public function of(string|int $amount, string $currency): self;
    public function ofMinor(int $minorAmount, string $currency): self;
    public function zero(string $currency): self;

    // Aritmética fluente (mutável, retorna $this) — RF-02
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

    // Escala / arredondamento — RF-03
    public function withScale(int $scale, ?PzCurrRoundingModeEnum $mode = null): self;
    public function withRoundingMode(PzCurrRoundingModeEnum $mode): self;

    // Comparações / sinal — RF-04
    public function compareTo(self|string|int $other): int;     // -1, 0, 1
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
    public function getSign(): int;                             // -1, 0, 1
    public function isSameValueAs(self $other): bool;           // não lança em mismatch

    // Extração / formatação — RF-06
    public function getAmount(): string;       // '19.90'
    public function toDecimal(): string;       // alias semântico
    public function getMinorAmount(): int;     // 1990
    public function getCurrency(): PzCurrCurrency;
    public function getScale(): int;
    public function format(?string $locale = null): string;

    // Serialização — RF-07
    /** @return array{amount: string, currency: string, scale: int} */
    public function toArray(): array;
    public function jsonSerialize(): array;

    // Snapshot (mutabilidade controlada)
    public function copy(): self;
}
```

### 3.3 Tipos e Enums
```php
namespace Puzl\PzCurr\Factory;

enum PzCurrAdapterEnum: int   // espelha PzRequestEnum
{
    case BCMATH = 0;
    case BRICK_MONEY = 1;   // previsto, sem implementação (fallback → BCMATH)
    case MONEYPHP = 2;      // previsto, sem implementação (fallback → BCMATH)

    public static function tryFromName(string $name): ?self { /* match case-sensitive por ->name */ }
}

namespace Puzl\PzCurr\Enum;

enum PzCurrRoundingModeEnum: string   // RF-03 / RN-04
{
    case HALF_UP   = 'HALF_UP';     // default configurável
    case HALF_DOWN = 'HALF_DOWN';
    case HALF_EVEN = 'HALF_EVEN';   // banker's rounding
    case UP        = 'UP';          // away from zero
    case DOWN      = 'DOWN';        // toward zero (truncate)
    case CEILING   = 'CEILING';     // toward +inf
    case FLOOR     = 'FLOOR';       // toward -inf
    case UNNECESSARY = 'UNNECESSARY'; // lança PzCurrRoundingNecessaryException
}
```

---

## 4. Endpoints e Integração com API
**Não se aplica.** PzCurr é uma biblioteca/SDK sem endpoints HTTP, sem chamadas de rede e sem integrações externas na v1 (sem conversão de câmbio — confirmado no PRD §8.1/§10). A "API" do pacote é a interface pública PHP descrita na seção 3.

---

## 5. Roteamento e Navegação
**Não se aplica.** Biblioteca sem rotas nem navegação. O "fluxo" é programático: `Factory → of()/ofMinor()/zero() → operações encadeadas → comparação/format/serialização/cast`.

---

## 6. Segurança

### 6.1 Armazenamento de Tokens
Não se aplica (sem autenticação/tokens).

### 6.2 Proteção contra Ataques
- **Integridade numérica**: validação estrita de entrada em `of()`/`ofMinor()` via regex de string numérica; valores malformados lançam `PzCurrInvalidAmountException` (evita injeção de strings arbitrárias em `bc*`).
- **Sem `eval`/`float`**: toda matemática é determinística sobre strings; não há conversão para `float` (RN-01) que pudesse introduzir imprecisão explorável em domínio financeiro.
- **Checagem de extensão**: ausência de `bcmath` é detectada cedo (no construtor do adapter) com mensagem clara, prevenindo comportamento indefinido.

### 6.3 Sanitização de Dados
- Normalização de moeda para uppercase e lookup estrito no registry; moeda inválida → `PzCurrInvalidCurrencyException`.
- `format()` via `intl` usa `NumberFormatter` (não concatena entrada bruta); modo manual escapa apenas separadores/símbolo configurados.

---

## 7. Performance e Otimização

### 7.1 Caching
- `PzCurrCurrencyRegistry` mantém o catálogo ISO 4217 como `array` estático/const carregado uma vez por processo; moedas customizadas registradas em runtime ficam no mesmo mapa em memória.
- `publishes()` do ServiceProvider registrado apenas em `runningInConsole()` (mesma otimização do `PzRequest`), evitando custo em runtime web.

### 7.2 Otimizações
- Escala de trabalho mínima nas operações intermediárias (escala da moeda + margem fixa) e arredondamento único no final, reduzindo chamadas `bc*`.
- Operações variádicas (`add`/`subtract`) acumulam em loop único sem instanciar objetos intermediários.

---

## 8. Testes

### 8.1 Estratégia de Testes
PHPUnit `^10.5` com duas suítes espelhando `PzRequest`: `tests/Unit` e `tests/Features`, configuradas em `phpunit.xml.dist` (`source` = `src`). Foco em não-regressão das regras de cálculo (RNF-04). Stub concreto da Base abstrata (`PzCurrBaseStub`) para testar estado fluente isolado do bcmath.

### 8.2 Testes Unitários
- **Criação** (RF-01): `of`/`ofMinor`/`zero`; rejeição de amount/moeda inválidos.
- **Aritmética** (RF-02): `add`/`subtract` variádicos; `multiply`/`divide` com cada modo de arredondamento; `mod`, `absolute`, `negated`, `ratioOf`; encadeamento retorna `$this`.
- **Arredondamento** (RF-03/RN-04): tabela de casos por modo (incluindo HALF_EVEN e `UNNECESSARY` lançando exceção); default vindo da config sobrescrito por chamada.
- **Allocate/Split** (RF-02/RN-03): asserção `Σ partes == total` para vários ratios, números de partes e valores negativos; independência de ordem.
- **Comparação/sinal** (RF-04): `compareTo`, todos os `is*`, `getSign`; mismatch lança exceto `isSameValueAs`.
- **Moedas** (RF-05): resolução de BRL/USD/EUR/JPY (escalas 2/2/2/0); registro de moeda customizada; moeda inválida.
- **Formatação/extração** (RF-06): `getMinorAmount` inteiro; formatação manual pt-BR; locale via intl quando disponível; degradação graciosa sem intl.
- **Serialização** (RF-07): estrutura estável de `toArray`/`jsonSerialize`; ausência de `float`.
- **Factory/Enum** (RF-08): `make()` default BCMATH; resolução por config/env; fallback de `BRICK_MONEY`/`MONEYPHP`; `tryFromName`.

### 8.3 Testes de Integração
- **ServiceProvider** (`tests/Features/Laravel`): `register()` faz merge da config; `publishes` com tag `pzcurr-config` apenas em console; auto-discovery via `extra.laravel.providers` (padrão idêntico ao `PzRequestServiceProviderTest`).
- **Eloquent Cast**: round-trip `set`/`get` em duas colunas reconstruindo o `PzCurr` corretamente, sem perda de precisão.
- **Smoke** (`tests/Features/Bootstrap`): pacote instala e `Factory::make()->of()->add()->format()` executa fim-a-fim.

---

## 9. Observabilidade

### 9.1 Logging
Biblioteca não realiza logging próprio (evita acoplamento ao logger do consumidor). Erros são comunicados via exceções tipadas, que o app consumidor loga conforme sua política.

### 9.2 Métricas
Não se aplica (sem runtime de serviço).

### 9.3 Monitoramento de Erros
Hierarquia de exceções clara e acionável (RNF-05), todas estendendo `PzCurrException` para captura genérica:
- `PzCurrInvalidAmountException` — entrada numérica malformada.
- `PzCurrInvalidCurrencyException` — moeda inexistente/ inválida.
- `PzCurrencyMismatchException` — operação entre moedas diferentes (RN-02).
- `PzCurrRoundingNecessaryException` — modo `UNNECESSARY` exigiria arredondar (RN-04).
- `PzCurrMissingExtensionException` — extensão `bcmath` ausente.

Mensagens incluem contexto (moedas envolvidas, valores, escala) para diagnóstico rápido.

---

## 10. Análise de Impacto

### 10.1 Arquivos Criados
```
composer.json
phpunit.xml.dist
config/pzcurr.php
src/Contract/PzCurrInterface.php
src/Adapter/PzCurrBase.php
src/Adapter/BcMath/PzCurrBcMath.php
src/Factory/PzCurrFactory.php
src/Factory/PzCurrAdapterEnum.php
src/Enum/PzCurrRoundingModeEnum.php
src/Currency/PzCurrCurrency.php
src/Currency/PzCurrCurrencyRegistry.php
src/Support/PzCurrRoundingHelper.php
src/Support/PzCurrAllocator.php
src/Support/PzCurrFormatter.php
src/Laravel/PzCurrServiceProvider.php
src/Laravel/PzCurrCast.php
src/Exception/PzCurrException.php
src/Exception/PzCurrInvalidAmountException.php
src/Exception/PzCurrInvalidCurrencyException.php
src/Exception/PzCurrencyMismatchException.php
src/Exception/PzCurrRoundingNecessaryException.php
src/Exception/PzCurrMissingExtensionException.php
tests/Unit/** , tests/Features/**
README.md (atualizar)
```

### 10.2 Arquivos Modificados
Nenhum arquivo existente do ecossistema é modificado (pacote independente). Apenas `README.md` do repositório (atualmente placeholder) será expandido com instruções de uso.

### 10.3 Dependências Adicionadas
- Runtime: `illuminate/support ^10|^11|^12`; `ext-bcmath` (require), `ext-intl` (suggest).
- Dev: `phpunit/phpunit ^10.5`, `illuminate/config`, `illuminate/container`, `vlucas/phpdotenv`.

### 10.4 Breaking Changes
Nenhum (release inicial v1). A interface `PzCurrInterface` é o contrato estável; mudanças futuras nela implicam bump MAJOR.

---

## 11. Plano de Implementação

### 11.1 Fases de Desenvolvimento
1. **Fundação do pacote**: `composer.json` (PSR-4, autoload, `extra.laravel.providers`), `phpunit.xml.dist`, hierarquia de exceções.
2. **Domínio de moeda + arredondamento**: `PzCurrCurrency`, `PzCurrCurrencyRegistry` (ISO + customizadas), `PzCurrRoundingHelper` com todos os modos + testes unitários exaustivos.
3. **Contract + Base abstrata**: `PzCurrInterface` e `PzCurrBase` (estado fluente, validações de moeda, sem bcmath).
4. **Adapter BCMath**: `PzCurrBcMath` (todas as operações `bc*`), `PzCurrAllocator` (largest-remainder) + testes de conservação.
5. **Saída**: `PzCurrFormatter` (manual pt-BR + intl opcional), `getMinorAmount`, serialização.
6. **Factory + Enum**: resolução de adapter (arg → config → env → BCMATH) + fallback silencioso.
7. **Laravel**: `PzCurrServiceProvider`, `config/pzcurr.php`, `PzCurrCast` (2 colunas) + testes de integração.
8. **Documentação**: README com exemplos do caso de uso principal.

### 11.2 Ordem de Implementação
Seguir a ordem das fases 1→8 (cada fase é pré-requisito da seguinte). Testes acompanham cada fase (TDD onde viável nas regras de cálculo).

### 11.3 Dependências entre Tarefas
- Fase 3 (Base) depende de 2 (moeda/arredondamento) e 1 (exceções).
- Fase 4 (adapter) depende de 3.
- Fases 5, 6 dependem de 4.
- Fase 7 (Laravel/Cast) depende de 6 (Factory) e 5 (serialização).

---

## 12. Considerações Adicionais

### 12.1 Compatibilidade
PHP `^8.1` (sem usar APIs 8.4+ como `bcround`); Laravel/Illuminate `^10|^11|^12`. Requer `ext-bcmath`. `ext-intl` opcional com degradação graciosa para formatação manual (RF-06).

### 12.2 Acessibilidade
Não se aplica (sem UI).

### 12.3 Internacionalização
Formatação por locale via `NumberFormatter` (intl) quando disponível; modo manual com separadores/símbolo configuráveis (foco pt-BR `R$ 1.234,56`). Catálogo ISO 4217 com símbolo por moeda.

### 12.4 Migração e Deploy
Distribuição via Composer (repositório interno Puzl). Auto-discovery dispensa registro manual do provider. Persistência recomendada: duas colunas (`*_amount` decimal/inteiro + `*_currency`) com migration de exemplo na documentação; nenhuma conversão `float` no caminho de persistência (RNF-01).

---

## 13. Referências Técnicas

### 13.1 Documentação
- PHP BCMath — https://www.php.net/manual/en/book.bc.php
- PHP NumberFormatter (intl) — https://www.php.net/manual/en/class.numberformatter.php
- Brick\Money (allocate/split) — https://github.com/brick/money
- MoneyPHP (allocate largest-remainder, JSON) — https://www.moneyphp.org/en/stable/
- Dinero.js — https://www.dinerojs.com/getting-started/quick-start

### 13.2 Padrões do Projeto
- Padrão arquitetural de referência: `PzRequest` (Contract/Base/Adapter/Factory/Enum/ServiceProvider).
- Convenções: `declare(strict_types=1)`, tipagem estrita, nomenclatura `PzCurr*`, fallback silencioso de adapter na Factory, `publishes` apenas em console.
- Observação: não há diretório `.cursor/rules` neste repositório; as convenções foram derivadas do código de `PzRequest`/`PzMail`/`PzPdf`.

### 13.3 Recursos Externos
- ISO 4217 (códigos, código numérico, escala/casas decimais de moedas).

---

## Histórico de Versões

| Versão | Data | Autor | Descrição |
|--------|------|-------|-----------|
| 1.0 | 2026-06-04 | Equipe Puzl | Versão inicial |
