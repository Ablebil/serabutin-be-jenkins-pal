<?php

return [
    'index' => [
        'success' => 'Daftar bid berhasil diambil.',
        'job_not_found' => 'Pekerjaan tidak ditemukan.',
    ],
    'store' => [
        'success' => 'Bid berhasil dikirim.',
        'job_not_found' => 'Pekerjaan tidak ditemukan.',
        'job_not_open' => 'Pekerjaan tidak dalam status open.',
        'already_bid' => 'Kamu sudah mengirim bid untuk pekerjaan ini.',
        'own_job' => 'Kamu tidak bisa mengirim bid pada pekerjaanmu sendiri.',
        'price_out_of_range' => 'Harga penawaran harus berada di antara budget minimal dan maksimal.',
    ],
    'cancel' => [
        'success' => 'Bid berhasil dibatalkan.',
        'not_found' => 'Bid tidak ditemukan.',
        'not_pending' => 'Bid tidak bisa dibatalkan karena statusnya bukan pending.',
    ],
    'reject' => [
        'success' => 'Bid berhasil ditolak.',
        'not_found' => 'Bid tidak ditemukan.',
        'job_not_found' => 'Pekerjaan tidak ditemukan.',
        'not_pending' => 'Bid tidak bisa ditolak karena statusnya bukan pending.',
    ],
    'accept' => [
        'success' => 'Bid berhasil diterima.',
        'not_found' => 'Bid tidak ditemukan.',
        'job_not_found' => 'Pekerjaan tidak ditemukan.',
        'job_not_open' => 'Pekerjaan tidak bisa menerima bid.',
        'not_pending' => 'Bid tidak bisa diterima karena statusnya bukan pending.',
        'slots_full' => 'Kuota pekerja sudah penuh.',
    ],
];
