<?php

use MkGrow\ContentControl\Assert;

/**
 * Helper to return values as mixed (avoids PHPStan narrowing)
 * 
 * @template T
 * @param T $value
 * @return mixed
 */
function mixedValue(mixed $value): mixed {
    return $value;
}

describe('Assert', function () {
    describe('notNull()', function () {
        it('does not throw when value is not null', function () {
            $value = mixedValue('test');
            
            Assert::notNull($value, 'Value should not be null');
            
            expect($value)->toBe('test');
        });

        it('throws LogicException when value is null', function () {
            $value = mixedValue(null);
            
            Assert::notNull($value, 'Custom error message');
        })->throws(\LogicException::class, 'Custom error message');

        it('works with objects', function () {
            $object = mixedValue(new stdClass());
            
            Assert::notNull($object, 'Object should not be null');
            
            expect($object)->toBeInstanceOf(stdClass::class);
        });

        it('works with arrays', function () {
            $array = mixedValue([1, 2, 3]);
            
            Assert::notNull($array, 'Array should not be null');
            
            expect($array)->toBe([1, 2, 3]);
        });

        it('works with zero', function () {
            $value = mixedValue(0);
            
            Assert::notNull($value, 'Zero should not be null');
            
            expect($value)->toBe(0);
        });

        it('works with empty string', function () {
            $value = mixedValue('');
            
            Assert::notNull($value, 'Empty string should not be null');
            
            expect($value)->toBe('');
        });

        it('works with false', function () {
            $value = mixedValue(false);
            
            Assert::notNull($value, 'False should not be null');
            
            expect($value)->toBe(false);
        });
    });
});
