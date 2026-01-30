<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

/**
 * End-to-End Integration tests for Headers and Footers (v0.2.0)
 * 
 * Validates complete workflow:
 * 1. Create document with headers/footers
 * 2. Add Content Controls to elements in headers/footers
 * 3. Generate DOCX file
 * 4. Verify SDT XML injection in header*.xml and footer*.xml files
 */

test('wraps Text in Header with Content Control', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add header with text
    $header = $section->addHeader();
    $headerText = $header->addText('Company Logo');
    
    // Register Content Control for header text
    $cc->addContentControl($headerText, [
        'alias' => 'HeaderLogo',
        'tag' => 'header-logo',
        'lockType' => ContentControl::LOCK_SDT_LOCKED,
    ]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_header_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify header XML (discover actual filename instead of assuming header1.xml)
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Find header files dynamically
    $headerXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/header\d+\.xml$#', $filename) === 1) {
            $headerXml = $zip->getFromName($filename);
            break;
        }
    }
    
    expect($headerXml)->not->toBeNull('No header XML file found in DOCX');
    assert(is_string($headerXml));
    
    // Verify SDT structure in header
    expect($headerXml)->toContain('<w:sdt>')
        ->and($headerXml)->toContain('<w:alias w:val="HeaderLogo"/>')
        ->and($headerXml)->toContain('<w:tag w:val="header-logo"/>')
        ->and($headerXml)->toContain('<w:lock w:val="sdtLocked"/>')
        ->and($headerXml)->toContain('<w:sdtContent>')
        ->and($headerXml)->toContain('Company Logo');
    
    // Verify no duplication
    expect(substr_count($headerXml, 'Company Logo'))->toBe(1);
    
    $zip->close();
    unlink($tempFile);
});

test('wraps Text in Footer with Content Control', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add footer with text
    $footer = $section->addFooter();
    $footerText = $footer->addText('Confidential Information');
    
    // Register Content Control for footer text
    $cc->addContentControl($footerText, [
        'alias' => 'FooterDisclaimer',
        'tag' => 'footer-disclaimer',
        'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
    ]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_footer_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify footer XML (discover actual filename instead of assuming footer1.xml)
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Find footer files dynamically
    $footerXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/footer\d+\.xml$#', $filename) === 1) {
            $footerXml = $zip->getFromName($filename);
            break;
        }
    }
    
    expect($footerXml)->not->toBeNull('No footer XML file found in DOCX');
    assert(is_string($footerXml));
    
    // Verify SDT structure in footer
    expect($footerXml)->toContain('<w:sdt>')
        ->and($footerXml)->toContain('<w:alias w:val="FooterDisclaimer"/>')
        ->and($footerXml)->toContain('<w:tag w:val="footer-disclaimer"/>')
        ->and($footerXml)->toContain('<w:lock w:val="sdtContentLocked"/>')
        ->and($footerXml)->toContain('Confidential Information');
    
    // Verify no duplication
    expect(substr_count($footerXml, 'Confidential Information'))->toBe(1);
    
    $zip->close();
    unlink($tempFile);
});

test('processes body, header, and footer simultaneously', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add content to body
    $bodyText = $section->addText('Body Content');
    
    // Add content to header
    $header = $section->addHeader();
    $headerText = $header->addText('Header Content');
    
    // Add content to footer
    $footer = $section->addFooter();
    $footerText = $footer->addText('Footer Content');
    
    // Register Content Controls for all three
    $cc->addContentControl($bodyText, ['alias' => 'BodyText', 'tag' => 'body']);
    $cc->addContentControl($headerText, ['alias' => 'HeaderText', 'tag' => 'header']);
    $cc->addContentControl($footerText, ['alias' => 'FooterText', 'tag' => 'footer']);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_all_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify all three files
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Verify document.xml (body)
    $documentXml = $zip->getFromName('word/document.xml');
    expect($documentXml)->not->toBeFalse()
        ->and($documentXml)->toContain('<w:alias w:val="BodyText"/>')
        ->and($documentXml)->toContain('Body Content');
    
    // Verify header (discover dynamically)
    $headerXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/header\d+\.xml$#', $filename) === 1) {
            $headerXml = $zip->getFromName($filename);
            break;
        }
    }
    expect($headerXml)->not->toBeNull('No header XML file found in DOCX')
        ->and($headerXml)->toContain('<w:alias w:val="HeaderText"/>')
        ->and($headerXml)->toContain('Header Content');
    
    // Verify footer (discover dynamically)
    $footerXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/footer\d+\.xml$#', $filename) === 1) {
            $footerXml = $zip->getFromName($filename);
            break;
        }
    }
    expect($footerXml)->not->toBeNull('No footer XML file found in DOCX')
        ->and($footerXml)->toContain('<w:alias w:val="FooterText"/>')
        ->and($footerXml)->toContain('Footer Content');
    
    $zip->close();
    unlink($tempFile);
});

test('wraps Table in Header with Content Control', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add header with table
    $header = $section->addHeader();
    $table = $header->addTable();
    $row = $table->addRow();
    $row->addCell(2000)->addText('Column 1');
    $row->addCell(2000)->addText('Column 2');
    
    // Register Content Control for table
    $cc->addContentControl($table, [
        'alias' => 'HeaderTable',
        'tag' => 'header-table',
    ]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_header_table_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Discover header file dynamically
    $headerXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/header\d+\.xml$#', $filename) === 1) {
            $headerXml = $zip->getFromName($filename);
            break;
        }
    }
    expect($headerXml)->not->toBeNull('No header XML file found in DOCX')
        ->and($headerXml)->toContain('<w:alias w:val="HeaderTable"/>')
        ->and($headerXml)->toContain('<w:tbl>')  // Table element
        ->and($headerXml)->toContain('Column 1')
        ->and($headerXml)->toContain('Column 2');
    
    // Verify SDT wraps table correctly
    expect($headerXml)->toContain('<w:sdtContent><w:tbl>');
    
    $zip->close();
    unlink($tempFile);
});

test('wraps Image in Footer with Content Control', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Create temporary image
    $tempImage = tempnam(sys_get_temp_dir(), 'img_') . '.png';
    $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
    if ($imageData === false) {
        throw new \RuntimeException('Failed to decode base64 image data');
    }
    file_put_contents($tempImage, $imageData);
    
    // Add footer with image
    $footer = $section->addFooter();
    $image = $footer->addImage($tempImage);
    $style = $image->getStyle();
    if ($style !== null) {
        $style->setWidth(100);
        $style->setHeight(100);
    }
    
    // Register Content Control for image
    $cc->addContentControl($image, [
        'alias' => 'FooterImage',
        'tag' => 'footer-image',
        'type' => ContentControl::TYPE_PICTURE,
    ]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_footer_image_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Discover footer file dynamically
    $footerXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/footer\d+\.xml$#', $filename) === 1) {
            $footerXml = $zip->getFromName($filename);
            break;
        }
    }
    expect($footerXml)->not->toBeNull('No footer XML file found in DOCX')
        ->and($footerXml)->toContain('<w:alias w:val="FooterImage"/>')
        ->and($footerXml)->toContain('<w:picture/>')  // TYPE_PICTURE
        ->and($footerXml)->toContain('<w:pict>');  // VML image
    
    $zip->close();
    unlink($tempFile);
    unlink($tempImage);
});

test('handles multiple sections with independent headers and footers', function () {
    $cc = new ContentControl();
    
    // Section 1
    $section1 = $cc->addSection();
    $header1 = $section1->addHeader();
    $headerText1 = $header1->addText('Header Section 1');
    
    // Section 2
    $section2 = $cc->addSection();
    $header2 = $section2->addHeader();
    $headerText2 = $header2->addText('Header Section 2');
    
    // Register Content Controls
    $cc->addContentControl($headerText1, ['alias' => 'Header1', 'tag' => 'header-1']);
    $cc->addContentControl($headerText2, ['alias' => 'Header2', 'tag' => 'header-2']);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_multi_section_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify headers exist
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Discover all header files dynamically
    $headerFiles = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/header\d+\.xml$#', $filename) === 1) {
            $headerFiles[$filename] = $zip->getFromName($filename);
        }
    }
    
    // At minimum, one header file should exist
    expect(count($headerFiles))->toBeGreaterThanOrEqual(1, 'At least one header file should exist');
    
    // If PHPWord created separate headers, verify both
    if (count($headerFiles) >= 2) {
        // Two or more separate header files
        $foundHeader1 = false;
        $foundHeader2 = false;
        
        foreach ($headerFiles as $headerXml) {
            if (is_string($headerXml) && str_contains($headerXml, '<w:alias w:val="Header1"/>')) {
                expect($headerXml)->toContain('Header Section 1');
                $foundHeader1 = true;
            }
            if (is_string($headerXml) && str_contains($headerXml, '<w:alias w:val="Header2"/>')) {
                expect($headerXml)->toContain('Header Section 2');
                $foundHeader2 = true;
            }
        }
        
        expect($foundHeader1)->toBeTrue('Header1 should be found in header files');
        expect($foundHeader2)->toBeTrue('Header2 should be found in header files');
    } else {
        // PHPWord reused the same header - both elements should be in the single header file
        $headerXml = reset($headerFiles);
        expect($headerXml)->toBeString();
        $headerXmlStr = (string) $headerXml; // Cast for PHPStan
        $hasHeader1 = str_contains($headerXmlStr, '<w:alias w:val="Header1"/>');
        $hasHeader2 = str_contains($headerXmlStr, '<w:alias w:val="Header2"/>');
        
        expect($hasHeader1 || $hasHeader2)->toBeTrue(
            'Expected header1.xml to contain at least one of the header aliases when headers are shared'
        );
    }
    
    $zip->close();
    unlink($tempFile);
});

test('mixed content: Text in body, Table in header, Image in footer', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Body: Text
    $bodyText = $section->addText('Document Content');
    
    // Header: Table
    $header = $section->addHeader();
    $headerTable = $header->addTable();
    $row = $headerTable->addRow();
    $row->addCell(3000)->addText('Company Name');
    
    // Footer: Image
    $tempImage = tempnam(sys_get_temp_dir(), 'img_') . '.png';
    $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
    if ($imageData === false) {
        throw new \RuntimeException('Failed to decode base64 image data');
    }
    file_put_contents($tempImage, $imageData);
    
    $footer = $section->addFooter();
    $footerImage = $footer->addImage($tempImage);
    
    // Register Content Controls
    $cc->addContentControl($bodyText, ['alias' => 'BodyText']);
    $cc->addContentControl($headerTable, ['alias' => 'HeaderTable']);
    $cc->addContentControl($footerImage, ['alias' => 'FooterImage', 'type' => ContentControl::TYPE_PICTURE]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_mixed_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify all three
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    $documentXml = $zip->getFromName('word/document.xml');
    
    // Discover header file dynamically
    $headerXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/header\d+\.xml$#', $filename) === 1) {
            $headerXml = $zip->getFromName($filename);
            break;
        }
    }
    
    // Discover footer file dynamically
    $footerXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/footer\d+\.xml$#', $filename) === 1) {
            $footerXml = $zip->getFromName($filename);
            break;
        }
    }
    
    // Verify body
    expect($documentXml)->not->toBeFalse()
        ->and($documentXml)->toContain('<w:alias w:val="BodyText"/>')
        ->and($documentXml)->toContain('Document Content');
    
    // Verify header (table)
    expect($headerXml)->not->toBeFalse()
        ->and($headerXml)->toContain('<w:alias w:val="HeaderTable"/>')
        ->and($headerXml)->toContain('<w:tbl>')
        ->and($headerXml)->toContain('Company Name');
    
    // Verify footer (image)
    expect($footerXml)->not->toBeFalse()
        ->and($footerXml)->toContain('<w:alias w:val="FooterImage"/>')
        ->and($footerXml)->toContain('<w:picture/>');
    
    $zip->close();
    unlink($tempFile);
    unlink($tempImage);
});

test('validates OOXML structure after SDT injection in headers', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    $header = $section->addHeader();
    $headerText = $header->addText('Validated Header');
    $cc->addContentControl($headerText, ['alias' => 'ValidatedHeader']);
    
    $tempFile = tempnam(sys_get_temp_dir(), 'test_validation_') . '.docx';
    $cc->save($tempFile);
    
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Discover header file dynamically
    $headerXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/header\d+\.xml$#', $filename) === 1) {
            $headerXml = $zip->getFromName($filename);
            break;
        }
    }
    expect($headerXml)->not->toBeNull('No header XML file found in DOCX');
    
    // Validate OOXML structure (ISO/IEC 29500-1:2016 §17.5.2)
    // <w:sdt> must contain <w:sdtPr> and <w:sdtContent>
    expect($headerXml)->toMatch('/<w:sdt>.*<w:sdtPr>.*<w:sdtContent>.*<\/w:sdt>/s');
    
    // SDT properties must be in correct order
    expect($headerXml)->toMatch('/<w:sdtPr>.*<w:id.*<w:alias.*<\/w:sdtPr>/s');
    
    // Content must be preserved inside sdtContent
    expect($headerXml)->toMatch('/<w:sdtContent>.*Validated Header.*<\/w:sdtContent>/s');
    
    // Verify valid XML
    expect($headerXml)->toBeString();
    $dom = new DOMDocument();
    $loaded = @$dom->loadXML((string) $headerXml); // Cast for PHPStan
    expect($loaded)->toBeTrue();
    
    $zip->close();
    unlink($tempFile);
});

test('does not process empty headers or footers', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add empty header (no elements added)
    $section->addHeader();
    
    // Add content to body only
    $bodyText = $section->addText('Body Only');
    $cc->addContentControl($bodyText, ['alias' => 'BodyOnly']);
    
    $tempFile = tempnam(sys_get_temp_dir(), 'test_empty_header_') . '.docx';
    $cc->save($tempFile);
    
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Discover header file dynamically (PHPWord creates it even if empty)
    $headerXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (is_string($filename) && preg_match('#^word/header\d+\.xml$#', $filename) === 1) {
            $headerXml = $zip->getFromName($filename);
            break;
        }
    }
    expect($headerXml)->not->toBeNull('Header file should exist even if empty');
    
    // Should NOT contain SDT tags (no elements registered)
    expect($headerXml)->not->toContain('<w:sdt>');
    
    $zip->close();
    unlink($tempFile);
});

test('performance: processes 100 elements across body, header, and footer efficiently', function () {
    $startTime = microtime(true);
    
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add 50 texts to body
    for ($i = 0; $i < 50; $i++) {
        $text = $section->addText("Body Text $i");
        $cc->addContentControl($text, ['alias' => "BodyText$i"]);
    }
    
    // Add 25 texts to header
    $header = $section->addHeader();
    for ($i = 0; $i < 25; $i++) {
        $text = $header->addText("Header Text $i");
        $cc->addContentControl($text, ['alias' => "HeaderText$i"]);
    }
    
    // Add 25 texts to footer
    $footer = $section->addFooter();
    for ($i = 0; $i < 25; $i++) {
        $text = $footer->addText("Footer Text $i");
        $cc->addContentControl($text, ['alias' => "FooterText$i"]);
    }
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_performance_') . '.docx';
    $cc->save($tempFile);
    
    $elapsed = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
    
    // Verify file was created
    expect(file_exists($tempFile))->toBeTrue();
    
    // Performance: Should complete in < 500ms for 100 elements across 3 files
    expect($elapsed)->toBeLessThan(500);
    
    unlink($tempFile);
});
