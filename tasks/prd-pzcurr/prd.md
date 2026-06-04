# PRD - PzCurr (Biblioteca de Valores Monetários)

## 1. Visão Geral

### 1.1 Contexto
A Puzl mantém um conjunto de bibliotecas internas padronizadas (PzRequest, PzMail, PzPdf) instaláveis em projetos Laravel via Composer, todas seguindo o mesmo padrão arquitetural: uma **interface pública estável** (Contract), uma **base abstrata** com o estado/comportamento, **adapters concretos** por tecnologia, uma **Factory** como ponto de entrada único, uma **Enum** de adapters e um **ServiceProvider** Laravel com auto-discovery e config publicável.

Falta nesse ecossistema uma biblioteca para lidar com **valores monetários** com segurança. Hoje cálculos com dinheiro em PHP usando `float` produzem erros silenciosos de arredondamento (ex.: `0.1 + 0.2 !== 0.3`), o que é inaceitável em domínio financeiro.

### 1.2 Problema
Não existe uma forma padronizada, segura e desacoplada de manipular dinheiro nos projetos Puzl. Usar `float` causa imprecisão; usar uma biblioteca de terceiros diretamente (Brick\Money, MoneyPHP) acopla o código a essa dependência, dificultando troca futura. É preciso uma camada própria, com API rica e estável, que internamente use cálculo exato e permita trocar o motor de cálculo no futuro **sem reescrever o código consumidor**.

### 1.3 Solução Proposta
Criar o **PzCurr**: uma biblioteca PHP/Laravel para representar e operar valores monetários com **precisão exata**, usando por baixo dos panos a extensão nativa **BCMath**. O PzCurr expõe uma **interface pública fluente** (encadeável) e isola o motor de cálculo atrás de um **adapter** (`BCMATH` na v1). A arquitetura por interfaces permite, no futuro, plugar adapters `BRICK_MONEY` ou `MONEYPHP` apenas mudando a forma de instanciar, sem quebrar o código consumidor. A biblioteca consolida os principais recursos das três referências estudadas (Dinero.js, Brick\Money, MoneyPHP).

---

## 2. Objetivos

### 2.1 Objetivos de Negócio
- Padronizar a manipulação de dinheiro em todos os projetos Puzl, eliminando erros de arredondamento por `float`.
- Reduzir acoplamento: permitir trocar o motor de cálculo (BCMath → Brick/MoneyPHP) no futuro sem refatorar consumidores.
- Reaproveitar o padrão arquitetural já consolidado (PzRequest/PzMail), reduzindo curva de aprendizado da equipe.

### 2.2 Objetivos do Usuário (desenvolvedor)
- Criar e operar valores monetários com uma **API fluente** e prazerosa de encadear.
- Ter cálculos exatos (sem `float`) com controle explícito de escala e arredondamento.
- Instalar em Laravel de forma "plug-and-play" (auto-discovery + config publicável).
- Persistir e serializar dinheiro com segurança (centavos/inteiro, decimal, JSON, cast Eloquent).

### 2.3 Critérios de Sucesso
- 100% das operações aritméticas usam BCMath (zero uso de `float` em cálculo).
- Cobertura de testes automatizados ampla sobre regras de cálculo, arredondamento, alocação, comparação e formatação (garantia de não-regressão).
- Trocar o adapter padrão via config não exige mudança no código consumidor.
- Instalação em um projeto Laravel funciona sem configuração manual obrigatória.

---

## 3. Requisitos Funcionais

### RF-01: Criação de valores monetários
**Descrição**: Permitir instanciar um valor monetário a partir de um decimal legível (`of('19.90', 'BRL')`), de unidades menores/centavos (`ofMinor(1990, 'BRL')`) e um valor zero (`zero('BRL')`). A criação deve validar a moeda e a escala.

**Critérios de Aceitação**:
- [ ] `of()` aceita string/int/decimal e moeda; rejeita valores inválidos com exceção clara.
- [ ] `ofMinor()` cria a partir de inteiro em unidades menores conforme a escala da moeda.
- [ ] `zero()` cria um valor zero na moeda informada.
- [ ] Valor é armazenado internamente como string/inteiro (nunca `float`).

**Prioridade**: Alta

### RF-02: Operações aritméticas fluentes (mutáveis)
**Descrição**: Suportar `add`, `subtract`, `multiply`, `divide`, `mod`, `absolute`, `negated`, `allocate`/`split`, `ratioOf`. As operações alteram o próprio objeto e retornam `$this` para encadeamento fluente. `add`/`subtract` aceitam variádicos.

**Critérios de Aceitação**:
- [ ] Todas as operações retornam a mesma instância (`$this`) permitindo encadear.
- [ ] `add`/`subtract` aceitam múltiplos argumentos de uma vez (variádico).
- [ ] Operações entre moedas diferentes lançam exceção de incompatibilidade.
- [ ] `allocate`/`split` distribuem o valor sem perder ou criar centavos.
- [ ] Todos os cálculos usam BCMath internamente.

**Prioridade**: Alta

### RF-03: Controle de escala e arredondamento
**Descrição**: Suportar modos de arredondamento via enum (`HALF_UP`, `HALF_DOWN`, `HALF_EVEN`, `UP`, `DOWN`, `CEILING`, `FLOOR`) com **default configurável** (`HALF_UP`). Permitir escala oficial da moeda ou escala customizada, e a opção de lançar exceção quando o arredondamento seria necessário.

**Critérios de Aceitação**:
- [ ] Cada operação que pode gerar casas excedentes aceita um modo de arredondamento opcional.
- [ ] O modo default é lido da configuração e pode ser sobrescrito por chamada.
- [ ] É possível definir escala customizada por instância.
- [ ] Existe modo que lança exceção em vez de arredondar silenciosamente.

**Prioridade**: Alta

### RF-04: Comparações e inspeção de sinal
**Descrição**: Expor `compareTo`, `isEqualTo`, `isGreaterThan(OrEqualTo)`, `isLessThan(OrEqualTo)`, `isZero`, `isPositive(OrZero)`, `isNegative(OrZero)`, `getSign`, e comparação segura sem lançar em mismatch (`isSameValueAs`).

**Critérios de Aceitação**:
- [ ] Métodos de comparação aceitam outro PzCurr ou número.
- [ ] Comparação entre moedas diferentes lança exceção (exceto `isSameValueAs`).
- [ ] Métodos de sinal retornam booleanos corretos; `getSign` retorna -1, 0 ou 1.

**Prioridade**: Alta

### RF-05: Catálogo de moedas (ISO 4217)
**Descrição**: Fornecer um catálogo de moedas ISO 4217 (código, código numérico, escala/casas decimais, símbolo) e suportar definição de **moedas customizadas** (ex.: cripto). Sem conversão de câmbio na v1.

**Critérios de Aceitação**:
- [ ] Moedas ISO comuns (ao menos BRL, USD, EUR, JPY) resolvem código, escala e símbolo.
- [ ] É possível registrar uma moeda customizada com escala própria.
- [ ] Moeda inexistente/ inválida lança exceção clara.

**Prioridade**: Média

### RF-06: Formatação e extração de valor
**Descrição**: Expor o valor como decimal (`getAmount`/`toDecimal`), como inteiro de unidades menores (`getMinorAmount`), e formatação para exibição. A formatação deve oferecer modo **manual configurável** (separadores e símbolo, foco pt-BR) e integração **opcional** com `intl`/`NumberFormatter` quando a extensão estiver disponível.

**Critérios de Aceitação**:
- [ ] `getMinorAmount()` retorna inteiro de centavos seguro para persistência.
- [ ] Formatação manual respeita separador de milhar/decimal e símbolo configuráveis.
- [ ] Quando `intl` está disponível, é possível formatar por locale.
- [ ] Ausência de `intl` não quebra a biblioteca (degradação graciosa para modo manual).

**Prioridade**: Média

### RF-07: Serialização (JSON / array) e persistência
**Descrição**: Suportar `toArray()`, `jsonSerialize()` e um **Eloquent Cast** para persistir/recuperar valores monetários (armazenando amount + currency). Recomendar persistência em unidades menores (inteiro) ou decimal string.

**Critérios de Aceitação**:
- [ ] `jsonSerialize()` produz estrutura estável (ex.: amount, currency, scale).
- [ ] Eloquent Cast converte coluna(s) ↔ PzCurr de forma transparente.
- [ ] Nenhuma conversão usa `float` em nenhuma etapa.

**Prioridade**: Média

### RF-08: Ponto de entrada (Factory) e seleção de adapter
**Descrição**: `PzCurrFactory::make()` é o ponto de entrada único. Resolve o adapter por: argumento explícito → `config('pzcurr.adapter')` → env `PZCURR_ADAPTER` → default `BCMATH`. Adapters declarados na enum sem implementação (`BRICK_MONEY`, `MONEYPHP`) sofrem fallback silencioso para `BCMATH`.

**Critérios de Aceitação**:
- [ ] `make()` sem argumentos retorna o adapter BCMath.
- [ ] Configuração/env alteram o adapter sem mudar código consumidor.
- [ ] Valores desconhecidos caem em fallback para BCMath sem lançar.

**Prioridade**: Alta

### RF-09: Integração Laravel (auto-discovery + config)
**Descrição**: Fornecer `PzCurrServiceProvider` com auto-discovery (`extra.laravel.providers`), merge de `config/pzcurr.php` e publish da config com tag dedicada apenas em console.

**Critérios de Aceitação**:
- [ ] Pacote funciona sem registro manual do provider (auto-discovery).
- [ ] `php artisan vendor:publish` com a tag publica `config/pzcurr.php`.
- [ ] Config define adapter padrão, moeda padrão, escala/arredondamento padrão e formatação.

**Prioridade**: Alta

---

## 4. Requisitos Não-Funcionais

### RNF-01: Precisão (Correção Numérica)
**Descrição**: Todo cálculo monetário deve usar BCMath com precisão arbitrária. É proibido converter valores para `float` em qualquer etapa de cálculo ou persistência.

### RNF-02: Desacoplamento e Extensibilidade
**Descrição**: O código consumidor depende apenas da interface pública (`PzCurrInterface`), nunca de BCMath, Brick\Money ou MoneyPHP diretamente. Trocar de adapter deve ser uma mudança de instanciação/config, sem alterar consumidores.

### RNF-03: Compatibilidade
**Descrição**: PHP `^8.1`, suporte a Laravel/Illuminate `^10|^11|^12`, alinhado às demais libs Puzl. Requer a extensão `bcmath` habilitada.

### RNF-04: Qualidade e Não-Regressão
**Descrição**: `declare(strict_types=1)` em todos os arquivos, tipagem estrita, e suíte de testes (Unit + Features) cobrindo todas as regras de negócio para garantir não-regressão. Convenção de nomenclatura `PzCurr*`.

### RNF-05: Usabilidade da API
**Descrição**: API fluente e encadeável nas operações; nomes consistentes com as bibliotecas de referência para reduzir surpresa. Mensagens de exceção claras e acionáveis.

---

## 5. Casos de Uso

### 5.1 Caso de Uso Principal — Cálculo de pedido
**Ator**: Desenvolvedor backend

**Fluxo Principal**:
1. Cria o valor base: `PzCurrFactory::make()->of('25.00', 'BRL')`.
2. Encadeia operações: `->add('4.99')->subtract('2.50')->multiply(2)`.
3. Formata para exibição: `->format()` → `R$ 54,98`.
4. Persiste em centavos via `getMinorAmount()` ou Eloquent Cast.

**Fluxo Alternativo**:
- Divisão com sobra: usa `allocate([…])`/`split(n)` para ratear sem perder centavos.

**Fluxo de Exceção**:
- Somar moedas diferentes → exceção de incompatibilidade de moeda.
- Operação que exige arredondamento no modo "exception" → exceção de arredondamento necessário.

### 5.2 Caso de Uso Secundário — Troca de adapter
**Ator**: Tech lead

**Fluxo Principal**:
1. Define `PZCURR_ADAPTER=BRICK_MONEY` (quando implementado no futuro).
2. Código consumidor permanece inalterado.
3. Factory passa a instanciar o novo adapter.

---

## 6. Regras de Negócio

### RN-01: Proibição de float
Nenhum cálculo ou conversão pode usar `float`. Toda aritmética é feita com BCMath sobre strings/inteiros.

### RN-02: Imutabilidade da moeda na operação
Operações aritméticas só são válidas entre valores de mesma moeda; caso contrário, lançar exceção de incompatibilidade.

### RN-03: Conservação na alocação
Em `allocate`/`split`, a soma das partes deve ser exatamente igual ao valor original (sem centavos perdidos ou criados).

### RN-04: Arredondamento explícito e configurável
O modo de arredondamento padrão vem da config (`HALF_UP`), mas pode ser sobrescrito por operação; deve existir modo que lança exceção em vez de arredondar.

### RN-05: Escala conforme a moeda
A escala padrão segue a definição ISO da moeda (ex.: BRL/USD = 2, JPY = 0), salvo escala customizada explícita.

---

## 7. Interface e Experiência do Usuário

### 7.1 Wireframes/Mockups
Não se aplica (biblioteca/SDK sem UI). A "interface" é a API pública fluente em PHP.

### 7.2 Fluxo de Navegação
Não se aplica. Fluxo de uso programático: `Factory → of()/ofMinor() → operações encadeadas → comparação/format/serialização`.

### 7.3 Diretrizes de UX (Developer Experience)
- API fluente e encadeável; setters/operações retornam `$this`.
- Nomenclatura alinhada às libs de referência para previsibilidade.
- Exceções específicas e mensagens claras.

---

## 8. Integrações e Dependências

### 8.1 APIs Externas
- Nenhuma na v1 (sem conversão de câmbio / sem provedores de taxa).

### 8.2 Dependências Internas
- Padrão arquitetural das libs Puzl (PzRequest/PzMail/PzPdf) como referência de estrutura.

### 8.3 Dependências Técnicas
- PHP `^8.1` com extensão **bcmath** (obrigatória).
- `illuminate/support ^10|^11|^12` (integração Laravel).
- Extensão **intl** (opcional, apenas para formatação por locale).
- `phpunit/phpunit ^10.5` (testes, dev).

---

## 9. Restrições e Limitações

### 9.1 Restrições Técnicas
- Requer a extensão `bcmath` habilitada no ambiente.
- Formatação por locale depende da extensão `intl` (opcional, com fallback manual).

### 9.2 Restrições de Negócio
- v1 implementa apenas o adapter `BCMATH`; `BRICK_MONEY` e `MONEYPHP` ficam previstos na enum, mas não implementados (fallback para BCMath).
- v1 não realiza conversão de câmbio entre moedas.

---

## 10. Fora do Escopo

**O que NÃO será implementado nesta versão**:
- ❌ Implementação concreta dos adapters `Brick\Money` e `MoneyPHP` (apenas previstos na enum).
- ❌ Conversão de câmbio e provedores de taxa (ConfigurableProvider, PdoProvider, etc.).
- ❌ `MoneyBag` / soma de múltiplas moedas em um único agregado.
- ❌ Números racionais/fração intermediária (estilo `RationalMoney` do Brick).
- ❌ Interface gráfica / componentes visuais.

---

## 11. Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Erros de arredondamento em divisões/alocações | Alto | Média | Testes exaustivos de allocate/split garantindo conservação; arredondamento explícito por enum |
| Modelo mutável causar efeitos colaterais inesperados | Médio | Média | Documentar claramente a mutabilidade; oferecer método de cópia/clone quando necessário |
| Ambiente sem extensão `bcmath` | Alto | Baixa | Checagem na inicialização com mensagem clara; documentar requisito |
| Acoplamento acidental ao BCMath vazando para consumidores | Médio | Baixa | Interface pública estrita; adapter isolado; teste de convenção/arquitetura |
| Divergência de nomenclatura com libs de referência | Baixo | Média | Mapear nomes no inventário das 3 libs e seguir convenção consistente |

---

## 12. Anexos

### 12.1 Referências
- Dinero.js — https://www.dinerojs.com/getting-started/quick-start
- Brick\Money — https://github.com/brick/money
- MoneyPHP — https://www.moneyphp.org/en/stable/getting-started.html
- Padrão arquitetural: PzRequest, PzMail, PzPdf (repositórios internos Puzl)

### 12.2 Glossário
- **Unidades menores (minor units)**: menor subdivisão da moeda (ex.: centavos). BRL 19,90 = 1990 centavos.
- **Escala (scale)**: número de casas decimais da moeda (BRL/USD = 2, JPY = 0).
- **Adapter**: implementação concreta do motor de cálculo (v1: BCMath) atrás da interface pública.
- **Allocate/Split**: distribuição de um valor em partes/ratios sem perda de centavos.
- **Fluent interface**: estilo de API encadeável (`$pz->add()->subtract()->format()`).
- **BCMath**: extensão nativa do PHP para aritmética de precisão arbitrária.

---

## Histórico de Versões

| Versão | Data | Autor | Descrição |
|--------|------|-------|-----------|
| 1.0 | 2026-06-04 | Equipe Puzl | Versão inicial |
