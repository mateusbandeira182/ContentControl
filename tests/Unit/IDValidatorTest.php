<?php

declare(strict_types=1);

use MkGrow\ContentControl\IDValidator;

describe('IDValidator', function () {
    describe('validate()', function () {
        test('aceita string vazia', function () {
            expect(fn() => IDValidator::validate(''))->not->toThrow(\InvalidArgumentException::class);
        });

        test('aceita ID válido de 8 dígitos', function () {
            expect(fn() => IDValidator::validate('10000000'))->not->toThrow(\InvalidArgumentException::class);
            expect(fn() => IDValidator::validate('12345678'))->not->toThrow(\InvalidArgumentException::class);
            expect(fn() => IDValidator::validate('99999999'))->not->toThrow(\InvalidArgumentException::class);
        });

        test('rejeita ID com menos de 8 dígitos', function () {
            expect(fn() => IDValidator::validate('123'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
            
            expect(fn() => IDValidator::validate('1234567'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
        });

        test('rejeita ID com mais de 8 dígitos', function () {
            expect(fn() => IDValidator::validate('123456789'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
        });

        test('rejeita ID com letras', function () {
            expect(fn() => IDValidator::validate('ABC12345'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
            
            expect(fn() => IDValidator::validate('1234567A'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
        });

        test('rejeita ID abaixo do range mínimo', function () {
            expect(fn() => IDValidator::validate('09999999'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID range');
        });

        test('rejeita ID com caracteres especiais', function () {
            expect(fn() => IDValidator::validate('1234-5678'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
            
            expect(fn() => IDValidator::validate('12345 678'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid ID format');
        });

        test('aceita ID no limite mínimo', function () {
            expect(fn() => IDValidator::validate('10000000'))->not->toThrow(\InvalidArgumentException::class);
        });

        test('aceita ID no limite máximo', function () {
            expect(fn() => IDValidator::validate('99999999'))->not->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('generateRandom()', function () {
        test('gera ID de 8 dígitos', function () {
            $id = IDValidator::generateRandom();
            
            expect($id)->toBeString();
            expect(strlen($id))->toBe(8);
            expect($id)->toMatch('/^\d{8}$/');
        });

        test('gera ID no range válido', function () {
            $id = IDValidator::generateRandom();
            $idInt = (int) $id;
            
            expect($idInt)->toBeGreaterThanOrEqual(10000000);
            expect($idInt)->toBeLessThanOrEqual(99999999);
        });

        test('gera IDs diferentes em múltiplas chamadas', function () {
            $ids = [];
            
            for ($i = 0; $i < 100; $i++) {
                $id = IDValidator::generateRandom();
                $ids[$id] = true;
            }
            
            // Deve ter gerado pelo menos 90 IDs únicos (permitindo pequena colisão)
            expect(count($ids))->toBeGreaterThan(90);
        });

        test('IDs gerados passam na validação', function () {
            for ($i = 0; $i < 10; $i++) {
                $id = IDValidator::generateRandom();
                expect(fn() => IDValidator::validate($id))->not->toThrow(\InvalidArgumentException::class);
            }
        });
    });

    describe('getMinId()', function () {
        test('retorna ID mínimo correto', function () {
            expect(IDValidator::getMinId())->toBe(10000000);
        });
    });

    describe('getMaxId()', function () {
        test('retorna ID máximo correto', function () {
            expect(IDValidator::getMaxId())->toBe(99999999);
        });
    });

    describe('integração com range', function () {
        test('range total é de 90 milhões de IDs', function () {
            $range = IDValidator::getMaxId() - IDValidator::getMinId() + 1;
            expect($range)->toBe(90000000);
        });
    });
});
