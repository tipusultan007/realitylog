<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;

/**
 * Used when no malware scanner is configured. The upload path still enforces
 * MIME, extension, and size validation; this class never claims a file is safe.
 */
class NullFileScanner implements FileScanner
{
    public function scan(UploadedFile $file): void
    {
        //
    }
}
