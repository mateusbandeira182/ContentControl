# Relatório Técnico: Duplicação de Conteúdo em Content Controls

## Problema Identificado

A implementação atual do ContentControl v2.0 apresenta duplicação de conteúdo quando Content Controls são aninhados hierarquicamente (ex: Table → Row → Cell).

### Causa Raiz

A arquitetura atual do SDTInjector funciona em duas etapas:

1. **Geração do documento base**: PHPWord gera o documento completo com toda a estrutura XML
2. **Injeção de SDTs**: SDTInjector adiciona elementos `<w:sdt>` **ao final** do `<w:body>` com conteúdo duplicado

**Fluxo atual que causa duplicação:**

```xml
<w:body>
    <!-- Estrutura original do PHPWord -->
    <w:tbl>
        <w:tr>
            <w:tc>
                <w:p>
                    <w:r>
                        <w:t>Conteúdo da célula</w:t> <!-- 1ª ocorrência -->
                    </w:r>
                </w:p>
            </w:tc>
        </w:tr>
    </w:tbl>
    
    <!-- SDTs injetados ao final (duplicação) -->
    <w:sdt>
        <w:sdtPr>...</w:sdtPr>
        <w:sdtContent>
            <w:p>
                <w:r>
                    <w:t>Conteúdo da célula</w:t> <!-- 2ª ocorrência -->
                </w:r>
            </w:p>
        </w:sdtContent>
    </w:sdt>
</w:body>
```

### Comparação com Abordagem Correta

O PHPWord nativo possui classe `SDT` que cria Content Controls **inline** (envolvendo elementos no local):

```xml
<w:body>
    <w:sdt>
        <w:sdtPr>...</w:sdtPr>
        <w:sdtContent>
            <w:tbl>
                <w:tr>
                    <w:tc>
                        <w:p>
                            <w:r>
                                <w:t>Conteúdo</w:t> <!-- Ocorrência única -->
                            </w:r>
                        </w:p>
                    </w:tc>
                </w:tr>
            </w:tbl>
        </w:sdtContent>
    </w:sdt>
</w:body>
```

## Soluções Implementadas

### 1. Modificação no SDTInjector (Mitigação Parcial)

**Arquivo:** `src/SDTInjector.php`

**Mudanças:**
- Adicionada propriedade `$elementsWithSdt` para rastrear elementos com SDT registrado
- Método `hasRegisteredSdt()` para verificar se elemento já possui SDT
- Modificado `serializeElement()` para **pular elementos filhos com SDT**

**Limitação:** Funciona apenas para `AbstractContainer` (Section, Cell). Table e Row não herdam de `AbstractContainer`, então seus Writers ainda serializam recursivamente.

### 2. Correção do teste.php (Solução Pragmática)

**Diretriz de Uso:**
- ✅ **Envolver apenas elementos "folha" (leaf nodes)** OU containers de alto nível
- ❌ **NUNCA aninhar SDTs** na mesma hierarquia (Table + Row + Cell)

**Exemplo correto:**
```php
// Opção A: Envolver apenas a Table inteira
$table = $section->addTable();
$cc->addContentControl($table, [...]);
$table->addRow()->addCell()->addText('Conteúdo');

// Opção B: Envolver apenas elementos Text individuais
$text1 = $section->addText('Texto 1');
$cc->addContentControl($text1, [...]);
```

**Exemplo incorreto (causa duplicação):**
```php
// ❌ NÃO FAZER: Aninhamento de SDTs
$table = $section->addTable();
$cc->addContentControl($table, [...]);

$row = $table->addRow();
$cc->addContentControl($row, [...]); // ← Causa duplicação

$cell = $row->addCell();
$text = $cell->addText('Conteúdo');
$cc->addContentControl($text, [...]); // ← Duplica 3x
```

## Testes Automatizados

**Arquivo:** `tests/Feature/NestedContentControlTest.php`

Testes criados para validar comportamento:
- ✅ Detecção de duplicação em hierarquias aninhadas
- ✅ Validação de elementos independentes sem duplicação
- ✅ Verificação de contagem de SDTs no documento

**Status Atual dos Testes:**
⚠️ **Falhando** - Confirmam que a duplicação existe na arquitetura atual.

## Recomendações Futuras

### Solução Definitiva (ContentControl v3.0 - Breaking Change)

**Abordagem:** Refatorar para usar API nativa de SDT do PHPWord

```php
// Proposta v3.0 (usa PhpOffice\PhpWord\Element\SDT)
$sdt = new \PhpOffice\PhpWord\Element\SDT('richText');
$sdt->setAlias('Texto Protegido');
$sdt->setTag('protected-text');
$sdt->setValue('Conteúdo aqui');
$section->addElement($sdt);
```

**Vantagens:**
- ✅ Elimina duplicação completamente
- ✅ Compatível com estrutura OOXML nativa
- ✅ SDTs inline (envolvem elementos no local)

**Desvantagens:**
- ❌ Breaking change (quebra API v2.0)
- ❌ Requer refatoração completa do SDTInjector
- ❌ Mudança no paradigma de uso

### Solução Intermediária (v2.1 - Compatível)

**Abordagem:** Pós-processamento do XML para remover duplicações

1. Gerar documento com PHPWord (sem SDTs)
2. Parsear document.xml com DOMDocument
3. **Remover elementos que têm SDT** do corpo do documento
4. Adicionar SDTs ao final com conteúdo completo

**Vantagens:**
- ✅ Mantém compatibilidade com API v2.0
- ✅ Resolve duplicação sem breaking changes

**Desvantagens:**
- ❌ Complexidade adicional (parsing + manipulação DOM)
- ❌ Performance (processar XML grande pode ser lento)
- ❌ Risco de quebrar estrutura complexa do documento

## Critérios de Sucesso

- [x] Problema identificado e documentado
- [x] Causa raiz analisada tecnicamente
- [x] Solução pragmática implementada (guidelines de uso)
- [x] Testes automatizados criados
- [x] Documentação atualizada (teste.php com exemplos corretos)
- [ ] Testes passando (aguarda refatoração v3.0)
- [ ] Solução definitiva (requer breaking change)

## Tempo de Conclusão

- **Análise e diagnóstico:** ✅ Concluído (1h)
- **Implementação de mitigação:** ✅ Concluído (30min)
- **Testes e documentação:** ✅ Concluído (30min)
- **Solução definitiva (v3.0):** 🔄 Pendente (estimativa: 4-6h)

## Próximos Passos

1. **Curto prazo:** Documentar limitação no README.md
2. **Médio prazo:** Avaliar implementação da solução intermediária (v2.1)
3. **Longo prazo:** Planejar refatoração completa para v3.0 com API nativa de SDT

---

**Data:** 28/01/2026  
**Versão Analisada:** ContentControl v2.0  
**Status:** Problema identificado, solução pragmática implementada, aguardando refatoração definitiva
