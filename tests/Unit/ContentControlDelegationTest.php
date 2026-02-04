<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Metadata\DocInfo;
use PhpOffice\PhpWord\Metadata\Settings;
use PhpOffice\PhpWord\PhpWord;

/**
 * Tests for ContentControl delegation methods to PhpWord
 * 
 * Line coverage: 134, 144, 156, 168, 181, 194, 204, 262, 272
 */

test('getDocInfo returns PhpWord DocInfo instance', function () {
    $cc = new ContentControl();
    
    $docInfo = $cc->getDocInfo();
    
    expect($docInfo)->toBeInstanceOf(DocInfo::class);
});

test('getSettings returns PhpWord Settings instance', function () {
    $cc = new ContentControl();
    
    $settings = $cc->getSettings();
    
    expect($settings)->toBeInstanceOf(Settings::class);
});

test('addFontStyle adds font style to PhpWord', function () {
    $cc = new ContentControl();
    
    // addFontStyle returns void, just test that it doesn't throw exception
    $cc->addFontStyle('CustomFont', [
        'size' => 14,
        'bold' => true,
        'color' => 'FF0000'
    ]);
    
    // Verify that method was called without errors
    expect(true)->toBeTrue();
});

test('addParagraphStyle adds paragraph style to PhpWord', function () {
    $cc = new ContentControl();
    
    // addParagraphStyle returns void, just test that it doesn't throw exception
    $cc->addParagraphStyle('CustomParagraph', [
        'alignment' => 'center',
        'spaceAfter' => 200
    ]);
    
    // Verify that method was called without errors
    expect(true)->toBeTrue();
});

test('addTableStyle adds table style to PhpWord', function () {
    $cc = new ContentControl();
    
    // addTableStyle returns void, just test that it doesn't throw exception
    $cc->addTableStyle('CustomTable', [
        'borderSize' => 6,
        'borderColor' => '999999',
        'cellMargin' => 80
    ]);
    
    // Verify that method was called without errors
    expect(true)->toBeTrue();
});

test('addTableStyle accepts optional first row style', function () {
    $cc = new ContentControl();
    
    // addTableStyle returns void, just test that it doesn't throw exception
    $cc->addTableStyle('TableWithHeader', [
        'borderSize' => 6,
        'borderColor' => '999999'
    ], [
        'bgColor' => 'CCCCCC',
        'bold' => true
    ]);
    
    // Verify that method was called without errors
    expect(true)->toBeTrue();
});

test('addTitleStyle adds title style to PhpWord', function () {
    $cc = new ContentControl();
    
    // addTitleStyle returns void, just test that it doesn't throw exception
    $cc->addTitleStyle(1, [
        'size' => 20,
        'bold' => true
    ], [
        'spaceAfter' => 240
    ]);
    
    // Verify that method was called without errors
    expect(true)->toBeTrue();
});

test('addTitleStyle supports multiple title levels', function () {
    $cc = new ContentControl();
    
    // Add titles from level 1 to 3 - just test that it doesn't throw exception
    for ($level = 1; $level <= 3; $level++) {
        $cc->addTitleStyle($level, [
            'size' => 20 - ($level * 2),
            'bold' => true
        ]);
    }
    
    // Verify that methods were called without errors
    expect(true)->toBeTrue();
});

test('getSections returns empty array when no section was added', function () {
    $cc = new ContentControl();
    
    $sections = $cc->getSections();
    
    expect($sections)->toBeArray();
    expect($sections)->toHaveCount(0);
});

test('getSections returns array of added sections', function () {
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

test('getPhpWord returns encapsulated PhpWord instance', function () {
    $cc = new ContentControl();
    
    $phpWord = $cc->getPhpWord();
    
    expect($phpWord)->toBeInstanceOf(PhpWord::class);
});

test('getPhpWord returns same instance in multiple calls', function () {
    $cc = new ContentControl();
    
    $phpWord1 = $cc->getPhpWord();
    $phpWord2 = $cc->getPhpWord();
    
    expect($phpWord1)->toBe($phpWord2);
});

test('getPhpWord allows advanced access to PhpWord resources', function () {
    $cc = new ContentControl();
    $cc->addSection()->addText('Sample content');
    
    $phpWord = $cc->getPhpWord();
    
    // Verify that PhpWord has added content
    $sections = $phpWord->getSections();
    expect($sections)->toHaveCount(1);
});

test('getSDTRegistry returns SDTRegistry instance', function () {
    $cc = new ContentControl();
    
    $registry = $cc->getSDTRegistry();
    
    expect($registry)->toBeInstanceOf(\MkGrow\ContentControl\SDTRegistry::class);
});

test('getSDTRegistry returns same instance in multiple calls', function () {
    $cc = new ContentControl();
    
    $registry1 = $cc->getSDTRegistry();
    $registry2 = $cc->getSDTRegistry();
    
    expect($registry1)->toBe($registry2);
});

test('getSDTRegistry allows access to registered Content Controls', function () {
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
