<?php

declare(strict_types=1);

use MkGrow\ContentControl\ElementLocator;
use MkGrow\ContentControl\ElementIdentifier;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\Text;

beforeEach(function () {
    ElementIdentifier::clearCache();
    
    // Create test image if it doesn't exist
    $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
    if (!file_exists($testImagePath)) {
        $dir = dirname($testImagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $image = imagecreatetruecolor(1, 1);
        $red = imagecolorallocate($image, 255, 0, 0);
        imagefilledrectangle($image, 0, 0, 1, 1, $red);
        imagepng($image, $testImagePath);
        imagedestroy($image);
    }
});

test('localizes Image element by VML pict', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:v="urn:schemas-microsoft-com:vml"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:body>
        <w:p>
            <w:pPr/>
            <w:r>
                <w:pict>
                    <v:shape type="#_x0000_t75" style="width:100pt; height:100pt;">
                        <v:imagedata r:id="rId7"/>
                    </v:shape>
                </w:pict>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    
    $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
    $image = new Image($testImagePath, ['width' => 100, 'height' => 100]);
    
    $found = $locator->findElementInDOM($dom, $image, 0);
    
    expect($found)->not->toBeNull();
    expect($found->nodeName)->toBe('w:p');
    
    // Verify it contains w:pict
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $pict = $xpath->query('.//w:pict', $found);
    expect($pict->length)->toBe(1);
});

test('hash differentiates Images by dimensions', function () {
    $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
    
    $image1 = new Image($testImagePath, ['width' => 100, 'height' => 100]);
    $hash1 = ElementIdentifier::generateContentHash($image1);
    
    $image2 = new Image($testImagePath, ['width' => 200, 'height' => 150]);
    $hash2 = ElementIdentifier::generateContentHash($image2);
    
    // Different dimensions = different hashes
    expect($hash1)->not->toBe($hash2);
});

test('hash differentiates Image from Text', function () {
    $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
    
    $image = new Image($testImagePath, ['width' => 100, 'height' => 100]);
    $imageHash = ElementIdentifier::generateContentHash($image);
    
    $text = new Text('Some text');
    $textHash = ElementIdentifier::generateContentHash($text);
    
    expect($imageHash)->not->toBe($textHash);
});

test('skips Image elements already wrapped in SDT', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:v="urn:schemas-microsoft-com:vml"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:body>
        <w:sdt>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:pict>
                            <v:shape style="width:100pt; height:100pt;">
                                <v:imagedata r:id="rId1"/>
                            </v:shape>
                        </w:pict>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
        <w:p>
            <w:r>
                <w:pict>
                    <v:shape style="width:200pt; height:150pt;">
                        <v:imagedata r:id="rId2"/>
                    </v:shape>
                </w:pict>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    
    $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
    $image = new Image($testImagePath, ['width' => 200, 'height' => 150]);
    
    $found = $locator->findElementInDOM($dom, $image, 0);
    
    expect($found)->not->toBeNull();
    
    // Verify it found the second image (not wrapped)
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
    
    $shape = $xpath->query('.//v:shape', $found);
    expect($shape->length)->toBe(1);
    expect($shape->item(0)->getAttribute('style'))->toContain('200pt');
});

test('registers VML namespaces correctly', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:v="urn:schemas-microsoft-com:vml"
            xmlns:o="urn:schemas-microsoft-com:office:office"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:body>
        <w:p>
            <w:r>
                <w:pict>
                    <v:shape type="#_x0000_t75" style="width:150pt; height:150pt;">
                        <v:imagedata r:id="rId5" o:title="Test"/>
                    </v:shape>
                </w:pict>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    
    $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
    $image = new Image($testImagePath, ['width' => 150, 'height' => 150]);
    
    // Should not throw exception
    $found = $locator->findElementInDOM($dom, $image, 0);
    
    expect($found)->not->toBeNull();
});

test('localizes inline images with centered alignment', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:v="urn:schemas-microsoft-com:vml"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:body>
        <w:p>
            <w:pPr>
                <w:jc w:val="center"/>
            </w:pPr>
            <w:r>
                <w:pict>
                    <v:shape style="width:100pt; height:100pt;">
                        <v:imagedata r:id="rId7"/>
                    </v:shape>
                </w:pict>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    
    $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
    $image = new Image($testImagePath, ['width' => 100, 'height' => 100]);
    
    $found = $locator->findElementInDOM($dom, $image, 0);
    
    expect($found)->not->toBeNull();
    
    // Verify alignment is preserved
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $jc = $xpath->query('.//w:pPr/w:jc', $found);
    expect($jc->length)->toBe(1);
    expect($jc->item(0)->getAttribute('w:val'))->toBe('center');
});

test('hash includes relationId for image differentiation', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:v="urn:schemas-microsoft-com:vml"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:body>
        <w:p>
            <w:r>
                <w:pict>
                    <v:shape style="width:100pt; height:100pt;">
                        <v:imagedata r:id="rId7"/>
                    </v:shape>
                </w:pict>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();
    
    $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
    $image = new Image($testImagePath, ['width' => 100, 'height' => 100]);
    
    $found = $locator->findElementInDOM($dom, $image, 0);
    
    expect($found)->not->toBeNull();
    
    // Verify rId is preserved in hash
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
    $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    
    $imageData = $xpath->query('.//v:imagedata', $found);
    expect($imageData->length)->toBe(1);
    
    $rId = $imageData->item(0)->getAttributeNS(
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
        'id'
    );
    expect($rId)->toBe('rId7');
});

test('finds image via content-hash fallback using reflection', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:v="urn:schemas-microsoft-com:vml"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:body>
        <w:p>
            <w:r>
                <w:pict>
                    <v:shape style="width:150pt; height:150pt;">
                        <v:imagedata r:id="rId10"/>
                    </v:shape>
                </w:pict>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $locator = new ElementLocator();

    $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
    $image = new Image($testImagePath, ['width' => 150, 'height' => 150]);
    
    // Use reflection to directly test findByContentHash()
    $reflection = new ReflectionClass($locator);
    $method = $reflection->getMethod('findByContentHash');
    $method->setAccessible(true);
    
    // Generate content hash for the image
    $contentHash = \MkGrow\ContentControl\ElementIdentifier::generateContentHash($image);
    
    // Initialize XPath in locator by calling findElementInDOM first
    $locator->findElementInDOM($dom, $image, 0);
    
    // Now call findByContentHash directly
    $found = $method->invoke($locator, $image, $contentHash);
    
    // Should find the image via content hash
    expect($found)->not->toBeNull();
    expect($found->nodeName)->toBe('w:p');
    
    // Verify it contains the correct w:pict
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
    
    $pict = $xpath->query('.//w:pict', $found);
    expect($pict->length)->toBe(1);
    
    // Verify style matches (150pt x 150pt)
    $shape = $xpath->query('.//v:shape', $found);
    expect($shape->length)->toBe(1);
    expect($shape->item(0)->getAttribute('style'))->toContain('150pt');
});

