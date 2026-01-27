<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\PhpWord;

describe('XML Generation - Estrutura OOXML', function () {
    
    test('gera namespace WordprocessingML no elemento raiz', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"');
    });

    test('estrutura possui elementos na ordem correta', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section);
        
        $xml = $control->getXml();
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        // Verificar estrutura: sdt > sdtPr + sdtContent
        $sdt = $xpath->query('//w:sdt');
        expect($sdt->length)->toBe(1);
        
        $sdtPr = $xpath->query('//w:sdt/w:sdtPr');
        expect($sdtPr->length)->toBe(1);
        
        $sdtContent = $xpath->query('//w:sdt/w:sdtContent');
        expect($sdtContent->length)->toBe(1);
    });

    test('ID presente como elemento separado', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['id' => '87654321']);
        
        $xml = $control->getXml();
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        // Verificar <w:id w:val="87654321"/>
        $id = $xpath->query('//w:sdtPr/w:id[@w:val="87654321"]');
        expect($id->length)->toBe(1);
    });

    test('alias presente quando fornecido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['alias' => 'Test Alias']);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('<w:alias w:val="Test Alias"/>');
    });

    test('alias ausente quando não fornecido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section);
        
        $xml = $control->getXml();
        
        expect($xml)->not->toContain('<w:alias');
    });

    test('tag presente quando fornecido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['tag' => 'metadata-tag']);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('<w:tag w:val="metadata-tag"/>');
    });

    test('tag ausente quando não fornecido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section);
        
        $xml = $control->getXml();
        
        expect($xml)->not->toContain('<w:tag');
    });
});

describe('XML Generation - Tipos de Content Control', function () {
    
    test('tipo richText gera elemento <w:richText/>', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['type' => ContentControl::TYPE_RICH_TEXT]);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('<w:richText/>');
    });

    test('tipo plainText gera elemento <w:text/>', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['type' => ContentControl::TYPE_PLAIN_TEXT]);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('<w:text/>');
    });

    test('tipo group gera elemento <w:group/>', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['type' => ContentControl::TYPE_GROUP]);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('<w:group/>');
    });

    test('tipo picture gera elemento <w:picture/>', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['type' => ContentControl::TYPE_PICTURE]);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('<w:picture/>');
    });
});

describe('XML Generation - Locks', function () {
    
    test('lock ausente quando LOCK_NONE', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['lockType' => ContentControl::LOCK_NONE]);
        
        $xml = $control->getXml();
        
        expect($xml)->not->toContain('<w:lock');
    });

    test('lock presente quando SDT_LOCKED', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['lockType' => ContentControl::LOCK_SDT_LOCKED]);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('<w:lock w:val="sdtLocked"/>');
    });

    test('lock presente quando CONTENT_LOCKED', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['lockType' => ContentControl::LOCK_CONTENT_LOCKED]);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('<w:lock w:val="sdtContentLocked"/>');
    });

    test('lock presente quando UNLOCKED', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section, ['lockType' => ContentControl::LOCK_UNLOCKED]);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('<w:lock w:val="unlocked"/>');
    });
});

test('XML é válido e bem formado', function () {
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    $section->addText('Conteúdo de teste');
    
    $control = new ContentControl($section, [
        'alias' => 'Test Control',
        'tag' => 'test-tag',
        'type' => ContentControl::TYPE_RICH_TEXT,
        'lockType' => ContentControl::LOCK_SDT_LOCKED,
    ]);
    
    $xml = $control->getXml();
    
    // Tentar fazer parse - deve ter sucesso
    $dom = new DOMDocument();
    $loaded = @$dom->loadXML($xml);
    
    expect($loaded)->toBeTrue();
    expect($dom->documentElement->nodeName)->toBe('w:sdt');
});

describe('XML Generation - Error Handling', function () {
    
    test('verifica que DOMDocument::appendXML falha com XML malformado', function () {
        // Criar um XMLWriter que gera XML malformado
        $xmlWriter = new \PhpOffice\PhpWord\Shared\XMLWriter();
        $xmlWriter->startDocument('1.0', 'UTF-8');
        $xmlWriter->writeRaw('<w:p><w:t>unclosed tag'); // XML malformado
        
        // Obter o XML malformado
        $malformedXml = $xmlWriter->getData();
        
        // Usar DOMDocument para tentar fazer parse - deve falhar
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $fragment = $doc->createDocumentFragment();
        
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $success = $fragment->appendXML($malformedXml);
        
        // Verificar que realmente falhou
        expect($success)->toBeFalse();
        
        // Restaurar configuração
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);
    });
});
