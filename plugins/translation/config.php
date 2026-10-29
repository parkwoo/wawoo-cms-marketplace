<?php
return [
    'enabled' => false,
    'target_language' => 'en',
    'source_language' => 'ja',
    'provider' => 'openai',
    'model' => 'gpt-3.5-turbo',
    'api_key' => '',
    'auto_translate' => false,
    'validate_translation' => true,
    'fallback_to_original' => true,
    'max_retries' => 3,
    'timeout' => 30,
    'batch_size' => 5,
    'excluded_fields' => ['title', 'slug', 'date', 'author'],
    'custom_prompts' => [
        'default' => 'Translate the following Japanese content to natural-sounding English. Maintain the original meaning and formatting.',
        'technical' => 'Translate technical Japanese content to English with precise terminology.',
        'casual' => 'Translate casual Japanese content to natural, conversational English.'
    ]
];