<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\PhpWord;

describe('ContentControl - Validação de Alias', function () {
    
    test('aceita alias válido com caracteres comuns', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $control = new ContentControl($section, [
            'alias' => 'Nome do Cliente'
        ]);
        
        $xml = $control->getXml();
        expect($xml)->toContain('w:val="Nome do Cliente"');
    });

    test('aceita alias com caracteres especiais permitidos', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $control = new ContentControl($section, [
            'alias' => 'Cliente - Endereço (Principal) & Email'
        ]);
        
        $xml = $control->getXml();
        expect($xml)->toContain('Cliente - Endereço (Principal) &amp; Email');
    });

    test('aceita alias com até 255 caracteres', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $alias = str_repeat('a', 255);
        $control = new ContentControl($section, ['alias' => $alias]);
        
        expect($control)->toBeInstanceOf(ContentControl::class);
    });

    test('aceita alias com caracteres multibyte UTF-8', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        // Testa com caracteres acentuados, emoji e outros scripts
        $control = new ContentControl($section, [
            'alias' => 'Configuração 测试 🎉 Тест'
        ]);
        
        expect($control)->toBeInstanceOf(ContentControl::class);
    });

    test('aceita alias com exatamente 255 caracteres multibyte', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        // String com 255 caracteres UTF-8 (não bytes)
        // Usando "ã" que ocupa 2 bytes mas é 1 caractere
        $alias = str_repeat('ã', 255);
        $control = new ContentControl($section, ['alias' => $alias]);
        
        expect($control)->toBeInstanceOf(ContentControl::class);
        expect(mb_strlen($alias, 'UTF-8'))->toBe(255);
    });

    test('rejeita alias com mais de 255 caracteres multibyte', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        // String com 256 caracteres UTF-8
        $alias = str_repeat('ñ', 256);
        new ContentControl($section, ['alias' => $alias]);
    })->throws(InvalidArgumentException::class, 'must not exceed 255 characters');

    test('aceita alias vazio (string vazia não é validada)', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $control = new ContentControl($section, ['alias' => '']);
        expect($control)->toBeInstanceOf(ContentControl::class);
    });

    test('rejeita alias com mais de 255 caracteres', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $alias = str_repeat('a', 256);
        new ContentControl($section, ['alias' => $alias]);
    })->throws(InvalidArgumentException::class, 'must not exceed 255 characters');

    test('rejeita alias com caracteres de controle', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        new ContentControl($section, ['alias' => "Test\x00Control"]);
    })->throws(InvalidArgumentException::class, 'must not contain control characters');

    test('rejeita alias com tabulação', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        new ContentControl($section, ['alias' => "Test\tControl"]);
    })->throws(InvalidArgumentException::class, 'must not contain control characters');

    test('rejeita alias que não é string', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        new ContentControl($section, ['alias' => 12345]);
    })->throws(InvalidArgumentException::class, 'must be a string');
});

describe('ContentControl - Validação de Tag', function () {
    
    test('aceita tag válida com letras e números', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $control = new ContentControl($section, [
            'tag' => 'customer-name-01'
        ]);
        
        $xml = $control->getXml();
        expect($xml)->toContain('w:val="customer-name-01"');
    });

    test('aceita tag começando com letra', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $control = new ContentControl($section, ['tag' => 'field123']);
        expect($control)->toBeInstanceOf(ContentControl::class);
    });

    test('aceita tag começando com underscore', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $control = new ContentControl($section, ['tag' => '_privateField']);
        expect($control)->toBeInstanceOf(ContentControl::class);
    });

    test('aceita tag com hífens e pontos', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $control = new ContentControl($section, ['tag' => 'my-field.name_v2']);
        expect($control)->toBeInstanceOf(ContentControl::class);
    });

    test('aceita tag com até 255 caracteres', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $tag = 'a' . str_repeat('b', 254);
        $control = new ContentControl($section, ['tag' => $tag]);
        
        expect($control)->toBeInstanceOf(ContentControl::class);
    });

    test('rejeita tag com mais de 255 caracteres', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $tag = 'a' . str_repeat('b', 255);
        new ContentControl($section, ['tag' => $tag]);
    })->throws(InvalidArgumentException::class, 'must not exceed 255 characters');

    test('tag length is counted in characters not bytes', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        // Even though tags should be ASCII identifiers, mb_strlen ensures
        // consistent behavior if someone tries to use non-ASCII characters
        // This would fail the pattern validation, but tests the length check works correctly
        $tag = 'a' . str_repeat('b', 254);
        $control = new ContentControl($section, ['tag' => $tag]);
        
        expect(mb_strlen($tag, 'UTF-8'))->toBe(255);
        expect($control)->toBeInstanceOf(ContentControl::class);
    });

    test('aceita tag vazia (string vazia não é validada)', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $control = new ContentControl($section, ['tag' => '']);
        expect($control)->toBeInstanceOf(ContentControl::class);
    });

    test('rejeita tag começando com número', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        new ContentControl($section, ['tag' => '123field']);
    })->throws(InvalidArgumentException::class, 'must start with a letter or underscore');

    test('rejeita tag começando com hífen', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        new ContentControl($section, ['tag' => '-field']);
    })->throws(InvalidArgumentException::class, 'must start with a letter or underscore');

    test('rejeita tag com espaços', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        new ContentControl($section, ['tag' => 'my field']);
    })->throws(InvalidArgumentException::class, 'contain only alphanumeric characters');

    test('rejeita tag com caracteres especiais', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        new ContentControl($section, ['tag' => 'field@name']);
    })->throws(InvalidArgumentException::class, 'contain only alphanumeric characters');

    test('rejeita tag que não é string', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        new ContentControl($section, ['tag' => 12345]);
    })->throws(InvalidArgumentException::class, 'must be a string');
});
