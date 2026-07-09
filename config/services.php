<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Inference Services
    |--------------------------------------------------------------------------
    |
    | Configuration for the LLM failover service. The local endpoint (llama.cpp)
    | is tried first using the OpenAI-compatible API format. If it fails or
    | times out, the request is rerouted to Google Gemini API.
    |
    | Note: llama.cpp serves a fixed model (e.g., qwen3.6:35b) configured
    | at server startup time, so no model selection is needed in requests.
    |
    */

    'llm' => [
        'local_url' => env('LLM_LOCAL_URL', 'http://localhost:8080'),
        'local_max_tokens' => env('LLM_LOCAL_MAX_TOKENS', 4096),
        'local_timeout' => env('LLM_LOCAL_TIMEOUT', 120),
        'local_temperature' => env('LLM_LOCAL_TEMPERATURE', 0.7),
        'local_max_completion_tokens' => env('LLM_LOCAL_MAX_COMPLETION_TOKENS', 4096),
        'local_reasoning' => env('LLM_LOCAL_REASONING', false),
        'gemini_api_key' => env('GEMINI_API_KEY', ''),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

];
