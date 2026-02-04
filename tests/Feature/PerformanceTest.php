<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

describe('Performance Tests', function () {
    
    test('generates document with 1000 elements in less than 600ms', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $start = microtime(true);
        
        // Add 1000 text elements
        for ($i = 0; $i < 1000; $i++) {
            $section->addText("Test element number {$i}");
        }
        
        $elapsedMs = (microtime(true) - $start) * 1000;
        
        // Validate element addition performance
        // Threshold: 600ms (3x relaxed from optimal 200ms for CI stability)
        // Local dev typically sees ~50-100ms, CI environments may be slower
        expect($elapsedMs)->toBeLessThan(600.0, 
            "Adding 1000 elements took {$elapsedMs}ms (limit: 600ms)"
        );
    });
    
    test('saves document with 100 Content Controls in reasonable time', function () {
        $cc = new ContentControl();
        
        // Create 100 Text elements with Content Controls
        $section = $cc->addSection(); // Create section BEFORE loop
        
        for ($i = 0; $i < 100; $i++) {
            $textElement = $section->addText("Protected text {$i}");
            
            $cc->addContentControl($textElement, [
                'alias' => "Field {$i}",
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
                "Saving 100 Content Controls took {$elapsedMs}ms (limit: 5000ms)"
            );
            
            // Validate created file
            expect(file_exists($tempFile))->toBeTrue();
            
            // Validate reasonable size (> 8KB for 100 elements)
            $fileSize = filesize($tempFile);
            expect($fileSize)->toBeGreaterThan(8192, "File too small: {$fileSize} bytes");
            
        } finally {
            if (file_exists($tempFile)) {
                safeUnlink($tempFile);
            }
        }
    });
    
    test('unique ID generation is efficient for 10000 IDs', function () {
        $registry = new \MkGrow\ContentControl\SDTRegistry();
        
        $start = microtime(true);
        
        $ids = [];
        for ($i = 0; $i < 10000; $i++) {
            $id = $registry->generateUniqueId();
            $registry->markIdAsUsed($id); // Mark as used for next iteration
            $ids[] = $id;
        }
        
        $elapsedMs = (microtime(true) - $start) * 1000;
        
        // Generation of 10000 IDs should be < 1000ms (adjusted for CI/CD)
        expect($elapsedMs)->toBeLessThan(1000.0, 
            "Generation of 10000 IDs took {$elapsedMs}ms (limit: 1000ms)"
        );
        
        // Validate no duplicates
        $uniqueIds = array_unique($ids);
        expect(count($uniqueIds))->toBe(10000, "Duplicate IDs detected!");
    });
    
    test('SDTConfig validation does not impact performance', function () {
        $iterations = 1000;
        
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $config = \MkGrow\ContentControl\SDTConfig::fromArray([
                'id' => str_pad((string)(10000000 + $i), 8, '0', STR_PAD_LEFT),
                'alias' => "Field {$i}",
                'tag' => "field-{$i}",
                'type' => \MkGrow\ContentControl\ContentControl::TYPE_RICH_TEXT,
                'lockType' => \MkGrow\ContentControl\ContentControl::LOCK_NONE,
            ]);
        }
        
        $elapsedMs = (microtime(true) - $start) * 1000;
        
        // Creation of 1000 SDTConfigs should be < 50ms
        expect($elapsedMs)->toBeLessThan(50.0, 
            "Creation of {$iterations} SDTConfigs took {$elapsedMs}ms (limit: 50ms)"
        );
    });
    
    test('registering 1000 elements does not cause degradation', function () {
        $registry = new \MkGrow\ContentControl\SDTRegistry();
        $cc = new ContentControl();
        
        $sections = [];
        for ($i = 0; $i < 1000; $i++) {
            $sections[] = $cc->addSection();
        }
        
        $start = microtime(true);
        
        foreach ($sections as $i => $section) {
            $config = \MkGrow\ContentControl\SDTConfig::fromArray([
                'alias' => "Field {$i}",
                'tag' => "field-{$i}",
            ]);
            
            $registry->register($section, $config);
        }
        
        $elapsedMs = (microtime(true) - $start) * 1000;
        
        // Registration of 1000 elements should be < 500ms (adjusted from 100ms)
        expect($elapsedMs)->toBeLessThan(500.0, 
            "Registration of 1000 elements took {$elapsedMs}ms (limit: 500ms)"
        );
        
        expect($registry->count())->toBe(1000);
    });
});
describe('Performance (DOM Inline Wrapping)', function () {
    test('performance with 100 elements', function () {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $cc = new ContentControl();
        $section = $cc->addSection();

        // Add 100 elements
        $elements = [];
        for ($i = 0; $i < 100; $i++) {
            $text = $section->addText("Element {$i}");
            $elements[] = $text;
        }

        // Register all as SDTs
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

        // Expected benchmarks (adjust according to hardware)
        expect($executionTime)->toBeLessThan(5.0);  // < 5 seconds
        expect($memoryUsed)->toBeLessThan(50);  // < 50 MB

        // Validate duplication using XPath
        $zip = new ZipArchive();
        $zip->open($outputFile);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        assert(is_string($documentXml));

        $dom = new DOMDocument();
        $dom->loadXML($documentXml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Verify random sample (elements 10, 50, 90)
        foreach ([10, 50, 90] as $idx) {
            $textNodes = $xpath->query("//w:t[contains(., 'Element {$idx}')]");
            expect($textNodes->length)->toBe(1, "Element {$idx} must appear exactly once (duplication detected!)");
        }

        // Cleanup
        safeUnlink($outputFile);
    });
});