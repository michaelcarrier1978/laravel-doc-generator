# Laravel Documentation Generator

> Automatically generate comprehensive documentation for Laravel controllers using Claude AI and publish to Confluence.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Generate Controller Documentation](#generate-controller-documentation)
  - [Publish to Confluence](#publish-to-confluence)
  - [Test Confluence Connection](#test-confluence-connection)
- [Commands](#commands)
- [Architecture](#architecture)
- [Error Handling](#error-handling)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Overview

Laravel Documentation Generator is a command-line tool that automates the process of creating comprehensive documentation for Laravel controller methods. It uses Claude AI to analyze your code and generate intelligent documentation that can be written back to your files or published directly to Confluence.

## Features

- ✨ **AI-Powered Documentation**: Leverages Claude AI (Anthropic) to generate intelligent documentation
- 📝 **Controller Analysis**: Automatically parses Laravel controllers and extracts method signatures
- 🔄 **Confluence Integration**: Publish documentation directly to Confluence pages
- 📊 **Progress Tracking**: Visual progress bars for multi-method processing
- 🔍 **Dry Run Mode**: Preview generated documentation before writing to files
- ⚡ **Error Handling**: Comprehensive error messages for debugging
- 🎯 **Flexible Output**: Write to the same file or specify a different output path

## Requirements

- PHP 8.0 or higher
- Composer
- Anthropic API key (for Claude AI)
- Confluence account (for publishing features)

## Installation

1. Clone the repository:

```bash
git clone https://github.com/michaelcarrier/laravel-doc-generator.git
cd laravel-doc-generator
```

2. Install dependencies:

```bash
composer install
```

3. Copy the example environment file and configure it:

```bash
cp .env.example .env
```

4. Make the console script executable:

```bash
chmod +x bin/console
```

## Configuration

Edit the `.env` file with your credentials:

```env
# Anthropic API Configuration
ANTHROPIC_API_KEY=your-anthropic-api-key-here

# Confluence Configuration
CONFLUENCE_BASE_URL=https://your-domain.atlassian.net
CONFLUENCE_EMAIL=your-email@example.com
CONFLUENCE_API_TOKEN=your-confluence-api-token
CONFLUENCE_SPACE_KEY=YOUR_SPACE
```

### Getting Your API Keys

**Anthropic API Key:**
1. Go to [Anthropic Console](https://console.anthropic.com/settings/keys)
2. Click "Create API Key"
3. Copy the key and add it to your `.env` file

**Confluence API Token:**
1. Go to [Atlassian API Tokens](https://id.atlassian.com/manage-profile/security/api-tokens)
2. Click "Create API token"
3. Give it a name and copy the token
4. Add it to your `.env` file

## Usage

### Generate Controller Documentation

Generate documentation for a Laravel controller:

```bash
php bin/console generate:controller path/to/Controller.php
```

**With custom output file:**

```bash
php bin/console generate:controller path/to/Controller.php path/to/DocumentedController.php
```

**Dry run mode (preview only):**

```bash
php bin/console generate:controller path/to/Controller.php --dry-run
```

**Example:**

```bash
php bin/console generate:controller tests/fixtures/UserController.php
```

This command will:
1. Parse the controller file
2. Extract all public methods
3. Send each method to Claude AI for analysis
4. Generate comprehensive documentation
5. Write the documentation back to the file

### Publish to Confluence

Generate documentation and publish it to Confluence:

```bash
php bin/console publish:confluence path/to/Controller.php
```

**With custom space:**

```bash
php bin/console publish:confluence path/to/Controller.php --space=YOUR_SPACE
```

**With parent page:**

```bash
php bin/console publish:confluence path/to/Controller.php --parent-id=123456
```

**Example:**

```bash
php bin/console publish:confluence tests/fixtures/UserController.php
```

This command will:
1. Parse the controller
2. Generate documentation with Claude AI
3. Format it for Confluence (with code blocks, tables, etc.)
4. Create or update a Confluence page
5. Return a URL to view the published documentation

### Test Confluence Connection

Verify your Confluence credentials are working:

```bash
php bin/console test:confluence
```

## Commands

### `generate:controller`

Generate documentation for a Laravel controller.

**Syntax:**
```bash
php bin/console generate:controller <file> [<output>] [--dry-run]
```

**Arguments:**
- `file` - Path to the controller file (required)
- `output` - Output file path (optional, defaults to input file)

**Options:**
- `--dry-run` - Preview changes without writing to file

**Example Output:**
```
Laravel Documentation Generator
================================

Parsing controller...
---------------------

 ✓ Found UserController with 2 methods

Generating documentation...
---------------------------

 2/2 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

 ✓ Documentation written to tests/fixtures/UserController.php
```

### `publish:confluence`

Generate and publish controller documentation to Confluence.

**Syntax:**
```bash
php bin/console publish:confluence <file> [--space=SPACE] [--parent-id=ID]
```

**Arguments:**
- `file` - Path to the controller file (required)

**Options:**
- `-s, --space=SPACE` - Confluence space key (defaults to CONFLUENCE_SPACE_KEY from .env)
- `-p, --parent-id=ID` - Parent page ID for nested pages

**Example Output:**
```
Publish to Confluence
=====================

Parsing controller...
---------------------

 ✓ Found UserController with 2 methods

Generating documentation...
---------------------------

 2/2 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

Formatting for Confluence...
----------------------------

Publishing to Confluence...
---------------------------

 ✓ Created new page: https://your-domain.atlassian.net/wiki/spaces/DEV/pages/123456/UserController+Documentation
```

### `test:confluence`

Test your Confluence API connection.

**Syntax:**
```bash
php bin/console test:confluence
```

**Example Output:**
```
 ✓ Connected! (Set CONFLUENCE_SPACE_KEY to test further)
```

## Architecture

```
laravel-doc-generator/
├── bin/
│   └── console              # CLI entry point
├── src/
│   ├── Analyzers/
│   │   └── ClaudeAnalyzer.php       # Claude AI integration
│   ├── Clients/
│   │   └── ConfluenceClient.php     # Confluence API client
│   ├── Commands/
│   │   ├── GenerateControllerDocs.php   # Generate docs command
│   │   ├── PublishToConfluence.php      # Publish to Confluence command
│   │   └── TestConfluence.php           # Test connection command
│   ├── Formatters/
│   │   └── ConfluenceFormatter.php  # Format docs for Confluence
│   ├── Parsers/
│   │   ├── ControllerParser.php     # Parse PHP controllers
│   │   └── QueryParser.php          # Parse database queries
│   └── Writers/
│       └── DocumentWriter.php       # Write docs back to files
├── tests/
│   └── fixtures/
│       └── UserController.php       # Example controller for testing
├── .env                     # Environment configuration
├── .env.example            # Example environment file
├── composer.json           # Dependencies
└── README.md              # This file
```

### Key Components

**ClaudeAnalyzer**
- Sends controller methods to Claude AI
- Handles API authentication and rate limiting
- Parses AI responses into documentation format

**ControllerParser**
- Uses nikic/php-parser to analyze PHP files
- Extracts class names, method signatures, parameters
- Builds Abstract Syntax Tree (AST) for code analysis

**ConfluenceClient**
- Manages Confluence API authentication
- Creates and updates pages
- Handles version conflicts and permissions

**DocumentWriter**
- Modifies PHP files while preserving structure
- Injects documentation using AST manipulation
- Validates file permissions before writing

## Error Handling

The tool provides detailed error messages for common issues:

### Anthropic API Errors

**Invalid API Key:**
```
Invalid Anthropic API key. Please check your ANTHROPIC_API_KEY in .env file
```
**Solution:** Verify your API key at https://console.anthropic.com/settings/keys

**Rate Limit:**
```
Rate limit exceeded. Please wait and try again
```
**Solution:** Wait a few moments and retry, or upgrade your Anthropic plan

### Confluence Errors

**Invalid Credentials:**
```
Unauthorized: Invalid Confluence credentials. Check CONFLUENCE_EMAIL and CONFLUENCE_API_TOKEN
```
**Solution:** Verify your credentials in the `.env` file

**Space Not Found:**
```
Not Found: Space key "YOUR_SPACE" does not exist
```
**Solution:** Check the space key exists and you have access

**Permission Denied:**
```
Forbidden: You do not have permission to create pages in this space
```
**Solution:** Request write permissions from your Confluence admin

### File Errors

**File Not Found:**
```
File not found: path/to/Controller.php
```
**Solution:** Check the file path is correct

**No Class Found:**
```
No class found in file: path/to/Controller.php
```
**Solution:** Ensure the file contains a PHP class

## Troubleshooting

### Issue: "ANTHROPIC_API_KEY environment variable not set"

Even though you have it in `.env`, the environment variable might be cached.

**Solution:**
```bash
unset ANTHROPIC_API_KEY
php bin/console generate:controller path/to/Controller.php
```

Or set it inline:
```bash
ANTHROPIC_API_KEY="your-key-here" php bin/console generate:controller path/to/Controller.php
```

### Issue: "Model not found" error from Claude

Your API key might not have access to newer models.

**Solution:** The tool uses `claude-3-haiku-20240307` which is available on all plans. If you get this error, check your Anthropic account status.

### Issue: Documentation quality is poor

**Solution:** The quality depends on your code clarity. Ensure:
- Methods have clear, descriptive names
- Parameters are properly typed
- Code logic is well-structured

### Issue: Confluence page not updating

**Solution:** Check for version conflicts:
```bash
# The tool automatically handles version incrementing
# If manual intervention is needed, check the page in Confluence
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is open-source and available under the MIT License.

## Author

Michael Carrier - [michael.carrier1978@gmail.com](mailto:michael.carrier1978@gmail.com)

## Acknowledgments

- Built with [Symfony Console](https://symfony.com/doc/current/components/console.html)
- Powered by [Anthropic's Claude AI](https://www.anthropic.com/)
- PHP parsing by [nikic/php-parser](https://github.com/nikic/PHP-Parser)
- HTTP client: [Guzzle](https://docs.guzzlephp.org/)
