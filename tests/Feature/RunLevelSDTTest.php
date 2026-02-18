<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

/**
 * Feature tests for run-level Content Controls (CT_SdtContentRun)
 *
 * SDTInjector wraps individual <w:r> elements inside <w:p> with <w:sdt>.
 * Requires runLevel=true in SDTConfig.
 *
 * @since 0.6.0
 */
describe('Feature - Run-Level SDTs', function (): void {

    /**
     * INJ-RL-05: Multiple runs in same paragraph independently wrapped
     */
    test('wraps multiple runs in same paragraph independently', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();

        $textRun = $section->addTextRun();
        $text1 = $textRun->addText('John');
        $text2 = $textRun->addText('Doe');

        $cc->addContentControl($text1, [
            'tag' => 'first-name',
            'alias' => 'First Name',
            'runLevel' => true,
        ]);

        $cc->addContentControl($text2, [
            'tag' => 'last-name',
            'alias' => 'Last Name',
            'runLevel' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'run_rl05_') . '.docx';
        $cc->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('<w:tag w:val="first-name"/>');
        expect($xml)->toContain('<w:tag w:val="last-name"/>');
        expect($xml)->toContain('<w:alias w:val="First Name"/>');
        expect($xml)->toContain('<w:alias w:val="Last Name"/>');

        safeUnlink($tempFile);
    });

    /**
     * INJ-RL-06: Run-level SDT XML has no namespace pollution (xmlns:w)
     */
    test('run-level SDT has no namespace pollution', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();

        $text = $section->addText('Clean NS');

        $cc->addContentControl($text, [
            'tag' => 'clean-run',
            'runLevel' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'run_rl06_') . '.docx';
        $cc->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        // Verify SDT exists
        expect($xml)->toContain('<w:tag w:val="clean-run"/>');

        // Count xmlns:w declarations - should only be on root element
        $matches = [];
        preg_match_all('/xmlns:w=/', $xml, $matches);
        expect($matches[0])->toHaveCount(1);

        safeUnlink($tempFile);
    });

    /**
     * INJ-RL-10: End-to-end: TextRun with 2 tagged runs -> valid OOXML
     */
    test('TextRun with 2 tagged runs produces valid OOXML', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();

        $textRun = $section->addTextRun();
        $name = $textRun->addText('Alice');
        $space = $textRun->addText(' ');
        $surname = $textRun->addText('Smith');

        $cc->addContentControl($name, [
            'tag' => 'given-name',
            'alias' => 'Given Name',
            'runLevel' => true,
        ]);

        $cc->addContentControl($surname, [
            'tag' => 'surname',
            'alias' => 'Surname',
            'runLevel' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'run_rl10_') . '.docx';
        $cc->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        // Valid XML
        expect($xml)->toBeValidXml();

        // Both SDTs present
        expect($xml)->toContain('<w:tag w:val="given-name"/>');
        expect($xml)->toContain('<w:tag w:val="surname"/>');

        // Space text not wrapped in SDT
        expect($xml)->toContain(' ');

        safeUnlink($tempFile);
    });

    /**
     * PIPE-01: Config with runLevel=true propagates through pipeline
     */
    test('runLevel=true propagates through save pipeline', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();

        $text = $section->addText('Pipeline Test');

        $cc->addContentControl($text, [
            'tag' => 'pipe-01',
            'runLevel' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'run_pipe01_') . '.docx';
        $cc->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        // SDT wrapping a run must appear
        expect($xml)->toContain('<w:tag w:val="pipe-01"/>');
        // The SDT should wrap w:r, not w:p
        expect($xml)->toMatch('/<w:sdt>.*<w:tag w:val="pipe-01"\/>.*<w:sdtContent>.*<w:r>.*<\/w:r>.*<\/w:sdtContent>.*<\/w:sdt>/s');

        safeUnlink($tempFile);
    });

    /**
     * PIPE-02: Config with runLevel=false does not alter existing behavior
     */
    test('runLevel=false preserves block-level wrapping', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();

        $text = $section->addText('Block Level');

        $cc->addContentControl($text, [
            'tag' => 'pipe-02',
            'runLevel' => false,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'run_pipe02_') . '.docx';
        $cc->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        // SDT should wrap w:p (block-level), not individual w:r
        expect($xml)->toContain('<w:tag w:val="pipe-02"/>');
        expect($xml)->toMatch('/<w:sdt>.*<w:tag w:val="pipe-02"\/>.*<w:sdtContent>.*<w:p[ >].*<\/w:sdtContent>.*<\/w:sdt>/s');

        safeUnlink($tempFile);
    });

    /**
     * PIPE-03: Build table with run-level SDTs -> inject -> valid XML
     */
    test('table with run-level SDTs produces valid XML', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();

        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell(3000);
        $text = $cell->addText('Cell Value');

        $cc->addContentControl($text, [
            'tag' => 'pipe-03',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'run_pipe03_') . '.docx';
        $cc->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toBeValidXml();
        expect($xml)->toContain('<w:tag w:val="pipe-03"/>');

        safeUnlink($tempFile);
    });

    /**
     * PROC-01: Build table with run-level SDTs -> direct ContentControl -> verify
     */
    test('direct ContentControl API with run-level SDT on cell text', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();

        $table = $section->addTable();
        $row = $table->addRow();
        $cell1 = $row->addCell(3000);
        $name = $cell1->addText('Jane');
        $cell2 = $row->addCell(3000);
        $price = $cell2->addText('$42.00');

        $cc->addContentControl($name, [
            'tag' => 'proc-name',
            'alias' => 'Name',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);

        $cc->addContentControl($price, [
            'tag' => 'proc-price',
            'alias' => 'Price',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'run_proc01_') . '.docx';
        $cc->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('<w:tag w:val="proc-name"/>');
        expect($xml)->toContain('<w:tag w:val="proc-price"/>');
        expect($xml)->toContain('<w:alias w:val="Name"/>');
        expect($xml)->toContain('<w:alias w:val="Price"/>');

        safeUnlink($tempFile);
    });

    /**
     * PROC-02: Extracted table XML contains <w:sdt> at run level
     */
    test('generated XML contains run-level SDTs inside table cells', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();

        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell(5000);
        $text = $cell->addText('Run-Level Content');

        $cc->addContentControl($text, [
            'tag' => 'proc-02-run',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'run_proc02_') . '.docx';
        $cc->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        // The SDT should be inside w:tc > w:p, wrapping the w:r
        expect($xml)->toMatch('/<w:tc>.*<w:p[^>]*>.*<w:sdt>.*<w:tag w:val="proc-02-run"\/>.*<w:sdtContent>.*<w:r>.*<\/w:r>.*<\/w:sdtContent>.*<\/w:sdt>.*<\/w:p>.*<\/w:tc>/s');

        safeUnlink($tempFile);
    });

});
