<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    |
    | Demo mode exposes the sample account on the sign-in screen. It is off by
    | default and must be explicitly enabled through the environment. Leave it
    | disabled in production.
    |
    */

    'demo' => (bool) env('REALITYLOG_DEMO', false),

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    |
    | Human-readable limits are used in marketing and help copy. Validation
    | still enforces the same limits in kilobytes at the request layer.
    |
    */

    'uploads' => [
        'image_max_kb' => 15360,
        'document_max_kb' => 15360,
        'voice_max_kb' => 25600,

        // Supported: "null", "clamav". A null scanner still validates MIME,
        // extension, and size, but it does not scan file contents.
        'scanner' => env('UPLOAD_SCANNER', 'null'),
        'clamav_binary' => env('CLAMAV_BINARY', 'clamdscan'),
    ],

    'backup' => [
        'directory' => storage_path('app/backups'),
        'mysql_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
        'postgres_binary' => env('PG_DUMP_BINARY', 'pg_dump'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local AI Runtime
    |--------------------------------------------------------------------------
    |
    | OCR, transcription, and embeddings run on this server. Nothing is sent to
    | a third party. The binaries and Python packages are optional; when they
    | are missing, capture still preserves the original and asks for review.
    |
    */

    'ai' => [
        'enabled' => (bool) env('LOCAL_AI_ENABLED', true),

        'python' => env(
            'AI_PYTHON',
            PHP_OS_FAMILY === 'Windows'
                ? storage_path('app/ai-venv/Scripts/python.exe')
                : 'python3'
        ),

        'tesseract_binary' => env(
            'TESSERACT_BINARY',
            PHP_OS_FAMILY === 'Windows'
                ? 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'
                : 'tesseract'
        ),
        'tessdata_dir' => env('TESSDATA_DIR', storage_path('app/tessdata')),
        'ocr_languages' => env('OCR_LANGUAGES', 'eng+ben'),
        'max_ocr_pages' => (int) env('OCR_MAX_PAGES', 10),

        'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),
        'pdftoppm_binary' => env('PDFTOPPM_BINARY', 'pdftoppm'),

        'whisper_model' => env('WHISPER_MODEL', 'base'),
        'whisper_device' => env('WHISPER_DEVICE', 'cpu'),
        'whisper_compute_type' => env('WHISPER_COMPUTE_TYPE', 'int8'),

        'embedding_model' => env(
            'EMBEDDING_MODEL',
            'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2'
        ),
        'embedding_dimensions' => (int) env('EMBEDDING_DIMENSIONS', 384),

        // Vector matches below this cosine score are treated as unrelated.
        // Calibrated against the local multilingual model: strong matches are
        // usually 0.45 and above, unrelated content stays near 0.10.
        'semantic_search_threshold' => (float) env('SEMANTIC_SEARCH_THRESHOLD', 0.45),
        'semantic_score_weight' => (float) env('SEMANTIC_SCORE_WEIGHT', 10),
        'relationship_similarity_threshold' => (float) env('RELATIONSHIP_SIMILARITY_THRESHOLD', 0.40),
        'relationship_suggestion_limit' => (int) env('RELATIONSHIP_SUGGESTION_LIMIT', 3),

        'timeout' => (int) env('LOCAL_AI_TIMEOUT', 600),
    ],

];
