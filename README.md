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

- PHP 8.1 ou superior
- Extensão `ext-dom`
- Extensão `ext-xml`
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
- PHPStan level 6 (evoluindo para 8)
- Cobertura de testes mínima de 80%
- Documentação PHPDoc completa

## Licença

MIT License - veja o arquivo [LICENSE](LICENSE) para detalhes.

## Créditos

- Desenvolvido por [MkGrow](https://github.com/mkgrow)
- Baseado em [PHPOffice/PHPWord](https://github.com/PHPOffice/PHPWord)
