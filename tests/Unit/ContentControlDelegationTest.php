<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Metadata\DocInfo;
use PhpOffice\PhpWord\Metadata\Settings;
use PhpOffice\PhpWord\PhpWord;

/**
 * Testes de métodos de delegação do ContentControl para PhpWord
 * 
 * Cobertura de linhas: 134, 144, 156, 168, 181, 194, 204, 262, 272
 */

test('getDocInfo retorna instância DocInfo do PhpWord', function () {
    $cc = new ContentControl();
    
    $docInfo = $cc->getDocInfo();
    
    expect($docInfo)->toBeInstanceOf(DocInfo::class);
});

test('getSettings retorna instância Settings do PhpWord', function () {
    $cc = new ContentControl();
    
    $settings = $cc->getSettings();
    
    expect($settings)->toBeInstanceOf(Settings::class);
});

test('addFontStyle adiciona estilo de fonte ao PhpWord', function () {
    $cc = new ContentControl();
    
    // addFontStyle retorna void, apenas testar que não lança exceção
    $cc->addFontStyle('CustomFont', [
        'size' => 14,
        'bold' => true,
        'color' => 'FF0000'
    ]);
    
    // Verificar que método foi chamado sem erros
    expect(true)->toBeTrue();
});

test('addParagraphStyle adiciona estilo de parágrafo ao PhpWord', function () {
    $cc = new ContentControl();
    
    // addParagraphStyle retorna void, apenas testar que não lança exceção
    $cc->addParagraphStyle('CustomParagraph', [
        'alignment' => 'center',
        'spaceAfter' => 200
    ]);
    
    // Verificar que método foi chamado sem erros
    expect(true)->toBeTrue();
});

test('addTableStyle adiciona estilo de tabela ao PhpWord', function () {
    $cc = new ContentControl();
    
    // addTableStyle retorna void, apenas testar que não lança exceção
    $cc->addTableStyle('CustomTable', [
        'borderSize' => 6,
        'borderColor' => '999999',
        'cellMargin' => 80
    ]);
    
    // Verificar que método foi chamado sem erros
    expect(true)->toBeTrue();
});

test('addTableStyle aceita estilo de primeira linha opcional', function () {
    $cc = new ContentControl();
    
    // addTableStyle retorna void, apenas testar que não lança exceção
    $cc->addTableStyle('TableWithHeader', [
        'borderSize' => 6,
        'borderColor' => '999999'
    ], [
        'bgColor' => 'CCCCCC',
        'bold' => true
    ]);
    
    // Verificar que método foi chamado sem erros
    expect(true)->toBeTrue();
});

test('addTitleStyle adiciona estilo de título ao PhpWord', function () {
    $cc = new ContentControl();
    
    // addTitleStyle retorna void, apenas testar que não lança exceção
    $cc->addTitleStyle(1, [
        'size' => 20,
        'bold' => true
    ], [
        'spaceAfter' => 240
    ]);
    
    // Verificar que método foi chamado sem erros
    expect(true)->toBeTrue();
});

test('addTitleStyle suporta múltiplos níveis de título', function () {
    $cc = new ContentControl();
    
    // Adicionar títulos de nível 1 a 3 - apenas testar que não lança exceção
    for ($level = 1; $level <= 3; $level++) {
        $cc->addTitleStyle($level, [
            'size' => 20 - ($level * 2),
            'bold' => true
        ]);
    }
    
    // Verificar que métodos foram chamados sem erros
    expect(true)->toBeTrue();
});

test('getSections retorna array vazio quando nenhuma seção foi adicionada', function () {
    $cc = new ContentControl();
    
    $sections = $cc->getSections();
    
    expect($sections)->toBeArray();
    expect($sections)->toHaveCount(0);
});

test('getSections retorna array de seções adicionadas', function () {
    $cc = new ContentControl();
    
    $section1 = $cc->addSection();
    $section2 = $cc->addSection();
    $section3 = $cc->addSection();
    
    $sections = $cc->getSections();
    
    expect($sections)->toBeArray();
    expect($sections)->toHaveCount(3);
    expect($sections[0])->toBeInstanceOf(Section::class);
    expect($sections[1])->toBeInstanceOf(Section::class);
    expect($sections[2])->toBeInstanceOf(Section::class);
    expect($sections[0])->toBe($section1);
    expect($sections[1])->toBe($section2);
    expect($sections[2])->toBe($section3);
});

test('getPhpWord retorna instância PhpWord encapsulada', function () {
    $cc = new ContentControl();
    
    $phpWord = $cc->getPhpWord();
    
    expect($phpWord)->toBeInstanceOf(PhpWord::class);
});

test('getPhpWord retorna a mesma instância em múltiplas chamadas', function () {
    $cc = new ContentControl();
    
    $phpWord1 = $cc->getPhpWord();
    $phpWord2 = $cc->getPhpWord();
    
    expect($phpWord1)->toBe($phpWord2);
});

test('getPhpWord permite acesso avançado a recursos PhpWord', function () {
    $cc = new ContentControl();
    $cc->addSection()->addText('Sample content');
    
    $phpWord = $cc->getPhpWord();
    
    // Verificar que PhpWord tem conteúdo adicionado
    $sections = $phpWord->getSections();
    expect($sections)->toHaveCount(1);
});

test('getSDTRegistry retorna instância SDTRegistry', function () {
    $cc = new ContentControl();
    
    $registry = $cc->getSDTRegistry();
    
    expect($registry)->toBeInstanceOf(\MkGrow\ContentControl\SDTRegistry::class);
});

test('getSDTRegistry retorna a mesma instância em múltiplas chamadas', function () {
    $cc = new ContentControl();
    
    $registry1 = $cc->getSDTRegistry();
    $registry2 = $cc->getSDTRegistry();
    
    expect($registry1)->toBe($registry2);
});

test('getSDTRegistry permite acesso a Content Controls registrados', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Test');
    
    $cc->addContentControl($section, [
        'alias' => 'Test Control',
        'tag' => 'test'
    ]);
    
    $registry = $cc->getSDTRegistry();
    
    expect($registry->count())->toBe(1);
    expect($registry->has($section))->toBeTrue();
});
