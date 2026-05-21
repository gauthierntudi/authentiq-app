<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stockage des documents scannés (pages, QR)
    |--------------------------------------------------------------------------
    | uploads = public/uploads (local / dev)
    | s3      = bucket AWS (Laravel Cloud production)
    */
    'documents_disk' => env('AUTHENTIQ_DOCUMENTS_DISK', 'uploads'),

    /*
    |--------------------------------------------------------------------------
    | Préfixe des clés S3 / chemins relatifs
    |--------------------------------------------------------------------------
    */
    'documents_path_prefix' => env('AUTHENTIQ_DOCUMENTS_PREFIX', 'fileAuthentiq'),

    /*
    |--------------------------------------------------------------------------
    | File d'attente (Textract, jobs lourds — phase 3)
    |--------------------------------------------------------------------------
    */
    /*
    | Nom de la file (colonne jobs.queue), pas QUEUE_CONNECTION.
    | Worker : php artisan queue:work --queue=default
    */
    'queue' => env('AUTHENTIQ_QUEUE', 'default'),

    'otp_ttl_minutes' => (int) env('AUTHENTIQ_OTP_TTL_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Twilio / WhatsApp (OTP utilisateurs et clients)
    |--------------------------------------------------------------------------
    */
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
    ],

    'whatsapp' => [
        'sender' => env('TWILIO_WHATSAPP_SENDER'),
        'template_sid' => env('TWILIO_WHATSAPP_TEMPLATE_SID'),
        'otp_variable_key' => env('TWILIO_WHATSAPP_OTP_VARIABLE', '1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Expéditeur des e-mails transactionnels (OTP, etc.)
    |--------------------------------------------------------------------------
    */
    'mail' => [
        'from_address' => env('AUTHENTIQ_MAIL_FROM', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('AUTHENTIQ_MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'AuthentiQ')),
    ],

    'aws_region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

    /*
    |--------------------------------------------------------------------------
    | Amazon Textract (OCR serveur, file d'attente)
    |--------------------------------------------------------------------------
    */
    'textract_enabled' => filter_var(env('AUTHENTIQ_TEXTRACT_ENABLED', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Amazon Rekognition (recherche client par photo)
    |--------------------------------------------------------------------------
    */
    'rekognition_enabled' => filter_var(env('AUTHENTIQ_REKOGNITION_ENABLED', false), FILTER_VALIDATE_BOOL),
    'rekognition_collection_id' => env('AUTHENTIQ_REKOGNITION_COLLECTION', 'authentiq-clients'),
    'rekognition_min_similarity' => (float) env('AUTHENTIQ_REKOGNITION_MIN_SIMILARITY', 80),

];
