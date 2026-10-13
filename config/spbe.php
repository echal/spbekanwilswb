<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Target Indikator 186 per Tahun
    |--------------------------------------------------------------------------
    |
    | Indikator 186: Persentase pemenuhan infrastruktur dari layanan dukungan TIK
    | (komponen: Jaringan, Aplikasi, Cloud Server, Perangkat Pegawai).
    |
    | Persentase di sini diukur TERHADAP BASELINE tahun sebelumnya, bukan terhadap
    | target kuantitatif tahunan di Rencana TIK. Isi `tambahan_triwulan` dengan target
    | tambahan tiap triwulan; target kumulatif dan target tahunan dihitung darinya.
    |
    | Tahun yang tidak terdaftar dianggap belum memiliki target.
    |
    */

    'target_indikator' => [
        // Target usulan 2026 berdasarkan baseline 2025 (kumulatif: 20, 60, 75, 85)
        2026 => [
            'tambahan_triwulan' => [1 => 20.0, 2 => 40.0, 3 => 15.0, 4 => 10.0],
        ],
    ],

];
