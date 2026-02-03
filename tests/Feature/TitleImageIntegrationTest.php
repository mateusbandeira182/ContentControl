<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ElementIdentifier;
use MkGrow\ContentControl\Tests\Helpers\TestImageHelper;

beforeEach(function () {
    ElementIdentifier::clearCache();
    
    // Garantir que a imagem de teste existe
    TestImageHelper::ensureTestImageExists();
});

afterEach(function () {
    // Cleanup temp files
    if (isset($this->tempFile) && file_exists($this->tempFile)) {
        safeUnlink($this->tempFile);
    }
});

test('wraps Title elements with Content Controls in real DOCX', function () {
    $cc = new ContentControl();
    
    // Add title styles
    $cc->addTitleStyle(1, ['size' => 16, 'bold' => true]);
    $cc->addTitleStyle(2, ['size' => 14, 'bold' => true]);
    
    $section = $cc->addSection();
    
    // Add titles
    $title1 = $section->addTitle('Chapter 1: Introduction', 1);
    $section->addText('Content for chapter 1.');
    
    $title2 = $section->addTitle('1.1 Background', 2);
    $section->addText('Content for section 1.1.');
    
    // Wrap titles with Content Controls
    $cc->addContentControl($title1, [
        'alias' => 'Chapter Title',
        'tag' => 'chapter-1',
        'type' => ContentControl::TYPE_RICH_TEXT,
    ]);
    
    $cc->addContentControl($title2, [
        'alias' => 'Section Title',
        'tag' => 'section-1-1',
        'type' => ContentControl::TYPE_RICH_TEXT,
    ]);
    
    // Save to temp file
    $this->tempFile = tempnam(sys_get_temp_dir(), 'cc_title_test_') . '.docx';
    $cc->save($this->tempFile);
    
    // Verify file exists
    expect(file_exists($this->tempFile))->toBeTrue();
    
    // Extract and verify XML
    $zip = new ZipArchive();
    expect($zip->open($this->tempFile))->toBeTrue();
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    expect($xml)->not->toBeFalse();
    
    // Verify SDT wrappers exist
    expect($xml)->toContain('<w:sdt>');
    expect($xml)->toContain('<w:alias w:val="Chapter Title"/>');
    expect($xml)->toContain('<w:tag w:val="chapter-1"/>');
    expect($xml)->toContain('<w:alias w:val="Section Title"/>');
    expect($xml)->toContain('<w:tag w:val="section-1-1"/>');
    
    // Verify titles are inside SDTs
    expect($xml)->toContain('<w:pStyle w:val="Heading1"/>');
    expect($xml)->toContain('<w:pStyle w:val="Heading2"/>');
    
    // Verify bookmarks are preserved
    expect($xml)->toContain('<w:bookmarkStart');
    expect($xml)->toContain('<w:bookmarkEnd');
});

test('wraps Image elements with Content Controls in real DOCX', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    $testImagePath = TestImageHelper::getTestImagePath();
    
    // Add images
    $section->addText('Image 1:');
    $image1 = $section->addImage($testImagePath, [
        'width' => 100,
        'height' => 100,
    ]);
    
    $section->addTextBreak();
    
    $section->addText('Image 2:');
    $image2 = $section->addImage($testImagePath, [
        'width' => 200,
        'height' => 150,
    ]);
    
    // Wrap images with Content Controls
    $cc->addContentControl($image1, [
        'alias' => 'Small Image',
        'tag' => 'img-small',
        'type' => ContentControl::TYPE_PICTURE,
    ]);
    
    $cc->addContentControl($image2, [
        'alias' => 'Large Image',
        'tag' => 'img-large',
        'type' => ContentControl::TYPE_PICTURE,
    ]);
    
    // Save
    $this->tempFile = tempnam(sys_get_temp_dir(), 'cc_image_test_') . '.docx';
    $cc->save($this->tempFile);
    
    expect(file_exists($this->tempFile))->toBeTrue();
    
    // Extract and verify
    $zip = new ZipArchive();
    expect($zip->open($this->tempFile))->toBeTrue();
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    expect($xml)->not->toBeFalse();
    
    // Verify SDT wrappers with TYPE_PICTURE
    expect($xml)->toContain('<w:sdt>');
    expect($xml)->toContain('<w:alias w:val="Small Image"/>');
    expect($xml)->toContain('<w:tag w:val="img-small"/>');
    expect($xml)->toContain('<w:picture/>'); // Picture type indicator
    expect($xml)->toContain('<w:alias w:val="Large Image"/>');
    expect($xml)->toContain('<w:tag w:val="img-large"/>');
    
    // Verify images are present
    expect($xml)->toContain('<w:pict>');
});

test('mixed document with Titles, Images, and Text', function () {
    $cc = new ContentControl();
    
    $cc->addTitleStyle(1, ['size' => 16, 'bold' => true]);
    
    $section = $cc->addSection();
    
    $testImagePath = TestImageHelper::getTestImagePath();
    
    // Build mixed content
    $title1 = $section->addTitle('Chapter 1: Overview', 1);
    $text1 = $section->addText('This is the introduction paragraph.');
    
    $image1 = $section->addImage($testImagePath, ['width' => 150, 'height' => 150]);
    
    $title2 = $section->addTitle('Chapter 2: Details', 1);
    $text2 = $section->addText('More detailed content here.');
    
    $image2 = $section->addImage($testImagePath, ['width' => 100, 'height' => 100]);
    
    // Wrap all elements
    $cc->addContentControl($title1, ['alias' => 'Chapter 1', 'tag' => 'ch1']);
    $cc->addContentControl($text1, ['alias' => 'Intro Text', 'tag' => 'intro']);
    $cc->addContentControl($image1, ['alias' => 'Image 1', 'tag' => 'img1', 'type' => ContentControl::TYPE_PICTURE]);
    $cc->addContentControl($title2, ['alias' => 'Chapter 2', 'tag' => 'ch2']);
    $cc->addContentControl($text2, ['alias' => 'Detail Text', 'tag' => 'detail']);
    $cc->addContentControl($image2, ['alias' => 'Image 2', 'tag' => 'img2', 'type' => ContentControl::TYPE_PICTURE]);
    
    // Save
    $this->tempFile = tempnam(sys_get_temp_dir(), 'cc_mixed_test_') . '.docx';
    $cc->save($this->tempFile);
    
    expect(file_exists($this->tempFile))->toBeTrue();
    
    // Extract and verify
    $zip = new ZipArchive();
    expect($zip->open($this->tempFile))->toBeTrue();
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    expect($xml)->not->toBeFalse();
    
    // Count SDTs (should be 6)
    $sdtCount = substr_count($xml, '<w:sdt>');
    expect($sdtCount)->toBe(6);
    
    // Verify all aliases present
    expect($xml)->toContain('Chapter 1');
    expect($xml)->toContain('Intro Text');
    expect($xml)->toContain('Image 1');
    expect($xml)->toContain('Chapter 2');
    expect($xml)->toContain('Detail Text');
    expect($xml)->toContain('Image 2');
    
    // Verify mix of rich text and picture types
    $pictureCount = substr_count($xml, '<w:picture/>');
    expect($pictureCount)->toBeGreaterThanOrEqual(2); // At least 2 picture SDTs
});

test('hierarchical Titles with multiple depths', function () {
    $cc = new ContentControl();
    
    // Add styles for all depths
    $cc->addTitleStyle(0, ['size' => 20, 'bold' => true]);
    $cc->addTitleStyle(1, ['size' => 18, 'bold' => true]);
    $cc->addTitleStyle(2, ['size' => 16, 'bold' => true]);
    $cc->addTitleStyle(3, ['size' => 14, 'bold' => true]);
    
    $section = $cc->addSection();
    
    // Add hierarchical structure
    $docTitle = $section->addTitle('Document Title', 0);
    $chapter1 = $section->addTitle('Chapter 1: Introduction', 1);
    $section11 = $section->addTitle('1.1 Background', 2);
    $section111 = $section->addTitle('1.1.1 Historical Context', 3);
    
    // Wrap all
    $cc->addContentControl($docTitle, ['alias' => 'Document Title', 'tag' => 'doc-title']);
    $cc->addContentControl($chapter1, ['alias' => 'Chapter 1', 'tag' => 'ch1']);
    $cc->addContentControl($section11, ['alias' => 'Section 1.1', 'tag' => 's11']);
    $cc->addContentControl($section111, ['alias' => 'Section 1.1.1', 'tag' => 's111']);
    
    // Save
    $this->tempFile = tempnam(sys_get_temp_dir(), 'cc_hierarchy_test_') . '.docx';
    $cc->save($this->tempFile);
    
    expect(file_exists($this->tempFile))->toBeTrue();
    
    // Extract and verify
    $zip = new ZipArchive();
    expect($zip->open($this->tempFile))->toBeTrue();
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    // Verify all styles present
    expect($xml)->toContain('<w:pStyle w:val="Title"/>');
    expect($xml)->toContain('<w:pStyle w:val="Heading1"/>');
    expect($xml)->toContain('<w:pStyle w:val="Heading2"/>');
    expect($xml)->toContain('<w:pStyle w:val="Heading3"/>');
    
    // Verify all SDTs
    expect($xml)->toContain('Document Title');
    expect($xml)->toContain('Chapter 1');
    expect($xml)->toContain('Section 1.1');
    expect($xml)->toContain('Section 1.1.1');
});

test('preserves relationIds for images', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    $testImagePath = TestImageHelper::getTestImagePath();
    
    $image = $section->addImage($testImagePath, ['width' => 100, 'height' => 100]);
    
    $cc->addContentControl($image, [
        'alias' => 'Test Image',
        'tag' => 'test-img',
        'type' => ContentControl::TYPE_PICTURE,
    ]);
    
    // Save
    $this->tempFile = tempnam(sys_get_temp_dir(), 'cc_rel_test_') . '.docx';
    $cc->save($this->tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    expect($zip->open($this->tempFile))->toBeTrue();
    
    $xml = $zip->getFromName('word/document.xml');
    $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
    $zip->close();
    
    // Verify rId reference exists in document
    expect($xml)->toMatch('/r:id="rId\d+"/');
    
    // Verify relationship exists
    expect($relsXml)->not->toBeFalse();
    expect($relsXml)->toContain('Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"');
});

test('no duplication when wrapping Title elements', function () {
    $cc = new ContentControl();
    
    $cc->addTitleStyle(1, ['size' => 16, 'bold' => true]);
    
    $section = $cc->addSection();
    
    $title = $section->addTitle('Unique Title', 1);
    $section->addText('Some content.');
    
    // Wrap title
    $cc->addContentControl($title, ['alias' => 'Test Title', 'tag' => 'test']);
    
    // Save
    $this->tempFile = tempnam(sys_get_temp_dir(), 'cc_nodup_test_') . '.docx';
    $cc->save($this->tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    expect($zip->open($this->tempFile))->toBeTrue();
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    // Count occurrences of "Unique Title"
    $count = substr_count($xml, 'Unique Title');
    expect($count)->toBe(1); // Should appear only once (no duplication)
    
    // Verify bookmark count (should be only 1 start and 1 end)
    $bookmarkStartCount = substr_count($xml, '<w:bookmarkStart');
    $bookmarkEndCount = substr_count($xml, '<w:bookmarkEnd');
    expect($bookmarkStartCount)->toBe($bookmarkEndCount); // Must match
});
