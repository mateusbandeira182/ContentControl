<?php

declare(strict_types=1);

use MkGrow\ContentControl\ElementLocator;
use MkGrow\ContentControl\ElementIdentifier;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\Element\Text;

beforeEach(function () {
    ElementIdentifier::clearCache();
});

test('locates Title element by depth (Title - depth 0)', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="Title"/>
            </w:pPr>
            <w:r>
                <w:t>Document Title</w:t>
            </w:r>
        </w:p>
        <w:p>
            <w:pPr/>
            <w:r>
                <w:t>Regular text</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    
    // Create mock Title element with depth 0
    $title = new Title('Document Title', 0);
    
    $found = $locator->findElementInDOM($dom, $title, 0);
    
    expect($found)->not->toBeNull();
    expect($found->nodeName)->toBe('w:p');
    
    // Verify it found the correct paragraph (with Title style)
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $pStyle = $xpath->query('.//w:pPr/w:pStyle', $found);
    expect($pStyle->length)->toBe(1);
    expect($pStyle->item(0)->getAttribute('w:val'))->toBe('Title');
});

test('locates Title element by depth (Heading1 - depth 1)', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="Heading1"/>
            </w:pPr>
            <w:bookmarkStart w:id="0" w:name="_Toc1"/>
            <w:r>
                <w:t>Chapter 1</w:t>
            </w:r>
            <w:bookmarkEnd w:id="0"/>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    $title = new Title('Chapter 1', 1);
    
    $found = $locator->findElementInDOM($dom, $title, 0);
    
    expect($found)->not->toBeNull();
    
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $pStyle = $xpath->query('.//w:pPr/w:pStyle', $found);
    expect($pStyle->item(0)->getAttribute('w:val'))->toBe('Heading1');
});

test('locates Title element by depth (Heading2 - depth 2)', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="Heading2"/>
            </w:pPr>
            <w:r>
                <w:t>Section 1.1</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    $title = new Title('Section 1.1', 2);
    
    $found = $locator->findElementInDOM($dom, $title, 0);
    
    expect($found)->not->toBeNull();
    
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $pStyle = $xpath->query('.//w:pPr/w:pStyle', $found);
    expect($pStyle->item(0)->getAttribute('w:val'))->toBe('Heading2');
});

test('hash differentiates Title from Text with same content', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="Heading1"/>
            </w:pPr>
            <w:r>
                <w:t>Same Text</w:t>
            </w:r>
        </w:p>
        <w:p>
            <w:pPr/>
            <w:r>
                <w:t>Same Text</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    // Title element hash
    $title = new Title('Same Text', 1);
    $titleHash = ElementIdentifier::generateContentHash($title);
    
    // Text element hash
    $text = new Text('Same Text');
    $textHash = ElementIdentifier::generateContentHash($text);
    
    // Hashes must be different
    expect($titleHash)->not->toBe($textHash);
});

test('skips Title elements already wrapped in SDT', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:sdt>
            <w:sdtContent>
                <w:p>
                    <w:pPr>
                        <w:pStyle w:val="Heading1"/>
                    </w:pPr>
                    <w:r>
                        <w:t>Already Wrapped</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="Heading1"/>
            </w:pPr>
            <w:r>
                <w:t>Not Wrapped</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    $title = new Title('Not Wrapped', 1);
    
    $found = $locator->findElementInDOM($dom, $title, 0);
    
    expect($found)->not->toBeNull();
    
    // Verify that it found the second (not wrapped)
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $text = $xpath->query('.//w:t', $found);
    expect($text->item(0)->textContent)->toBe('Not Wrapped');
});

test('preserves bookmarks when Title is wrapped', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="Heading1"/>
            </w:pPr>
            <w:bookmarkStart w:id="0" w:name="_Toc1"/>
            <w:r>
                <w:t>Chapter 1</w:t>
            </w:r>
            <w:bookmarkEnd w:id="0"/>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    $title = new Title('Chapter 1', 1);
    
    $found = $locator->findElementInDOM($dom, $title, 0);
    
    expect($found)->not->toBeNull();
    
    // Verify that bookmarks are preserved
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    $bookmarkStart = $xpath->query('.//w:bookmarkStart', $found);
    expect($bookmarkStart->length)->toBe(1);
    expect($bookmarkStart->item(0)->getAttribute('w:name'))->toBe('_Toc1');
    
    $bookmarkEnd = $xpath->query('.//w:bookmarkEnd', $found);
    expect($bookmarkEnd->length)->toBe(1);
});

test('supports all Title depths (0-9)', function () {
    $depths = range(0, 9);
    
    foreach ($depths as $depth) {
        $styleName = $depth === 0 ? 'Title' : 'Heading' . $depth;
        
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="{$styleName}"/>
            </w:pPr>
            <w:r>
                <w:t>Title Depth {$depth}</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $title = new Title("Title Depth {$depth}", $depth);
        
        $found = $locator->findElementInDOM($dom, $title, 0);
        
        expect($found)->not->toBeNull("Failed to find Title with depth {$depth}");
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $pStyle = $xpath->query('.//w:pPr/w:pStyle', $found);
        expect($pStyle->item(0)->getAttribute('w:val'))->toBe($styleName);
    }
});
