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

## 🚀 Uso Rápido

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
