<?php

return [
    'admin' => [
        'username' => env('APP_INITIAL_ADMIN_USERNAME'),
        'nama' => env('APP_INITIAL_ADMIN_NAME', 'Administrator'),
        'email' => env('APP_INITIAL_ADMIN_EMAIL'),
        'password' => env('APP_INITIAL_ADMIN_PASSWORD'),
    ],
    'kesiswaan' => [
        'username' => env('APP_INITIAL_KESISWAAN_USERNAME'),
        'nama' => env('APP_INITIAL_KESISWAAN_NAME', 'Petugas Kesiswaan'),
        'password' => env('APP_INITIAL_KESISWAAN_PASSWORD'),
    ],
    'absensi' => [
        'username' => env('APP_INITIAL_ABSENSI_USERNAME'),
        'nama' => env('APP_INITIAL_ABSENSI_NAME', 'Petugas Absensi'),
        'password' => env('APP_INITIAL_ABSENSI_PASSWORD'),
    ],
];
