<?php

namespace Tests\Feature\Spbe;

use App\Filament\Resources\RencanaTiks\Pages\EditRencanaTik;
use App\Filament\Resources\RencanaTiks\Pages\ListRencanaTiks;
use App\Models\RealisasiTik;
use App\Models\RencanaTik;
use App\Models\RencanaTikItem;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Fase 2A: item rencana yang memiliki realisasi tidak boleh dihapus atau diubah kategorinya,
 * dan realisasi tidak boleh ikut terhapus lewat penghapusan berantai.
 */
class IntegritasRealisasiTest extends SpbeTestCase
{
    protected RencanaTik $rencana;

    protected RencanaTikItem $itemBerealisasi;

    protected RencanaTikItem $itemBebas;

    protected RealisasiTik $realisasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rencana = $this->buatRencana(2026, 'disetujui', [
            [$this->unitA, 'perangkat', 20],
            [$this->unitA, 'jaringan', 5],
        ]);
        $this->itemBerealisasi = $this->rencana->items->firstWhere('kategori', 'perangkat');
        $this->itemBebas = $this->rencana->items->firstWhere('kategori', 'jaringan');

        $this->realisasi = $this->buatRealisasi($this->itemBerealisasi, 7, 'disetujui', '2026-03-31');
        $this->realisasi->update([
            'eviden_file' => 'realisasi-tik/eviden/bukti.pdf',
            'eviden_url' => 'https://drive.example/bukti',
            'nomor_dokumen' => '023/TIK/2026',
            'keterangan' => 'Pengadaan laptop',
        ]);
    }

    protected function snapshotRealisasi(): array
    {
        return RealisasiTik::query()->orderBy('id')->get()
            ->map(fn (RealisasiTik $r) => $r->only([
                'id', 'rencana_item_id', 'jumlah_realisasi', 'tanggal_realisasi', 'status_verifikasi',
                'eviden_file', 'eviden_url', 'nomor_dokumen', 'keterangan', 'dibuat_oleh', 'updated_at',
            ]))
            ->map(fn (array $r) => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v, $r))
            ->all();
    }

    protected function editSebagai(User $user)
    {
        $this->actingAs($user);

        return Livewire::test(EditRencanaTik::class, ['record' => $this->rencana->getRouteKey()]);
    }

    protected function kunci(RencanaTikItem $item): string
    {
        return "record-{$item->getKey()}";
    }

    // 1 + 2: perubahan kategori ditolak walau field dikunci di UI dimanipulasi lewat request
    public function test_admin_tidak_dapat_mengubah_kategori_item_berealisasi_termasuk_lewat_manipulasi_request(): void
    {
        $sebelum = $this->snapshotRealisasi();
        $halaman = $this->editSebagai($this->admin)
            ->assertFormFieldDisabled("items.{$this->kunci($this->itemBerealisasi)}.kategori")
            ->assertFormFieldEnabled("items.{$this->kunci($this->itemBebas)}.kategori");

        $halaman
            ->set("data.nama_rencana", 'Nama diubah bersamaan')
            ->set("data.items.{$this->kunci($this->itemBerealisasi)}.kategori", 'aplikasi')
            ->call('save')
            ->assertHasErrors(["data.items.{$this->kunci($this->itemBerealisasi)}.kategori"]);

        $this->assertSame('perangkat', $this->itemBerealisasi->fresh()->kategori);
        // Tidak ada penulisan parsial: header rencana juga tidak berubah
        $this->assertNotSame('Nama diubah bersamaan', $this->rencana->fresh()->nama_rencana);
        $this->assertSame($sebelum, $this->snapshotRealisasi());
    }

    public function test_perubahan_kategori_langsung_lewat_model_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->itemBerealisasi->update(['kategori' => 'aplikasi']);
        } finally {
            $this->assertSame('perangkat', $this->itemBerealisasi->fresh()->kategori);
        }
    }

    // 3, 4, 5
    public function test_item_dengan_realisasi_status_apa_pun_tidak_dapat_dihapus(): void
    {
        foreach (['menunggu', 'disetujui', 'ditolak'] as $status) {
            $rencana = $this->buatRencana(2026, 'disetujui', [[$this->unitA, 'server', 3], [$this->unitA, 'jaringan', 2]]);
            $item = $rencana->items->firstWhere('kategori', 'server');
            $realisasi = $this->buatRealisasi($item, 1, $status, '2026-02-01');

            // Lewat repeater (form Admin)
            $this->actingAs($this->admin);
            $halaman = Livewire::test(EditRencanaTik::class, ['record' => $rencana->getRouteKey()])
                ->assertActionHidden(TestAction::make('delete')->schemaComponent('items')->arguments(['item' => "record-{$item->id}"]));

            $state = $halaman->get('data.items');
            unset($state["record-{$item->id}"]);

            $halaman->set('data.items', $state)
                ->call('save')
                ->assertHasErrors(['data.items']);

            $this->assertModelExists($item);
            $this->assertModelExists($realisasi);

            // Lewat model langsung
            $this->assertFalse($item->fresh()->delete(), "Item dengan realisasi {$status} tidak boleh terhapus");
            $this->assertModelExists($realisasi);
        }
    }

    // 6
    public function test_item_tanpa_realisasi_tetap_dapat_dikelola(): void
    {
        $halaman = $this->editSebagai($this->admin)
            ->assertActionVisible(TestAction::make('delete')->schemaComponent('items')->arguments(['item' => $this->kunci($this->itemBebas)]));

        // Admin mengubah kategori item tanpa realisasi
        $halaman->set("data.items.{$this->kunci($this->itemBebas)}.kategori", 'server')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('server', $this->itemBebas->fresh()->kategori);

        // Admin menghapus item tanpa realisasi; item berealisasi tetap
        $halaman = $this->editSebagai($this->admin);
        $state = $halaman->get('data.items');
        unset($state[$this->kunci($this->itemBebas)]);
        $halaman->set('data.items', $state)->call('save')->assertHasNoErrors();

        $this->assertModelMissing($this->itemBebas);
        $this->assertModelExists($this->itemBerealisasi);

        // Operator pada rencana draft unitnya tetap bisa menghapus item tanpa realisasi
        $draft = $this->buatRencana(2026, 'draft', [[$this->unitA, 'perangkat', 2], [$this->unitA, 'server', 1]], $this->operatorA);
        $this->actingAs($this->operatorA);
        $halaman = Livewire::test(EditRencanaTik::class, ['record' => $draft->getRouteKey()]);
        $state = $halaman->get('data.items');
        array_pop($state);
        $halaman->set('data.items', $state)->call('save')->assertHasNoErrors();
        $this->assertSame(1, $draft->items()->count());
    }

    // 7 + 8
    public function test_menyimpan_ulang_rencana_tidak_mengubah_realisasi_dan_eviden(): void
    {
        $sebelum = $this->snapshotRealisasi();

        // Admin menyimpan ulang rencana sambil mengubah field yang diizinkan pada item berealisasi
        $this->editSebagai($this->admin)
            ->set('data.keterangan', 'Catatan baru')
            ->set("data.items.{$this->kunci($this->itemBerealisasi)}.keterangan", 'Keterangan item diperbarui')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Catatan baru', $this->rencana->fresh()->keterangan);
        $this->assertSame('Keterangan item diperbarui', $this->itemBerealisasi->fresh()->keterangan);
        $this->assertSame('perangkat', $this->itemBerealisasi->fresh()->kategori);
        $this->assertSame($sebelum, $this->snapshotRealisasi());
    }

    // Jalur lain: hapus rencana (aksi tabel, bulk, header) tidak boleh menghapus realisasi berantai
    public function test_rencana_dengan_realisasi_tidak_dapat_dihapus_lewat_aksi_apa_pun(): void
    {
        $tanpaRealisasi = $this->buatRencana(2026, 'draft', [[$this->unitA, 'server', 1]]);
        $sebelum = $this->snapshotRealisasi();

        $this->actingAs($this->admin);

        Livewire::test(ListRencanaTiks::class)->callTableAction('delete', $this->rencana);
        $this->assertModelExists($this->rencana);

        Livewire::test(ListRencanaTiks::class)->callTableBulkAction('delete', [$this->rencana, $tanpaRealisasi]);
        $this->assertModelExists($this->rencana);
        $this->assertModelMissing($tanpaRealisasi);

        Livewire::test(EditRencanaTik::class, ['record' => $this->rencana->getRouteKey()])->callAction('delete');
        $this->assertModelExists($this->rencana);

        $this->assertSame(2, $this->rencana->items()->count());
        $this->assertSame($sebelum, $this->snapshotRealisasi());
    }

    public function test_hapus_unit_kerja_atau_pengguna_yang_terkait_realisasi_dibatalkan(): void
    {
        // unit_kerja → rencana_tik_items (CASCADE) → realisasi_tik (CASCADE)
        $this->assertFalse($this->unitA->delete());
        $this->assertModelExists($this->unitA);

        // users → realisasi_tik.dibuat_oleh (CASCADE) & rencana_tik.created_by (CASCADE)
        $this->assertFalse($this->admin->delete());
        $this->assertModelExists($this->admin);

        // Unit dan pengguna tanpa keterkaitan dengan realisasi tetap dapat dihapus
        $unitKosong = UnitKerja::create(['nama_unit' => 'Unit Kosong', 'kode_unit' => 'UK']);
        $this->assertTrue($unitKosong->delete());
        $penggunaLain = $this->buatUser('Operator', $this->unitB->id);
        $this->assertTrue($penggunaLain->delete());

        $this->assertModelExists($this->realisasi);
    }

    // 9 & 10 dicakup oleh seluruh suite (SpbeTriwulanTest, AplikasiPenggunaTest, *AksesTest); di sini cek dashboard ringkas
    public function test_perhitungan_dashboard_tidak_berubah_setelah_penyimpanan_ulang(): void
    {
        $service = app(\App\Services\SpbeQuarterlyPerformanceService::class);
        $sebelum = $service->getAnnualProgress(2026)['komponen'];

        $this->editSebagai($this->admin)->set('data.keterangan', 'Simpan ulang')->call('save')->assertHasNoErrors();

        $this->assertSame($sebelum, $service->getAnnualProgress(2026)['komponen']);
        $this->assertSame(35.0, $sebelum['perangkat']['persentase']);
    }
}
