<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RuanganSeeder extends Seeder
{
    public function run(): void
    {
        $ruangans = [
            // Kantor Wilayah - Lantai 1
            ['nama_ruangan' => 'Ruang Resepsionis', 'unit_kerja_id' => 1],
            ['nama_ruangan' => 'Ruang Kepala Kantor', 'unit_kerja_id' => 1],
            ['nama_ruangan' => 'Ruang Kasubag TU', 'unit_kerja_id' => 1],
            ['nama_ruangan' => 'Ruang Server', 'unit_kerja_id' => 1],
            ['nama_ruangan' => 'Ruang Operator', 'unit_kerja_id' => 1],
            ['nama_ruangan' => 'Ruang Rapat Utama', 'unit_kerja_id' => 1],

            // Bidang PHU - Lantai 2
            ['nama_ruangan' => 'Ruang Kabid PHU', 'unit_kerja_id' => 2],
            ['nama_ruangan' => 'Ruang Staff PHU', 'unit_kerja_id' => 2],
            ['nama_ruangan' => 'Ruang Arsip PHU', 'unit_kerja_id' => 2],

            // Bidang Pendidikan Madrasah
            ['nama_ruangan' => 'Ruang Kabid Pendma', 'unit_kerja_id' => 3],
            ['nama_ruangan' => 'Ruang Staff Pendma', 'unit_kerja_id' => 3],

            // Bidang Pendidikan Agama Islam
            ['nama_ruangan' => 'Ruang Kabid PAI', 'unit_kerja_id' => 4],
            ['nama_ruangan' => 'Ruang Staff PAI', 'unit_kerja_id' => 4],

            // Bidang Penyelenggara Haji dan Umrah
            ['nama_ruangan' => 'Ruang Kabid Haji', 'unit_kerja_id' => 5],
            ['nama_ruangan' => 'Ruang Pelayanan Haji', 'unit_kerja_id' => 5],
        ];

        foreach ($ruangans as $ruangan) {
            \App\Models\Ruangan::create($ruangan);
        }
    }
}
