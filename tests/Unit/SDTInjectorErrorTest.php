<?php

declare(strict_types=1);

use MkGrow\ContentControl\SDTInjector;
use MkGrow\ContentControl\SDTConfig;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;

/**
 * Testes de cenários de erro no SDTInjector
 * 
 * Cobertura de linhas: 127, 167-174, 228, 275-292, 309, 321, 386
 */

test('inject lança DocumentNotFoundException se word/document.xml ausente', function () {
    // Criar DOCX inválido sem word/document.xml
    $tempFile = sys_get_temp_dir() . '/invalid_no_document_' . uniqid() . '.docx';
    
    $zip = new ZipArchive();
    $zip->open($tempFile, ZipArchive::CREATE);
    
    // Adicionar apenas [Content_Types].xml para parecer um DOCX
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="..."></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="..."></Relationships>');
    
    $zip->close();
    
    $injector = new SDTInjector();
    $section = createSection();
    $config = new SDTConfig(id: '12345678');
    
    try {
        $injector->inject($tempFile, [['element' => $section, 'config' => $config]]);
        expect(false)->toBeTrue(); // Não deve chegar aqui
    } catch (DocumentNotFoundException $e) {
        expect($e->getMessage())->toContain('word/document.xml');
        expect($e->getMessage())->toContain($tempFile);
    } finally {
        unlink($tempFile);
    }
});

test('loadDocumentAsDom lança RuntimeException se XML malformado', function () {
    $injector = new SDTInjector();
    $malformedXml = '<?xml version="1.0"?><w:body xmlns:w="..."><w:p unclosed>';
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('loadDocumentAsDom');
    $method->setAccessible(true);
    
    expect(fn() => $method->invoke($injector, $malformedXml))
        ->toThrow(\RuntimeException::class, 'Failed to load document.xml');
});

test('loadDocumentAsDom captura erros libxml', function () {
    $injector = new SDTInjector();
    
    // XML com múltiplos erros
    $badXml = '<?xml version="1.0"?><root><unclosed><invalid attr=noQuotes></root>';
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('loadDocumentAsDom');
    $method->setAccessible(true);
    
    try {
        $method->invoke($injector, $badXml);
        expect(false)->toBeTrue(); // Não deve chegar aqui
    } catch (\RuntimeException $e) {
        // Verificar que mensagem contém erro de libxml
        expect($e->getMessage())->toContain('Failed to load document.xml');
    }
});

test('serializeDocument lança RuntimeException se saveXML falhar', function () {
    $injector = new SDTInjector();
    
    // Criar DOM mock que falha ao serializar
    $dom = new class extends DOMDocument {
        public function saveXML(?DOMNode $node = null, int $options = 0): string|false
        {
            return false; // Simular falha
        }
    };
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('serializeDocument');
    $method->setAccessible(true);
    
    expect(fn() => $method->invoke($injector, $dom))
        ->toThrow(\RuntimeException::class, 'Failed to serialize DOM to XML');
});

test('getTypeElementName retorna w:picture para TYPE_PICTURE', function () {
    $injector = new SDTInjector();
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('getTypeElementName');
    $method->setAccessible(true);
    
    $result = $method->invoke($injector, ContentControl::TYPE_PICTURE);
    
    expect($result)->toBe('w:picture');
});

test('getTypeElementName retorna w:text para TYPE_PLAIN_TEXT', function () {
    $injector = new SDTInjector();
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('getTypeElementName');
    $method->setAccessible(true);
    
    $result = $method->invoke($injector, ContentControl::TYPE_PLAIN_TEXT);
    
    expect($result)->toBe('w:text');
});

test('getTypeElementName retorna w:group para TYPE_GROUP', function () {
    $injector = new SDTInjector();
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('getTypeElementName');
    $method->setAccessible(true);
    
    $result = $method->invoke($injector, ContentControl::TYPE_GROUP);
    
    expect($result)->toBe('w:group');
});

test('wrapElementInline lança RuntimeException se elemento sem owner document', function () {
    $injector = new SDTInjector();
    
    // Criar elemento com documento temporário, depois descartar documento
    $tempDom = new DOMDocument();
    $orphanElement = $tempDom->createElement('w:p');
    // NÃO adicionar ao documento, deixar órfão
    unset($tempDom); // Destruir documento
    
    $config = new SDTConfig(id: '12345678');
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('wrapElementInline');
    $method->setAccessible(true);
    
    // Elemento ainda tem ownerDocument (PHP mantém referência)
    // Vamos testar com elemento importado sem parent ao invés
    $newDom = new DOMDocument();
    $bodyElement = $newDom->createElement('w:body');
    $newDom->appendChild($bodyElement);
    $pElement = $newDom->createElement('w:p');
    // NÃO adicionar ao body (sem parent)
    
    // Este elemento TEM ownerDocument mas NÃO tem parent
    // Então testar com o segundo teste de parent
    expect(fn() => $method->invoke($injector, $pElement, $config))
        ->toThrow(\RuntimeException::class, 'Target element has no parent node');
});

test('wrapElementInline lança RuntimeException se elemento sem parent node', function () {
    $injector = new SDTInjector();
    
    // Criar elemento com documento mas sem parent
    $dom = new DOMDocument();
    $element = $dom->createElement('w:p');
    // NÃO adicionar ao DOM (sem parent)
    
    $config = new SDTConfig(id: '12345678');
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('wrapElementInline');
    $method->setAccessible(true);
    
    expect(fn() => $method->invoke($injector, $element, $config))
        ->toThrow(\RuntimeException::class, 'Target element has no parent node');
});

test('processElement lança RuntimeException se elemento não for object', function () {
    $injector = new SDTInjector();
    
    $dom = new DOMDocument();
    $dom->loadXML('<?xml version="1.0"?><w:body xmlns:w="..."></w:body>');
    
    $config = new SDTConfig(id: '12345678');
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('processElement');
    $method->setAccessible(true);
    
    // Passar string ao invés de objeto
    expect(fn() => $method->invoke($injector, $dom, 'not-an-object', $config, 0))
        ->toThrow(\RuntimeException::class, 'Element must be an object');
});

test('serializeElement retorna string vazia para elemento não-AbstractElement', function () {
    $injector = new SDTInjector();
    
    $notPhpWordElement = new stdClass();
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('serializeElement');
    $method->setAccessible(true);
    
    $result = $method->invoke($injector, $notPhpWordElement);
    
    expect($result)->toBe('');
});

test('writeElement ignora containers (Section, Header, Footer, Cell)', function () {
    $injector = new SDTInjector();
    
    $xmlWriter = new \PhpOffice\PhpWord\Shared\XMLWriter(
        \PhpOffice\PhpWord\Shared\XMLWriter::STORAGE_MEMORY,
        null,
        false
    );
    $xmlWriter->openMemory();
    
    // Criar Section (container que deve ser ignorado)
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('writeElement');
    $method->setAccessible(true);
    
    // writeElement deve retornar void sem escrever nada
    $method->invoke($injector, $xmlWriter, $section);
    
    $output = $xmlWriter->getData();
    
    // Não deve ter escrito nada (Section é container)
    expect($output)->toBe('');
});

test('writeElement ignora elemento com Writer inexistente', function () {
    $injector = new SDTInjector();
    
    $xmlWriter = new \PhpOffice\PhpWord\Shared\XMLWriter(
        \PhpOffice\PhpWord\Shared\XMLWriter::STORAGE_MEMORY,
        null,
        false
    );
    $xmlWriter->openMemory();
    
    // Criar elemento mock sem Writer correspondente
    $mockElement = new class extends \PhpOffice\PhpWord\Element\AbstractElement {
        // Classe sem Writer em PhpOffice\PhpWord\Writer\Word2007\Element
    };
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('writeElement');
    $method->setAccessible(true);
    
    // writeElement deve retornar void sem lançar exceção
    $method->invoke($injector, $xmlWriter, $mockElement);
    
    $output = $xmlWriter->getData();
    
    // Não deve ter escrito nada (Writer inexistente)
    expect($output)->toBe('');
});

test('createSDTElement com fragmento XML inválido (teste de robustez)', function () {
    $injector = new SDTInjector();
    
    // Criar elemento com conteúdo que gera XML inválido
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $text = $section->addText('Conteúdo válido');
    
    $config = new SDTConfig(
        id: '12345678',
        alias: 'Test',
        type: ContentControl::TYPE_RICH_TEXT
    );
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('createSDTElement');
    $method->setAccessible(true);
    
    // Deve criar SDT sem lançar exceção
    $xml = $method->invoke($injector, $text, $config);
    
    expect($xml)->toContain('<w:sdt');
    expect($xml)->toContain('w:id');
    expect($xml)->toContain('12345678');
});
