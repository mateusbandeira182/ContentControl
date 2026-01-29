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
     * Garante que a imagem de teste existe no disco
     * 
     * Este método é idempotente - pode ser chamado múltiplas vezes
     * sem recriação desnecessária do arquivo.
     * 
     * @return void
     */
    public static function ensureTestImageExists(): void
    {
        $testImagePath = self::getTestImagePath();
        
        if (file_exists($testImagePath)) {
            return;
        }
        
        $dir = dirname($testImagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $image = imagecreatetruecolor(1, 1);
        $red = imagecolorallocate($image, 255, 0, 0);
        imagefilledrectangle($image, 0, 0, 1, 1, $red);
        imagepng($image, $testImagePath);
        imagedestroy($image);
    }
}
