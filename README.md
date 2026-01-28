# ContentControl v2.0 - PHPWord Extension

[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-Level%209-brightgreen.svg)](https://phpstan.org/)
[![Tests](https://img.shields.io/badge/tests-116%20passing-brightgreen.svg)](https://pestphp.com/)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-blue.svg)](https://www.php.net/)

Biblioteca PHP que adiciona suporte a **Content Controls** (Structured Document Tags conforme ISO/IEC 29500-1:2016 §17.5.2) para PHPOffice/PHPWord.

## ✨ Features

- 🎯 **API Simples**: Proxy Pattern unificado - uma classe para tudo
- 🔒 **Content Controls**: Rich Text, Plain Text, Picture, Group
- 🛡️ **Proteção de Conteúdo**: Bloqueio de SDT, conteúdo ou desbloqueado
- 🔑 **IDs Únicos**: Gerenciamento automático de IDs (8 dígitos)
- ✅ **Type Safety**: PHPStan Level 9 strict mode
- 📝 **ISO Compliant**: Conforme ISO/IEC 29500-1:2016

## 📦 Instalação

```bash
composer require mkgrow/content-control
```

**Requisitos:**
- PHP 8.2+
- ext-dom, ext-zip, ext-mbstring
- phpoffice/phpword ^1.4

## � Migração v1.x → v2.0

### Principais Breaking Changes

A versão 2.0 introduz uma nova arquitetura baseada no **Proxy Pattern**, eliminando a necessidade de gerenciar manualmente a classe `IOFactory` e simplificando drasticamente a API.

#### 1. Classe IOFactory Removida

**❌ v1.x (Deprecated):**
```php
use PhpOffice\PhpWord\PhpWord;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\IOFactory;

$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText('Conteúdo');

$control = new ContentControl($section, [
    'id' => '12345678',
    'alias' => 'Nome do Cliente'
]);

// Salvamento manual via IOFactory
IOFactory::saveWithContentControls($phpWord, [$control], 'output.docx');
```

**✅ v2.0 (Current):**
```php
use MkGrow\ContentControl\ContentControl;

// ContentControl encapsula PhpWord automaticamente
$cc = new ContentControl();
$section = $cc->addSection();
$section->addText('Conteúdo');

// Registrar Content Control
$cc->addContentControl($section, [
    'id' => '12345678',  // Opcional - auto-gerado se omitido
    'alias' => 'Nome do Cliente'
]);

// Salvamento direto
$cc->save('output.docx');
```

#### 2. API de Constructor Alterada

**❌ v1.x:**
```php
// Content Control criado passando elemento no constructor
$control = new ContentControl($section, ['alias' => 'Campo']);
```

**✅ v2.0:**
```php
// Content Control registrado após adicionar conteúdo
$section = $cc->addSection();
$cc->addContentControl($section, ['alias' => 'Campo']);
```

#### 3. Writer Customizado Removido

**❌ v1.x:** Necessário configurar Writer manualmente  
**✅ v2.0:** Injeção de SDTs totalmente automatizada em `$cc->save()`

### Exemplo Completo de Migração

#### Código v1.x (Deprecated)
```php
<?php
use PhpOffice\PhpWord\PhpWord;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\IOFactory;

// Criar documento base
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText('Prezado(a) Cliente,');

// Criar Content Control
$customerSection = $phpWord->addSection();
$customerSection->addText('Nome: __________');
$control1 = new ContentControl($customerSection, [
    'id' => '12345678',
    'alias' => 'Dados do Cliente',
    'tag' => 'customer-data',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Criar outro Content Control
$productSection = $phpWord->addSection();
$productSection->addText('Produto: __________');
$control2 = new ContentControl($productSection, [
    'id' => '87654321',
    'alias' => 'Informações do Produto',
    'tag' => 'product-info'
]);

// Salvar manualmente
IOFactory::saveWithContentControls(
    $phpWord, 
    [$control1, $control2], 
    'contrato.docx'
);
```

#### Código v2.0 (Current)
```php
<?php
use MkGrow\ContentControl\ContentControl;

// ContentControl é o ponto único de entrada
$cc = new ContentControl();

// Adicionar conteúdo normalmente
$section = $cc->addSection();
$section->addText('Prezado(a) Cliente,');

// Seção 1: Dados do Cliente
$customerSection = $cc->addSection();
$customerSection->addText('Nome: __________');
$cc->addContentControl($customerSection, [
    // ID omitido - será gerado automaticamente
    'alias' => 'Dados do Cliente',
    'tag' => 'customer-data',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Seção 2: Informações do Produto
$productSection = $cc->addSection();
$productSection->addText('Produto: __________');
$cc->addContentControl($productSection, [
    'alias' => 'Informações do Produto',
    'tag' => 'product-info'
]);

// Salvamento único com injeção automática
$cc->save('contrato.docx');
```

### Benefícios da Migração

| Aspecto | v1.x | v2.0 |
|---------|------|------|
| **Classes para importar** | 3 (PhpWord, ContentControl, IOFactory) | 1 (ContentControl) |
| **Gerenciamento de IDs** | Manual (obrigatório) | Automático (opcional) |
| **Error Handling** | Retorno booleano | Exceptions tipadas |
| **Type Safety** | PHPStan Level 7 | PHPStan Level 9 Strict |
| **Imutabilidade** | Propriedades públicas mutáveis | Value Objects readonly |
| **API Fluente** | ❌ Não suportada | ✅ Fluent chaining |

### Guia de Migração Passo a Passo

1. **Remover imports antigos:**
   ```php
   // ❌ Remover
   use PhpOffice\PhpWord\PhpWord;
   use MkGrow\ContentControl\IOFactory;
   ```

2. **Substituir criação de PhpWord:**
   ```php
   // ❌ v1.x
   $phpWord = new PhpWord();
   
   // ✅ v2.0
   $cc = new ContentControl();
   ```

3. **Atualizar adição de seções:**
   ```php
   // ❌ v1.x
   $section = $phpWord->addSection();
   
   // ✅ v2.0 (delega transparentemente)
   $section = $cc->addSection();
   ```

4. **Migrar criação de Content Controls:**
   ```php
   // ❌ v1.x
   $control = new ContentControl($section, ['alias' => '...']);
   
   // ✅ v2.0
   $cc->addContentControl($section, ['alias' => '...']);
   ```

5. **Substituir salvamento:**
   ```php
   // ❌ v1.x
   IOFactory::saveWithContentControls($phpWord, [$control1, $control2], 'file.docx');
   
   // ✅ v2.0
   $cc->save('file.docx');
   ```

### Casos Avançados: PhpWord Existente

Se você já tem uma instância de `PhpWord` e quer usar Content Controls:

```php
use PhpOffice\PhpWord\PhpWord;
use MkGrow\ContentControl\ContentControl;

// Documento PHPWord existente
$phpWord = new PhpWord();
$phpWord->getDocInfo()->setTitle('Meu Documento');
// ... configurações existentes ...

// Encapsular em ContentControl
$cc = new ContentControl($phpWord);

// Continuar normalmente
$section = $cc->addSection();
$cc->addContentControl($section, ['alias' => 'Campo']);
$cc->save('documento.docx');
```

### Troubleshooting

**Erro: `Class IOFactory not found`**
- **Causa:** Código v1.x usando API antiga
- **Solução:** Remover `use MkGrow\ContentControl\IOFactory` e usar `$cc->save()`

**Erro: `ContentControl::__construct() expects 0-1 parameters, 2 given`**
- **Causa:** Tentando passar elemento no constructor (padrão v1.x)
- **Solução:** Usar `$cc->addContentControl($element, $options)` após criar seção

**IDs duplicados após migração:**
- **Causa:** IDs hardcoded podem colidir com IDs gerados
- **Solução:** Remover parâmetro `id` das opções (deixar auto-gerar) ou usar IDs únicos

## �🚀 Uso Rápido

```php
use MkGrow\ContentControl\ContentControl;

// 1. Criar instância do ContentControl (proxy para PhpWord)
$cc = new ContentControl();

// 2. Adicionar conteúdo ao documento
$section = $cc->addSection();
$section->addText('Este texto está protegido por Content Control');

// 3. Envolver Section em Content Control
$cc->addContentControl($section, [
    'alias' => 'Nome do Cliente',      // Nome exibido no Word
    'tag' => 'customer-name',          // ID para programação
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED  // Não pode deletar
]);

// 4. Salvar documento (.docx com SDTs injetados)
$cc->save('documento.docx');
```

## 📖 Documentação

### Content Control Types

```php
ContentControl::TYPE_RICH_TEXT    // Texto com formatação (padrão)
ContentControl::TYPE_PLAIN_TEXT   // Texto simples
ContentControl::TYPE_PICTURE      // Controle de imagem
ContentControl::TYPE_GROUP        // Agrupa elementos
```

### Lock Types

```php
ContentControl::LOCK_NONE              // Sem bloqueio (padrão)
ContentControl::LOCK_SDT_LOCKED        // Não pode deletar o SDT
ContentControl::LOCK_CONTENT_LOCKED    // Não pode editar conteúdo
ContentControl::LOCK_UNLOCKED          // Explicitamente desbloqueado
```

### Configuração Completa

```php
$cc = new ContentControl();
$section = $cc->addSection();
$table = $section->addTable();
// ... adicionar linhas/células

$cc->addContentControl($table, [
    'id' => '12345678',                           // ID único (opcional - auto-gerado)
    'alias' => 'Tabela de Produtos',              // Nome amigável
    'tag' => 'products-table',                    // Tag para busca programática
    'type' => ContentControl::TYPE_RICH_TEXT,     // Tipo do controle
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED  // Bloquear edição
]);

$cc->save('catalogo.docx');
```

### Restrições de Caracteres

#### Alias (Nome Amigável)
O `alias` é exibido no Word e não pode conter:
- ❌ Caracteres XML reservados: `< > & " '`
- ❌ Caracteres de controle (0x00-0x1F, 0x7F-0x9F)
- ✅ Máximo 255 caracteres UTF-8

```php
// ✅ Válido
$cc->addContentControl($section, [
    'alias' => 'Nome do Cliente (Obrigatório)'
]);

// ❌ Inválido - contém caracteres XML reservados
$cc->addContentControl($section, [
    'alias' => 'Cliente <obrigatório>'  // Exception: XML reserved characters
]);
```

#### Tag (Identificador Programático)
A `tag` é usada para identificação programática e deve:
- ✅ Começar com letra ou underscore (`a-z`, `A-Z`, `_`)
- ✅ Conter apenas: letras, números, hífen, underscore, ponto
- ✅ Máximo 255 caracteres
- ❌ Não pode conter espaços ou caracteres especiais

```php
// ✅ Válido
$cc->addContentControl($section, [
    'tag' => 'customer-name',
    'tag' => 'product_price',
    'tag' => 'field.1.name',
    'tag' => '_internal_field'
]);

// ❌ Inválido
$cc->addContentControl($section, [
    'tag' => '123-field',        // Não pode começar com número
    'tag' => 'customer name',    // Não pode conter espaços
    'tag' => 'field@customer'    // Caractere @ não permitido
]);
```

#### ID (Identificador Único)
- ✅ 8 dígitos (10000000-99999999)
- ✅ Auto-gerado se omitido
- ❌ Não pode conter letras ou caracteres especiais

```php
// ✅ Válido
$cc->addContentControl($section, ['id' => '12345678']);
$cc->addContentControl($section, ['id' => '99999999']);
$cc->addContentControl($section, []);  // ID gerado automaticamente

// ❌ Inválido
$cc->addContentControl($section, ['id' => '123']);      // Menos de 8 dígitos
$cc->addContentControl($section, ['id' => 'ABC12345']); // Contém letras
```

### Múltiplos Content Controls

```php
$cc = new ContentControl();

// Seção 1: Cliente
$section1 = $cc->addSection();
$section1->addText('Nome: ___________');
$cc->addContentControl($section1, [
    'alias' => 'Dados do Cliente',
    'tag' => 'customer-info'
]);

// Seção 2: Produto
$section2 = $cc->addSection();
$section2->addText('Produto: ___________');
$cc->addContentControl($section2, [
    'alias' => 'Informações do Produto',
    'tag' => 'product-info'
]);

$cc->save('formulario.docx');
```

### Delegação PHPWord

ContentControl é um **Proxy** para `PhpWord`, então você pode usar todos os métodos:

```php
$cc = new ContentControl();

// Configurar documento
$cc->getDocInfo()->setTitle('Meu Documento');
$cc->getDocInfo()->setCreator('Sistema XYZ');

// Adicionar estilos
$cc->addFontStyle('negrito', ['bold' => true]);
$cc->addParagraphStyle('centralizado', ['alignment' => 'center']);

// Adicionar seções
$section = $cc->addSection(['orientation' => 'landscape']);
$section->addText('Texto em negrito', 'negrito', 'centralizado');

$cc->save('documento-estilizado.docx');
```

### Tratamento de Erros

#### Abordagem Simples

```php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ContentControlException;

try {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Conteúdo');
    
    $cc->addContentControl($section, [
        'alias' => 'Campo Principal',
        'tag' => 'main-field'
    ]);
    
    $cc->save('/caminho/documento.docx');
    
    echo "Documento salvo com sucesso!";
    
} catch (ContentControlException $e) {
    // Captura TODOS os erros da biblioteca
    error_log("Erro: " . $e->getMessage());
}
```

#### Tratamento Granular

```php
use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;
use MkGrow\ContentControl\Exception\TemporaryFileException;

try {
    $cc->save($filename);
    
} catch (ZipArchiveException $e) {
    // Erro ao manipular ZIP (arquivo corrupto)
    error_log("DOCX inválido: " . $e->getMessage());
    
} catch (DocumentNotFoundException $e) {
    // word/document.xml ausente (estrutura inválida)
    error_log("Estrutura DOCX corrompida: " . $e->getMessage());
    
} catch (TemporaryFileException $e) {
    // Falha ao limpar temp file (pode ignorar)
    error_log("Aviso: temp file não removido: " . $e->getMessage());
    
} catch (\RuntimeException $e) {
    // Diretório não gravável, falha I/O
    error_log("Erro de permissão: " . $e->getMessage());
}
```

#### Hierarquia de Exceptions

```
RuntimeException (PHP built-in)
└── ContentControlException (base)
    ├── ZipArchiveException
    ├── DocumentNotFoundException
    └── TemporaryFileException
```

#### Cenários Práticos de Error Handling

##### Validação de Entrada do Usuário

```php
use MkGrow\ContentControl\ContentControl;

function createProtectedDocument(string $customerName, string $outputPath): void
{
    try {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText("Cliente: {$customerName}");
        
        // Validação automática via SDTConfig
        $cc->addContentControl($section, [
            'alias' => $customerName,  // Pode lançar exception se contém < > & " '
            'tag' => 'customer-name',
            'lockType' => ContentControl::LOCK_SDT_LOCKED
        ]);
        
        $cc->save($outputPath);
        
    } catch (\InvalidArgumentException $e) {
        // Entrada inválida (caracteres XML reservados, ID inválido, etc)
        throw new \DomainException(
            "Nome do cliente contém caracteres inválidos: " . $e->getMessage(),
            0,
            $e
        );
    } catch (\RuntimeException $e) {
        // Erro de I/O (diretório não gravável, disco cheio)
        throw new \RuntimeException(
            "Falha ao salvar documento em {$outputPath}: " . $e->getMessage(),
            0,
            $e
        );
    }
}

// Uso
try {
    createProtectedDocument('João Silva', '/docs/contrato.docx');
} catch (\DomainException $e) {
    echo "Erro de validação: " . $e->getMessage();
} catch (\RuntimeException $e) {
    echo "Erro do sistema: " . $e->getMessage();
}
```

##### Processamento em Lote com Recuperação

```php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ContentControlException;

function processMultipleDocuments(array $customers, string $outputDir): array
{
    $results = ['success' => [], 'failed' => []];
    
    foreach ($customers as $customer) {
        try {
            $cc = new ContentControl();
            $section = $cc->addSection();
            $section->addText("Cliente: {$customer['name']}");
            
            $cc->addContentControl($section, [
                'alias' => $customer['name'],
                'tag' => "customer-{$customer['id']}"
            ]);
            
            $filename = "{$outputDir}/customer-{$customer['id']}.docx";
            $cc->save($filename);
            
            $results['success'][] = $customer['id'];
            
        } catch (ContentControlException $e) {
            // Erro específico da biblioteca - logar e continuar
            error_log("Falha ao processar cliente {$customer['id']}: " . $e->getMessage());
            $results['failed'][] = [
                'id' => $customer['id'],
                'error' => $e->getMessage()
            ];
            
        } catch (\Throwable $e) {
            // Erro inesperado - logar e continuar
            error_log("Erro inesperado para cliente {$customer['id']}: " . $e->getMessage());
            $results['failed'][] = [
                'id' => $customer['id'],
                'error' => 'Sistema indisponível'
            ];
        }
    }
    
    return $results;
}

// Uso
$customers = [
    ['id' => 1, 'name' => 'João Silva'],
    ['id' => 2, 'name' => 'Maria Santos'],
    ['id' => 3, 'name' => 'Cliente <Inválido>'],  // Falhará
];

$results = processMultipleDocuments($customers, '/tmp/docs');
echo "Processados: " . count($results['success']) . "\n";
echo "Falharam: " . count($results['failed']) . "\n";
```

##### Validação de Permissões

```php
use MkGrow\ContentControl\ContentControl;

function ensureDirectoryWritable(string $path): void
{
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) {
            throw new \RuntimeException("Não foi possível criar diretório: {$path}");
        }
    }
    
    if (!is_writable($path)) {
        throw new \RuntimeException("Diretório sem permissão de escrita: {$path}");
    }
}

function saveSecureDocument(string $content, string $outputPath): void
{
    // Validar diretório ANTES de processar
    ensureDirectoryWritable(dirname($outputPath));
    
    try {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText($content);
        
        $cc->addContentControl($section, [
            'alias' => 'Conteúdo Protegido',
            'lockType' => ContentControl::LOCK_CONTENT_LOCKED
        ]);
        
        $cc->save($outputPath);
        
    } catch (\RuntimeException $e) {
        // Se falhou após validação, pode ser disco cheio ou arquivo bloqueado
        throw new \RuntimeException(
            "Erro ao salvar documento (disco cheio ou arquivo em uso): " . $e->getMessage(),
            0,
            $e
        );
    }
}
```

##### Retry com Exponential Backoff

```php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\TemporaryFileException;

function saveWithRetry(ContentControl $cc, string $path, int $maxAttempts = 3): void
{
    $attempt = 0;
    $lastException = null;
    
    while ($attempt < $maxAttempts) {
        try {
            $cc->save($path);
            return; // Sucesso
            
        } catch (TemporaryFileException $e) {
            // Falha ao limpar temp file - não afeta documento final, ignorar
            error_log("Aviso: " . $e->getMessage());
            return;
            
        } catch (\RuntimeException $e) {
            $attempt++;
            $lastException = $e;
            
            if ($attempt < $maxAttempts) {
                // Exponential backoff: 100ms, 400ms, 1600ms
                $delay = (int) (100000 * pow(4, $attempt - 1));
                error_log("Tentativa {$attempt} falhou, aguardando " . ($delay / 1000) . "ms...");
                usleep($delay);
            }
        }
    }
    
    // Todas as tentativas falharam
    throw new \RuntimeException(
        "Falha ao salvar documento após {$maxAttempts} tentativas: " . 
        ($lastException ? $lastException->getMessage() : 'erro desconhecido'),
        0,
        $lastException
    );
}

// Uso em ambiente com I/O instável (rede, disco lento)
try {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Documento crítico');
    
    saveWithRetry($cc, '/mnt/network/document.docx');
    echo "Salvo com sucesso!\n";
    
} catch (\RuntimeException $e) {
    echo "Falha definitiva: " . $e->getMessage() . "\n";
}
```

## 🧪 Desenvolvimento

### Setup Inicial

```bash
git clone https://github.com/mkgrow/content-control.git
cd content-control
composer install
```

### Executar Testes

```bash
composer test              # Todos os testes (116 testes, 240 assertions)
composer test:unit         # Apenas unit tests
composer test:feature      # Apenas integration tests
composer test:coverage     # Com cobertura (requer Xdebug)
```

### Análise Estática

```bash
composer analyse           # PHPStan Level 9 strict mode
composer check             # Análise + Testes
```

### Estrutura do Projeto

```
src/
├── ContentControl.php      # Classe principal (Proxy Pattern)
├── SDTConfig.php          # Value Object para configuração
├── SDTRegistry.php        # Registry de IDs únicos
├── SDTInjector.php        # Service Layer (injeção de XML)
├── Assert.php             # Utility para type narrowing
└── Exception/             # Hierarquia de exceptions
    ├── ContentControlException.php
    ├── ZipArchiveException.php
    ├── DocumentNotFoundException.php
    └── TemporaryFileException.php

tests/
├── Unit/                  # Testes unitários (83 tests)
│   ├── SDTConfigTest.php
│   ├── SDTRegistryTest.php
│   └── SDTInjectorTest.php
└── Feature/               # Testes de integração (7 tests)
    ├── ElementSerializationTest.php
    └── PhpWordIntegrationTest.php
```

### Padrões de Código

- ✅ PSR-12 code style
- ✅ PHPStan Level 9 (máximo rigor)
- ✅ 80%+ test coverage
- ✅ Exception-based error handling
- ✅ Immutable value objects (readonly properties)
- ✅ Type hints completos (strict_types=1)

## 🏗️ Arquitetura v2.0

### Design Patterns

- **Proxy Pattern**: ContentControl encapsula PhpWord + SDTRegistry
- **Value Object**: SDTConfig imutável com readonly properties
- **Registry Pattern**: SDTRegistry gerencia IDs únicos
- **Service Layer**: SDTInjector abstrai manipulação de ZIP

### Decisões de Design

**Por que não estender PHPWord?**
- ✅ Mantém compatibilidade (sem fork)
- ✅ Permite atualizações do PHPWord
- ✅ Reduz acoplamento
- ⚠️  Requer manipulação de ZIP pós-geração

**Por que injeção pós-geração?**
- PHPWord não tem suporte nativo a SDTs
- Fork quebraria compatibilidade com upstream
- Injeção via ZIP mantém conformidade ISO/IEC 29500-1

Veja [.github/copilot-instructions.md](.github/copilot-instructions.md) para detalhes da arquitetura.

## ⚠️ Limitações Conhecidas

### Aninhamento de Content Controls

**Problema:** A arquitetura atual v2.0 pode causar duplicação de conteúdo quando Content Controls são aninhados hierarquicamente (ex: Table → Row → Cell).

**Causa:** SDTs são injetados ao final do `<w:body>` com conteúdo serializado, ao invés de envolverem elementos inline na estrutura original.

**Solução Temporária:**
- ✅ **Envolver apenas elementos "folha"** (Text, TextRun, Image) OU containers de alto nível (Table, Section)
- ❌ **NUNCA aninhar SDTs** na mesma hierarquia

**Exemplo correto:**
```php
// ✅ Opção A: Envolver apenas a Table inteira
$table = $section->addTable();
$cc->addContentControl($table, ['alias' => 'Tabela', ...]);
$table->addRow()->addCell()->addText('Conteúdo');

// ✅ Opção B: Envolver apenas elementos Text individuais
$text = $section->addText('Texto protegido');
$cc->addContentControl($text, ['alias' => 'Texto', ...]);
```

**Exemplo incorreto:**
```php
// ❌ NÃO FAZER: Aninhamento (causa duplicação)
$table = $section->addTable();
$cc->addContentControl($table, [...]);

$row = $table->addRow();
$cc->addContentControl($row, [...]); // ← Duplicação!

$cell = $row->addCell();
$text = $cell->addText('Conteúdo');
$cc->addContentControl($text, [...]); // ← Triplicação!
```

**Roadmap para v3.0:** Refatoração para usar API nativa de SDT do PHPWord (`\PhpOffice\PhpWord\Element\SDT`), eliminando completamente a duplicação. Veja [TECHNICAL_REPORT_DUPLICACAO.md](TECHNICAL_REPORT_DUPLICACAO.md) para análise técnica completa.

## 📝 Changelog

Veja [CHANGELOG.md](CHANGELOG.md) para histórico de versões.

**v2.0.0 (Breaking Changes):**
- ✨ Proxy Pattern: API unificada via classe ContentControl
- ✨ Gerenciamento automático de IDs únicos
- ✨ Value Objects imutáveis (SDTConfig)
- ✨ Exception-based error handling
- ❌ REMOVED: IOFactory (use `ContentControl::save()`)
- ❌ REMOVED: Herança de AbstractContainer

## 🤝 Contribuindo

Contribuições são bem-vindas!

1. Fork o repositório
2. Crie uma branch (`git checkout -b feature/nova-feature`)
3. Faça commit (`git commit -m 'Add: nova feature'`)
4. Execute testes (`composer check`)
5. Push (`git push origin feature/nova-feature`)
6. Abra um Pull Request

**Critérios de aceitação:**
- PHPStan Level 9 sem erros
- Testes com cobertura ≥80%
- PHPDoc completo com tipos

## 📄 Licença

MIT License - veja [LICENSE](LICENSE) para detalhes.

## 🙏 Créditos

- Desenvolvido por [MkGrow](https://github.com/mkgrow)
- Baseado em [PHPOffice/PHPWord](https://github.com/PHPOffice/PHPWord)
- Conforme ISO/IEC 29500-1:2016 (Office Open XML)
