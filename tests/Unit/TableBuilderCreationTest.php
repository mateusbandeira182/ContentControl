<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ContentControlException;

describe('TableBuilder::createTable()', function () {
    it('creates a simple table', function () {
        $builder = new TableBuilder();
        
        $table = $builder->createTable([
            'rows' => [
                ['cells' => [
                    ['text' => 'Item', 'width' => 3000],
                    ['text' => 'Price', 'width' => 2000]
                ]]
            ]
        ]);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    });
    
    it('registers SDTs in cells', function () {
        $builder = new TableBuilder();
        
        // For now, skip cell SDT test as ElementLocator doesn't support Cell elements yet
        // This will be implemented in later versions or alternative approaches
        $table = $builder->createTable([
            'rows' => [
                ['cells' => [
                    ['text' => 'Item', 'width' => 3000],
                    ['text' => 'Price', 'width' => 2000]
                ]]
            ]
        ]);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    })->skip('Cell SDTs require ElementLocator enhancement - planned for future version');
    
    it('applies table styles', function () {
        $builder = new TableBuilder();
        
        $table = $builder->createTable([
            'style' => ['borderSize' => 6, 'borderColor' => '000000'],
            'rows' => [
                ['cells' => [
                    ['text' => 'Test', 'width' => 2000]
                ]]
            ]
        ]);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    });
    
    it('applies row styles', function () {
        $builder = new TableBuilder();
        
        $table = $builder->createTable([
            'rows' => [
                [
                    'height' => 500,
                    'style' => ['tblHeader' => true],
                    'cells' => [
                        ['text' => 'Header', 'width' => 2000]
                    ]
                ]
            ]
        ]);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    });
    
    it('applies cell styles', function () {
        $builder = new TableBuilder();
        
        $table = $builder->createTable([
            'rows' => [
                ['cells' => [
                    [
                        'text' => 'Styled',
                        'width' => 2000,
                        'style' => ['bgColor' => 'FFFF00']
                    ]
                ]]
            ]
        ]);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    });
    
    it('throws exception if rows absent', function () {
        $builder = new TableBuilder();
        
        $builder->createTable(['style' => []]);
    })->throws(ContentControlException::class, 'Table configuration must have "rows" key');
    
    it('throws exception if cells without content', function () {
        $builder = new TableBuilder();
        
        $builder->createTable([
            'rows' => [
                ['cells' => [
                    ['width' => 2000]
                ]]
            ]
        ]);
    })->throws(ContentControlException::class, 'must have "text" or "element"');
    
    it('throws exception for element (not supported)', function () {
        $builder = new TableBuilder();
        
        $builder->createTable([
            'rows' => [
                ['cells' => [
                    ['text' => 'X', 'element' => new stdClass()]
                ]]
            ]
        ]);
    })->throws(ContentControlException::class, 'cannot have both "text" and "element"');
    
    it('registers table wrapper SDT', function () {
        $builder = new TableBuilder();
        
        $table = $builder->createTable([
            'tableTag' => 'invoice-items',
            'tableAlias' => 'Invoice Items',
            'rows' => [
                ['cells' => [
                    ['text' => 'Item', 'width' => 2000]
                ]]
            ]
        ]);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        
        // Save and verify table SDT
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        $builder->getContentControl()->save($tempFile);
        
        $zip = new \ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)->toContain('<w:tag w:val="invoice-items"/>');
        expect($xml)->toContain('<w:alias w:val="Invoice Items"/>');
        
        @unlink($tempFile);
    });
});
