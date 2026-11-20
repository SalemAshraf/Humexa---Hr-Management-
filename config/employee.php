<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Employee identity data
    |--------------------------------------------------------------------------
    |
    | Use a dedicated random secret (at least 32 characters) for the HMAC
    | blind index. Do not reuse APP_KEY. Keep it stable across deployments
    | and rotate it only through a planned re-indexing procedure.
    |
    */
    'identity_hash_key' => env('EMPLOYEE_IDENTITY_HASH_KEY'),

    'profile_photo_disk' => env('EMPLOYEE_PROFILE_PHOTO_DISK', 'local'),
    'document_disk' => env('EMPLOYEE_DOCUMENT_DISK', 'local'),

    'profile_photo_max_kb' => 5120,
    'allowed_profile_photo_mimes' => ['jpg', 'jpeg', 'png'],
    'document_max_kb' => 10240,
    'allowed_document_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
];
