<?php

declare(strict_types=1);

use MkGrow\ContentControl\SDTConfig;
use MkGrow\ContentControl\ContentControl;

describe('SDTConfig - Constructor', function () {
    test('creates instance with valid ID', function () {
        $config = new SDTConfig(id: '12345678');
        
        expect($config->id)->toBe('12345678');
        expect($config->alias)->toBe('');
        expect($config->tag)->toBe('');
        expect($config->type)->toBe(ContentControl::TYPE_RICH_TEXT);
        expect($config->lockType)->toBe(ContentControl::LOCK_NONE);
    });

    test('creates instance with all parameters', function () {
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

    test('accepts empty ID (will be filled by Registry)', function () {
        $config = new SDTConfig(id: '');
        
        expect($config->id)->toBe('');
    });
});

describe('SDTConfig - ID Validation', function () {
    test('rejects ID with less than 8 digits', function () {
        expect(fn() => new SDTConfig(id: '123'))
            ->toThrow(InvalidArgumentException::class, 'Must be 8 digits');
    });

    test('rejects ID with more than 8 digits', function () {
        expect(fn() => new SDTConfig(id: '123456789'))
            ->toThrow(InvalidArgumentException::class, 'Must be 8 digits');
    });

    test('rejects ID with non-numeric characters', function () {
        expect(fn() => new SDTConfig(id: '1234567a'))
            ->toThrow(InvalidArgumentException::class, 'Must be 8 digits');
    });

    test('rejects ID below minimum range', function () {
        expect(fn() => new SDTConfig(id: '09999999'))
            ->toThrow(InvalidArgumentException::class, 'Must be between 10000000 and 99999999');
    });

    test('accepts ID at lower limit', function () {
        $config = new SDTConfig(id: '10000000');
        expect($config->id)->toBe('10000000');
    });

    test('accepts ID at upper limit', function () {
        $config = new SDTConfig(id: '99999999');
        expect($config->id)->toBe('99999999');
    });
});

describe('SDTConfig - Alias Validation', function () {
    test('accepts empty alias', function () {
        $config = new SDTConfig(id: '12345678', alias: '');
        expect($config->alias)->toBe('');
    });

    test('accepts alias with 255 characters', function () {
        $alias = str_repeat('a', 255);
        $config = new SDTConfig(id: '12345678', alias: $alias);
        expect($config->alias)->toBe($alias);
    });

    test('rejects alias with 256 characters', function () {
        $alias = str_repeat('a', 256);
        expect(fn() => new SDTConfig(id: '12345678', alias: $alias))
            ->toThrow(InvalidArgumentException::class, 'must not exceed 255 characters');
    });

    test('rejects alias with control characters', function () {
        expect(fn() => new SDTConfig(id: '12345678', alias: "Test\x00Control"))
            ->toThrow(InvalidArgumentException::class, 'must not contain control characters');
    });

    test('rejects alias with reserved XML characters', function () {
        expect(fn() => new SDTConfig(id: '12345678', alias: 'Test<Control'))
            ->toThrow(InvalidArgumentException::class, 'XML reserved characters');
        
        expect(fn() => new SDTConfig(id: '12345678', alias: 'Test>Control'))
            ->toThrow(InvalidArgumentException::class, 'XML reserved characters');
        
        expect(fn() => new SDTConfig(id: '12345678', alias: 'Test&Control'))
            ->toThrow(InvalidArgumentException::class, 'XML reserved characters');
    });

    test('accepts alias with UTF-8', function () {
        $config = new SDTConfig(id: '12345678', alias: 'Contrôle çñá');
        expect($config->alias)->toBe('Contrôle çñá');
    });
});

describe('SDTConfig - Tag Validation', function () {
    test('accepts empty tag', function () {
        $config = new SDTConfig(id: '12345678', tag: '');
        expect($config->tag)->toBe('');
    });

    test('accepts valid tag', function () {
        $config = new SDTConfig(id: '12345678', tag: 'customer_name');
        expect($config->tag)->toBe('customer_name');
    });

    test('accepts tag with hyphens and dots', function () {
        $config = new SDTConfig(id: '12345678', tag: 'customer-name.v1');
        expect($config->tag)->toBe('customer-name.v1');
    });

    test('rejects tag that does not start with letter or underscore', function () {
        expect(fn() => new SDTConfig(id: '12345678', tag: '1invalid'))
            ->toThrow(InvalidArgumentException::class, 'must start with a letter or underscore');
        
        expect(fn() => new SDTConfig(id: '12345678', tag: '-invalid'))
            ->toThrow(InvalidArgumentException::class, 'must start with a letter or underscore');
    });

    test('rejects tag with invalid characters', function () {
        expect(fn() => new SDTConfig(id: '12345678', tag: 'invalid tag'))
            ->toThrow(InvalidArgumentException::class, 'must start with a letter or underscore');
        
        expect(fn() => new SDTConfig(id: '12345678', tag: 'invalid@tag'))
            ->toThrow(InvalidArgumentException::class, 'must start with a letter or underscore');
    });

    test('rejects tag with more than 255 characters', function () {
        $tag = 'a' . str_repeat('b', 255);
        expect(fn() => new SDTConfig(id: '12345678', tag: $tag))
            ->toThrow(InvalidArgumentException::class, 'must not exceed 255 characters');
    });
});

describe('SDTConfig - Type Validation', function () {
    test('accepts TYPE_RICH_TEXT', function () {
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_RICH_TEXT);
        expect($config->type)->toBe(ContentControl::TYPE_RICH_TEXT);
    });

    test('accepts TYPE_PLAIN_TEXT', function () {
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_PLAIN_TEXT);
        expect($config->type)->toBe(ContentControl::TYPE_PLAIN_TEXT);
    });

    test('accepts TYPE_GROUP', function () {
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_GROUP);
        expect($config->type)->toBe(ContentControl::TYPE_GROUP);
    });

    test('accepts TYPE_PICTURE', function () {
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_PICTURE);
        expect($config->type)->toBe(ContentControl::TYPE_PICTURE);
    });

    test('rejects invalid type', function () {
        expect(fn() => new SDTConfig(id: '12345678', type: 'invalidType'))
            ->toThrow(InvalidArgumentException::class, 'Invalid type');
    });
});

describe('SDTConfig - LockType Validation', function () {
    test('accepts LOCK_NONE', function () {
        $config = new SDTConfig(id: '12345678', lockType: ContentControl::LOCK_NONE);
        expect($config->lockType)->toBe(ContentControl::LOCK_NONE);
    });

    test('accepts LOCK_SDT_LOCKED', function () {
        $config = new SDTConfig(id: '12345678', lockType: ContentControl::LOCK_SDT_LOCKED);
        expect($config->lockType)->toBe(ContentControl::LOCK_SDT_LOCKED);
    });

    test('accepts LOCK_CONTENT_LOCKED', function () {
        $config = new SDTConfig(id: '12345678', lockType: ContentControl::LOCK_CONTENT_LOCKED);
        expect($config->lockType)->toBe(ContentControl::LOCK_CONTENT_LOCKED);
    });

    test('accepts LOCK_UNLOCKED', function () {
        $config = new SDTConfig(id: '12345678', lockType: ContentControl::LOCK_UNLOCKED);
        expect($config->lockType)->toBe(ContentControl::LOCK_UNLOCKED);
    });

    test('rejects invalid lockType', function () {
        expect(fn() => new SDTConfig(id: '12345678', lockType: 'invalidLock'))
            ->toThrow(InvalidArgumentException::class, 'Invalid lock type');
    });
});

describe('SDTConfig - fromArray factory method', function () {
    test('creates with defaults when array is empty', function () {
        $config = SDTConfig::fromArray([]);
        
        expect($config->id)->toBe('');
        expect($config->alias)->toBe('');
        expect($config->tag)->toBe('');
        expect($config->type)->toBe(ContentControl::TYPE_RICH_TEXT);
        expect($config->lockType)->toBe(ContentControl::LOCK_NONE);
    });

    test('creates with all values provided', function () {
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

    test('uses defaults for omitted values', function () {
        $config = SDTConfig::fromArray(['id' => '12345678']);
        
        expect($config->id)->toBe('12345678');
        expect($config->type)->toBe(ContentControl::TYPE_RICH_TEXT);
        expect($config->lockType)->toBe(ContentControl::LOCK_NONE);
    });
});

describe('SDTConfig - with* methods (immutability)', function () {
    test('withId returns new instance', function () {
        $original = new SDTConfig(id: '12345678', alias: 'Test');
        $modified = $original->withId('87654321');
        
        expect($original->id)->toBe('12345678');
        expect($modified->id)->toBe('87654321');
        expect($modified->alias)->toBe('Test');
        expect($original)->not->toBe($modified);
    });

    test('withAlias returns new instance', function () {
        $original = new SDTConfig(id: '12345678', alias: 'Original');
        $modified = $original->withAlias('Modified');
        
        expect($original->alias)->toBe('Original');
        expect($modified->alias)->toBe('Modified');
        expect($modified->id)->toBe('12345678');
        expect($original)->not->toBe($modified);
    });

    test('withTag returns new instance', function () {
        $original = new SDTConfig(id: '12345678', tag: 'original-tag');
        $modified = $original->withTag('modified-tag');
        
        expect($original->tag)->toBe('original-tag');
        expect($modified->tag)->toBe('modified-tag');
        expect($modified->id)->toBe('12345678');
        expect($original)->not->toBe($modified);
    });

    test('withId validates new ID', function () {
        $original = new SDTConfig(id: '12345678');
        
        expect(fn() => $original->withId('invalid'))
            ->toThrow(InvalidArgumentException::class, 'Must be 8 digits');
    });

    test('withAlias validates new alias', function () {
        $original = new SDTConfig(id: '12345678');
        
        expect(fn() => $original->withAlias(str_repeat('a', 256)))
            ->toThrow(InvalidArgumentException::class, 'must not exceed 255 characters');
    });

    test('withTag validates new tag', function () {
        $original = new SDTConfig(id: '12345678');
        
        expect(fn() => $original->withTag('invalid tag'))
            ->toThrow(InvalidArgumentException::class, 'must start with a letter or underscore');
    });
});

describe('SDTConfig - Readonly properties', function () {
    test('properties are immutable via reflection', function () {
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
