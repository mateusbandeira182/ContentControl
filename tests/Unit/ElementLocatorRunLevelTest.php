<?php

declare(strict_types=1);

use MkGrow\ContentControl\ElementLocator;

describe('ElementLocator Run-Level Strategy', function (): void {

    it('LOC-RL-01: findRunByTextContent() locates <w:r> by text content', function (): void {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p><w:r><w:t>John</w:t></w:r></w:p>
        </w:body>
        </w:document>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('John');

        $result = $locator->findElementInDOM($dom, $text, 0, 'w:body', false, true);

        expect($result)->not->toBeNull();
        expect($result->localName)->toBe('r');
    });

    it('LOC-RL-02: findRunByTextContent() uses normalize-space (whitespace tolerance)', function (): void {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p><w:r><w:t> John </w:t></w:r></w:p>
        </w:body>
        </w:document>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('John');

        $result = $locator->findElementInDOM($dom, $text, 0, 'w:body', false, true);

        expect($result)->not->toBeNull();
        expect($result->localName)->toBe('r');
    });

    it('LOC-RL-03: findRunByTextContent() excludes runs inside <w:sdtContent>', function (): void {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p>
                <w:sdt><w:sdtPr/><w:sdtContent><w:r><w:t>John</w:t></w:r></w:sdtContent></w:sdt>
                <w:r><w:t>Jane</w:t></w:r>
            </w:p>
        </w:body>
        </w:document>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('John');

        // Should NOT find 'John' (inside sdtContent), should fallback to 'Jane'
        $result = $locator->findElementInDOM($dom, $text, 0, 'w:body', false, true);

        expect($result)->not->toBeNull();
        expect($result->localName)->toBe('r');
        // Verify it found 'Jane' (the unprocessed run), not 'John' (inside SDT)
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $tNode = $xpath->query('.//w:t', $result);
        expect($tNode->item(0)?->textContent)->toBe('Jane');
    });

    it('LOC-RL-04: findRunByTextContent() fallback returns first unprocessed run', function (): void {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p><w:r><w:t>Fallback</w:t></w:r></w:p>
        </w:body>
        </w:document>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        // Empty text -> won't match by content -> triggers fallback
        $text = $section->addText('');

        $result = $locator->findElementInDOM($dom, $text, 0, 'w:body', false, true);

        expect($result)->not->toBeNull();
        expect($result->localName)->toBe('r');
    });

    it('LOC-RL-05: findRunInCell() locates <w:r> inside <w:tc>', function (): void {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p><w:r><w:t>Body text</w:t></w:r></w:p>
            <w:tbl><w:tr><w:tc>
                <w:p><w:r><w:t>Cell text</w:t></w:r></w:p>
            </w:tc></w:tr></w:tbl>
        </w:body>
        </w:document>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Cell text');

        // inlineLevel=true + runLevel=true -> findRunInCell
        $result = $locator->findElementInDOM($dom, $text, 0, 'w:body', true, true);

        expect($result)->not->toBeNull();
        expect($result->localName)->toBe('r');
        // Verify it is inside a cell, not body-level
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $tNode = $xpath->query('.//w:t', $result);
        expect($tNode->item(0)?->textContent)->toBe('Cell text');
    });

    it('LOC-RL-06: findRunInCell() excludes cell runs inside <w:sdtContent>', function (): void {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:tbl><w:tr><w:tc>
                <w:p>
                    <w:sdt><w:sdtPr/><w:sdtContent><w:r><w:t>Wrapped</w:t></w:r></w:sdtContent></w:sdt>
                    <w:r><w:t>Free</w:t></w:r>
                </w:p>
            </w:tc></w:tr></w:tbl>
        </w:body>
        </w:document>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Wrapped');

        // Should skip 'Wrapped' (inside sdtContent) and fallback to 'Free'
        $result = $locator->findElementInDOM($dom, $text, 0, 'w:body', true, true);

        expect($result)->not->toBeNull();
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $tNode = $xpath->query('.//w:t', $result);
        expect($tNode->item(0)?->textContent)->toBe('Free');
    });

    it('LOC-RL-07: findElementInDOM() dispatches to findRunByTextContent when runLevel=true', function (): void {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p><w:r><w:t>Hello</w:t></w:r></w:p>
        </w:body>
        </w:document>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Hello');

        // With runLevel=true, should return <w:r> (not <w:p>)
        $result = $locator->findElementInDOM($dom, $text, 0, 'w:body', false, true);

        expect($result)->not->toBeNull();
        expect($result->localName)->toBe('r');
    });

    it('LOC-RL-08: findElementInDOM() dispatches to findRunInCell when runLevel+inlineLevel=true', function (): void {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p><w:r><w:t>Body Run</w:t></w:r></w:p>
            <w:tbl><w:tr><w:tc>
                <w:p><w:r><w:t>Cell Run</w:t></w:r></w:p>
            </w:tc></w:tr></w:tbl>
        </w:body>
        </w:document>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Cell Run');

        // Both flags true -> findRunInCell
        $result = $locator->findElementInDOM($dom, $text, 0, 'w:body', true, true);

        expect($result)->not->toBeNull();
        expect($result->localName)->toBe('r');
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $tNode = $xpath->query('.//w:t', $result);
        expect($tNode->item(0)?->textContent)->toBe('Cell Run');
    });

    it('LOC-RL-09: escapeXPathString() handles single quotes, double quotes, and both', function (): void {
        $locator = new ElementLocator();
        $reflection = new ReflectionMethod($locator, 'escapeXPathString');

        // Simple string -> wrapped in single quotes
        expect($reflection->invoke($locator, 'John'))->toBe("'John'");

        // Contains single quote -> wrapped in double quotes
        expect($reflection->invoke($locator, "O'Brien"))->toBe('"O\'Brien"');

        // Contains double quote -> wrapped in single quotes
        expect($reflection->invoke($locator, 'Say "Hello"'))->toBe("'Say \"Hello\"'");

        // Contains both -> uses concat()
        $result = $reflection->invoke($locator, "He said \"it's fine\"");
        expect($result)->toContain('concat(');
    });

    it('LOC-RL-10: Backward compat - existing 5-param calls unaffected (default runLevel=false)', function (): void {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Test</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Test');

        // 5-param call (no runLevel) -> returns <w:p> (paragraph level), not <w:r>
        $result = $locator->findElementInDOM($dom, $text, 0, 'w:body', false);

        expect($result)->not->toBeNull();
        expect($result->localName)->toBe('p');
    });

});
