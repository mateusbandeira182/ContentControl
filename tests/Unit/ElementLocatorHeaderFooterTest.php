<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ElementLocator;

/**
 * Tests for ElementLocator with headers and footers support
 * 
 * Validates that ElementLocator can locate elements in w:hdr and w:ftr
 * root elements, in addition to the existing w:body support.
 */

test('detectRootElement identifies w:body correctly', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p><w:r><w:t>Test</w:t></w:r></w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    $rootElement = $locator->detectRootElement($dom);

    expect($rootElement)->toBe('w:body');
});

test('detectRootElement identifies w:hdr correctly', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:p><w:r><w:t>Header Content</w:t></w:r></w:p>
</w:hdr>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    $rootElement = $locator->detectRootElement($dom);

    expect($rootElement)->toBe('w:hdr');
});

test('detectRootElement identifies w:ftr correctly', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:p><w:r><w:t>Footer Content</w:t></w:r></w:p>
</w:ftr>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    $rootElement = $locator->detectRootElement($dom);

    expect($rootElement)->toBe('w:ftr');
});

test('findElementInDOM locates Text in w:hdr with explicit rootElement parameter', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:p><w:r><w:t>Header Text</w:t></w:r></w:p>
</w:hdr>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    // Create a Text element for comparison via Section header
    $cc = new ContentControl();
    $section = $cc->addSection();
    $header = $section->addHeader();
    $textElement = $header->addText('Header Text');

    $locator = new ElementLocator();
    $found = $locator->findElementInDOM($dom, $textElement, 0, 'w:hdr');

    expect($found)->not->toBeNull()
        ->and($found->nodeName)->toBe('w:p');
});

test('findElementInDOM locates Text in w:ftr with explicit rootElement parameter', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:p><w:r><w:t>Footer Text</w:t></w:r></w:p>
</w:ftr>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    // Create a Text element for comparison via Section footer
    $cc = new ContentControl();
    $section = $cc->addSection();
    $footer = $section->addFooter();
    $textElement = $footer->addText('Footer Text');

    $locator = new ElementLocator();
    $found = $locator->findElementInDOM($dom, $textElement, 0, 'w:ftr');

    expect($found)->not->toBeNull()
        ->and($found->nodeName)->toBe('w:p');
});

test('findElementInDOM returns null for Title in w:hdr', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:p>
        <w:pPr>
            <w:pStyle w:val="Title"/>
        </w:pPr>
        <w:r><w:t>Title in Header</w:t></w:r>
    </w:p>
</w:hdr>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    // Create a Title element (Title can only be in Section body)
    $cc = new ContentControl();
    $section = $cc->addSection();
    $titleElement = $section->addTitle('Title in Header', 0);

    $locator = new ElementLocator();
    $found = $locator->findElementInDOM($dom, $titleElement, 0, 'w:hdr');

    // Title elements are not supported in headers/footers - should return null
    expect($found)->toBeNull();
});

test('findElementInDOM locates Table in w:hdr', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:tbl>
        <w:tr>
            <w:tc><w:p><w:r><w:t>Cell 1</w:t></w:r></w:p></w:tc>
        </w:tr>
    </w:tbl>
</w:hdr>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    // Create a Table element via Section header
    $cc = new ContentControl();
    $section = $cc->addSection();
    $header = $section->addHeader();
    $table = $header->addTable();
    $table->addRow();
    $table->addCell()->addText('Cell 1');

    $locator = new ElementLocator();
    $found = $locator->findElementInDOM($dom, $table, 0, 'w:hdr');

    expect($found)->not->toBeNull()
        ->and($found->nodeName)->toBe('w:tbl');
});

test('findElementInDOM locates Image in w:ftr', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
       xmlns:v="urn:schemas-microsoft-com:vml"
       xmlns:o="urn:schemas-microsoft-com:office:office"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:p>
        <w:r>
            <w:pict>
                <v:shape style="width:100pt; height:100pt;">
                    <v:imagedata r:id="rId1"/>
                </v:shape>
            </w:pict>
        </w:r>
    </w:p>
</w:ftr>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    // Create an Image element via Section footer
    $cc = new ContentControl();
    $section = $cc->addSection();
    $footer = $section->addFooter();
    
    // Use a temporary image file
    $tempImage = tempnam(sys_get_temp_dir(), 'img_') . '.png';
    $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
    if ($imageData === false) {
        throw new \RuntimeException('Failed to decode base64 image data');
    }
    file_put_contents($tempImage, $imageData);
    
    $imageElement = $footer->addImage($tempImage);
    $style = $imageElement->getStyle();
    if ($style !== null) {
        $style->setWidth(100);
        $style->setHeight(100);
    }

    $locator = new ElementLocator();
    $found = $locator->findElementInDOM($dom, $imageElement, 0, 'w:ftr');

    // Cleanup
    unlink($tempImage);

    expect($found)->not->toBeNull()
        ->and($found->nodeName)->toBe('w:p');
});

test('findElementInDOM with w:body remains backward compatible', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p><w:r><w:t>Body Text</w:t></w:r></w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    // Create a Text element
    $cc = new ContentControl();
    $section = $cc->addSection();
    $textElement = $section->addText('Body Text');

    $locator = new ElementLocator();
    
    // Test with explicit w:body
    $foundExplicit = $locator->findElementInDOM($dom, $textElement, 0, 'w:body');
    expect($foundExplicit)->not->toBeNull();

    // Test with default parameter (should default to w:body)
    $foundDefault = $locator->findElementInDOM($dom, $textElement, 0);
    expect($foundDefault)->not->toBeNull()
        ->and($foundDefault->nodeName)->toBe('w:p');
});

test('findElementInDOM does not find element in wrong root', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:p><w:r><w:t>Header Text</w:t></w:r></w:p>
</w:hdr>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    // Create a Text element in header
    $cc = new ContentControl();
    $section = $cc->addSection();
    $header = $section->addHeader();
    $textElement = $header->addText('Header Text');

    $locator = new ElementLocator();
    
    // Try to find in w:body (should fail - element is in w:hdr)
    $found = $locator->findElementInDOM($dom, $textElement, 0, 'w:body');

    expect($found)->toBeNull();
});
