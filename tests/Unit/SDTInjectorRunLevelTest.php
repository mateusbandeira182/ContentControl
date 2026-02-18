<?php

declare(strict_types=1);

use MkGrow\ContentControl\SDTInjector;
use MkGrow\ContentControl\SDTConfig;
use MkGrow\ContentControl\ContentControl;

/**
 * Test suite for SDTInjector run-level SDT wrapping (CT_SdtContentRun)
 *
 * Tests processRunLevelSDT() and wrapRunInline() methods.
 *
 * @since 0.6.0
 */
describe('SDTInjector - Run-Level SDT Wrapping', function (): void {

    it('INJ-RL-01: wrapRunInline() wraps single <w:r> with <w:sdt>', function (): void {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>John</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        /** @var DOMElement $runElement */
        $runElement = $xpath->query('//w:r')->item(0);

        $injector = new SDTInjector();
        $config = new SDTConfig(
            id: '12345678',
            alias: 'Test',
            tag: 'test-tag',
        );

        $reflection = new ReflectionMethod($injector, 'wrapRunInline');
        $reflection->invoke($injector, $runElement, $config);

        $resultXml = $dom->saveXML();

        // Verify SDT structure was created
        expect($resultXml)->toContain('<w:sdt>');
        expect($resultXml)->toContain('<w:sdtPr>');
        expect($resultXml)->toContain('<w:sdtContent>');
        // The run should be inside sdtContent
        expect($resultXml)->toContain('<w:sdtContent><w:r>');
        // SDT should be inside paragraph
        $sdtNodes = $xpath->query('//w:p/w:sdt');
        expect($sdtNodes->length)->toBe(1);
    });

    it('INJ-RL-02: wrapRunInline() preserves <w:rPr> formatting inside run', function (): void {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:rPr><w:b/><w:i/></w:rPr><w:t>Bold Italic</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        /** @var DOMElement $runElement */
        $runElement = $xpath->query('//w:r')->item(0);

        $injector = new SDTInjector();
        $config = new SDTConfig(id: '12345678');

        $reflection = new ReflectionMethod($injector, 'wrapRunInline');
        $reflection->invoke($injector, $runElement, $config);

        $resultXml = $dom->saveXML();

        // Formatting should be preserved inside the run (inside sdtContent)
        expect($resultXml)->toContain('<w:rPr>');
        expect($resultXml)->toContain('<w:b/>');
        expect($resultXml)->toContain('<w:i/>');

        // Verify structure: sdtContent > r > rPr
        $rPrNodes = $xpath->query('//w:sdt/w:sdtContent/w:r/w:rPr');
        expect($rPrNodes->length)->toBe(1);
    });

    it('INJ-RL-03: processRunLevelSDT() throws if target is not <w:r>', function (): void {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Text</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Pass a <w:p> element instead of <w:r>
        /** @var DOMElement $pElement */
        $pElement = $xpath->query('//w:p')->item(0);

        $injector = new SDTInjector();
        $config = new SDTConfig(id: '12345678');

        $reflection = new ReflectionMethod($injector, 'processRunLevelSDT');

        expect(fn () => $reflection->invoke($injector, $pElement, $config))
            ->toThrow(RuntimeException::class, 'can only wrap run elements');
    });

    it('INJ-RL-04: processRunLevelSDT() throws if parent is not <w:p>', function (): void {
        // Construct a DOM where <w:r> is directly under <w:body> (invalid but testable)
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:r><w:t>Orphan run</w:t></w:r>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        /** @var DOMElement $runElement */
        $runElement = $xpath->query('//w:r')->item(0);

        $injector = new SDTInjector();
        $config = new SDTConfig(id: '12345678');

        $reflection = new ReflectionMethod($injector, 'processRunLevelSDT');

        expect(fn () => $reflection->invoke($injector, $runElement, $config))
            ->toThrow(RuntimeException::class, 'requires <w:r> to be inside <w:p>');
    });

    it('INJ-RL-07: processElement() routes runLevel=true to processRunLevelSDT', function (): void {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Run Text</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Run Text');

        $injector = new SDTInjector();
        $config = new SDTConfig(
            id: '12345678',
            alias: 'Run Control',
            tag: 'run-tag',
            runLevel: true,
        );

        $reflection = new ReflectionMethod($injector, 'processElement');
        $reflection->invoke($injector, $dom, $text, $config, 0, 'w:body');

        $resultXml = $dom->saveXML();

        // Verify run-level SDT was created inside paragraph
        expect($resultXml)->toContain('<w:sdt>');
        expect($resultXml)->toContain('<w:sdtContent><w:r>');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $sdtInP = $xpath->query('//w:p/w:sdt');
        expect($sdtInP->length)->toBe(1);
    });

    it('INJ-RL-08: processElement() runLevel takes precedence over inlineLevel', function (): void {
        // When both runLevel=true and inlineLevel=true, findRunInCell is used
        // (inlineLevel scopes to cell, runLevel targets <w:r>)
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl><w:tr><w:tc>
      <w:p><w:r><w:t>Both Flags</w:t></w:r></w:p>
    </w:tc></w:tr></w:tbl>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Both Flags');

        $injector = new SDTInjector();
        $config = new SDTConfig(
            id: '12345678',
            runLevel: true,
            inlineLevel: true,
        );

        $reflection = new ReflectionMethod($injector, 'processElement');
        // runLevel=true should route to processRunLevelSDT, NOT processInlineLevelSDT
        // Even when both flags are set
        $reflection->invoke($injector, $dom, $text, $config, 0, 'w:body');

        $resultXml = $dom->saveXML();

        // Should wrap <w:r>, not <w:p>
        expect($resultXml)->toContain('<w:sdtContent><w:r>');
    });

    it('INJ-RL-09: isElementProcessed() prevents re-wrapping of runs', function (): void {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Once</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        /** @var DOMElement $runElement */
        $runElement = $xpath->query('//w:r')->item(0);

        $injector = new SDTInjector();
        $config = new SDTConfig(id: '12345678');

        // Wrap once
        $wrapMethod = new ReflectionMethod($injector, 'wrapRunInline');
        $wrapMethod->invoke($injector, $runElement, $config);

        // Verify it's marked as processed
        $checkMethod = new ReflectionMethod($injector, 'isElementProcessed');
        expect($checkMethod->invoke($injector, $runElement))->toBeTrue();

        // Only one SDT should exist (not double-wrapped)
        $sdtNodes = $xpath->query('//w:sdt');
        expect($sdtNodes->length)->toBe(1);
    });

});
