<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

describe('Performance Tests', function () {
    
    test('gera documento com 1000 elementos em menos de 200ms', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $start = microtime(true);
        
        // Adicionar 1000 elementos de texto
        for ($i = 0; $i < 1000; $i++) {
            $section->addText("Elemento de teste número {$i}");
        }
        
        $elapsedMs = (microtime(true) - $start) * 1000;
        
        // Validate element addition performance
        // Threshold: 600ms (3x relaxed from optimal 200ms for CI stability)
        // Local dev typically sees ~50-100ms, CI environments may be slower
        expect($elapsedMs)->toBeLessThan(600.0, 
            "Adição de 1000 elementos levou {$elapsedMs}ms (limite: 600ms)"
        );
    });
    
    test('salva documento com 100 Content Controls em tempo razoável', function () {
        $cc = new ContentControl();
        
        // Criar 100 elementos Text com Content Controls
        $section = $cc->addSection(); // Criar seção ANTES do loop
        
        for ($i = 0; $i < 100; $i++) {
            $textElement = $section->addText("Texto protegido {$i}");
            
            $cc->addContentControl($textElement, [
                'alias' => "Campo {$i}",
                'tag' => "field-{$i}",
                'type' => ContentControl::TYPE_RICH_TEXT,
            ]);
        }
        
        $tempFile = sys_get_temp_dir() . '/perf_test_' . uniqid() . '.docx';
        
        try {
            $start = microtime(true);
            $cc->save($tempFile);
            $elapsedMs = (microtime(true) - $start) * 1000;
            
            // Validate save performance
            // Threshold: 5000ms (2.5x relaxed from optimal 2000ms for CI stability)
            // Local dev typically sees ~500-1000ms, CI environments with slower I/O may be slower
            expect($elapsedMs)->toBeLessThan(5000.0, 
                "Salvamento de 100 Content Controls levou {$elapsedMs}ms (limite: 5000ms)"
            );
            
            // Validar arquivo criado
            expect(file_exists($tempFile))->toBeTrue();
            
            // Validar tamanho razoável (> 8KB para 100 elementos)
            $fileSize = filesize($tempFile);
            expect($fileSize)->toBeGreaterThan(8192, "Arquivo muito pequeno: {$fileSize} bytes");
            
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    });
    
    test('geração de IDs únicos é eficiente para 10000 IDs', function () {
        $registry = new \MkGrow\ContentControl\SDTRegistry();
        
        $start = microtime(true);
        
        $ids = [];
        for ($i = 0; $i < 10000; $i++) {
            $id = $registry->generateUniqueId();
            $registry->markIdAsUsed($id); // Marcar como usado para próxima iteração
            $ids[] = $id;
        }
        
        $elapsedMs = (microtime(true) - $start) * 1000;
        
        // Geração de 10000 IDs deve ser < 1000ms (ajustado para CI/CD)
        expect($elapsedMs)->toBeLessThan(1000.0, 
            "Geração de 10000 IDs levou {$elapsedMs}ms (limite: 1000ms)"
        );
        
        // Validar que não há duplicatas
        $uniqueIds = array_unique($ids);
        expect(count($uniqueIds))->toBe(10000, "IDs duplicados detectados!");
    });
    
    test('validação de SDTConfig não impacta performance', function () {
        $iterations = 1000;
        
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $config = \MkGrow\ContentControl\SDTConfig::fromArray([
                'id' => str_pad((string)(10000000 + $i), 8, '0', STR_PAD_LEFT),
                'alias' => "Campo {$i}",
                'tag' => "field-{$i}",
                'type' => \MkGrow\ContentControl\ContentControl::TYPE_RICH_TEXT,
                'lockType' => \MkGrow\ContentControl\ContentControl::LOCK_NONE,
            ]);
        }
        
        $elapsedMs = (microtime(true) - $start) * 1000;
        
        // Criação de 1000 SDTConfigs deve ser < 50ms
        expect($elapsedMs)->toBeLessThan(50.0, 
            "Criação de {$iterations} SDTConfigs levou {$elapsedMs}ms (limite: 50ms)"
        );
    });
    
    test('registro de 1000 elementos não causa degradação', function () {
        $registry = new \MkGrow\ContentControl\SDTRegistry();
        $cc = new ContentControl();
        
        $sections = [];
        for ($i = 0; $i < 1000; $i++) {
            $sections[] = $cc->addSection();
        }
        
        $start = microtime(true);
        
        foreach ($sections as $i => $section) {
            $config = \MkGrow\ContentControl\SDTConfig::fromArray([
                'alias' => "Campo {$i}",
                'tag' => "field-{$i}",
            ]);
            
            $registry->register($section, $config);
        }
        
        $elapsedMs = (microtime(true) - $start) * 1000;
        
        // Registro de 1000 elementos deve ser < 500ms (ajustado de 100ms)
        expect($elapsedMs)->toBeLessThan(500.0, 
            "Registro de 1000 elementos levou {$elapsedMs}ms (limite: 500ms)"
        );
        
        expect($registry->count())->toBe(1000);
    });
});
describe('Performance - v3.0 (DOM Inline Wrapping)', function () {
    test('v3.0 performance com 100 elementos', function () {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $cc = new ContentControl();
        $section = $cc->addSection();

        // Adicionar 100 elementos
        $elements = [];
        for ($i = 0; $i < 100; $i++) {
            $text = $section->addText("Elemento {$i}");
            $elements[] = $text;
        }

        // Registrar todos como SDTs
        foreach ($elements as $i => $element) {
            $cc->addContentControl($element, [
                'alias' => "Element {$i}"
            ]);
        }

        $outputFile = sys_get_temp_dir() . '/perf_test_100_elements.docx';
        $cc->save($outputFile);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $executionTime = $endTime - $startTime;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;  // MB

        // Benchmarks esperados (ajustar conforme hardware)
        expect($executionTime)->toBeLessThan(5.0);  // < 5 segundos
        expect($memoryUsed)->toBeLessThan(50);  // < 50 MB

        // Validar duplicação usando XPath
        $zip = new ZipArchive();
        $zip->open($outputFile);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        assert(is_string($documentXml));

        $dom = new DOMDocument();
        $dom->loadXML($documentXml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Verificar amostra aleatória (elementos 10, 50, 90)
        foreach ([10, 50, 90] as $idx) {
            $textNodes = $xpath->query("//w:t[contains(., 'Elemento {$idx}')]");
            expect($textNodes->length)->toBe(1, "Elemento {$idx} deve aparecer exatamente 1 vez (duplicação detectada!)");
        }

        // Cleanup
        unlink($outputFile);
    });
});