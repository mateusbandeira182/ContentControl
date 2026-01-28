<?php

declare(strict_types=1);

use MkGrow\ContentControl\SDTRegistry;
use MkGrow\ContentControl\SDTConfig;
use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\Element\Section;

describe('SDTRegistry - Geração de IDs', function () {
    test('gera ID único de 8 dígitos', function () {
        $registry = new SDTRegistry();
        $id = $registry->generateUniqueId();
        
        expect($id)->toMatch('/^\d{8}$/');
        expect((int) $id)->toBeGreaterThanOrEqual(10000000);
        expect((int) $id)->toBeLessThanOrEqual(99999999);
    });

    test('gera IDs diferentes em chamadas consecutivas', function () {
        $registry = new SDTRegistry();
        $id1 = $registry->generateUniqueId();
        $id2 = $registry->generateUniqueId();
        
        expect($id1)->not->toBe($id2);
    });

    test('gera 10.000 IDs sem duplicatas', function () {
        $registry = new SDTRegistry();
        $ids = [];
        
        for ($i = 0; $i < 10000; $i++) {
            $id = $registry->generateUniqueId();
            $ids[$id] = true;
        }
        
        // Se todos são únicos, count($ids) deve ser 10.000
        expect(count($ids))->toBe(10000);
    });

    test('marca ID gerado como usado', function () {
        $registry = new SDTRegistry();
        $id = $registry->generateUniqueId();
        
        expect($registry->isIdUsed($id))->toBeTrue();
    });
});

describe('SDTRegistry - Registro de elementos', function () {
    test('registra elemento com config', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        $config = new SDTConfig(id: '12345678');
        
        $registry->register($section, $config);
        
        expect($registry->count())->toBe(1);
        expect($registry->has($section))->toBeTrue();
    });

    test('registra múltiplos elementos', function () {
        $registry = new SDTRegistry();
        $section1 = createSection();
        $section2 = createSection();
        
        $registry->register($section1, new SDTConfig(id: '12345678'));
        $registry->register($section2, new SDTConfig(id: '87654321'));
        
        expect($registry->count())->toBe(2);
        expect($registry->has($section1))->toBeTrue();
        expect($registry->has($section2))->toBeTrue();
    });

    test('rejeita elemento duplicado', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        
        $registry->register($section, new SDTConfig(id: '12345678'));
        
        expect(fn() => $registry->register($section, new SDTConfig(id: '87654321')))
            ->toThrow(InvalidArgumentException::class, 'already registered');
    });

    test('rejeita ID duplicado', function () {
        $registry = new SDTRegistry();
        $section1 = createSection();
        $section2 = createSection();
        
        $registry->register($section1, new SDTConfig(id: '12345678'));
        
        expect(fn() => $registry->register($section2, new SDTConfig(id: '12345678')))
            ->toThrow(InvalidArgumentException::class, 'already in use');
    });

    test('aceita config com ID vazio', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        $config = new SDTConfig(id: '');
        
        $registry->register($section, $config);
        
        expect($registry->count())->toBe(1);
    });

    // Edge case: Multiple elements can have empty IDs because the duplicate check
    // explicitly skips empty IDs (see SDTRegistry::register line 115).
    // This allows elements without IDs to be registered multiple times.
    // When the document is saved, Word will auto-generate unique IDs if needed.
    test('múltiplos elementos com ID vazio não causam erro', function () {
        $registry = new SDTRegistry();
        $section1 = createSection();
        $section2 = createSection();
        
        $registry->register($section1, new SDTConfig(id: ''));
        $registry->register($section2, new SDTConfig(id: ''));
        
        expect($registry->count())->toBe(2);
    });
});

describe('SDTRegistry - getAll', function () {
    test('retorna array vazio quando não há registros', function () {
        $registry = new SDTRegistry();
        
        expect($registry->getAll())->toBe([]);
    });

    test('retorna tuplas corretas', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', alias: 'Test');
        
        $registry->register($section, $config);
        
        $all = $registry->getAll();
        
        expect($all)->toHaveCount(1);
        expect($all[0]['element'])->toBe($section);
        expect($all[0]['config'])->toBe($config);
    });

    test('retorna múltiplas tuplas', function () {
        $registry = new SDTRegistry();
        $section1 = createSection();
        $section2 = createSection();
        $config1 = new SDTConfig(id: '12345678');
        $config2 = new SDTConfig(id: '87654321');
        
        $registry->register($section1, $config1);
        $registry->register($section2, $config2);
        
        $all = $registry->getAll();
        
        expect($all)->toHaveCount(2);
        expect($all[0]['element'])->toBe($section1);
        expect($all[1]['element'])->toBe($section2);
    });
});

describe('SDTRegistry - getConfig', function () {
    test('retorna config de elemento registrado', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', alias: 'Test');
        
        $registry->register($section, $config);
        
        $retrieved = $registry->getConfig($section);
        
        expect($retrieved)->toBe($config);
    });

    test('retorna null para elemento não registrado', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        
        expect($registry->getConfig($section))->toBeNull();
    });

    test('distingue elementos diferentes', function () {
        $registry = new SDTRegistry();
        $section1 = createSection();
        $section2 = createSection();
        $config1 = new SDTConfig(id: '12345678', alias: 'Section 1');
        $config2 = new SDTConfig(id: '87654321', alias: 'Section 2');
        
        $registry->register($section1, $config1);
        $registry->register($section2, $config2);
        
        expect($registry->getConfig($section1))->toBe($config1);
        expect($registry->getConfig($section2))->toBe($config2);
    });
});

describe('SDTRegistry - has', function () {
    test('retorna true para elemento registrado', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        
        $registry->register($section, new SDTConfig(id: '12345678'));
        
        expect($registry->has($section))->toBeTrue();
    });

    test('retorna false para elemento não registrado', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        
        expect($registry->has($section))->toBeFalse();
    });
});

describe('SDTRegistry - isIdUsed', function () {
    test('retorna true para ID gerado', function () {
        $registry = new SDTRegistry();
        $id = $registry->generateUniqueId();
        
        expect($registry->isIdUsed($id))->toBeTrue();
    });

    test('retorna true para ID de config registrado', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        
        $registry->register($section, new SDTConfig(id: '12345678'));
        
        expect($registry->isIdUsed('12345678'))->toBeTrue();
    });

    test('retorna false para ID não usado', function () {
        $registry = new SDTRegistry();
        
        expect($registry->isIdUsed('99999999'))->toBeFalse();
    });

    test('não marca ID vazio como usado', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        
        $registry->register($section, new SDTConfig(id: ''));
        
        expect($registry->isIdUsed(''))->toBeFalse();
    });
});

describe('SDTRegistry - count', function () {
    test('retorna 0 para registry vazio', function () {
        $registry = new SDTRegistry();
        
        expect($registry->count())->toBe(0);
    });

    test('retorna contagem correta', function () {
        $registry = new SDTRegistry();
        
        $registry->register(createSection(), new SDTConfig(id: '12345678'));
        expect($registry->count())->toBe(1);
        
        $registry->register(createSection(), new SDTConfig(id: '87654321'));
        expect($registry->count())->toBe(2);
    });
});

describe('SDTRegistry - clear', function () {
    test('limpa todos os registros', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        
        $registry->register($section, new SDTConfig(id: '12345678'));
        expect($registry->count())->toBe(1);
        
        $registry->clear();
        
        expect($registry->count())->toBe(0);
        expect($registry->has($section))->toBeFalse();
        expect($registry->isIdUsed('12345678'))->toBeFalse();
    });

    test('permite registrar novamente após clear', function () {
        $registry = new SDTRegistry();
        $section = createSection();
        
        $registry->register($section, new SDTConfig(id: '12345678'));
        $registry->clear();
        
        // Deve permitir usar mesmo ID novamente
        $registry->register($section, new SDTConfig(id: '12345678'));
        
        expect($registry->count())->toBe(1);
    });
});

describe('SDTRegistry - Performance', function () {
    test('registra 1.000 elementos sem erros', function () {
        $registry = new SDTRegistry();
        $sections = [];
        
        // Criar elementos
        for ($i = 0; $i < 1000; $i++) {
            $sections[] = createSection();
        }
        
        // Registrar
        foreach ($sections as $i => $section) {
            $id = str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT);
            $registry->register($section, new SDTConfig(id: $id));
        }
        
        expect($registry->count())->toBe(1000);
    });
});
