<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

/**
 * Test suite for ContentControl inline-level
 * 
 * NOTE: Auto-detection DISABLED due to PhpWord limitation
 * ('container' property not available in AbstractElement).
 * 
 * Users must explicitly specify 'inlineLevel' => true.
 */
describe('ContentControl - Explicit inlineLevel Parameter', function () {
    test('addContentControl accepts explicit inlineLevel => true', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell(2000);
        $text = $cell->addText('Test');
        
        // EXPLICIT: user specifies inlineLevel
        $cc->addContentControl($text, [
            'alias' => 'TestCell',
            'inlineLevel' => true  // EXPLICIT
        ]);
        
        // Verify that SDTRegistry received correct config
        $registry = $cc->getSDTRegistry();
        $configs = $registry->getAllConfigs();
        
        expect($configs)->toHaveCount(1);
        expect($configs[0]->inlineLevel)->toBeTrue();
    });
    
    test('addContentControl defaults to inlineLevel => false when not specified', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $text = $section->addText('Test');
        
        // WITHOUT specifying inlineLevel
        $cc->addContentControl($text, [
            'alias' => 'TestParagraph'
        ]);
        
        $registry = $cc->getSDTRegistry();
        $configs = $registry->getAllConfigs();
        
        expect($configs)->toHaveCount(1);
        expect($configs[0]->inlineLevel)->toBeFalse();  // Default
    });
    
    test('addContentControl accepts explicit inlineLevel => false', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $text = $section->addText('Test');
        
        $cc->addContentControl($text, [
            'alias' => 'TestParagraph',
            'inlineLevel' => false  // EXPLICIT false
        ]);
        
        $registry = $cc->getSDTRegistry();
        $configs = $registry->getAllConfigs();
        
        expect($configs)->toHaveCount(1);
        expect($configs[0]->inlineLevel)->toBeFalse();
    });
});
