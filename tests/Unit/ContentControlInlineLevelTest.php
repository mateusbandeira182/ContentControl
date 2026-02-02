<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

/**
 * Test suite for ContentControl inline-level (v3.1)
 * 
 * NOTA v3.1: Auto-detecção DESABILITADA devido à limitação do PHPWord
 * (propriedade 'container' não disponível em AbstractElement).
 * 
 * Usuários devem especificar explicitamente 'inlineLevel' => true.
 */
describe('ContentControl - Explicit inlineLevel Parameter', function () {
    test('addContentControl accepts explicit inlineLevel => true', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell(2000);
        $text = $cell->addText('Test');
        
        // EXPLÍCITO: usuário especifica inlineLevel
        $cc->addContentControl($text, [
            'alias' => 'TestCell',
            'inlineLevel' => true  // ← EXPLÍCITO
        ]);
        
        // Verificar que SDTRegistry recebeu config correta
        $registry = $cc->getSDTRegistry();
        $configs = $registry->getAllConfigs();
        
        expect($configs)->toHaveCount(1);
        expect($configs[0]->inlineLevel)->toBeTrue();
    });
    
    test('addContentControl defaults to inlineLevel => false when not specified', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $text = $section->addText('Test');
        
        // SEM especificar inlineLevel
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
            'inlineLevel' => false  // ← EXPLÍCITO false
        ]);
        
        $registry = $cc->getSDTRegistry();
        $configs = $registry->getAllConfigs();
        
        expect($configs)->toHaveCount(1);
        expect($configs[0]->inlineLevel)->toBeFalse();
    });
});
