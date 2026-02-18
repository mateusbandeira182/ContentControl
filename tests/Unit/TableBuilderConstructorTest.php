<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\PhpWord;

describe('TableBuilder Constructor and addContentControl (v0.6.0)', function (): void {

    it('TB-CTOR-01: Constructor with Table instance stores table and creates ContentControl', function (): void {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $table->addRow()->addCell(2000)->addText('Data');

        $builder = new TableBuilder($table);

        // Should have a ContentControl (not null)
        expect($builder->getContentControl())->toBeInstanceOf(ContentControl::class);

        // The table should be stored (accessible via addRow which would fail if table already set)
        // We verify by checking that it doesn't create a new table when addRow is called
        // (it reuses the existing one)
        $reflection = new ReflectionClass($builder);
        $tableProp = $reflection->getProperty('table');
        expect($tableProp->getValue($builder))->toBe($table);
    });

    it('TB-CTOR-02: Constructor with ContentControl preserves exact instance', function (): void {
        $cc = new ContentControl();
        $builder = new TableBuilder($cc);

        expect($builder->getContentControl())->toBe($cc);
    });

    it('TB-CTOR-03: Constructor with null creates default ContentControl', function (): void {
        $builder = new TableBuilder(null);

        expect($builder->getContentControl())->toBeInstanceOf(ContentControl::class);
    });

    it('TB-CTOR-04: Constructor with Table allows immediate addContentControl()', function (): void {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell(3000);
        $text = $cell->addText('John Doe');

        $builder = new TableBuilder($table);

        // addContentControl should work without throwing
        $result = $builder->addContentControl($text, [
            'tag' => 'first-name',
            'alias' => 'First Name',
        ]);

        expect($result)->toBeInstanceOf(TableBuilder::class);
    });

    it('TB-ACC-01: addContentControl() returns self for chaining', function (): void {
        $builder = new TableBuilder();
        $section = $builder->getContentControl()->addSection();
        $text = $section->addText('Chainable');

        $result = $builder->addContentControl($text, ['tag' => 'chain-test']);

        expect($result)->toBe($builder);
    });

    it('TB-ACC-02: addContentControl() with runLevel=true config accepted', function (): void {
        $builder = new TableBuilder();
        $section = $builder->getContentControl()->addSection();
        $text = $section->addText('Run Level');

        // Should not throw
        $result = $builder->addContentControl($text, [
            'tag' => 'run-test',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);

        expect($result)->toBeInstanceOf(TableBuilder::class);
    });

});
