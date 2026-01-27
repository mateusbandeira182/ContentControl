<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

describe('Properties - Constantes', function () {
    
    test('constantes TYPE_* possuem valores corretos', function () {
        expect(ContentControl::TYPE_GROUP)->toBe('group');
        expect(ContentControl::TYPE_PLAIN_TEXT)->toBe('plainText');
        expect(ContentControl::TYPE_RICH_TEXT)->toBe('richText');
        expect(ContentControl::TYPE_PICTURE)->toBe('picture');
    });

    test('constantes LOCK_* possuem valores corretos', function () {
        expect(ContentControl::LOCK_NONE)->toBe('');
        expect(ContentControl::LOCK_SDT_LOCKED)->toBe('sdtLocked');
        expect(ContentControl::LOCK_CONTENT_LOCKED)->toBe('sdtContentLocked');
        expect(ContentControl::LOCK_UNLOCKED)->toBe('unlocked');
    });
});

describe('Properties - Defaults', function () {
    
    test('type padrão é TYPE_RICH_TEXT', function () {
        $phpWord = new PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('<w:richText/>');
    });

    test('lockType padrão é LOCK_NONE (ausente)', function () {
        $phpWord = new PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section);
        
        $xml = $control->getXml();
        
        expect($xml)->not->toContain('<w:lock');
    });
});
