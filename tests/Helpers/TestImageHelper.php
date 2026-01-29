<?php

declare(strict_types=1);

namespace MkGrow\ContentControl\Tests\Helpers;

/**
 * Helper para gerenciar fixtures de imagens nos testes
 */
final class TestImageHelper
{
    /**
     * Retorna o caminho para a imagem de teste fixture
     * 
     * @return string Caminho absoluto para tests/Fixtures/test_image.png
     */
    public static function getTestImagePath(): string
    {
        return __DIR__ . '/../Fixtures/test_image.png';
    }
    
    /**
     * Garante que a imagem de teste fixture existe no disco
     * 
     * Este método valida que a fixture commitada existe. Se o arquivo
     * estiver ausente, lança uma exceção para falhar rapidamente e
     * garantir comportamento determinístico dos testes.
     * 
     * @return void
     * @throws \RuntimeException Se a fixture não existir
     */
    public static function ensureTestImageExists(): void
    {
        $testImagePath = self::getTestImagePath();
        
        if (!file_exists($testImagePath)) {
            throw new \RuntimeException(sprintf(
                'Test image fixture not found at %s. The committed fixture file is required for deterministic test behavior.',
                $testImagePath
            ));
        }
    }
}
