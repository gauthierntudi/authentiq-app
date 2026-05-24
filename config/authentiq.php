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

    /*
    |--------------------------------------------------------------------------
    | Changement photo client mobile (même personne + présence physique)
    |--------------------------------------------------------------------------
    */
    'face_liveness_enabled' => filter_var(env('AUTHENTIQ_FACE_LIVENESS_ENABLED', false), FILTER_VALIDATE_BOOL),
    'face_liveness_s3_bucket' => env('AUTHENTIQ_FACE_LIVENESS_S3_BUCKET', env('AWS_BUCKET')),
    'face_liveness_s3_prefix' => env('AUTHENTIQ_FACE_LIVENESS_S3_PREFIX', 'face-liveness/'),
    'face_liveness_min_confidence' => (float) env('AUTHENTIQ_FACE_LIVENESS_MIN_CONFIDENCE', 90),
    'photo_verification_ttl_minutes' => (int) env('AUTHENTIQ_PHOTO_VERIFICATION_TTL_MINUTES', 10),
    'photo_presence_min_frames' => (int) env('AUTHENTIQ_PHOTO_PRESENCE_MIN_FRAMES', 3),
    'cognito_identity_pool_id' => env('AWS_COGNITO_IDENTITY_POOL_ID'),

    /*
    |--------------------------------------------------------------------------
    | Microservice PDF (Rust / Fly.io) — rasterisation fichiers lourds
    |--------------------------------------------------------------------------
    */
    'pdf_service' => [
        'enabled' => filter_var(env('AUTHENTIQ_PDF_SERVICE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'url' => rtrim((string) env('AUTHENTIQ_PDF_SERVICE_URL', ''), '/'),
        'api_key' => env('AUTHENTIQ_PDF_SERVICE_API_KEY', ''),
        'timeout' => (int) env('AUTHENTIQ_PDF_SERVICE_TIMEOUT', 120),
        'min_bytes' => (int) env('AUTHENTIQ_PDF_SERVICE_MIN_BYTES', 2 * 1024 * 1024),
        'dpi' => (int) env('AUTHENTIQ_PDF_SERVICE_DPI', 150),
        'max_pages' => (int) env('AUTHENTIQ_PDF_SERVICE_MAX_PAGES', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | API mobile client (Flutter) — durée des tokens Bearer
    |--------------------------------------------------------------------------
    */
    'client_token_ttl_days' => (int) env('AUTHENTIQ_CLIENT_TOKEN_TTL_DAYS', 30),

];
