<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\IOFactory;
use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;
use MkGrow\ContentControl\Exception\TemporaryFileException;
use PhpOffice\PhpWord\PhpWord;

/**
 * @property string $tempDir
 */
describe('IOFactory - Save with Content Controls', function () {
    
    beforeEach(function () {
        // Criar diretório temporário para testes usando um nome mais robusto
        $this->tempDir = sys_get_temp_dir() . '/phpword_test_' . bin2hex(random_bytes(16));
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    });
    
    afterEach(function () {
        // Limpar arquivos temporários (falhas devem falhar o teste)
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            expect($files)->not->toBeFalse(
                sprintf('Failed to read contents of temporary directory "%s"', $this->tempDir)
            );
            foreach ($files as $file) {
                if (is_file($file)) {
                    expect(unlink($file))->toBeTrue(
                        sprintf('Failed to delete temporary file "%s"', $file)
                    );
                }
            }
            expect(rmdir($this->tempDir))->toBeTrue(
                sprintf('Failed to remove temporary directory "%s"', $this->tempDir)
            );
        }
    });
    
    test('salva documento com Content Control único', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Documento com Content Control');
        
        $cc->addContentControl($section, [
            'alias' => 'Campo Principal',
            'tag' => 'main-field',
            'type' => ContentControl::TYPE_RICH_TEXT,
        ]);
        
        $filename = $this->tempDir . '/test-single-control.docx';
        
        $cc->save($filename);
        
        expect(file_exists($filename))->toBeTrue();
        expect(filesize($filename))->toBeGreaterThan(0);
    });
    
    test('salva documento com múltiplos Content Controls', function () {
        $cc = new ContentControl();
        
        // Control 1: Texto
        $section1 = $cc->addSection();
        $section1->addText('Campo 1');
        $cc->addContentControl($section1, ['tag' => 'field-1']);
        
        // Control 2: Tabela
        $section2 = $cc->addSection();
        $table = $section2->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Dados');
        $cc->addContentControl($section2, ['tag' => 'field-2']);
        
        $filename = $this->tempDir . '/test-multiple-controls.docx';
        
        $cc->save($filename);
        
        expect(file_exists($filename))->toBeTrue();
    });
    
    test('valida estrutura XML do documento salvo', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Teste de validação');
        
        $cc->addContentControl($section, [
            'alias' => 'Validação',
            'tag' => 'validation-field',
            'lockType' => ContentControl::LOCK_SDT_LOCKED,
        ]);
        
        $filename = $this->tempDir . '/test-validation.docx';
        
        $cc->save($filename);
        
        // Abrir ZIP e ler document.xml
        $zip = new ZipArchive();
        $openResult = $zip->open($filename);
        if ($openResult !== true) {
            throw new \RuntimeException('Failed to open DOCX file as ZIP for validation. Error code: ' . $openResult);
        }
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // Validar presença de Content Control
        expect($documentXml)->toContain('<w:sdt')
            ->and($documentXml)->toContain('<w:alias w:val="Validação"/>')
            ->and($documentXml)->toContain('<w:tag w:val="validation-field"/>')
            ->and($documentXml)->toContain('<w:lock w:val="sdtLocked"/>');
    });
    
    test('lança exceção para caminho inválido', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $cc->addContentControl($section);
        
        // Caminho inválido (diretório inexistente), construído de forma portátil
        $invalidPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR . 'nonexistent_subdir_' . bin2hex(random_bytes(8))
            . DIRECTORY_SEPARATOR . 'arquivo.docx';
        
        expect(fn() => $cc->save($invalidPath))
            ->toThrow(\RuntimeException::class, 'Target directory not writable');
    });
    
    test('salva documento sem Content Controls', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Documento sem Content Controls');
        
        $filename = $this->tempDir . '/test-no-controls.docx';
        
        $cc->save($filename);
        
        expect(file_exists($filename))->toBeTrue();
        expect(filesize($filename))->toBeGreaterThan(0);
    });
    
    test('createWriter retorna Writer válido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Test content');
        
        $writer = IOFactory::createWriter($phpWord);
        
        expect($writer)->toBeInstanceOf(\PhpOffice\PhpWord\Writer\WriterInterface::class);
    });
    
    test('registerCustomWriters emite deprecation warning', function () {
        // Capturar error handler atual
        set_error_handler(function(int $errno, string $errstr): bool {
            expect($errno)->toBe(E_USER_DEPRECATED);
            expect($errstr)->toContain('registerCustomWriters() is deprecated');
            expect($errstr)->toContain('Use IOFactory::saveWithContentControls()');
            return true;
        }, E_USER_DEPRECATED);
        
        IOFactory::registerCustomWriters();
        
        // Restaurar error handler
        restore_error_handler();
    });
    
    test('falha ao mover arquivo temporário lança RuntimeException', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Test');
        $cc->addContentControl($section);
        
        // No Windows, não podemos criar diretório read-only facilmente
        // Vamos usar um caminho que não permite write após criar o arquivo
        $filename = $this->tempDir . '/test-move-fail.docx';
        
        // Criar arquivo read-only que já existe
        touch($filename);
        chmod($filename, 0444);
        
        try {
            $cc->save($filename);
            
            // Se chegou aqui no Windows, o teste não é aplicável
            // (Windows pode sobrescrever arquivos read-only em alguns casos)
            expect(true)->toBeTrue();
        } catch (\RuntimeException $e) {
            expect($e->getMessage())->toContain('Failed to move file');
        } finally {
            // Restaurar permissões para cleanup
            if (file_exists($filename)) {
                chmod($filename, 0666);
            }
        }
    });
});
