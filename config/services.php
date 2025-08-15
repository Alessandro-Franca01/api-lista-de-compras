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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Configuração do WhatsApp Cloud API
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v18.0'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

        // Configurações de webhook
        'webhook_enabled' => env('WHATSAPP_WEBHOOK_ENABLED', true),

        // Rate limiting
        'max_messages_per_hour' => env('WHATSAPP_MAX_MESSAGES_PER_HOUR', 10),
        'max_messages_per_day' => env('WHATSAPP_MAX_MESSAGES_PER_DAY', 100),

        // Timeout e retry
        'timeout' => env('WHATSAPP_TIMEOUT', 30),
        'retry_attempts' => env('WHATSAPP_RETRY_ATTEMPTS', 3),

        // URLs base
        'graph_api_url' => env('WHATSAPP_GRAPH_API_URL', 'https://graph.facebook.com'),

        // Configurações de mídia
        'max_media_size' => env('WHATSAPP_MAX_MEDIA_SIZE', 16777216), // 16MB em bytes
        'allowed_media_types' => [
            'image' => ['jpeg', 'jpg', 'png', 'webp'],
            'video' => ['mp4', '3gp'],
            'audio' => ['aac', 'amr', 'mp3', 'ogg'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt']
        ],

        // Templates aprovados (configurar conforme seus templates)
        'approved_templates' => [
            'welcome' => [
                'name' => 'welcome_message',
                'language' => 'pt_BR',
                'parameters_count' => 1
            ],
            'order_confirmation' => [
                'name' => 'order_confirmation',
                'language' => 'pt_BR',
                'parameters_count' => 3
            ],
            'appointment_reminder' => [
                'name' => 'appointment_reminder',
                'language' => 'pt_BR',
                'parameters_count' => 2
            ]
        ],

        // Configurações de logging
        'log_incoming_messages' => env('WHATSAPP_LOG_INCOMING', true),
        'log_outgoing_messages' => env('WHATSAPP_LOG_OUTGOING', true),
        'mask_phone_numbers_in_logs' => env('WHATSAPP_MASK_PHONE_LOGS', true),

        // Configurações de segurança
        'webhook_signature_verification' => env('WHATSAPP_VERIFY_SIGNATURE', true),
        'app_secret' => env('WHATSAPP_APP_SECRET'),

        // Configurações de cache
        'cache_media_urls' => env('WHATSAPP_CACHE_MEDIA_URLS', true),
        'media_url_cache_duration' => env('WHATSAPP_MEDIA_CACHE_DURATION', 3600), // 1 hora
    ],

];
