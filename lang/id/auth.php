<?php

return [
    'register' => [
        'success' => 'Registrasi berhasil. Silakan cek email untuk verifikasi akun.',
        'email_exists' => 'Email sudah terdaftar.',
    ],
    'verify' => [
        'success' => 'Email berhasil diverifikasi. Silakan login.',
        'invalid_or_expired' => 'Token verifikasi tidak valid atau sudah kedaluwarsa.',
    ],
    'login' => [
        'success' => 'Login berhasil.',
        'invalid_credentials' => 'Email atau password salah.',
        'email_not_verified' => 'Silakan verifikasi email terlebih dahulu.',
        'account_inactive' => 'Akun tidak aktif.',
    ],
    'refresh' => [
        'success' => 'Token berhasil diperbarui.',
        'invalid_or_expired' => 'Refresh token tidak valid atau sudah kedaluwarsa.',
    ],
    'logout' => [
        'success' => 'Logout berhasil.',
    ],
    'mail' => [
        'verify_subject' => 'Verifikasi Email Akun Serabutin',
        'verify_greeting' => 'Halo, :name',
        'verify_intro' => 'Terima kasih sudah mendaftar di ',
        'verify_action' => 'Klik tautan berikut untuk verifikasi email akun kamu:',
        'verify_button' => 'Verifikasi Email',
        'verify_outro' => 'Jika kamu tidak merasa membuat akun, abaikan email ini.',
    ],
    'validation' => [
        'email_required' => 'Email harus diisi.',
        'email_email' => 'Format email tidak valid.',
        'email_max' => 'Email maksimal 255 karakter.',
        'email_unique' => 'Email sudah terdaftar.',
        'password_required' => 'Password harus diisi.',
        'password_string' => 'Password harus berupa teks.',
        'password_min' => 'Password minimal 8 karakter.',
        'full_name_required' => 'Nama lengkap harus diisi.',
        'full_name_string' => 'Nama lengkap harus berupa teks.',
        'full_name_max' => 'Nama lengkap maksimal 100 karakter.',
        'role_required' => 'Role harus dipilih.',
        'role_in' => 'Role harus bernilai client atau worker.',
        'verify_token_required' => 'Token verifikasi harus diisi.',
        'verify_token_string' => 'Token verifikasi tidak valid.',
        'refresh_token_required' => 'Refresh token tidak ditemukan.',
        'refresh_token_string' => 'Refresh token tidak valid.',
    ],
    'jwt' => [
        'secret_missing' => 'JWT secret belum dikonfigurasi.',
        'secret_too_short' => 'JWT secret minimal 32 karakter.',
        'invalid' => 'Access token tidak valid.',
        'expired' => 'Access token sudah kedaluwarsa.',
        'signature_invalid' => 'Signature access token tidak valid.',
        'issuer_invalid' => 'Issuer access token tidak sesuai.',
        'audience_invalid' => 'Audience access token tidak sesuai.',
    ],
];
