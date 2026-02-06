<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ContentControlException;
use PhpOffice\PhpWord\Element\Table;

describe('TableBuilder::setStyles()', function () {
    it('successfully sets table styles before table creation', function () {
        $builder = new TableBuilder();
        
        $result = $builder->setStyles([
            'borderSize' => 6,
            'borderColor' => '000000',
            'alignment' => 'center'
        ]);
        
        expect($result)->toBe($builder);
    });
    
    it('returns self for method chaining', function () {
        $builder = new TableBuilder();
        
        $result = $builder->setStyles(['borderSize' => 6]);
        
        expect($result)
            ->toBeInstanceOf(TableBuilder::class)
            ->toBe($builder);
    });
    
    it('allows chaining setStyles with addRow', function () {
        $builder = new TableBuilder();
        
        $builder->setStyles(['borderSize' => 6])
            ->addRow()
                ->addCell(3000)->addText('Test')->end()
                ->end();
        
        expect($builder)->toBeInstanceOf(TableBuilder::class);
    });
    
    it('applies styles to table when addRow is called', function () {
        $builder = new TableBuilder();
        
        $builder->setStyles([
            'borderSize' => 6,
            'borderColor' => '000000'
        ])
        ->addRow()
            ->addCell(3000)->addText('Content')->end()
            ->end();
        
        // Extract table and verify it was created
        $cc = $builder->getContentControl();
        expect($cc)->toBeInstanceOf(ContentControl::class);
        
        // Save and verify the document contains a table
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        $cc->save($tempFile);
        
        expect($tempFile)->toBeFile();
        
        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)
            ->toContain('<w:tbl>')
            ->toContain('<w:tblPr>');
        
        unlink($tempFile);
    });
    
    it('throws exception when called after table creation', function () {
        $builder = new TableBuilder();
        
        // Create table by calling addRow
        $builder->addRow()
            ->addCell(3000)->addText('First row')->end()
            ->end();
        
        // Attempt to set styles after table creation
        $builder->setStyles(['borderSize' => 6]);
    })->throws(
        ContentControlException::class,
        'Cannot call setStyles() after table creation'
    );
    
    it('accepts empty style array', function () {
        $builder = new TableBuilder();
        
        $result = $builder->setStyles([]);
        
        expect($result)->toBe($builder);
    });
    
    it('works with various style properties', function () {
        $builder = new TableBuilder();
        
        $builder->setStyles([
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 100,
            'alignment' => 'center',
            'width' => 100,
            'unit' => 'pct',
            'layout' => 'fixed'
        ])
        ->addRow()
            ->addCell(5000)->addText('Full-width table')->end()
            ->end();
        
        expect($builder->getContentControl())->toBeInstanceOf(ContentControl::class);
    });
    
    it('integrates with multiple rows', function () {
        $builder = new TableBuilder();
        
        $builder->setStyles(['borderSize' => 6, 'borderColor' => '000000'])
            ->addRow()
                ->addCell(3000)->addText('Row 1 Cell 1')->end()
                ->addCell(3000)->addText('Row 1 Cell 2')->end()
                ->end()
            ->addRow()
                ->addCell(3000)->addText('Row 2 Cell 1')->end()
                ->addCell(3000)->addText('Row 2 Cell 2')->end()
                ->end();
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        $builder->getContentControl()->save($tempFile);
        
        expect($tempFile)->toBeFile();
        
        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // Verify table structure with multiple rows
        expect($xml)
            ->toContain('<w:tbl>')
            ->toContain('<w:tr>');
        
        unlink($tempFile);
    });
    
    it('works with addContentControl for table SDT', function () {
        $builder = new TableBuilder();
        
        $builder->setStyles(['borderSize' => 6])
            ->addContentControl(['tag' => 'test-table', 'alias' => 'Test Table'])
            ->addRow()
                ->addCell(3000)->addText('Content')->end()
                ->end();
        
        expect($builder->getContentControl())->toBeInstanceOf(ContentControl::class);
    });
});
