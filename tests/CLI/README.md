# CLI Modernization Tests

This directory contains comprehensive tests for the REUT CLI modernization features.

## Test Structure

### Output System Tests (`Output/`)
- **FormatterTest.php** - Tests for color formatting, styles, and icons
- **TableTest.php** - Tests for table rendering with headers and rows
- **ProgressBarTest.php** - Tests for progress bar functionality
- **SpinnerTest.php** - (Can be added) Tests for loading spinners

### Interactive System Tests (`Interactive/`)
- **PromptTest.php** - Tests for prompt validation (required, minLength, pattern, in)
- **CommandSuggestionsTest.php** - Tests for typo detection and command suggestions

### Command System Tests (`Commands/`)
- **CommandRegistryTest.php** - Tests for command registration and discovery
- **HelpCommandTest.php** - Tests for help command functionality
- **InitCommandTest.php** - Tests for init command
- **MigrateCommandTest.php** - Tests for migrate command
- **StatusCommandTest.php** - Tests for status command
- **GenerateModelCommandTest.php** - Tests for model generation command

### Help System Tests (`Help/`)
- **ExamplesTest.php** - Tests for command examples database
- **HelpGeneratorTest.php** - Tests for dynamic help generation

### Base Tests
- **CommandBaseTest.php** - Tests for base Command class functionality

## Running Tests

Run all CLI tests:
```bash
vendor/bin/phpunit tests/CLI
```

Run specific test suite:
```bash
vendor/bin/phpunit tests/CLI/Output
vendor/bin/phpunit tests/CLI/Commands
```

Run a specific test:
```bash
vendor/bin/phpunit tests/CLI/Output/FormatterTest.php
```

## Test Coverage

The tests cover:
- ✅ Output formatting and styling
- ✅ Table rendering
- ✅ Progress indicators
- ✅ Interactive prompts and validation
- ✅ Command registration and discovery
- ✅ Help system generation
- ✅ Command argument parsing
- ✅ Command option handling

## Notes

- Most tests are unit tests that don't require database connections
- Some command tests may need mocking for file system operations
- Interactive tests use reflection to test private methods where needed


