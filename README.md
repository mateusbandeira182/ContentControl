# ContentControl - PHPWord Extension

Biblioteca PHP que estende PHPOffice/PHPWord para adicionar suporte a Content Controls (Structured Document Tags) do Microsoft Word.

## Instalação

```bash
composer require mkgrow/content-control
```

## Uso Básico

```php
use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\PhpWord;

$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText('Conteúdo protegido');

$contentControl = new ContentControl($section, [
    'id' => 'ctrl-1',
    'alias' => 'Meu Controle',
    'tag' => 'protected',
    'lockType' => 'sdtContentLocked'
]);

$xml = $contentControl->getXml();
```

## Error Handling

A partir da versão 2.0, a biblioteca utiliza exceptions customizadas para tratamento de erros mais robusto.

### Abordagem Simples

Para casos simples, capture a exception base para todos os erros da biblioteca:

```php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\IOFactory;
use MkGrow\ContentControl\Exception\ContentControlException;
use PhpOffice\PhpWord\PhpWord;

try {
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    $section->addText('Conteúdo');
    
    $control = new ContentControl($section, [
        'alias' => 'Campo Principal',
        'tag' => 'main-field'
    ]);
    
    IOFactory::saveWithContentControls(
        $phpWord,
        [$control],
        '/caminho/documento.docx'
    );
    
    echo "Documento salvo com sucesso!";
    
} catch (ContentControlException $e) {
    // Captura todos os erros da biblioteca
    error_log("Erro ao salvar documento: " . $e->getMessage());
    // Tratar erro (exibir mensagem, retry, etc)
}
```

### Tratamento Granular

Para controle mais fino, capture exceptions específicas:

```php
use MkGrow\ContentControl\IOFactory;
use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;
use MkGrow\ContentControl\Exception\TemporaryFileException;

try {
    IOFactory::saveWithContentControls($phpWord, [$control], $filename);
    
} catch (ZipArchiveException $e) {
    // Erro ao manipular arquivo ZIP (corrupto, formato inválido)
    error_log("Arquivo DOCX corrompido ou inválido: " . $e->getMessage());
    
} catch (DocumentNotFoundException $e) {
    // word/document.xml ausente no DOCX
    error_log("Estrutura DOCX inválida: " . $e->getMessage());
    
} catch (TemporaryFileException $e) {
    // Falha ao limpar arquivo temporário (pode ser ignorado)
    error_log("Aviso: arquivo temporário não removido: " . $e->getMessage());
    
} catch (\RuntimeException $e) {
    // Diretório não gravável, falha ao mover arquivo
    error_log("Erro de permissão ou I/O: " . $e->getMessage());
}
```

### Hierarquia de Exceptions

```
RuntimeException (built-in)
└── ContentControlException (base)
    ├── ZipArchiveException
    ├── DocumentNotFoundException
    └── TemporaryFileException
```

Todas as exceptions customizadas estendem `ContentControlException`, permitindo captura unificada ou granular conforme necessidade.

## Desenvolvimento

### Setup Inicial

```bash
# Clonar o repositório
git clone https://github.com/mkgrow/content-control.git
cd content-control

# Instalar dependências
composer install
```

### Executar Testes

```bash
# Todos os testes
composer test

# Apenas testes unitários
composer test:unit

# Apenas testes de feature
composer test:feature

# Com cobertura (requer Xdebug)
composer test:coverage

# Gerar relatório HTML de cobertura
composer test:coverage-html
```

Após gerar o relatório HTML, abra `coverage/html/index.html` no navegador.

### Análise Estática

```bash
# Executar PHPStan
composer analyse

# Gerar baseline (primeira vez ou após refatorações grandes)
composer analyse:baseline
```

### Validação Completa

```bash
# Executar análise estática + testes
composer check

# Comando usado no CI/CD
composer ci
```

### Estrutura de Testes

```
tests/
├── Pest.php              # Configuração e helpers globais
├── Unit/                 # Testes unitários da classe ContentControl
│   ├── ContentControlTest.php
│   ├── PropertiesTest.php
│   └── XmlGenerationTest.php
├── Feature/              # Testes de integração com PHPWord
│   ├── PhpWordIntegrationTest.php
│   └── ElementSerializationTest.php
└── Fixtures/             # Dados de teste reutilizáveis
    └── SampleElements.php
```

### Cobertura de Código

**Mínimo exigido:** 80%

Para visualizar a cobertura atual:

```bash
composer test:coverage-html
```

O relatório será gerado em `coverage/html/index.html`.

### Requisitos

- PHP 8.2 ou superior
- Extensão `ext-dom`
- Extensão `ext-zip`
- Extensão `ext-mbstring`

## Contribuindo

Contribuições são bem-vindas! Siga estas etapas:

1. Fork o repositório
2. Crie uma branch para sua feature (`git checkout -b feature/nova-feature`)
3. Faça suas alterações
4. Execute os testes e validações (`composer check`)
5. Commit suas mudanças (`git commit -m 'Add: nova feature'`)
6. Push para a branch (`git push origin feature/nova-feature`)
7. Abra um Pull Request

### Padrões de Código

- PSR-12 para estilo de código
- **PHPStan level 9** (máximo rigor de type safety)
- Cobertura de testes mínima de 80%
- Documentação PHPDoc completa com tipos
- Exception-based error handling (não retornar `false` para erros)

## Licença

MIT License - veja o arquivo [LICENSE](LICENSE) para detalhes.

## Créditos

- Desenvolvido por [MkGrow](https://github.com/mkgrow)
- Baseado em [PHPOffice/PHPWord](https://github.com/PHPOffice/PHPWord)
