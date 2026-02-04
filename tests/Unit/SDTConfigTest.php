<?php

declare(strict_types=1);

use MkGrow\ContentControl\SDTConfig;
use MkGrow\ContentControl\ContentControl;

describe('SDTConfig - Construtor', function () {
    test('cria instância com ID válido', function () {
        $config = new SDTConfig(id: '12345678');
        
        expect($config->id)->toBe('12345678');
        expect($config->alias)->toBe('');
        expect($config->tag)->toBe('');
        expect($config->type)->toBe(ContentControl::TYPE_RICH_TEXT);
        expect($config->lockType)->toBe(ContentControl::LOCK_NONE);
    });

    test('cria instância com todos parâmetros', function () {
        $config = new SDTConfig(
            id: '87654321',
            alias: 'Test Control',
            tag: 'test-tag',
            type: ContentControl::TYPE_PLAIN_TEXT,
            lockType: ContentControl::LOCK_SDT_LOCKED
        );
        
        expect($config->id)->toBe('87654321');
        expect($config->alias)->toBe('Test Control');
        expect($config->tag)->toBe('test-tag');
        expect($config->type)->toBe(ContentControl::TYPE_PLAIN_TEXT);
        expect($config->lockType)->toBe(ContentControl::LOCK_SDT_LOCKED);
    });

    test('aceita ID vazio (será preenchido pelo Registry)', function () {
        $config = new SDTConfig(id: '');
        
        expect($config->id)->toBe('');
    });
});

describe('SDTConfig - Validação de ID', function () {
    test('rejeita ID com menos de 8 dígitos', function () {
        expect(fn() => new SDTConfig(id: '123'))
            ->toThrow(InvalidArgumentException::class, 'Must be 8 digits');
    });

    test('rejeita ID com mais de 8 dígitos', function () {
        expect(fn() => new SDTConfig(id: '123456789'))
            ->toThrow(InvalidArgumentException::class, 'Must be 8 digits');
    });

    test('rejeita ID com caracteres não numéricos', function () {
        expect(fn() => new SDTConfig(id: '1234567a'))
            ->toThrow(InvalidArgumentException::class, 'Must be 8 digits');
    });

    test('rejeita ID abaixo do range mínimo', function () {
        expect(fn() => new SDTConfig(id: '09999999'))
            ->toThrow(InvalidArgumentException::class, 'Must be between 10000000 and 99999999');
    });

    test('aceita ID no limite inferior', function () {
        $config = new SDTConfig(id: '10000000');
        expect($config->id)->toBe('10000000');
    });

    test('aceita ID no limite superior', function () {
        $config = new SDTConfig(id: '99999999');
        expect($config->id)->toBe('99999999');
    });
});

describe('SDTConfig - Validação de Alias', function () {
    test('aceita alias vazio', function () {
        $config = new SDTConfig(id: '12345678', alias: '');
        expect($config->alias)->toBe('');
    });

    test('aceita alias com 255 caracteres', function () {
        $alias = str_repeat('a', 255);
        $config = new SDTConfig(id: '12345678', alias: $alias);
        expect($config->alias)->toBe($alias);
    });

    test('rejeita alias com 256 caracteres', function () {
        $alias = str_repeat('a', 256);
        expect(fn() => new SDTConfig(id: '12345678', alias: $alias))
            ->toThrow(InvalidArgumentException::class, 'must not exceed 255 characters');
    });

    test('rejeita alias com caracteres de controle', function () {
        expect(fn() => new SDTConfig(id: '12345678', alias: "Test\x00Control"))
            ->toThrow(InvalidArgumentException::class, 'must not contain control characters');
    });

    test('rejeita alias com caracteres XML reservados', function () {
        expect(fn() => new SDTConfig(id: '12345678', alias: 'Test<Control'))
            ->toThrow(InvalidArgumentException::class, 'XML reserved characters');
        
        expect(fn() => new SDTConfig(id: '12345678', alias: 'Test>Control'))
            ->toThrow(InvalidArgumentException::class, 'XML reserved characters');
        
        expect(fn() => new SDTConfig(id: '12345678', alias: 'Test&Control'))
            ->toThrow(InvalidArgumentException::class, 'XML reserved characters');
    });

    test('aceita alias com UTF-8', function () {
        $config = new SDTConfig(id: '12345678', alias: 'Contrôle çñá');
        expect($config->alias)->toBe('Contrôle çñá');
    });
});

describe('SDTConfig - Validação de Tag', function () {
    test('aceita tag vazia', function () {
        $config = new SDTConfig(id: '12345678', tag: '');
        expect($config->tag)->toBe('');
    });

    test('aceita tag válida', function () {
        $config = new SDTConfig(id: '12345678', tag: 'customer_name');
        expect($config->tag)->toBe('customer_name');
    });

    test('aceita tag com hífens e pontos', function () {
        $config = new SDTConfig(id: '12345678', tag: 'customer-name.v1');
        expect($config->tag)->toBe('customer-name.v1');
    });

    test('rejeita tag que não começa com letra ou underscore', function () {
        expect(fn() => new SDTConfig(id: '12345678', tag: '1invalid'))
            ->toThrow(InvalidArgumentException::class, 'must start with a letter or underscore');
        
        expect(fn() => new SDTConfig(id: '12345678', tag: '-invalid'))
            ->toThrow(InvalidArgumentException::class, 'must start with a letter or underscore');
    });

    test('rejeita tag com caracteres inválidos', function () {
        expect(fn() => new SDTConfig(id: '12345678', tag: 'invalid tag'))
            ->toThrow(InvalidArgumentException::class, 'must start with a letter or underscore');
        
        expect(fn() => new SDTConfig(id: '12345678', tag: 'invalid@tag'))
            ->toThrow(InvalidArgumentException::class, 'must start with a letter or underscore');
    });

    test('rejeita tag com mais de 255 caracteres', function () {
        $tag = 'a' . str_repeat('b', 255);
        expect(fn() => new SDTConfig(id: '12345678', tag: $tag))
            ->toThrow(InvalidArgumentException::class, 'must not exceed 255 characters');
    });
});

describe('SDTConfig - Validação de Type', function () {
    test('aceita TYPE_RICH_TEXT', function () {
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_RICH_TEXT);
        expect($config->type)->toBe(ContentControl::TYPE_RICH_TEXT);
    });

    test('aceita TYPE_PLAIN_TEXT', function () {
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_PLAIN_TEXT);
        expect($config->type)->toBe(ContentControl::TYPE_PLAIN_TEXT);
    });

    test('aceita TYPE_GROUP', function () {
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_GROUP);
        expect($config->type)->toBe(ContentControl::TYPE_GROUP);
    });

    test('aceita TYPE_PICTURE', function () {
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_PICTURE);
        expect($config->type)->toBe(ContentControl::TYPE_PICTURE);
    });

    test('rejeita tipo inválido', function () {
        expect(fn() => new SDTConfig(id: '12345678', type: 'invalidType'))
            ->toThrow(InvalidArgumentException::class, 'Invalid type');
    });
});

describe('SDTConfig - Validação de LockType', function () {
    test('aceita LOCK_NONE', function () {
        $config = new SDTConfig(id: '12345678', lockType: ContentControl::LOCK_NONE);
        expect($config->lockType)->toBe(ContentControl::LOCK_NONE);
    });

    test('aceita LOCK_SDT_LOCKED', function () {
        $config = new SDTConfig(id: '12345678', lockType: ContentControl::LOCK_SDT_LOCKED);
        expect($config->lockType)->toBe(ContentControl::LOCK_SDT_LOCKED);
    });

    test('aceita LOCK_CONTENT_LOCKED', function () {
        $config = new SDTConfig(id: '12345678', lockType: ContentControl::LOCK_CONTENT_LOCKED);
        expect($config->lockType)->toBe(ContentControl::LOCK_CONTENT_LOCKED);
    });

    test('aceita LOCK_UNLOCKED', function () {
        $config = new SDTConfig(id: '12345678', lockType: ContentControl::LOCK_UNLOCKED);
        expect($config->lockType)->toBe(ContentControl::LOCK_UNLOCKED);
    });

    test('rejeita lockType inválido', function () {
        expect(fn() => new SDTConfig(id: '12345678', lockType: 'invalidLock'))
            ->toThrow(InvalidArgumentException::class, 'Invalid lock type');
    });
});

describe('SDTConfig - fromArray factory method', function () {
    test('cria com defaults quando array vazio', function () {
        $config = SDTConfig::fromArray([]);
        
        expect($config->id)->toBe('');
        expect($config->alias)->toBe('');
        expect($config->tag)->toBe('');
        expect($config->type)->toBe(ContentControl::TYPE_RICH_TEXT);
        expect($config->lockType)->toBe(ContentControl::LOCK_NONE);
    });

    test('cria com todos valores fornecidos', function () {
        $config = SDTConfig::fromArray([
            'id' => '12345678',
            'alias' => 'Test',
            'tag' => 'test-tag',
            'type' => ContentControl::TYPE_PLAIN_TEXT,
            'lockType' => ContentControl::LOCK_SDT_LOCKED
        ]);
        
        expect($config->id)->toBe('12345678');
        expect($config->alias)->toBe('Test');
        expect($config->tag)->toBe('test-tag');
        expect($config->type)->toBe(ContentControl::TYPE_PLAIN_TEXT);
        expect($config->lockType)->toBe(ContentControl::LOCK_SDT_LOCKED);
    });

    test('usa defaults para valores omitidos', function () {
        $config = SDTConfig::fromArray(['id' => '12345678']);
        
        expect($config->id)->toBe('12345678');
        expect($config->type)->toBe(ContentControl::TYPE_RICH_TEXT);
        expect($config->lockType)->toBe(ContentControl::LOCK_NONE);
    });
});

describe('SDTConfig - Métodos with* (imutabilidade)', function () {
    test('withId retorna nova instância', function () {
        $original = new SDTConfig(id: '12345678', alias: 'Test');
        $modified = $original->withId('87654321');
        
        expect($original->id)->toBe('12345678');
        expect($modified->id)->toBe('87654321');
        expect($modified->alias)->toBe('Test');
        expect($original)->not->toBe($modified);
    });

    test('withAlias retorna nova instância', function () {
        $original = new SDTConfig(id: '12345678', alias: 'Original');
        $modified = $original->withAlias('Modified');
        
        expect($original->alias)->toBe('Original');
        expect($modified->alias)->toBe('Modified');
        expect($modified->id)->toBe('12345678');
        expect($original)->not->toBe($modified);
    });

    test('withTag retorna nova instância', function () {
        $original = new SDTConfig(id: '12345678', tag: 'original-tag');
        $modified = $original->withTag('modified-tag');
        
        expect($original->tag)->toBe('original-tag');
        expect($modified->tag)->toBe('modified-tag');
        expect($modified->id)->toBe('12345678');
        expect($original)->not->toBe($modified);
    });

    test('withId valida novo ID', function () {
        $original = new SDTConfig(id: '12345678');
        
        expect(fn() => $original->withId('invalid'))
            ->toThrow(InvalidArgumentException::class, 'Must be 8 digits');
    });

    test('withAlias valida novo alias', function () {
        $original = new SDTConfig(id: '12345678');
        
        expect(fn() => $original->withAlias(str_repeat('a', 256)))
            ->toThrow(InvalidArgumentException::class, 'must not exceed 255 characters');
    });

    test('withTag valida nova tag', function () {
        $original = new SDTConfig(id: '12345678');
        
        expect(fn() => $original->withTag('invalid tag'))
            ->toThrow(InvalidArgumentException::class, 'must start with a letter or underscore');
    });
});

describe('SDTConfig - Propriedades readonly', function () {
    test('propriedades são imutáveis via reflexão', function () {
        $config = new SDTConfig(id: '12345678');
        
        $reflection = new ReflectionClass($config);
        $idProperty = $reflection->getProperty('id');
        
        expect($idProperty->isReadOnly())->toBeTrue();
    });
});

describe('SDTConfig - inlineLevel Property', function () {
    test('SDTConfig accepts inlineLevel flag', function () {
        $config = new SDTConfig(
            id: '12345678',
            inlineLevel: true
        );
        
        expect($config->inlineLevel)->toBeTrue();
    });

    test('SDTConfig defaults inlineLevel to false', function () {
        $config = new SDTConfig(id: '12345678');
        
        expect($config->inlineLevel)->toBeFalse();
    });

    test('SDTConfig fromArray accepts inlineLevel', function () {
        $config = SDTConfig::fromArray([
            'id' => '12345678',
            'inlineLevel' => true
        ]);
        
        expect($config->inlineLevel)->toBeTrue();
        
        // Test default when not provided
        $configDefault = SDTConfig::fromArray([
            'id' => '87654321'
        ]);
        
        expect($configDefault->inlineLevel)->toBeFalse();
    });

    test('withInlineLevel returns new instance with updated inlineLevel', function () {
        $original = new SDTConfig(
            id: '12345678',
            inlineLevel: false
        );
        
        $modified = $original->withInlineLevel(true);
        
        // Verify original is unchanged (immutability)
        expect($original->inlineLevel)->toBeFalse();
        
        // Verify new instance has updated value
        expect($modified->inlineLevel)->toBeTrue();
        
        // Verify other properties preserved
        expect($modified->id)->toBe('12345678');
        
        // Verify they are different instances
        expect($original)->not->toBe($modified);
    });
});
