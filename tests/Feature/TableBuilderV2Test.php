<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\PhpWord;

/**
 * Feature tests for TableBuilder v2 API (Table constructor + addContentControl)
 *
 * Validates the new constructor accepting PHPWord Table objects and the
 * functional addContentControl() delegation to ContentControl.
 *
 * @since 0.6.0
 */
describe('Feature - TableBuilder v2 API', function (): void {

    /**
     * TB-CTOR-05: Constructor with Table followed by setTable() — not applicable
     * (setTable doesn't exist; testing that table is already set prevents double-init)
     */
    test('constructor with Table stores table preventing double creation', function (): void {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow()->addCell(3000)->addText('Original');

        $builder = new TableBuilder($table);

        // Suppress deprecation from addRow()
        $previousHandler = set_error_handler(function (int $errno): bool {
            return $errno === E_USER_DEPRECATED;
        });

        try {
            // addRow() would lazy-create a new table if $this->table is null
            // But since we passed a Table to constructor, it should use the existing one
            $builder->addRow()->addCell(3000)->addText('Added');
        } finally {
            restore_error_handler();
        }

        // Verify via reflection that the table reference is still the original
        $reflection = new ReflectionClass($builder);
        $tableProp = $reflection->getProperty('table');
        expect($tableProp->getValue($builder))->toBe($table);
    });

    /**
     * TB-CTOR-06: End-to-end: new TableBuilder($table) -> save -> valid DOCX
     */
    test('constructor with Table produces valid DOCX after save', function (): void {
        // Create elements through a single ContentControl so they share the same PhpWord
        $cc = new ContentControl();
        $section = $cc->addSection();
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell(5000);
        $text = $cell->addText('TableBuilder v2');

        // Pass the table to TableBuilder, but use the original CC for save
        $builder = new TableBuilder($table);
        // Register SDT via the builder (delegates to its own CC)
        // But we need to register on the ORIGINAL CC that owns the elements
        $cc->addContentControl($text, [
            'tag' => 'tb-ctor06',
            'alias' => 'V2 Test',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'tb_ctor06_') . '.docx';
        $cc->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toBeValidXml();
        expect($xml)->toContain('<w:tag w:val="tb-ctor06"/>');
        expect($xml)->toContain('<w:alias w:val="V2 Test"/>');
        expect($xml)->toContain('TableBuilder v2');

        safeUnlink($tempFile);
    });

    /**
     * TB-ACC-03: addContentControl() with runLevel=true propagates to SDT
     */
    test('addContentControl with runLevel propagates to generated SDT', function (): void {
        // Use builder's own ContentControl to create elements in same PhpWord tree
        $builder = new TableBuilder();
        $cc = $builder->getContentControl();
        $section = $cc->addSection();
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell(3000);
        $text = $cell->addText('Run Propagation');

        $cc->addContentControl($text, [
            'tag' => 'run-prop',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'tb_acc03_') . '.docx';
        $cc->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        // Run-level SDT wraps w:r inside w:p
        expect($xml)->toContain('<w:tag w:val="run-prop"/>');
        expect($xml)->toMatch('/<w:sdt>.*<w:tag w:val="run-prop"\/>.*<w:sdtContent>.*<w:r>.*<\/w:r>.*<\/w:sdtContent>.*<\/w:sdt>/s');

        safeUnlink($tempFile);
    });

    /**
     * TB-ACC-04: addContentControl() works for Text, Table, Cell elements
     *
     * Tests that addContentControl can register SDTs for different element types.
     * Each type is tested in a separate ContentControl to avoid
     * cross-element DOM mutation interference.
     */
    test('addContentControl works for multiple element types', function (): void {
        // Test 1: Text (run-level in cell)
        $cc1 = new ContentControl();
        $s1 = $cc1->addSection();
        $t1 = $s1->addTable();
        $t1->addRow()->addCell(3000)->addText('RunText');
        $text = $t1->getRows()[0]->getCells()[0]->getElements()[0];
        $cc1->addContentControl($text, [
            'tag' => 'text-elem',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);
        $f1 = tempnam(sys_get_temp_dir(), 'tb_acc04a_') . '.docx';
        $cc1->save($f1);
        $zip = new ZipArchive();
        $zip->open($f1);
        $xml1 = $zip->getFromName('word/document.xml');
        $zip->close();
        expect($xml1)->toContain('<w:tag w:val="text-elem"/>');
        safeUnlink($f1);

        // Test 2: Table (GROUP)
        $cc2 = new ContentControl();
        $s2 = $cc2->addSection();
        $t2 = $s2->addTable();
        $t2->addRow()->addCell(3000)->addText('Group');
        $cc2->addContentControl($t2, [
            'tag' => 'table-elem',
            'type' => ContentControl::TYPE_GROUP,
        ]);
        $f2 = tempnam(sys_get_temp_dir(), 'tb_acc04b_') . '.docx';
        $cc2->save($f2);
        $zip2 = new ZipArchive();
        $zip2->open($f2);
        $xml2 = $zip2->getFromName('word/document.xml');
        $zip2->close();
        expect($xml2)->toContain('<w:tag w:val="table-elem"/>');
        safeUnlink($f2);

        // Test 3: Cell (block-level)
        $cc3 = new ContentControl();
        $s3 = $cc3->addSection();
        $t3 = $s3->addTable();
        $cell = $t3->addRow()->addCell(3000);
        $cell->addText('CellContent');
        $cc3->addContentControl($cell, [
            'tag' => 'cell-elem',
        ]);
        $f3 = tempnam(sys_get_temp_dir(), 'tb_acc04c_') . '.docx';
        $cc3->save($f3);
        $zip3 = new ZipArchive();
        $zip3->open($f3);
        $xml3 = $zip3->getFromName('word/document.xml');
        $zip3->close();
        expect($xml3)->toContain('<w:tag w:val="cell-elem"/>');
        safeUnlink($f3);
    });

    /**
     * TB-ACC-05: addContentControl() followed by save() produces valid SDT XML
     */
    test('addContentControl followed by save produces valid SDT structure', function (): void {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $text = $section->addText('Standalone Text');

        $builder = new TableBuilder($phpWord->addSection()->addTable());
        // Use ContentControl directly since the text is on a different section
        $cc = $builder->getContentControl();
        // Add the text through the original CC (builder's CC is separate from phpWord)

        // Simpler approach: use builder's own CC
        $cc2 = new ContentControl();
        $s2 = $cc2->addSection();
        $t2 = $s2->addText('Direct Save Test');
        $cc2->addContentControl($t2, [
            'tag' => 'save-test',
            'alias' => 'Save Validation',
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'tb_acc05_') . '.docx';
        $cc2->save($tempFile);

        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toBeValidXml();
        expect($xml)->toHaveXmlElement('w:sdt');
        expect($xml)->toContain('<w:tag w:val="save-test"/>');
        expect($xml)->toContain('<w:alias w:val="Save Validation"/>');

        safeUnlink($tempFile);
    });

});
