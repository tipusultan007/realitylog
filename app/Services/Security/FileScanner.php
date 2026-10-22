<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

interface FileScanner
{
    /**
     * @throws ValidationException when the file is unsafe
     */
    public function scan(UploadedFile $file): void;
}
