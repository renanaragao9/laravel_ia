<?php

return [
    'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/auto'),
        'referer' => env('OPENROUTER_REFERER', env('APP_URL')),
        'title' => env('OPENROUTER_TITLE', env('APP_NAME')),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 120),
        'max_steps' => (int) env('OPENROUTER_MAX_STEPS', 8),
        'temperature' => (float) env('OPENROUTER_TEMPERATURE', 0.6),
    ],

    'mcp' => [
        'url' => env('MCP_BICHO_URL'),
        'token' => env('MCP_BICHO_TOKEN'),
    ],

    'results' => [
        'default_lottery' => env('RESULTS_DEFAULT_LOTTERY', 'rio'),
        'scraper' => [
            'url' => env('RESULTS_SCRAPER_URL'),
            'rows_xpath' => env('RESULTS_SCRAPER_ROWS_XPATH', '//table//tr'),
            'time_column' => (int) env('RESULTS_SCRAPER_TIME_COLUMN', 0),
            'prize_columns' => array_map('intval', array_filter(explode(',', (string) env('RESULTS_SCRAPER_PRIZE_COLUMNS', '1,2,3,4,5')), fn ($value) => $value !== '')),
            'lottery' => env('RESULTS_SCRAPER_LOTTERY', 'rio'),
            'user_agent' => env('RESULTS_SCRAPER_USER_AGENT', 'laravel-ia/1.0'),
            'timeout' => (int) env('RESULTS_SCRAPER_TIMEOUT', 20),
        ],
    ],

    'rag' => [
        'chunk_size' => 700,
        'min_score' => 0.05,
    ],
];
