<?php

namespace Tests\Feature\Spbe;

use App\Models\Aplikasi;
use App\Models\Pegawai;
use App\Models\PegawaiAplikasi;
use App\Models\RealisasiTik;
use App\Models\RencanaTik;
use App\Models\RencanaTikItem;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fixture bersama untuk test SPBE. Seluruh data dibuat di SQLite :memory:.
 */
abstract class SpbeTestCase extends TestCase
{
    use RefreshDatabase;

    protected UnitKerja $unitA;

    protected UnitKerja $unitB;

    protected User $admin;

    protected User $auditor;

    protected User $operatorA;

    protected User $operatorB;

    protected User $operatorTanpaUnit;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Operator', 'Auditor', 'Pimpinan'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $this->unitA = UnitKerja::create(['nama_unit' => 'Unit A', 'kode_unit' => 'UA']);
        $this->unitB = UnitKerja::create(['nama_unit' => 'Unit B', 'kode_unit' => 'UB']);

        $this->admin = $this->buatUser('Admin');
        $this->auditor = $this->buatUser('Auditor');
        $this->operatorA = $this->buatUser('Operator', $this->unitA->id);
        $this->operatorB = $this->buatUser('Operator', $this->unitB->id);
        $this->operatorTanpaUnit = $this->buatUser('Operator');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function buatUser(string $role, ?int $managedUnitId = null): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'managed_unit_kerja_id' => $managedUnitId,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function buatRencana(int $tahun, string $status, array $items, ?User $pembuat = null): RencanaTik
    {
        $rencana = RencanaTik::create([
            'tahun' => $tahun,
            'nama_rencana' => "Rencana {$tahun} {$status}",
            'status' => $status,
            'created_by' => ($pembuat ?? $this->admin)->id,
        ]);

        foreach ($items as [$unit, $kategori, $jumlah]) {
            RencanaTikItem::create([
                'rencana_tik_id' => $rencana->id,
                'kategori' => $kategori,
                'unit_kerja_id' => $unit->id,
                'nama_item' => "Item {$kategori} {$unit->kode_unit}",
                'jumlah_direncanakan' => $jumlah,
            ]);
        }

        return $rencana->fresh();
    }

    /**
     * Memanggil aksi tabel langsung lewat method Livewire (meniru request yang dimanipulasi klien),
     * tanpa pengecekan visibilitas yang dilakukan helper callTableAction().
     */
    protected function paksaAksiTabel(Testable $livewire, string $nama, Model $record): Testable
    {
        return $livewire
            ->call('mountAction', $nama, [], ['table' => true, 'recordKey' => (string) $record->getKey()])
            ->call('callMountedAction');
    }

    protected function paksaAksiBulk(Testable $livewire, string $nama, array $records): Testable
    {
        return $livewire
            ->set('selectedTableRecords', array_map(fn (Model $r) => (string) $r->getKey(), $records))
            ->call('mountAction', $nama, [], ['table' => true, 'bulk' => true])
            ->call('callMountedAction');
    }

    /**
     * Membuat sejumlah pegawai uji pada unit tertentu.
     *
     * @return \Illuminate\Support\Collection<int, Pegawai>
     */
    protected function buatPegawai(int $jumlah, ?UnitKerja $unit = null): \Illuminate\Support\Collection
    {
        return collect(range(1, $jumlah))->map(function () use ($unit) {
            $urut = ++static::$nomorPegawai;

            return Pegawai::create([
                'nip' => sprintf('UJI%015d', $urut),
                'nama' => "Pegawai Uji {$urut}",
                'jabatan' => 'Staf',
                'unit_kerja_id' => ($unit ?? $this->unitA)->id,
            ]);
        });
    }

    protected static int $nomorPegawai = 0;

    protected function buatAplikasi(string $nama): Aplikasi
    {
        return Aplikasi::create([
            'nama_aplikasi' => $nama,
            'jenis' => 'Internal',
            'basis' => 'Web',
            'tingkat_kritis' => 'Sedang',
        ]);
    }

    protected function beriAkses(Pegawai $pegawai, Aplikasi $aplikasi, string $tanggal, string $status = 'Aktif', string $peran = 'User'): PegawaiAplikasi
    {
        return PegawaiAplikasi::create([
            'pegawai_id' => $pegawai->id,
            'aplikasi_id' => $aplikasi->id,
            'peran_pengguna' => $peran,
            'status_akses' => $status,
            'tanggal_diberikan' => $tanggal,
        ]);
    }

    protected function buatRealisasi(RencanaTikItem $item, int $jumlah, string $status = 'menunggu', ?string $tanggal = null): RealisasiTik
    {
        return RealisasiTik::create([
            'rencana_item_id' => $item->id,
            'jumlah_realisasi' => $jumlah,
            'tanggal_realisasi' => $tanggal ?? now()->toDateString(),
            'status_verifikasi' => $status,
            'dibuat_oleh' => $this->admin->id,
        ]);
    }
}
