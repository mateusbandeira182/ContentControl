# Contributing to ContentControl

Thank you for your interest in contributing to ContentControl! This document provides guidelines and instructions for contributing to the project.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Commit Message Guidelines](#commit-message-guidelines)
- [Pull Request Process](#pull-request-process)
- [Project Architecture](#project-architecture)

## 🤝 Code of Conduct

This project follows a simple code of conduct:

- Be respectful and constructive in all interactions
- Welcome newcomers and help them learn
- Focus on what is best for the community and project
- Show empathy towards other community members

## 🚀 Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Git

### Setting Up Development Environment

1. **Fork the repository** on GitHub

2. **Clone your fork**:
   ```bash
   git clone https://github.com/YOUR-USERNAME/ContentControl.git
   cd ContentControl
   ```

3. **Install dependencies**:
   ```bash
   composer install
   ```

4. **Verify installation**:
   ```bash
   composer test
   composer phpstan
   ```

All tests should pass and PHPStan should report 0 errors.

## 💻 Development Workflow

### 1. Create a Feature Branch

Always create a new branch for your work:

```bash
git checkout -b feature/my-new-feature
# or for bug fixes:
git checkout -b fix/issue-description
```

### 2. Make Your Changes

- Write clean, readable code
- Follow existing code style and patterns
- Add PHPDoc comments for public methods
- Update README.md if adding new features

### 3. Write Tests

All new features and bug fixes **must** include tests:

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/pest tests/Unit/MyNewTest.php

# Run tests with coverage
composer test:coverage
```

**Minimum coverage requirement**: 80%

### 4. Run Quality Checks

Before committing, ensure code quality:

```bash
# PHPStan Level 9 (must pass)
composer phpstan

# Run all tests
composer test
```

### 5. Commit Your Changes

Follow [Conventional Commits](https://www.conventionalcommits.org/) format:

```bash
git add .
git commit -m "feat: add support for dropdown content controls"
```

See [Commit Message Guidelines](#commit-message-guidelines) below for details.

## 📝 Coding Standards

### PHP Standards

- **PHP Version**: 8.2+ (use typed properties, readonly, etc.)
- **Strict Types**: Always use `declare(strict_types=1);`
- **PHPStan Level**: 9 (strictest)
- **Visibility**: Use `private` by default, `public` only when necessary
- **Final Classes**: Mark classes as `final` unless designed for extension

### Code Style

```php
<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

/**
 * Brief description of the class
 * 
 * Detailed explanation if needed.
 * 
 * @since x.y.z
 */
final class MyClass
{
    /**
     * Brief description of method
     * 
     * @param string $param Description of parameter
     * @return bool Description of return value
     * @throws \InvalidArgumentException When validation fails
     */
    public function myMethod(string $param): bool
    {
        // Implementation
    }
}
```

### Key Conventions

1. **Type Safety**:
   - Always use strict types
   - Use `instanceof` instead of string class comparisons
   - Use `=== 1` for `preg_match()` results (not truthy checks)

2. **Error Handling**:
   - Throw specific exceptions (extend `ContentControlException`)
   - Prefix error messages with class/context: `"SDTConfig: Invalid ID"`
   - Include helpful details in exception messages

3. **Validation**:
   - Validate ALL inputs in constructors/methods
   - Use fail-fast pattern (validate before assigning)
   - Reject XML reserved characters (`< > & " '`)

4. **Documentation**:
   - Add PHPDoc to all public methods
   - Include `@since` tags for new features
   - Reference OOXML spec sections when relevant (§17.5.2.x)

## 🧪 Testing Guidelines

### Test Structure

```php
<?php

use MkGrow\ContentControl\MyClass;

test('should do something specific', function () {
    // Arrange
    $instance = new MyClass();
    
    // Act
    $result = $instance->doSomething();
    
    // Assert
    expect($result)->toBeTrue();
});

test('should throw exception on invalid input', function () {
    new MyClass('invalid');
})->throws(\InvalidArgumentException::class);
```

### Test Categories

- **Unit Tests** (`tests/Unit/`): Test individual classes/methods
- **Feature Tests** (`tests/Feature/`): Test complete workflows
- **Integration Tests**: Test with real DOCX files when needed

### Custom Expectations

Use project-specific expectations:

```php
expect($xml)->toBeValidXml();
expect($xml)->toHaveXmlElement('w:sdt');
expect($xml)->toHaveXmlAttribute('w:id', '12345678');
```

### Test Fixtures

Use `tests/Fixtures/SampleElements.php` for reusable test elements:

```php
use Tests\Fixtures\SampleElements;

$section = SampleElements::createSectionWithText('Test');
$table = SampleElements::createSectionWithTable(3, 2);
```

## 📨 Commit Message Guidelines

Follow [Conventional Commits](https://www.conventionalcommits.org/) format:

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, no logic change)
- `refactor`: Code refactoring (no behavior change)
- `perf`: Performance improvements
- `test`: Adding or updating tests
- `chore`: Maintenance tasks (dependencies, build, etc.)

### Examples

**Good commit messages:**
```
feat(SDTConfig): add validation for alias length

Validates that alias does not exceed 255 characters.
Throws InvalidArgumentException with character count in message.

Closes #42
```

```
fix(SDTInjector): prevent content duplication in nested elements

Changed from string replacement to DOM manipulation.
Elements are now moved (not copied) into <w:sdtContent>.

Fixes #38
```

**Bad commit messages:**
```
update code          ❌ Too vague
fix tests            ❌ Doesn't explain WHAT or WHY
WIP                  ❌ Not descriptive
```

### Commit Message Best Practices

1. **Use imperative mood**: "Add feature" not "Added feature"
2. **Capitalize first letter**: "Fix bug" not "fix bug"
3. **No period at end**: "Add feature" not "Add feature."
4. **Explain WHY not just WHAT**: Include context in body
5. **Reference issues**: Use "Fixes #123", "Closes #456"

## 🔄 Pull Request Process

### Before Opening a PR

- [ ] All tests pass (`composer test`)
- [ ] PHPStan Level 9 passes (`composer phpstan`)
- [ ] Code follows project conventions
- [ ] New features have tests
- [ ] Documentation updated (README, CHANGELOG)
- [ ] Commit messages follow guidelines

### PR Title

Use same format as commit messages:

```
feat(ElementLocator): add cache for XPath queries
fix(SDTRegistry): handle ID collision edge case
```

### PR Description Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## How Has This Been Tested?
Describe tests added or manual testing performed

## Checklist
- [ ] Code follows project style guidelines
- [ ] Self-reviewed my code
- [ ] Commented complex code sections
- [ ] Updated documentation
- [ ] Added tests covering changes
- [ ] All tests pass locally
- [ ] PHPStan Level 9 passes

## Related Issues
Fixes #123
```

### Review Process

1. Automated checks must pass (GitHub Actions)
2. At least one maintainer approval required
3. Address review feedback
4. Squash commits if requested
5. Maintainer will merge when ready

## 🏗️ Project Architecture

### Design Patterns

- **Proxy Pattern**: `ContentControl` encapsulates `PhpWord`
- **Value Object**: `SDTConfig` (immutable configuration)
- **Registry Pattern**: `SDTRegistry` (unique ID management)
- **Service Layer**: `SDTInjector` (XML operations)

### Key Principles

- **SOLID Principles**: Single Responsibility, Open/Closed, etc.
- **Immutability**: Value objects use readonly properties
- **Type Safety**: PHPStan Level 9 strict mode
- **Fail-Fast**: Validate inputs immediately
- **Composition over Inheritance**: Prefer delegation

### File Structure

```
src/
  ContentControl.php      - Main API (Proxy Pattern)
  SDTConfig.php           - Immutable configuration
  SDTRegistry.php         - ID management & element tracking
  SDTInjector.php         - XML injection service
  ElementIdentifier.php   - Unique marker generation
  ElementLocator.php      - DOM element location
  IDValidator.php         - ID validation helper
  Assert.php              - PHPStan type narrowing
  Exception/              - Custom exceptions
```

### Adding New Features

1. **Plan first**: Open an issue to discuss approach
2. **Check existing patterns**: Follow established conventions
3. **Write tests first**: TDD when possible
4. **Update docs**: README, CHANGELOG, PHPDoc
5. **Consider BC**: Avoid breaking changes when possible

## 📚 Resources

- [PHPOffice/PHPWord Documentation](https://phpword.readthedocs.io/)
- [ISO/IEC 29500-1:2016 (OOXML Spec)](https://www.iso.org/standard/71691.html)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [Pest PHP Testing Framework](https://pestphp.com/docs)
- [Conventional Commits](https://www.conventionalcommits.org/)

## ❓ Questions?

- **General questions**: Open a [Discussion](https://github.com/mateusbandeira182/ContentControl/discussions)
- **Bug reports**: Open an [Issue](https://github.com/mateusbandeira182/ContentControl/issues)
- **Feature requests**: Open an [Issue](https://github.com/mateusbandeira182/ContentControl/issues) with "enhancement" label

## 📄 License

By contributing, you agree that your contributions will be licensed under the same license as the project.

---

Thank you for contributing to ContentControl! 🎉
