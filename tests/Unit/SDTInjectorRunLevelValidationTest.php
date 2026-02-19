<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

/**
 * Unit tests for runLevel cross-validation in SDTInjector::processElement().
 *
 * Tests that runLevel=true is only allowed for Text elements.
 * Non-Text elements (TextRun, Table, Image) must throw InvalidArgumentException.
 *
 * @since 0.7.0
 */
describe('SDTInjector runLevel Validation', function (): void {
    it('[INJ-RL-20] throws InvalidArgumentException for runLevel with TextRun', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $textRun = $section->addTextRun();
        $textRun->addText('Hello');
        $cc->addContentControl($textRun, ['tag' => 'test-textrun', 'runLevel' => true]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_rl_') . '.docx';

        expect(fn () => $cc->save($tempFile))
            ->toThrow(\InvalidArgumentException::class, 'runLevel=true is only supported for Text elements');

        // Cleanup
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    });

    it('[INJ-RL-21] throws InvalidArgumentException for runLevel with Table', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $table = $section->addTable();
        $table->addRow()->addCell(3000)->addText('Cell');
        $cc->addContentControl($table, ['tag' => 'test-table', 'runLevel' => true]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_rl_') . '.docx';

        expect(fn () => $cc->save($tempFile))
            ->toThrow(\InvalidArgumentException::class, 'runLevel=true is only supported for Text elements');

        // Cleanup
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    });

    it('[INJ-RL-22] throws InvalidArgumentException for runLevel with Image', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();

        // Create a minimal image source for testing
        $tempImage = tempnam(sys_get_temp_dir(), 'test_img_') . '.png';
        // Create a 1x1 PNG image
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tempImage);
        imagedestroy($img);

        $image = $section->addImage($tempImage, ['width' => 100, 'height' => 100]);
        $cc->addContentControl($image, ['tag' => 'test-image', 'runLevel' => true]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_rl_') . '.docx';

        expect(fn () => $cc->save($tempFile))
            ->toThrow(\InvalidArgumentException::class, 'runLevel=true is only supported for Text elements');

        // Cleanup
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        if (file_exists($tempImage)) {
            @unlink($tempImage);
        }
    });

    it('[INJ-RL-23] does NOT throw for runLevel with Text element', function (): void {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $textRun = $section->addTextRun();
        $text = $textRun->addText('Hello World');
        $cc->addContentControl($text, ['tag' => 'test-text', 'runLevel' => true]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_rl_') . '.docx';

        // Should NOT throw - Text with runLevel=true is valid
        $cc->save($tempFile);

        expect(file_exists($tempFile))->toBeTrue();

        // Verify SDT was injected
        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('<w:sdt>');
        expect($xml)->toContain('test-text');

        // Cleanup
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    });
});
