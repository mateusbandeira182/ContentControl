<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\SDTInjector;

/**
 * @phpstan-ignore offsetAccess.nonOffsetAccessible
 */
describe('SDTInjector - Header/Footer Discovery', function () {
    
    test('discoverHeaderFooterFiles lists header and footer files correctly', function () {
        // Create DOCX with 1 header, 1 footer
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Body content');
        
        // Add header and footer
        $header = $section->addHeader('default');
        $header->addText('Header text');
        
        $footer = $section->addFooter('default');
        $footer->addText('Footer text');
        
        // Save to temporary file
        $tempFile = sys_get_temp_dir() . '/test_discover_' . uniqid() . '.docx';
        $cc->save($tempFile);
        
        // Open ZIP and discover files
        $zip = new ZipArchive();
        $zip->open($tempFile);
        
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('discoverHeaderFooterFiles');
        $method->setAccessible(true);
        
        $files = $method->invoke($injector, $zip);
        
        $zip->close();
        unlink($tempFile);
        
        // Should find header1.xml and footer1.xml
        expect($files)->toBeArray();
        expect($files)->not->toBeEmpty();
        
        // Check that files match pattern
        foreach ($files as $file) {
            expect(is_string($file))->toBeTrue();
            expect($file)->toMatch('#^word/(header|footer)\d+\.xml$#');
        }
    });
    
    test('getXmlFileForElement detects element in Header', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $header = $section->addHeader('default');
        $text = $header->addText('Header content');
        
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('getXmlFileForElement');
        $method->setAccessible(true);
        
        $xmlPath = $method->invoke($injector, $text);
        
        expect($xmlPath)->toBe('word/header1.xml');
    });
    
    test('getXmlFileForElement detects element in Footer', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $footer = $section->addFooter('default');
        $text = $footer->addText('Footer content');
        
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('getXmlFileForElement');
        $method->setAccessible(true);
        
        $xmlPath = $method->invoke($injector, $text);
        
        expect($xmlPath)->toBe('word/footer1.xml');
    });
    
    test('getXmlFileForElement detects element in Section (body)', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Body content');
        
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('getXmlFileForElement');
        $method->setAccessible(true);
        
        $xmlPath = $method->invoke($injector, $text);
        
        expect($xmlPath)->toBe('word/document.xml');
    });
    
    test('filterElementsByXmlFile filters elements from header', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $header = $section->addHeader('default');
        $headerText = $header->addText('Header');
        
        $bodyText = $section->addText('Body');
        
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('filterElementsByXmlFile');
        $method->setAccessible(true);
        
        $sdtTuples = [
            ['element' => $headerText, 'config' => new \MkGrow\ContentControl\SDTConfig(id: '', alias: 'Header Control')],
            ['element' => $bodyText, 'config' => new \MkGrow\ContentControl\SDTConfig(id: '', alias: 'Body Control')],
        ];
        
        $headerTuples = $method->invoke($injector, $sdtTuples, 'word/header1.xml');
        
        expect($headerTuples)->toBeArray();
        expect($headerTuples)->toHaveCount(1);
        expect($headerTuples[0])->toBeArray();
        expect($headerTuples[0]['config'] ?? null)->toBeInstanceOf(\MkGrow\ContentControl\SDTConfig::class);
        /** @var \MkGrow\ContentControl\SDTConfig $config */
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        $config = $headerTuples[0]['config'];
        expect($config->alias)->toBe('Header Control');
    });
    
    test('filterElementsByXmlFile filters elements from body', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $header = $section->addHeader('default');
        $headerText = $header->addText('Header');
        
        $bodyText = $section->addText('Body');
        
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('filterElementsByXmlFile');
        $method->setAccessible(true);
        
        $sdtTuples = [
            ['element' => $headerText, 'config' => new \MkGrow\ContentControl\SDTConfig(id: '', alias: 'Header Control')],
            ['element' => $bodyText, 'config' => new \MkGrow\ContentControl\SDTConfig(id: '', alias: 'Body Control')],
        ];
        
        $bodyTuples = $method->invoke($injector, $sdtTuples, 'word/document.xml');
        
        expect($bodyTuples)->toBeArray();
        expect($bodyTuples)->toHaveCount(1);
        expect($bodyTuples[0])->toBeArray();
        expect($bodyTuples[0]['config'] ?? null)->toBeInstanceOf(\MkGrow\ContentControl\SDTConfig::class);
        /** @var \MkGrow\ContentControl\SDTConfig $config */
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        $config = $bodyTuples[0]['config'];
        expect($config->alias)->toBe('Body Control');
    });
    
    test('filterElementsByXmlFile returns empty array when no matching elements', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $bodyText = $section->addText('Body only');
        
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('filterElementsByXmlFile');
        $method->setAccessible(true);
        
        $sdtTuples = [
            ['element' => $bodyText, 'config' => new \MkGrow\ContentControl\SDTConfig(id: '', alias: 'Body Control')],
        ];
        
        $headerTuples = $method->invoke($injector, $sdtTuples, 'word/header1.xml');
        
        expect($headerTuples)->toBeArray();
        expect($headerTuples)->toHaveCount(0);
    });
});
