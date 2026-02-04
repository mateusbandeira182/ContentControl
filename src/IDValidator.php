<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

/**
 * Helper class for Content Control ID validation and generation
 * 
 * Centralizes ID validation logic to avoid duplication
 * between SDTConfig and SDTRegistry.
 * 
 * IDs must be 8-digit numbers in the range 10000000-99999999
 * according to ISO/IEC 29500-1:2016 §17.5.2.14
 * 
 * @since 2.0.0
 */
final class IDValidator
{
    /**
     * Minimum allowed ID (8 digits)
     */
    private const MIN_ID = 10000000;

    /**
     * Maximum allowed ID (8 digits)
     */
    private const MAX_ID = 99999999;

    /**
     * Validates ID format and range
     * 
     * @param string $id ID to validate
     * @return void
     * @throws \InvalidArgumentException If ID is invalid
     */
    public static function validate(string $id): void
    {
        // Allow empty string (will be filled by Registry)
        if ($id === '') {
            return;
        }

        // Validate format (8 digits)
        if (preg_match('/^\d{8}$/', $id) !== 1) {
            throw new \InvalidArgumentException(
                sprintf(
                    'IDValidator: Invalid ID format. Must be 8 digits, got "%s"',
                    $id
                )
            );
        }

        // Validate range (10000000 - 99999999)
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
     * Generates random ID in valid range
     * 
     * @return string 8-digit ID (10000000-99999999)
     */
    public static function generateRandom(): string
    {
        return (string) random_int(self::MIN_ID, self::MAX_ID);
    }

    /**
     * Returns minimum allowed ID
     * 
     * @return int
     */
    public static function getMinId(): int
    {
        return self::MIN_ID;
    }

    /**
     * Returns maximum allowed ID
     * 
     * @return int
     */
    public static function getMaxId(): int
    {
        return self::MAX_ID;
    }
}
