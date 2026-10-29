# Translation Plugin for Wawoo-CMS

A Japanese to English automatic translation plugin for Wawoo-CMS that integrates with external LLM APIs.

## Features

- **Automatic Translation**: Automatically translates Japanese content to English
- **Multiple LLM Support**: Supports OpenAI GPT and Anthropic Claude APIs
- **Translation Validation**: Validates translation quality and accuracy
- **Batch Processing**: Processes multiple content items efficiently
- **Queue Management**: Manages translation queue for bulk operations
- **Admin Interface**: Web-based admin panel for configuration and monitoring
- **CLI Commands**: Command-line interface for translation operations
- **Logging System**: Comprehensive logging and error handling
- **File Management**: Generates translated files and manages metadata
- **Security**: Secure API key management and input validation

## Installation

1. Clone the plugin repository:
```bash
git clone <repository-url> /path/to/wawoo-cms/plugins/translation
```

2. Enable the plugin in your `config.local.php`:
```php
<?php
return [
    'ENABLED_PLUGINS' => ['translation', ...],
];
```

3. Set up the cache directory:
```bash
mkdir -p cache/settings cache/logs
```

## Configuration

### Basic Configuration

Edit `plugin.json` to configure the plugin:

```json
{
    "name": "translation",
    "type": "plugin",
    "version": "1.0.0",
    "namespace": "Wawoo\\Plugin\\Translation",
    "entry": "Plugin",
    "settings": true,
    "config": {
        "enabled": true,
        "target_language": "en",
        "source_language": "ja",
        "provider": "openai",
        "model": "gpt-3.5-turbo",
        "api_key": "your-api-key-here",
        "auto_translate": false,
        "validate_translation": true,
        "fallback_to_original": true,
        "max_retries": 3,
        "timeout": 30,
        "batch_size": 5,
        "excluded_fields": ["title", "slug", "date", "author"]
    }
}
```

### Environment Variables

The plugin supports `.env` file for environment variable management:

1. **Create .env file**:
   ```bash
   php bin/wawoo translation:env:create
   ```

2. **Edit .env file**:
   ```bash
   # .env
   TRANSLATION_API_KEY=your-api-key-here
   TRANSLATION_PROVIDER=openai
   TRANSLATION_MODEL=gpt-3.5-turbo
   ```

3. **Environment variable priority**:
   - Environment variables (highest priority)
   - Global variables (`$GLOBALS['WAWOO_TRANSLATION']`)
   - Configuration file (`plugin.json`)
   - Default values (lowest priority)

### Legacy Environment Variables

For backward compatibility, the plugin also supports the legacy format:

```bash
export WAWOO_TRANSLATION_API_KEY="your-api-key-here"
export WAWOO_TRANSLATION_PROVIDER="openai"
export WAWOO_TRANSLATION_MODEL="gpt-3.5-turbo"
```

## Usage

### Web Interface

1. Access the admin panel at `/admin/translation`
2. Configure your translation settings
3. Test your API connection
4. Enable automatic translation or manually translate content

### CLI Commands

The plugin provides several CLI commands:

```bash
# Translate a specific post
php bin/wawoo translation:post my-post

# Translate a specific page
php bin/wawoo translation:page my-page

# Translate all content
php bin/wawoo translate:all

# Environment management
php bin/wawoo translation:env:create    # Create .env file
php bin/wawoo translation:env:validate  # Validate environment
php bin/wawoo translation:env:status     # Show environment status

# Configuration
php bin/wawoo config:test                # Test configuration
php bin/wawoo config:validate            # Validate configuration
php bin/wawoo config:export              # Export configuration
php bin/wawoo config:import config.json  # Import configuration

# Queue management
php bin/wawoo queue:process              # Process translation queue
php bin/wawoo queue:clear                # Clear translation queue

# Logs
php bin/wawoo logs:show                  # Show recent logs
php bin/wawoo logs:clear                 # Clear logs

# Statistics
php bin/wawoo stats                      # Show statistics
```

### API Endpoints

The plugin provides REST API endpoints:

- `POST /api/translation?action=translate` - Translate content
- `POST /api/translation?action=validate` - Validate translation
- `POST /api/translation?action=queue` - Manage translation queue

## Supported LLM Providers

### OpenAI

- Models: `gpt-3.5-turbo`, `gpt-4`, `gpt-4-turbo`
- API Key Format: `sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

### Anthropic

- Models: `claude-3-haiku-20240307`, `claude-3-sonnet-20240229`, `claude-3-opus-20240229`
- API Key Format: `sk-ant-api03-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

## Translation Quality

The plugin includes several quality checks:

1. **Language Detection**: Ensures translated content is in the target language
2. **Length Validation**: Checks for significant length differences
3. **Content Similarity**: Measures semantic similarity between original and translated text
4. **Character Validation**: Ensures no source language characters remain
5. **Grammar Checks**: Basic grammar validation

## File Structure

```
plugins/translation/
├── plugin.json              # Plugin manifest
├── config.php               # Default configuration
├── src/
│   ├── Plugin.php           # Main plugin class
│   ├── ContentFinder.php    # Content discovery and queue management
│   ├── Translator.php       # Translation engine
│   ├── Validator.php        # Translation quality validation
│   ├── FileGenerator.php    # File generation and metadata management
│   ├── ConfigManager.php    # Configuration management
│   ├── APIClient.php       # External API client
│   ├── Logger.php          # Logging system
│   ├── AdminPanel.php      # Admin interface
│   ├── Router.php          # Route handling
│   ├── Settings.php        # Settings page
│   └── CLI.php             # CLI commands
├── tests/
│   └── TranslationPluginTest.php
└── README.md               # This file
```

## Hooks

The plugin hooks into Wawoo-CMS at the following points:

- `admin.before_render` - Renders admin panel
- `core.request` - Handles routing
- `post.load` / `page.load` - Finds content needing translation
- `post.html` / `page.html` - Translates HTML content

## Security Considerations

1. **API Key Security**: 
   - Use `.env` file for API key storage
   - Never commit `.env` files to version control
   - Set proper file permissions: `chmod 600 .env`

2. **Input Validation**: All user inputs are validated and sanitized

3. **File Permissions**: Proper file system permissions are enforced

4. **Rate Limiting**: Respects API rate limits and implements retry logic

5. **Error Handling**: Secure error messages that don't expose sensitive information

6. **Environment Variables**: 
   - `.env` files are automatically added to `.gitignore`
   - Environment variables are masked in logs and error messages

## Performance

- **Batch Processing**: Processes multiple translations in batches to reduce API calls
- **Caching**: Caches translation results to avoid redundant translations
- **Queue Management**: Asynchronous processing for bulk operations
- **Memory Management**: Efficient memory usage for large content

## Troubleshooting

### Common Issues

1. **API Connection Failed**
   - Check API key is correct
   - Verify API endpoint is accessible
   - Check rate limits

2. **Translation Quality Poor**
   - Adjust custom prompts
   - Try different models
   - Enable validation checks

3. **File Generation Issues**
   - Check file permissions
   - Verify cache directory exists
   - Check disk space

### Debug Mode

Enable debug mode for detailed logging:

```php
ConfigManager::set('debug', true);
```

### Logs

Check logs in `cache/logs/translation.log`

## Testing

Run the test suite:

```bash
php plugins/translation/tests/TranslationPluginTest.php
```

Or run specific tests:

```bash
php -r "require_once 'plugins/translation/tests/TranslationPluginTest.php'; \$test = new Wawoo\Plugin\Translation\TranslationPluginTest(); echo \$test->testConfiguration();"
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

This plugin is released under the MIT License.