# NF-e / NFC-e — Casas decimais, arredondamento e validação SEFAZ

Documento de referência para emissão de **NF-e (modelo 55)** e **NFC-e (modelo 65)** com foco em **precisão decimal** e **regras de validação** que causam rejeições (629, 630, 610, 865, etc.).  
Consolidado a partir do **leiaute XSD 4.00** (Portal Fiscal / `sped-nfe`), **Notas Técnicas**, **Manual de Orientação do Contribuinte (MOC)** e documentação **ACBr** / bases de conhecimento de emissores.

> **Escopo:** regras de **layout XML** e **validação na autorização**. Regras tributárias de cálculo de ICMS/PIS/COFINS têm tolerâncias e fórmulas próprias (mencionadas onde impactam totais).  
> **Atualização:** conferir sempre o Anexo I e NT vigentes no [Portal da NF-e](https://www.nfe.fazenda.gov.br/portal/principal.aspx).

---

## 1. Resumo executivo

| Campo (tag) | Descrição | Casas decimais no XML | Tipo XSD (PL 009 v4) |
|-------------|-----------|------------------------|----------------------|
| `qCom` | Quantidade comercial | **0 a 4** | `TDec_1104v` |
| `qTrib` | Quantidade tributável | **0 a 4** | `TDec_1104v` |
| `vUnCom` | Valor unitário comercial | **0 a 10** | `TDec_1110v` |
| `vUnTrib` | Valor unitário tributação | **0 a 10** | `TDec_1110v` |
| `vProd` | Valor bruto do item | **exatamente 2** | `TDec_1302` |
| `vNF` e demais totais monetários | Totais da nota | **2** | tipos `TDec_1302*` |

**Regra central por item (finNFe = Normal):**

```
vProd ≈ vUnCom × qCom   (rejeição 629 se erro > R$ 0,01)
vProd ≈ vUnTrib × qTrib (rejeição 630 se erro > R$ 0,01)
```

O resultado da multiplicação deve ser **arredondado para 2 casas decimais** antes de comparar/informar `vProd`.

**Arredondamento usado na validação SEFAZ (consenso técnico):** **HALF_UP** — se o primeiro dígito descartado for **≥ 5**, incrementa a última casa conservada. **Não** é banker's rounding (HALF_EVEN).

**NFC-e:** as mesmas regras de item (**629** / **630**) e de totais aplicam-se à NFC-e; diferenças aparecem sobretudo em **pagamento** (`vPag` vs `vNF`, rejeição **865**) e no fluxo varejo.

---

## 2. Casas decimais — o que o leiaute permite

Fonte primária: `tiposBasico_v4.00.xsd` e `leiauteNFe_v4.00.xsd` (pacote **PL_009_V4**, NF-e 4.00).

### 2.1 Quantidade (`qCom`, `qTrib`)

- **Até 11 dígitos inteiros** e **de 0 a 4 casas decimais**.
- Padrão XSD `TDec_1104v`: aceita `0`, inteiros, ou parte decimal com 1–4 dígitos.
- Exemplos válidos: `10`, `2.5`, `0.4999`, `12345678901.1234`.
- **Inválido no XML:** 5 ou mais casas decimais (rejeição de **schema**, antes da SEFAZ).

Documentação do leiaute: *"Quantidade Comercial do produto, alterado para aceitar de 0 a 4 casas decimais e 11 inteiros"*.

### 2.2 Valor unitário (`vUnCom`, `vUnTrib`)

- **Até 11 dígitos inteiros** e **de 0 a 10 casas decimais**.
- Tipo XSD `TDec_1110v`.
- Campo **informativo** no manual: o contribuinte escolhe a precisão (0–10) conforme o negócio.
- Para **efeito de validação**, o valor unitário efetivo usado na regra 629/630 é o que está no XML; o MOC orienta que, alternativamente, pode-se obter unitário por `vProd / qCom` com todas as casas do quociente — o emissor deve ser **consistente** com o que a SEFAZ recalcula.

### 2.3 Valor do produto (`vProd`)

- **Sempre 2 casas decimais** (`TDec_1302`: 13 dígitos de corpo + 2 decimais).
- Exemplos: `19.98`, `1000.00`, `0.01`.

### 2.4 Valores monetários da nota (totais, impostos, frete, etc.)

- Predominantemente **2 decimais** (ex.: `vNF`, `vICMS`, `vDesc` no grupo `ICMSTot`).
- Alguns campos específicos usam outros tipos (ex.: percentuais com até 4 decimais em tags de alíquota) — ver XSD do grupo correspondente.

### 2.5 Campos especiais (veículos, combustível, etc.)

O leiaute define tipos próprios (ex.: **CMT** com 4 decimais em toneladas). Não substituem `qCom`/`vUnCom` do item comum.

### 2.6 Esclarecimento histórico (versões antigas)

Artigos antigos (ex.: orientações pré-ampliação do leiaute) citavam **4 casas** para quantidade e unitário no XML. No **layout 4.00 atual**, **unitário aceita até 10 casas**; quantidade permanece em **até 4**. Sempre validar contra o **XSD da versão** que você emite.

---

## 3. Pares comercial × tributável

**NT 2011/005** (orientação SEFAZ, ex.: [Secretaria da Economia GO](https://goias.gov.br/economia/n-2/)):

| Regra | Orientação |
|-------|------------|
| Quantidades | `qCom` e `qTrib` devem refletir a mesma operação quando a unidade é a mesma (ex.: ambos `10`). |
| Unitários | `vUnCom` e `vUnTrib` devem ser **idênticos** quando unidade comercial = tributável. |
| Total do item | `vProd` = `qCom × vUnCom` (629) **e** coerente com `qTrib × vUnTrib` (630). |

Quando **uCom ≠ uTrib** (ex.: venda em caixa, tributação em unidade), `qCom`/`vUnCom` e `qTrib`/`vUnTrib` divergem, mas **`vProd` é um único valor** — as **duas** multiplicações devem fechar com o mesmo `vProd` (dentro da tolerância). Erros de conversão entre unidades são causa frequente da **630**.

Detalhes adicionais podem ir em **informações complementares** (`infCpl`), inclusive preço/quantidade com todas as casas usadas internamente.

---

## 4. Arredondamento

### 4.1 Por item: `vProd` a partir de `vUnCom × qCom`

1. Multiplicar **vUnCom × qCom** (ou **vUnTrib × qTrib** para conferência 630) com precisão suficiente — na prática, usar aritmética decimal (**string** / BCMath / `decimal.js`), **nunca `float`** IEEE 754.
2. **Arredondar o resultado para 2 casas decimais**.
3. Informar esse valor em **`vProd`**.

**Modo de arredondamento na validação:** **HALF_UP** (dígito seguinte à 2ª casa ≥ 5 → sobe).

Exemplo:

| vUnCom | qCom | Produto exato | vProd no XML |
|--------|------|---------------|--------------|
| 9.99 | 2 | 19.98 | **19.98** |
| 1.005 | 3 | 3.015 → | **3.02** |

### 4.2 Tolerância por item (rejeições 629 e 630)

- **Tolerância: R$ 0,01** para mais ou para menos entre o `vProd` informado e o valor calculado (`vUnCom × qCom` ou `vUnTrib × qTrib`).
- Erro **superior a R$ 0,01** → rejeição **629** (comercial) ou **630** (tributável).
- Com tolerância, para vProd calculado 19,98, aceitam-se informados de **19,97** a **19,99**.

Aplica-se a **NF-e e NFC-e** com **finNFe = 1 (Normal)**.

### 4.3 ACBr e `RoundABNT`

O ACBr expõe **`ACBr.RoundABNT(valor, [digitos])`**, documentado como arredondamento **ABNT NBR 5891** (regra do “algarismo par/ímpar” quando seguido de 5 e zeros).

| Aspecto | ABNT (`RoundABNT`) | Validação SEFAZ (629/630) |
|---------|-------------------|---------------------------|
| Meio exato (ex. 2,5 → 2 casas) | Par mantém / ímpar sobe | **HALF_UP** — sobe se ≥ 5 |
| Uso recomendado | Legado ERP, serviços | **Cálculo fiscal NF-e** |

**Conclusão:** para **vProd** e totais da nota, alinhar ao **HALF_UP em 2 casas** após a multiplicação. Usar `RoundABNT` sem testar pode gerar **629** em empates “,5”. Preferir motor decimal (ex.: biblioteca com BCMath) com modo **HALF_UP**.

### 4.4 Impostos: base × alíquota

**NT 2013.005** (resumos em bases técnicas): o produto **base de cálculo × alíquota** deve ser arredondado para **2 casas**, com **tolerância de R$ 0,01** na validação — mesma ordem de magnitude da regra de item.

### 4.5 Total da nota (`vNF`) — rejeição 610

Fórmula validada (itens com `indTot = 1` no `vProd`; frete/desconto do item entram nos totais conforme regra):

```
vNF = (+) vProd (-) vDesc (-) vICMSDeson (+) vST (+) vFCPST
      (+) vFrete (+) vSeg (+) vOutro (+) vII (+) vIPI
      (+) vIPIDevol (+) vServ   (± conforme layout e exceções)
```

- **Tolerância no total da NF:** **R$ 0,50** para mais ou para menos (**NT 2012.003**, citada em manuais de validação GW03–GW22).
- Diferença **> R$ 0,50** → rejeição **610**.

Há **exceções** (importação CFOP 3xxx, faturamento direto veículos, ICMS desonerado não subtraído em versões específicas da NT) — ver tabela de regras do Anexo I.

---

## 5. NFC-e — pontos adicionais

A NFC-e **compartilha o leiaute de produtos e totais** com a NF-e:

| Tema | Comportamento |
|------|----------------|
| Item 629/630 | Igual NF-e |
| `vProd` 2 decimais | Igual |
| `qCom` / `vUnCom` | Igual |
| Total `vNF` | Igual (610, tolerância **R$ 0,50**) |
| Pagamento | Soma de `vPag` deve ser **≥ vNF** (rejeição **865** se pagamento **menor** que total) |

**Rejeição 865:** `Σ vPag < vNF` (NF-e e NFC-e). Exceções: `finNFe` 3 ou 4; `tPag = 90` (sem pagamento). Para arredondamentos, documentação cita a mesma linha de tolerância **R$ 0,01** em validações de valor derivado de multiplicação; no **865** o problema usual é pagamento digitado abaixo do total, não tolerância ampla.

**Troco:** em NFC-e, conferir grupo `pag` / `vTroco` para que **pagamentos − troco** fechem com `vNF`.

---

## 6. Fluxo recomendado de cálculo (implementação)

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Definir escala interna (ERP)                             │
│    • qCom/qTrib: ≤ 4 casas no XML                           │
│    • vUnCom/vUnTrib: ≤ 10 casas no XML                      │
│    • Trabalho interno: decimal/string (nunca float)           │
└───────────────────────────┬─────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Por item: produto = vUnCom × qCom (precisão ampla)       │
│    Arredondar HALF_UP → 2 casas → vProd                       │
└───────────────────────────┬─────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Conferir vUnTrib × qTrib → mesmo vProd (± R$ 0,01)      │
└───────────────────────────┬─────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Somar itens (indTot=1), frete, desc, impostos → vNF      │
│    Conferir ± R$ 0,50                                       │
└───────────────────────────┬─────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. NFC-e: Σ vPag ≥ vNF; ajustar troco                       │
└─────────────────────────────────────────────────────────────┘
```

### 6.1 Erros comuns

| Problema | Efeito |
|----------|--------|
| `float` / `double` no ERP | Deriva binária → 629 com centavos “fantasma” |
| Arredondar `vUnCom` antes de multiplicar | `vProd` não bate com SEFAZ |
| 5+ casas em `qCom` no XML | Rejeição de schema |
| `vProd` truncado em vez de arredondado | 629 (fórum ACBr: truncar `vProd`) |
| Soma de `vProd` dos itens ≠ `W07` | 564 e cadeia de totais |
| Muitas casas em unitário sem estratégia | Perda de precisão na multiplicação → 629 |

### 6.2 Estratégias ERP com mais casas internas que o XML

- **Opção A:** calcular `vProd` em 2 casas a partir de unitário e quantidade **já arredondados/truncados** para o que vai no XML.
- **Opção B (MOC):** fixar `vProd` (2 casas) e derivar `vUnCom = vProd / qCom` com até 10 casas no XML.
- **Opção C:** parametrizar “ajuste do valor bruto” (ex. parâmetros tipo `ArrVlrBru` em ERPs) para forçar coerência antes do XML.

O estoque interno pode usar **5 casas**; o XML só aceita **4** em quantidade — planejar **arredondamento na exportação**, não só na exibição.

---

## 7. Relação com bibliotecas de valor decimal (ex.: PzCurr)

| Conceito NF-e | Equivalente sugerido |
|---------------|------------------------|
| `vProd`, `vNF` (2 casas) | Moeda BRL, `scale = 2`, `HALF_UP` |
| `qCom` (até 4 casas) | “Unidade” customizada com `scale` 0–4 (ex. `QTY4`) |
| `vUnCom` (até 10 casas) | `scale` até 10 ou cálculo amplo + arredondamento final em 2 casas para `vProd` |
| Tolerância 629 | Comparar `abs(vProd - vUnCom×qCom) ≤ 0.01` em decimal |
| Evitar float | Alinhado ao RNF do PzCurr (BCMath / string) |

---

## 8. Rejeições rápidas (cheat sheet)

| Código | Condição resumida | Tolerância típica |
|--------|-------------------|-------------------|
| **629** | `vProd` ≠ `vUnCom × qCom` | ± **R$ 0,01** |
| **630** | `vProd` ≠ `vUnTrib × qTrib` | ± **R$ 0,01** |
| **610** | `vNF` ≠ fórmula dos totais | ± **R$ 0,50** |
| **865** | `Σ vPag` < `vNF` | Não se aplica tolerância “para menos” no pagamento |
| **564** | `vProd` total ≠ soma itens | Conferir `indTot` e somas |

---

## 9. Referências consultadas

### Oficiais / leiaute

- [NF-e — Portal Nacional](https://www.nfe.fazenda.gov.br/portal/principal.aspx) — NT e MOC
- XSD PL_009_V4: [tiposBasico_v4.00.xsd](https://github.com/nfephp-org/sped-nfe/blob/master/schemes/PL_009_V4/tiposBasico_v4.00.xsd), [leiauteNFe_v4.00.xsd](https://github.com/nfephp-org/sped-nfe/blob/master/schemes/PL_009_V4/leiauteNFe_v4.00.xsd)
- [Novas regras 629/630 — GO Economia (NT 2011/005)](https://goias.gov.br/economia/n-2/)

### Notas técnicas (citadas em manuais)

- **NT 2011/005** — validação `vProd` vs unitário × quantidade (629/630)
- **NT 2012.003** — tolerância **R$ 0,50** em totais da NF-e
- **NT 2013.005** — arredondamento e consistência de totais / base × alíquota
- **NT 2016.002** — pagamentos (865)

### ACBr

- [ACBr.RoundABNT](https://acbr.sourceforge.io/ACBrMonitor/ACBrRoundABNT.html)
- [Fórum ACBr — política de arredondamento NFe](https://www.projetoacbr.com.br/forum/topic/63583-politica-de-arredondamento-na-nfe/)
- [Fórum ACBr — vProd truncando](https://www.projetoacbr.com.br/forum/topic/77404-o-campo-vprod-valor-total-do-item-est%C3%A1-truncando-e-n%C3%A3o-arredondando-como-deveria/)

### Manuais e bases de conhecimento

- [FlexDocs — grupo prod](https://flexdocs.net/guia-nfe/prod/)
- [ND Digital — manual NF-e 4.00](http://manuais.nddigital.com.br/e-Forms/formacaoArquivos/nf-e-4_00-nt2022_003_v1_00b.html)
- [TOTVS TDN — vUnCom](https://tdn.totvs.com/pages/viewpage.action?pageId=353292106)
- [Softdata — erro 629](https://helpdesk.softdata.com.br/support/solutions/articles/11000118722-erro-629-valor-do-produto-difere-do-produto-valor-unit%C3%A1rio-de-comercializac%C3%A3o-e-quantidade-comercial)
- [Softdata — erro 610](https://helpdesk.softdata.com.br/support/solutions/articles/11000118769-erro-610-total-da-nf-difere-do-somat%C3%B3rio-dos-valores-comp%C3%B5e-o-valor-total-da-nf)
- [Alterdata — tolerâncias NT 2012.003](https://novoajuda.alterdata.com.br/outros-produtos/diferencas-toleraveis-na-nf-e)
- [DEV — IEEE 754 e rejeição 629](https://dev.to/vilsonneto/por-que-a-sefaz-rejeita-sua-nf-e-e-a-culpa-e-do-ieee-754-13a)

---

## 10. Disclaimer

Este arquivo **não substitui** o Anexo I (MOC) nem as Notas Técnicas publicadas no Portal da NF-e. Em caso de divergência, prevalece o documento oficial e a implementação da **UF autorizadora**. Recomenda-se homologação com XML de teste e consulta à versão da NT usada pelo seu provedor (ACBr, Focus, Tecnospeed, etc.).
