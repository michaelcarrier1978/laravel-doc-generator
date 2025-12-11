<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anthropic API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Anthropic API credentials for Claude AI integration.
    | Get your API key from: https://console.anthropic.com/settings/keys
    |
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-haiku-20240307'),
        'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 1000),
        'timeout' => env('ANTHROPIC_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Confluence Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Confluence instance for publishing documentation.
    | Get your API token from: https://id.atlassian.com/manage-profile/security/api-tokens
    |
    */

    'confluence' => [
        'base_url' => env('CONFLUENCE_BASE_URL'),
        'email' => env('CONFLUENCE_EMAIL'),
        'api_token' => env('CONFLUENCE_API_TOKEN'),
        'space_key' => env('CONFLUENCE_SPACE_KEY'),
        'timeout' => env('CONFLUENCE_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Options
    |--------------------------------------------------------------------------
    |
    | Customize how documentation is generated and formatted.
    |
    */

    'documentation' => [
        // Include method visibility in documentation
        'include_visibility' => true,

        // Include return types
        'include_return_types' => true,

        // Include parameter types
        'include_parameter_types' => true,

        // Generate @throws tags
        'include_throws' => true,
    ],

];
