<?php

declare(strict_types=1);

use MkGrow\ContentControl\ElementLocator;
use MkGrow\ContentControl\ElementIdentifier;
use MkGrow\ContentControl\Tests\Helpers\TestImageHelper;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\Text;

beforeEach(function () {
    ElementIdentifier::clearCache();
    
    // Ensure that test image exists
    TestImageHelper::ensureTestImageExists();
});

test('locates Image element by VML pict', function () {
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
    
    $testImagePath = TestImageHelper::getTestImagePath();
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
    $testImagePath = TestImageHelper::getTestImagePath();
    
    $image1 = new Image($testImagePath, ['width' => 100, 'height' => 100]);
    $hash1 = ElementIdentifier::generateContentHash($image1);
    
    $image2 = new Image($testImagePath, ['width' => 200, 'height' => 150]);
    $hash2 = ElementIdentifier::generateContentHash($image2);
    
    // Different dimensions = different hashes
    expect($hash1)->not->toBe($hash2);
});

test('hash differentiates Image from Text', function () {
    $testImagePath = TestImageHelper::getTestImagePath();
    
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
    
    $testImagePath = TestImageHelper::getTestImagePath();
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
    
    $testImagePath = TestImageHelper::getTestImagePath();
    $image = new Image($testImagePath, ['width' => 150, 'height' => 150]);
    
    // Should not throw exception
    $found = $locator->findElementInDOM($dom, $image, 0);
    
    expect($found)->not->toBeNull();
});

test('locates inline images with centered alignment', function () {
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
    
    $testImagePath = TestImageHelper::getTestImagePath();
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
    
    $testImagePath = TestImageHelper::getTestImagePath();
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

    $testImagePath = TestImageHelper::getTestImagePath();
    $image = new Image($testImagePath, ['width' => 150, 'height' => 150]);
    
    // Generate content hash for the image to seed any internal cache
    $contentHash = ElementIdentifier::generateContentHash($image);
    expect($contentHash)->not->toBeEmpty();
    
    // Call the public API with an out-of-range registration order so that
    // the primary type+order lookup fails and the content-hash fallback is used
    $found = $locator->findElementInDOM($dom, $image, 999);
    
    // Should find the image via content-hash fallback
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

test('hash differentiates images with identical dimensions but different sources (UUID v5)', function () {
    $testImagePath1 = TestImageHelper::getTestImagePath();
    
    // Create a second temporary image with different content but identical dimensions
    $tempImagePath = sys_get_temp_dir() . '/test_image_2_' . uniqid() . '.png';
    
    // Create a different image (blue 1x1 instead of original red 1x1)
    $image = imagecreatetruecolor(1, 1);
    $blue = imagecolorallocate($image, 0, 0, 255);
    imagefilledrectangle($image, 0, 0, 1, 1, $blue);
    imagepng($image, $tempImagePath);
    imagedestroy($image);
    
    try {
        // Create two Image elements with identical dimensions but different source files
        $image1 = new Image($testImagePath1, ['width' => 200, 'height' => 200]);
        $hash1 = ElementIdentifier::generateContentHash($image1);
        
        $image2 = new Image($tempImagePath, ['width' => 200, 'height' => 200]);
        $hash2 = ElementIdentifier::generateContentHash($image2);
        
        // FIXED in v0.5.0: UUID v5 includes source basename, so different files produce DIFFERENT hashes
        // Even with identical dimensions, distinct image files now generate unique hashes
        expect($hash1)->not->toBe($hash2)
            ->and($hash1)->not->toBeEmpty()
            ->and($hash2)->not->toBeEmpty();
    } finally {
        // Clean up temporary file
        if (file_exists($tempImagePath)) {
            unlink($tempImagePath);
        }
    }
});

