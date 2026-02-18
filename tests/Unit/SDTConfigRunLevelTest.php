<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\SDTConfig;

describe('SDTConfig runLevel property', function (): void {

    it('CFG-RL-01: runLevel property defaults to false in constructor', function (): void {
        $config = new SDTConfig(id: '12345678');

        expect($config->runLevel)->toBeFalse();
    });

    it('CFG-RL-02: fromArray with runLevel=true creates config with runLevel true', function (): void {
        $config = SDTConfig::fromArray([
            'id' => '12345678',
            'runLevel' => true,
        ]);

        expect($config->runLevel)->toBeTrue();
    });

    it('CFG-RL-03: withRunLevel(true) returns new instance, original unchanged', function (): void {
        $original = SDTConfig::fromArray(['id' => '12345678']);
        $modified = $original->withRunLevel(true);

        expect($original->runLevel)->toBeFalse();
        expect($modified->runLevel)->toBeTrue();
        expect($modified)->not->toBe($original);
    });

    it('CFG-RL-04: withId() propagates runLevel', function (): void {
        $config = SDTConfig::fromArray([
            'id' => '12345678',
            'runLevel' => true,
        ]);

        $newConfig = $config->withId('87654321');

        expect($newConfig->runLevel)->toBeTrue();
        expect($newConfig->id)->toBe('87654321');
    });

    it('CFG-RL-05: withAlias() propagates runLevel', function (): void {
        $config = SDTConfig::fromArray([
            'id' => '12345678',
            'runLevel' => true,
        ]);

        $newConfig = $config->withAlias('Test Alias');

        expect($newConfig->runLevel)->toBeTrue();
        expect($newConfig->alias)->toBe('Test Alias');
    });

    it('CFG-RL-06: withTag() propagates runLevel', function (): void {
        $config = SDTConfig::fromArray([
            'id' => '12345678',
            'runLevel' => true,
        ]);

        $newConfig = $config->withTag('test-tag');

        expect($newConfig->runLevel)->toBeTrue();
        expect($newConfig->tag)->toBe('test-tag');
    });

    it('CFG-RL-07: withInlineLevel() propagates runLevel', function (): void {
        $config = SDTConfig::fromArray([
            'id' => '12345678',
            'runLevel' => true,
        ]);

        $newConfig = $config->withInlineLevel(true);

        expect($newConfig->runLevel)->toBeTrue();
        expect($newConfig->inlineLevel)->toBeTrue();
    });

    it('CFG-RL-08: runLevel=true and inlineLevel=true coexist without exception', function (): void {
        $config = SDTConfig::fromArray([
            'id' => '12345678',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);

        expect($config->runLevel)->toBeTrue();
        expect($config->inlineLevel)->toBeTrue();
    });

    it('CFG-RL-09: fromArray with no runLevel key defaults to false', function (): void {
        $config = SDTConfig::fromArray([
            'id' => '12345678',
            'alias' => 'Test',
            'tag' => 'test-tag',
        ]);

        expect($config->runLevel)->toBeFalse();
    });

});
