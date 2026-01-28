<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

/**
 * Helper class para validação e geração de IDs de Content Control
 * 
 * Centraliza a lógica de validação de IDs para evitar duplicação
 * entre SDTConfig e SDTRegistry.
 * 
 * IDs devem ser números de 8 dígitos no range 10000000-99999999
 * conforme ISO/IEC 29500-1:2016 §17.5.2.14
 * 
 * @since 2.0.0
 */
final class IDValidator
{
    /**
     * ID mínimo permitido (8 dígitos)
     */
    private const MIN_ID = 10000000;

    /**
     * ID máximo permitido (8 dígitos)
     */
    private const MAX_ID = 99999999;

    /**
     * Valida formato e range de um ID
     * 
     * @param string $id ID a ser validado
     * @return void
     * @throws \InvalidArgumentException Se ID inválido
     */
    public static function validate(string $id): void
    {
        // Permitir string vazia (será preenchido pelo Registry)
        if ($id === '') {
            return;
        }

        // Validar formato (8 dígitos)
        if (preg_match('/^\d{8}$/', $id) !== 1) {
            throw new \InvalidArgumentException(
                sprintf(
                    'IDValidator: Invalid ID format. Must be 8 digits, got "%s"',
                    $id
                )
            );
        }

        // Validar range (10000000 - 99999999)
        $idInt = (int) $id;
        if ($idInt < self::MIN_ID || $idInt > self::MAX_ID) {
            throw new \InvalidArgumentException(
                sprintf(
                    'IDValidator: Invalid ID range. Must be between %d and %d, got %d',
                    self::MIN_ID,
                    self::MAX_ID,
                    $idInt
                )
            );
        }
    }

    /**
     * Gera ID aleatório no range válido
     * 
     * @return string ID de 8 dígitos (10000000-99999999)
     */
    public static function generateRandom(): string
    {
        return (string) random_int(self::MIN_ID, self::MAX_ID);
    }

    /**
     * Retorna o ID mínimo permitido
     * 
     * @return int
     */
    public static function getMinId(): int
    {
        return self::MIN_ID;
    }

    /**
     * Retorna o ID máximo permitido
     * 
     * @return int
     */
    public static function getMaxId(): int
    {
        return self::MAX_ID;
    }
}
