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
    | Configuration for the LLM failover service. The local endpoint (Ollama)
    | is tried first. If it fails or times out, the request is rerouted to
    | Google Gemini API.
    |
    */

    'llm' => [
        'local_url' => env('LLM_LOCAL_URL', 'http://localhost:11434'),
        'local_model' => env('LLM_LOCAL_MODEL', 'llama3.1'),
        'local_timeout' => env('LLM_LOCAL_TIMEOUT', 60),
        'gemini_api_key' => env('GEMINI_API_KEY', ''),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

];
