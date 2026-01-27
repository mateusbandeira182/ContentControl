<?php

namespace MkGrow\ContentControl;

/**
 * Assertion helper for PHPStan Level 9 type narrowing
 * 
 * Provides custom assertions that help PHPStan understand type narrowing
 * in contexts where native assert() is insufficient.
 * 
 * @package MkGrow\ContentControl
 */
class Assert
{
    /**
     * Asserts that value is not null
     * 
     * Throws LogicException if value is null, otherwise PHPStan understands
     * that the value is non-null after this call.
     * 
     * @template T
     * @param T|null $value Value to check
     * @param string $message Error message if assertion fails
     * @return void
     * @throws \LogicException If value is null
     * 
     * @phpstan-assert !null $value
     */
    public static function notNull($value, string $message): void
    {
        if ($value === null) {
            throw new \LogicException($message);
        }
    }
}
