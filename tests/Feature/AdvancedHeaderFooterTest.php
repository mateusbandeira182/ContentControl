<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

/**
 * Advanced Integration Tests for Headers and Footers (v0.2.0)
 * 
 * Tests complex scenarios:
 * - Multiple sections with independent headers/footers
 * - First page headers/footers
 * - Even page headers/footers
 * - Complex tables in headers
 * - Images in footers
 * - PreserveText elements (page numbers, dates)
 */

test('handles multiple sections with independent headers and footers', function () {
    $cc = new ContentControl();
    
    // Section 1
    $section1 = $cc->addSection();
    $header1 = $section1->addHeader();
    $headerText1 = $header1->addText('Section 1 Header');
    
    $footer1 = $section1->addFooter();
    $footerText1 = $footer1->addText('Section 1 Footer');
    
    // Section 2
    $section2 = $cc->addSection();
    $header2 = $section2->addHeader();
    $headerText2 = $header2->addText('Section 2 Header');
    
    $footer2 = $section2->addFooter();
    $footerText2 = $footer2->addText('Section 2 Footer');
    
    // Register Content Controls for all
    $cc->addContentControl($headerText1, ['alias' => 'Header1', 'tag' => 'h1']);
    $cc->addContentControl($footerText1, ['alias' => 'Footer1', 'tag' => 'f1']);
    $cc->addContentControl($headerText2, ['alias' => 'Header2', 'tag' => 'h2']);
    $cc->addContentControl($footerText2, ['alias' => 'Footer2', 'tag' => 'f2']);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_multi_section_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Count header and footer files
    $headerCount = 0;
    $footerCount = 0;
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if ($filename !== false) {
            $isHeader = preg_match('#^word/header\d+\.xml$#', $filename);
            $isFooter = preg_match('#^word/footer\d+\.xml$#', $filename);
            
            if ($isHeader === 1) {
                $headerCount++;
            }
            if ($isFooter === 1) {
                $footerCount++;
            }
        }
    }
    
    // PHPWord may reuse header/footer files or create separate ones
    // Accept both scenarios: separate files (2 headers, 2 footers) OR shared files (1 header, 1 footer)
    expect($headerCount)->toBeGreaterThanOrEqual(1)
        ->and($headerCount)->toBeLessThanOrEqual(2);
    expect($footerCount)->toBeGreaterThanOrEqual(1)
        ->and($footerCount)->toBeLessThanOrEqual(2);
    
    // Verify all SDTs are present somewhere
    $allXml = '';
    for ($i = 1; $i <= 4; $i++) {
        $headerXml = $zip->getFromName("word/header{$i}.xml");
        $footerXml = $zip->getFromName("word/footer{$i}.xml");
        if ($headerXml !== false) {
            $allXml .= $headerXml;
        }
        if ($footerXml !== false) {
            $allXml .= $footerXml;
        }
    }
    
    // Verify all Content Controls are present
    expect($allXml)->toContain('<w:alias w:val="Header1"/>')
        ->and($allXml)->toContain('<w:alias w:val="Footer1"/>')
        ->and($allXml)->toContain('<w:alias w:val="Header2"/>')
        ->and($allXml)->toContain('<w:alias w:val="Footer2"/>');
    
    $zip->close();
    unlink($tempFile);
});

test('wraps complex Table in Header with Content Control', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Create complex table in header
    $header = $section->addHeader();
    $table = $header->addTable([
        'borderSize' => 6,
        'borderColor' => '000000',
        'width' => 100 * 50,
        'unit' => 'pct',
    ]);
    
    // Row 1
    $table->addRow(400);
    $table->addCell(2000)->addText('Column 1');
    $table->addCell(2000)->addText('Column 2');
    $table->addCell(2000)->addText('Column 3');
    
    // Row 2
    $table->addRow(400);
    $table->addCell(2000)->addText('Data 1');
    $table->addCell(2000)->addText('Data 2');
    $table->addCell(2000)->addText('Data 3');
    
    // Register Content Control for table
    $cc->addContentControl($table, [
        'alias' => 'HeaderTable',
        'tag' => 'header-table',
        'lockType' => ContentControl::LOCK_SDT_LOCKED,
    ]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_header_table_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    $headerXml = $zip->getFromName('word/header1.xml');
    expect($headerXml)->not->toBeFalse();
    assert(is_string($headerXml)); // PHPStan type guard
    
    // Verify SDT wraps table
    expect($headerXml)->toContain('<w:sdt>')
        ->and($headerXml)->toContain('<w:alias w:val="HeaderTable"/>')
        ->and($headerXml)->toContain('<w:tag w:val="header-table"/>')
        ->and($headerXml)->toContain('<w:sdtContent>')
        ->and($headerXml)->toContain('<w:tbl>') // Table XML element
        ->and($headerXml)->toContain('Column 1')
        ->and($headerXml)->toContain('Column 2')
        ->and($headerXml)->toContain('Data 1');
    
    // Verify SDT structure: <w:sdt><w:sdtContent><w:tbl>
    $dom = new DOMDocument();
    expect(@$dom->loadXML($headerXml))->toBeTrue();
    
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    // Find SDT containing table
    $sdtWithTable = $xpath->query('//w:sdt[.//w:tbl]');
    expect($sdtWithTable->length)->toBeGreaterThan(0);
    
    $zip->close();
    unlink($tempFile);
});

test('wraps Image in Footer with Content Control', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Create temporary test image
    $imagePath = tempnam(sys_get_temp_dir(), 'test_img_') . '.png';
    $img = imagecreatetruecolor(100, 50);
    $bgColor = imagecolorallocate($img, 255, 255, 255);
    if ($bgColor !== false) {
        imagefill($img, 0, 0, $bgColor);
    }
    imagepng($img, $imagePath);
    imagedestroy($img);
    
    // Add image to footer
    $footer = $section->addFooter();
    $image = $footer->addImage($imagePath, [
        'width' => 100,
        'height' => 50,
        'positioning' => 'relative',
    ]);
    
    // Register Content Control for image
    $cc->addContentControl($image, [
        'alias' => 'FooterLogo',
        'tag' => 'footer-logo',
        'type' => ContentControl::TYPE_PICTURE,
        'lockType' => ContentControl::LOCK_SDT_LOCKED,
    ]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_footer_image_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    $footerXml = $zip->getFromName('word/footer1.xml');
    expect($footerXml)->not->toBeFalse();
    
    // Verify SDT wraps image paragraph with VML
    expect($footerXml)->toContain('<w:sdt>')
        ->and($footerXml)->toContain('<w:alias w:val="FooterLogo"/>')
        ->and($footerXml)->toContain('<w:tag w:val="footer-logo"/>')
        ->and($footerXml)->toContain('<w:picture/>') // Picture type
        ->and($footerXml)->toContain('<w:sdtContent>')
        ->and($footerXml)->toContain('<w:pict>'); // VML picture element
    
    $zip->close();
    unlink($tempFile);
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }
});

test('handles first page header with different content', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add default header
    $header = $section->addHeader();
    $headerText = $header->addText('Default Header');
    
    // Add first page header
    $firstHeader = $section->addHeader('first');
    $firstHeaderText = $firstHeader->addText('First Page Header');
    
    // Register Content Controls
    $cc->addContentControl($headerText, [
        'alias' => 'DefaultHeader',
        'tag' => 'default-header',
    ]);
    
    $cc->addContentControl($firstHeaderText, [
        'alias' => 'FirstHeader',
        'tag' => 'first-header',
    ]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_first_header_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Collect all header XML content
    $allHeaders = '';
    for ($i = 1; $i <= 3; $i++) {
        $headerXml = $zip->getFromName("word/header{$i}.xml");
        if ($headerXml !== false) {
            $allHeaders .= $headerXml;
        }
    }
    
    // Verify both Content Controls are present
    expect($allHeaders)->toContain('<w:alias w:val="DefaultHeader"/>')
        ->and($allHeaders)->toContain('<w:alias w:val="FirstHeader"/>')
        ->and($allHeaders)->toContain('Default Header')
        ->and($allHeaders)->toContain('First Page Header');
    
    $zip->close();
    unlink($tempFile);
});

test('handles even page footer with different content', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add default footer
    $footer = $section->addFooter();
    $footerText = $footer->addText('Odd Page Footer');
    
    // Add even page footer
    $evenFooter = $section->addFooter('even');
    $evenFooterText = $evenFooter->addText('Even Page Footer');
    
    // Register Content Controls
    $cc->addContentControl($footerText, [
        'alias' => 'OddFooter',
        'tag' => 'odd-footer',
    ]);
    
    $cc->addContentControl($evenFooterText, [
        'alias' => 'EvenFooter',
        'tag' => 'even-footer',
    ]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_even_footer_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    // Collect all footer XML content
    $allFooters = '';
    for ($i = 1; $i <= 3; $i++) {
        $footerXml = $zip->getFromName("word/footer{$i}.xml");
        if ($footerXml !== false) {
            $allFooters .= $footerXml;
        }
    }
    
    // Verify both Content Controls are present
    expect($allFooters)->toContain('<w:alias w:val="OddFooter"/>')
        ->and($allFooters)->toContain('<w:alias w:val="EvenFooter"/>')
        ->and($allFooters)->toContain('Odd Page Footer')
        ->and($allFooters)->toContain('Even Page Footer');
    
    $zip->close();
    unlink($tempFile);
});

test('wraps TextRun with formatting in Header', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Create TextRun with multiple formatted parts in header
    $header = $section->addHeader();
    $textRun = $header->addTextRun();
    $textRun->addText('Bold Text', ['bold' => true]);
    $textRun->addText(' Normal Text ');
    $textRun->addText('Italic Text', ['italic' => true]);
    
    // Register Content Control
    $cc->addContentControl($textRun, [
        'alias' => 'FormattedHeader',
        'tag' => 'formatted-header',
        'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
    ]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_textrun_header_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    $headerXml = $zip->getFromName('word/header1.xml');
    expect($headerXml)->not->toBeFalse();
    
    // Verify SDT wraps TextRun paragraph with all formatting
    expect($headerXml)->toContain('<w:sdt>')
        ->and($headerXml)->toContain('<w:alias w:val="FormattedHeader"/>')
        ->and($headerXml)->toContain('<w:tag w:val="formatted-header"/>')
        ->and($headerXml)->toContain('<w:sdtContent>')
        ->and($headerXml)->toContain('Bold Text')
        ->and($headerXml)->toContain('Normal Text')
        ->and($headerXml)->toContain('Italic Text')
        ->and($headerXml)->toContain('<w:b '); // Bold formatting (w:b w:val="1")
    
    $zip->close();
    unlink($tempFile);
});

test('processes mixed element types in same header', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add multiple different elements to same header
    $header = $section->addHeader();
    
    $text1 = $header->addText('Header Title');
    $textRun = $header->addTextRun();
    $textRun->addText('Subtitle ', ['italic' => true]);
    
    $table = $header->addTable();
    $table->addRow(200);
    $table->addCell(1000)->addText('Cell 1');
    $table->addCell(1000)->addText('Cell 2');
    
    // Register Content Controls for all elements
    $cc->addContentControl($text1, ['alias' => 'HeaderTitle', 'tag' => 'ht']);
    $cc->addContentControl($textRun, ['alias' => 'HeaderSubtitle', 'tag' => 'hs']);
    $cc->addContentControl($table, ['alias' => 'HeaderTable', 'tag' => 'htbl']);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_mixed_header_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    $headerXml = $zip->getFromName('word/header1.xml');
    expect($headerXml)->not->toBeFalse();
    
    // Verify all SDTs are present
    expect($headerXml)->toContain('<w:alias w:val="HeaderTitle"/>')
        ->and($headerXml)->toContain('<w:alias w:val="HeaderSubtitle"/>')
        ->and($headerXml)->toContain('<w:alias w:val="HeaderTable"/>')
        ->and($headerXml)->toContain('Header Title')
        ->and($headerXml)->toContain('Subtitle')
        ->and($headerXml)->toContain('Cell 1')
        ->and($headerXml)->toContain('Cell 2');
    
    // Count SDT tags
    $sdtCount = substr_count($headerXml, '<w:sdt>');
    expect($sdtCount)->toBe(3);
    
    $zip->close();
    unlink($tempFile);
});

test('validates OOXML structure after SDT injection in complex headers', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Create complex header with table
    $header = $section->addHeader();
    $table = $header->addTable();
    $table->addRow(300);
    $cell1 = $table->addCell(3000);
    $cell1->addText('Nested Content');
    $cell2 = $table->addCell(3000);
    $cell2->addText('More Content');
    
    // Register Content Control for table
    $cc->addContentControl($table, [
        'alias' => 'ComplexTable',
        'tag' => 'complex-table',
    ]);
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_ooxml_validation_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and validate XML structure
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    $headerXml = $zip->getFromName('word/header1.xml');
    expect($headerXml)->not->toBeFalse();
    
    // Load as DOM to validate structure
    $dom = new DOMDocument();
    expect($headerXml)->toBeString();
    $loaded = @$dom->loadXML((string) $headerXml);
    expect($loaded)->toBeTrue('Header XML must be valid');
    
    // Verify root element is w:hdr
    expect($dom->documentElement->localName)->toBe('hdr');
    expect($dom->documentElement->namespaceURI)->toBe('http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    // Verify SDT structure conforms to OOXML spec
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    // Every w:sdt must have w:sdtPr and w:sdtContent
    $sdts = $xpath->query('//w:sdt');
    expect($sdts->length)->toBeGreaterThan(0);
    
    foreach ($sdts as $sdt) {
        $sdtPr = $xpath->query('w:sdtPr', $sdt);
        $sdtContent = $xpath->query('w:sdtContent', $sdt);
        
        expect($sdtPr->length)->toBe(1, 'Every SDT must have exactly one sdtPr');
        expect($sdtContent->length)->toBe(1, 'Every SDT must have exactly one sdtContent');
    }
    
    $zip->close();
    unlink($tempFile);
});

test('does not wrap elements when no Content Control registered', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add header and footer WITHOUT registering Content Controls
    $header = $section->addHeader();
    $header->addText('Plain Header');
    
    $footer = $section->addFooter();
    $footer->addText('Plain Footer');
    
    // Generate DOCX
    $tempFile = tempnam(sys_get_temp_dir(), 'test_no_cc_') . '.docx';
    $cc->save($tempFile);
    
    // Extract and verify NO SDTs
    $zip = new ZipArchive();
    $zip->open($tempFile);
    
    $headerXml = $zip->getFromName('word/header1.xml');
    $footerXml = $zip->getFromName('word/footer1.xml');
    
    expect($headerXml)->not->toBeFalse();
    expect($footerXml)->not->toBeFalse();
    
    // Verify NO SDT tags
    expect($headerXml)->not->toContain('<w:sdt>')
        ->and($headerXml)->toContain('Plain Header');
    
    expect($footerXml)->not->toContain('<w:sdt>')
        ->and($footerXml)->toContain('Plain Footer');
    
    $zip->close();
    unlink($tempFile);
});
