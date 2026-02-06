<?php

declare(strict_types=1);

use MkGrow\ContentControl\IDValidator;

describe('IDValidator', function () {
    describe('validate()', function () {
        test('accepts empty string', function () {
            expect(fn() => IDValidator::validate(''))->not->toThrow(\InvalidArgumentException::class);
        });

        test('accepts valid 8-digit ID', function () {
            expect(fn() => IDValidator::validate('10000000'))->not->toThrow(\InvalidArgumentException::class);
            expect(fn() => IDValidator::validate('12345678'))->not->toThrow(\InvalidArgumentException::class);
            expect(fn() => IDValidator::validate('99999999'))->not->toThrow(\InvalidArgumentException::class);
        });

        test('rejects ID with less than 8 digits', function () {
            expect(fn() => IDValidator::validate('123'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
            
            expect(fn() => IDValidator::validate('1234567'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
        });

        test('rejects ID with more than 8 digits', function () {
            expect(fn() => IDValidator::validate('123456789'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
        });

        test('rejects ID with letters', function () {
            expect(fn() => IDValidator::validate('ABC12345'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
            
            expect(fn() => IDValidator::validate('1234567A'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
        });

        test('rejects ID below minimum range', function () {
            expect(fn() => IDValidator::validate('09999999'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID range');
        });

        test('rejects ID with special characters', function () {
            expect(fn() => IDValidator::validate('1234-5678'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
            
            expect(fn() => IDValidator::validate('12345 678'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
        });

        test('accepts ID at minimum limit', function () {
            expect(fn() => IDValidator::validate('10000000'))->not->toThrow(\InvalidArgumentException::class);
        });

        test('accepts ID at maximum limit', function () {
            expect(fn() => IDValidator::validate('99999999'))->not->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('generateRandom()', function () {
        test('generates 8-digit ID', function () {
            $id = IDValidator::generateRandom();
            
            expect($id)->toBeString();
            expect(strlen($id))->toBe(8);
            expect($id)->toMatch('/^\d{8}$/');
        });

        test('generates ID in valid range', function () {
            $id = IDValidator::generateRandom();
            $idInt = (int) $id;
            
            expect($idInt)->toBeGreaterThanOrEqual(10000000);
            expect($idInt)->toBeLessThanOrEqual(99999999);
        });

        test('generates different IDs in multiple calls', function () {
            $ids = [];
            
            for ($i = 0; $i < 100; $i++) {
                $id = IDValidator::generateRandom();
                $ids[$id] = true;
            }
            
            // Should have generated at least 90 unique IDs (allowing for small collision chance)
            expect(count($ids))->toBeGreaterThan(90);
        });

        test('generated IDs pass validation', function () {
            for ($i = 0; $i < 10; $i++) {
                $id = IDValidator::generateRandom();
                expect(fn() => IDValidator::validate($id))->not->toThrow(\InvalidArgumentException::class);
            }
        });
    });

    describe('getMinId()', function () {
        test('returns correct minimum ID', function () {
            expect(IDValidator::getMinId())->toBe(10000000);
        });
    });

    describe('getMaxId()', function () {
        test('returns correct maximum ID', function () {
            expect(IDValidator::getMaxId())->toBe(99999999);
        });
    });

    describe('range integration', function () {
        test('total range is 90 million IDs', function () {
            $range = IDValidator::getMaxId() - IDValidator::getMinId() + 1;
            expect($range)->toBe(90000000);
        });
    });
});
