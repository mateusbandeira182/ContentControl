<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

/**
 * Value Object for Content Control (SDT) configuration
 * 
 * Immutable class encapsulating all properties of a
 * Structured Document Tag according to ISO/IEC 29500-1:2016 §17.5.2
 * 
 * @since 2.0.0
 */
final class SDTConfig
{
    /**
     * Creates immutable Content Control configuration
     * 
     * @param string $id Unique Identifier (8 digits: 10000000-99999999)
     * @param string $alias Friendly name displayed in Word (max 255 chars)
     * @param string $tag Metadata tag for programmatic identification
     * @param string $type Control type (TYPE_RICH_TEXT, TYPE_PLAIN_TEXT, etc)
     * @param string $lockType Lock level (LOCK_NONE, LOCK_SDT_LOCKED, etc)
     * @param bool $inlineLevel If true, injects SDT inside cell; if false, at body level
     * 
     * @throws \InvalidArgumentException If any parameter is invalid
     */
    public function __construct(
        public readonly string $id,
        public readonly string $alias = '',
        public readonly string $tag = '',
        public readonly string $type = ContentControl::TYPE_RICH_TEXT,
        public readonly string $lockType = ContentControl::LOCK_NONE,
        public readonly bool $inlineLevel = false,
    ) {
        $this->validateId($id);
        $this->validateAlias($alias);
        $this->validateTag($tag);
        $this->validateType($type);
        $this->validateLockType($lockType);
    }

    /**
     * Creates SDTConfig from options array
     * 
     * @param array{
     *     id?: string,
     *     alias?: string,
     *     tag?: string,
     *     type?: string,
     *     lockType?: string,
     *     inlineLevel?: bool
     * } $options Content Control Configuration
     * 
     * @return self
     * @throws \InvalidArgumentException If options are invalid
     * 
     * @example
     * ```php
     * $config = SDTConfig::fromArray([
     *     'id' => '12345678',
     *     'alias' => 'Nome do Cliente',
     *     'tag' => 'customer-name',
     *     'type' => ContentControl::TYPE_RICH_TEXT,
     *     'inlineLevel' => true
     * ]);
     * ```
     */
    public static function fromArray(array $options): self
    {
        return new self(
            id: $options['id'] ?? '',
            alias: $options['alias'] ?? '',
            tag: $options['tag'] ?? '',
            type: $options['type'] ?? ContentControl::TYPE_RICH_TEXT,
            lockType: $options['lockType'] ?? ContentControl::LOCK_NONE,
            inlineLevel: $options['inlineLevel'] ?? false,
        );
    }

    /**
     * Returns new instance with different ID (immutability)
     * 
     * @param string $id New identifier
     * @return self New instance with updated ID
     * @throws \InvalidArgumentException If ID is invalid
     */
    public function withId(string $id): self
    {
        return new self(
            id: $id,
            alias: $this->alias,
            tag: $this->tag,
            type: $this->type,
            lockType: $this->lockType,
            inlineLevel: $this->inlineLevel,
        );
    }

    /**
     * Returns new instance with different inlineLevel (immutability)
     * 
     * Used to toggle between inline-level injection (inside cells)
     * and block-level (document body level).
     * 
     * @param bool $inlineLevel If true, injects SDT inside cell
     * @return self New instance with updated inlineLevel
     * 
     * @example
     * ```php
     * $config = SDTConfig::fromArray(['id' => '12345678']);
     * $inlineConfig = $config->withInlineLevel(true);
     * ```
     */
    public function withInlineLevel(bool $inlineLevel): self
    {
        return new self(
            id: $this->id,
            alias: $this->alias,
            tag: $this->tag,
            type: $this->type,
            lockType: $this->lockType,
            inlineLevel: $inlineLevel,
        );
    }

    /**
     * Returns new instance with different alias (immutability)
     * 
     * @param string $alias New friendly name
     * @return self New instance with updated alias
     * @throws \InvalidArgumentException If alias is invalid
     */
    public function withAlias(string $alias): self
    {
        return new self(
            id: $this->id,
            alias: $alias,
            tag: $this->tag,
            type: $this->type,
            lockType: $this->lockType,
            inlineLevel: $this->inlineLevel,
        );
    }

    /**
     * Returns new instance with different tag (immutability)
     * 
     * @param string $tag New metadata tag
     * @return self New instance with updated tag
     * @throws \InvalidArgumentException If tag is invalid
     */
    public function withTag(string $tag): self
    {
        return new self(
            id: $this->id,
            alias: $this->alias,
            tag: $tag,
            type: $this->type,
            lockType: $this->lockType,
            inlineLevel: $this->inlineLevel,
        );
    }

    /**
     * Validates and normalizes Content Control ID
     * 
     * ID must be an 8-digit number between 10000000 and 99999999.
     * 
     * Specification: ISO/IEC 29500-1:2016 §17.5.2.14
     * 
     * @param string $id Value to validate
     * @throws \InvalidArgumentException If ID is invalid
     * @return void
     */
    private function validateId(string $id): void
    {
        IDValidator::validate($id);
    }

    /**
     * Validates alias value
     * 
     * Alias is a friendly name displayed in Word. This validation ensures:
     * - Maximum length of 255 characters (practical limit for display)
     * - No control characters (0x00-0x1F, 0x7F-0x9F)
     * 
     * @param string $alias Value to validate
     * @throws \InvalidArgumentException If alias is invalid
     * @return void
     */
    private function validateAlias(string $alias): void
    {
        // Allow empty string
        if ($alias === '') {
            return;
        }

        // Reasonable length limit for display
        $length = mb_strlen($alias, 'UTF-8');
        if ($length > 255) {
            throw new \InvalidArgumentException(
                sprintf('SDTConfig: Alias must not exceed 255 characters, got %d characters', $length)
            );
        }

        // Check for control characters that can cause issues
        // Blocks C0 controls (0x00-0x1F) and C1 controls (0x7F-0x9F)
        if (preg_match('/[\x00-\x1F\x7F-\x9F]/u', $alias) === 1) {
            // Sanitize alias for safe display
            $sanitized = addcslashes($alias, "\x00..\x1F\x7F..\x9F");
            throw new \InvalidArgumentException(
                sprintf('SDTConfig: Alias "%s" must not contain control characters', $sanitized)
            );
        }

        // Check for XML reserved characters which may cause parsing issues
        if (preg_match('/[<>&"\']/', $alias) === 1) {
            throw new \InvalidArgumentException(
                sprintf('SDTConfig: Alias "%s" contains XML reserved characters', $alias)
            );
        }
    }

    /**
     * Validates tag value
     * 
     * Tag is a metadata identifier for programmatic use. This validation ensures:
     * - Maximum length of 255 characters
     * - Only alphanumeric characters, hyphens, underscores and periods
     * - Must start with a letter or underscore (identifier convention)
     * 
     * @param string $tag Value to validate
     * @throws \InvalidArgumentException If tag is invalid
     * @return void
     */
    private function validateTag(string $tag): void
    {
        // Allow empty string
        if ($tag === '') {
            return;
        }

        // Length limit
        $length = mb_strlen($tag, 'UTF-8');
        if ($length > 255) {
            throw new \InvalidArgumentException(
                sprintf('SDTConfig: Tag must not exceed 255 characters, got %d characters', $length)
            );
        }

        // Tag must follow identifier pattern: starts with letter or _, then alphanumeric, -, _, .
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]*$/', $tag) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('SDTConfig: Tag "%s" must start with a letter or underscore and contain only alphanumeric characters, hyphens, underscores, and periods', $tag)
            );
        }
    }

    /**
     * Validates Content Control Type
     * 
     * @param string $type Value to validate
     * @throws \InvalidArgumentException If type is invalid
     * @return void
     */
    private function validateType(string $type): void
    {
        $validTypes = [
            ContentControl::TYPE_GROUP,
            ContentControl::TYPE_PLAIN_TEXT,
            ContentControl::TYPE_RICH_TEXT,
            ContentControl::TYPE_PICTURE,
        ];

        if (!in_array($type, $validTypes, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'SDTConfig: Invalid type "%s". Must be one of: %s',
                    $type,
                    implode(', ', $validTypes)
                )
            );
        }
    }

    /**
     * Validates Content Control Lock Level
     * 
     * @param string $lockType Value to validate
     * @throws \InvalidArgumentException If lockType is invalid
     * @return void
     */
    private function validateLockType(string $lockType): void
    {
        $validLockTypes = [
            ContentControl::LOCK_NONE,
            ContentControl::LOCK_SDT_LOCKED,
            ContentControl::LOCK_CONTENT_LOCKED,
            ContentControl::LOCK_UNLOCKED,
        ];

        if (!in_array($lockType, $validLockTypes, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'SDTConfig: Invalid lock type "%s". Must be one of: %s',
                    $lockType,
                    implode(', ', $validLockTypes)
                )
            );
        }
    }
}
