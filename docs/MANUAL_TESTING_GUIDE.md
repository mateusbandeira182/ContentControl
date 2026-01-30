# Manual Testing Guide - TableBuilder v0.4.0

**Data:** 30/01/2026  
**Versão:** 0.4.0  
**Testador:** _____________________  
**Ambiente:** Microsoft Word Desktop (Windows)

## Objetivo

Validar manualmente que as tabelas geradas pelo TableBuilder abrem corretamente no Microsoft Word e que os Content Controls funcionam conforme esperado.

## Arquivos de Teste Gerados

Execute os samples para gerar os arquivos de teste:

```bash
php samples/table_builder_basic.php
php samples/table_builder_advanced.php
```

### Arquivos Básicos (samples/output/)

1. **basic_simple_table.docx** - Tabela 2x2 simples
2. **basic_with_widths.docx** - Tabela com larguras customizadas
3. **basic_with_borders.docx** - Tabela com bordas estilizadas
4. **basic_dynamic_table.docx** - Tabela gerada dinamicamente de array

### Arquivos Avançados (samples/output/)

5. **advanced_styled_header.docx** - Tabela com cabeçalho estilizado
6. **advanced_alternating_colors.docx** - Cores alternadas nas linhas
7. **advanced_financial_report.docx** - Relatório financeiro completo
8. **advanced_multi_section.docx** - Tabela multi-seção complexa

## Checklist de Testes

### Teste 1: Abertura de Arquivos

**Objetivo:** Verificar se todos os arquivos abrem sem erros no Word

| Arquivo | Abre? | Sem Avisos? | Notas |
|---------|-------|-------------|-------|
| basic_simple_table.docx | ☐ | ☐ | |
| basic_with_widths.docx | ☐ | ☐ | |
| basic_with_borders.docx | ☐ | ☐ | |
| basic_dynamic_table.docx | ☐ | ☐ | |
| advanced_styled_header.docx | ☐ | ☐ | |
| advanced_alternating_colors.docx | ☐ | ☐ | |
| advanced_financial_report.docx | ☐ | ☐ | |
| advanced_multi_section.docx | ☐ | ☐ | |

**Critério de Sucesso:** Todos os arquivos devem abrir sem erros ou avisos de corrupção

---

### Teste 2: Renderização de Tabelas

**Objetivo:** Verificar se as tabelas aparecem corretamente formatadas

#### 2.1 basic_simple_table.docx

- ☐ Tabela 2x2 visível
- ☐ Células contêm: "Name", "Age", "John", "30"
- ☐ Bordas visíveis

#### 2.2 basic_with_widths.docx

- ☐ Coluna "Product" mais larga que "Price"
- ☐ Larguras proporcionais (3000 vs 2000 TWIPs)

#### 2.3 basic_with_borders.docx

- ☐ Bordas duplas visíveis
- ☐ Cor azul (#0000FF) nas bordas
- ☐ Espessura 12pt aplicada

#### 2.4 basic_dynamic_table.docx

- ☐ 4 linhas (1 header + 3 dados)
- ☐ Headers: "ID", "Name", "Department"
- ☐ Dados corretos (Alice/HR, Bob/IT, Carol/Sales)

---

### Teste 3: Estilos Avançados

#### 3.1 advanced_styled_header.docx

- ☐ Linha de cabeçalho com fundo escuro
- ☐ Texto do cabeçalho em branco/negrito
- ☐ Linhas de dados com fundo claro

#### 3.2 advanced_alternating_colors.docx

- ☐ Cores alternadas visíveis nas linhas
- ☐ Cinza claro (F0F0F0) nas linhas ímpares
- ☐ Branco nas linhas pares

#### 3.3 advanced_financial_report.docx

- ☐ Tabela com 5 colunas
- ☐ Formatação de valores monetários
- ☐ Linha de total destacada
- ☐ Bordas e alinhamentos corretos

#### 3.4 advanced_multi_section.docx

- ☐ Múltiplas seções visíveis
- ☐ Headers de seção com estilo diferenciado
- ☐ Dados agrupados corretamente

---

### Teste 4: Content Controls (Se Aplicável)

**Nota:** Este teste requer arquivos com SDTs. Se os samples não incluem SDTs, marque como N/A.

- ☐ Content Controls visíveis no modo de desenvolvedor
- ☐ Tags visíveis (Developer Tab → Design Mode)
- ☐ Aliases aparecem ao selecionar controle
- ☐ Controles são editáveis

**Como ativar Developer Tab no Word:**
1. File → Options → Customize Ribbon
2. Marcar "Developer" no lado direito
3. Click OK

---

### Teste 5: Compatibilidade

#### 5.1 Salvar e Reabrir

- ☐ Salvar arquivo como .docx
- ☐ Fechar e reabrir
- ☐ Formatação preservada após salvar

#### 5.2 Edição

- ☐ Adicionar texto nas células
- ☐ Modificar estilos via interface do Word
- ☐ Adicionar/remover linhas
- ☐ Sem perda de formatação ao editar

#### 5.3 Exportação

- ☐ Exportar para PDF
- ☐ PDF preserva formatação
- ☐ Tabelas legíveis no PDF

---

## Testes de Injeção (OPCIONAL)

**Nota:** O teste de injeção está apresentando erro de parsing XML. Marcar como SKIP por enquanto.

### teste_inject_manual.php (SKIP)

- ☐ SKIP - Erro conhecido: "Failed to parse word/document.xml as XML"
- ☐ Issue a ser criada: Caracteres especiais não escapados em XML

---

## Problemas Encontrados

### Problema 1
**Arquivo:** ___________________  
**Descrição:** ___________________  
**Severidade:** ☐ Crítico ☐ Alto ☐ Médio ☐ Baixo  
**Screenshot:** ___________________

### Problema 2
**Arquivo:** ___________________  
**Descrição:** ___________________  
**Severidade:** ☐ Crítico ☐ Alto ☐ Médio ☐ Baixo  
**Screenshot:** ___________________

### Problema 3
**Arquivo:** ___________________  
**Descrição:** ___________________  
**Severidade:** ☐ Crítico ☐ Alto ☐ Médio ☐ Baixo  
**Screenshot:** ___________________

---

## Resultado Final

### Resumo
- **Total de arquivos testados:** ___/8
- **Arquivos OK:** ___
- **Arquivos com problemas:** ___
- **Problemas críticos encontrados:** ___

### Aprovação
- ☐ **APROVADO** - Todos os testes passaram
- ☐ **APROVADO COM RESSALVAS** - Problemas menores encontrados (listar acima)
- ☐ **REPROVADO** - Problemas críticos impedem release

### Assinatura
**Nome:** _____________________  
**Data:** _____________________  
**Observações:** _____________________

---

## Anexos

### Versões de Software Testadas
- **Microsoft Word:** _____________________
- **Windows:** _____________________
- **PHP:** _____________________
- **ContentControl:** 0.4.0

### Arquivos de Log (se aplicável)
- Anexar screenshots de problemas encontrados
- Colar erros do console/terminal

