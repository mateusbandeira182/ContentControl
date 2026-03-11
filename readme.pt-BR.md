# ContentControl

> Leia em inglês: [README.md](README.md)

[![Build Status](https://github.com/mateusbandeira182/ContentControl/workflows/CI/badge.svg)](https://github.com/mateusbandeira182/ContentControl/actions)
[![Code Coverage](https://img.shields.io/badge/coverage-82.2%25-green.svg)](coverage/html/index.html)
[![Tests](https://img.shields.io/badge/tests-559%20passed-brightgreen.svg)](https://github.com/mateusbandeira182/ContentControl/actions)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](phpstan.neon)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net)
[![Version](https://img.shields.io/badge/version-0.7.1-blue.svg)](CHANGELOG.md)

**ContentControl** e uma biblioteca PHP que estende [PHPOffice/PHPWord](https://github.com/PHPOffice/PHPWord) para adicionar Word Content Controls (Structured Document Tags/SDTs) a arquivos .docx. A biblioteca permite protecao de conteudo e marcacao por metadados em conformidade com **ISO/IEC 29500-1:2016 secao 17.5.2**.

## Sumario

- [Instalacao](#instalacao)
- [Inicio Rapido](#inicio-rapido)
- [Recursos](#recursos)
- [Documentacao](#documentacao)
  - [Visao Geral da Arquitetura](#visao-geral-da-arquitetura)
  - [Componentes Principais](#componentes-principais)
  - [Configuracao](#configuracao)
  - [Tratamento de Erros](#tratamento-de-erros)
  - [Logs e Debug](#logs-e-debug)
- [Testes](#testes)
- [Changelog e Contribuicao](#changelog-e-contribuicao)
- [Seguranca](#seguranca)
- [Creditos](#creditos)
- [Licenca](#licenca)

## Instalacao

Instale via Composer:

```bash
composer require mkgrow/content-control
```

**Requisitos:**
- PHP >= 8.2
- ext-dom
- ext-mbstring
- ext-zip
- phpoffice/phpword ^1.4
- ramsey/uuid ^4.7

## Inicio Rapido

### Criando um Novo Documento com Content Controls

```php
<?php
require 'vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

// Cria um novo documento
$cc = new ContentControl();
$section = $cc->addSection();

// Adiciona elemento de texto
$text = $section->addText('Este campo esta protegido');

// Envolve com Content Control
$cc->addContentControl($text, [
    'alias' => 'Campo Protegido',
    'tag' => 'field_1',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Salva o documento
$cc->save('documento_protegido.docx');
```

### Modificando Documentos Existentes

```php
<?php
use MkGrow\ContentControl\ContentProcessor;

// Abre template existente
$processor = new ContentProcessor('template.docx');

// Substitui conteudo SDT por tag
$processor->replaceContent('field_1', 'Valor atualizado');

// Atualiza texto preservando formatacao
$processor->setValue('field_2', 'Novo texto');

// Opcional: remover wrappers SDT preservando conteudo visivel
$processor->removeAllControlContents();

// Salva alteracoes
$processor->save('saida.docx');
```

### Construindo Tabelas com Content Controls (v0.6.0+)

```php
<?php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

$cc = new ContentControl();
$section = $cc->addSection();
$table = $section->addTable(['borderSize' => 6, 'borderColor' => '1F4788']);

$row = $table->addRow();
$row->addCell(3000)->addText('Nome', ['bold' => true]);
$row->addCell(3000)->addText('Valor', ['bold' => true]);

$row2 = $table->addRow();
$row2->addCell(3000)->addText('Item 1');
$priceCell = $row2->addCell(3000);
$priceText = $priceCell->addText('$100');

$builder = new TableBuilder($table);
$builder->addContentControl($priceText, [
    'tag' => 'price_1',
    'alias' => 'Preco',
    'inlineLevel' => true,
    'runLevel' => true,
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

$cc->save('tabela.docx');
```

Para mais exemplos, veja [samples/](samples/).

## Recursos

**Capacidades principais:**
- Suporte a Content Controls em elementos PHPWord
- Conformidade com ISO/IEC 29500-1:2016 secao 17.5.2
- Protecao de SDTs e de conteudo
- Processamento de templates com XPath
- Run-level SDT (`<w:r>`) para cenarios granulares
- TableBuilder v2 com API direta do PHPWord
- Substituicao de GROUP SDT com estruturas complexas
- Suporte a SDTs em header e footer
- Hash UUID v5 para identificacao sem colisao
- Finalizacao de documento com unwrap de SDTs preservando conteudo visivel (v0.7.1)

## Documentacao

### Visao Geral da Arquitetura

A biblioteca segue arquitetura por composicao, com classes `final` e responsabilidades bem definidas:

- `ContentControl`: facade para criacao de documentos novos
- `ContentProcessor`: modificacao de templates existentes
- `SDTRegistry`: registro de elementos e IDs SDT
- `SDTInjector`: injecao SDT no XML via DOM
- `ElementLocator`: localizacao XPath de elementos
- `ElementIdentifier`: hashing e identificacao de elementos
- `SDTConfig`: value object imutavel
- `TableBuilder`: bridge para tabelas com SDTs

### Componentes Principais

- Guia de `ContentControl`: [docs/contentcontrol.md](docs/contentcontrol.md)
- Guia de `ContentProcessor`: [docs/contentprocessor.md](docs/contentprocessor.md)
- Guia de `TableBuilder`: [docs/TableBuilder.md](docs/TableBuilder.md)
- Hub de documentacao: [docs/README.md](docs/README.md)

### Configuracao

Principais opcoes de configuracao SDT:

```php
$config = [
    'id' => '12345678',
    'alias' => 'Nome de exibicao',
    'tag' => 'metadata_id',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
    'inlineLevel' => false,
    'runLevel' => false,
];
```

### Tratamento de Erros

Excecoes da biblioteca:

- `ContentControlException`
- `DocumentNotFoundException`
- `ZipArchiveException`
- `TemporaryFileException`

## Logs e Debug

Para inspecionar arquivos DOCX (ZIP + XML), consulte os fluxos de debug e validacao no README em ingles: [README.md](README.md).

## Testes

A biblioteca usa [Pest](https://pestphp.com/) e PHPStan nivel 9.

```bash
composer test
composer test:unit
composer test:feature
composer test:coverage
composer analyse
composer check
composer ci
```

Status atual de suite:
- 559 testes passando
- 3 testes pulados
- 0 falhas
- Cobertura minima: 80%

## Changelog e Contribuicao

- Changelog principal: [CHANGELOG.md](CHANGELOG.md)
- Changelog detalhado v0.7.1: [docs/0.x/CHANGELOG-v0.7.1.md](docs/0.x/CHANGELOG-v0.7.1.md)
- Guia de contribuicao: [CONTRIBUTING.md](CONTRIBUTING.md)

## Seguranca

Se voce identificar vulnerabilidade de seguranca, nao abra issue publica.

Reporte em privado para: **mateusbandeiraweb@gmail.com**

## Creditos

- [Mateus Bandeira](https://github.com/mateusbandeira182) - criador e maintainer
- [PHPOffice/PHPWord](https://github.com/PHPOffice/PHPWord)
- [ramsey/uuid](https://github.com/ramsey/uuid)
- [Pest](https://pestphp.com/)
- [PHPStan](https://phpstan.org/)

## Licenca

Este projeto e distribuido sob licenca MIT. Veja [LICENSE](LICENSE).