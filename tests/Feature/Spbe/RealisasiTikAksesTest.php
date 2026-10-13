<?php

namespace Tests\Feature\Spbe;

use App\Filament\Resources\RealisasiTiks\Pages\CreateRealisasiTik;
use App\Filament\Resources\RealisasiTiks\Pages\EditRealisasiTik;
use App\Filament\Resources\RealisasiTiks\Pages\ListRealisasiTiks;
use App\Filament\Resources\RealisasiTiks\RealisasiTikResource;
use App\Models\RealisasiTik;
use Livewire\Livewire;

class RealisasiTikAksesTest extends SpbeTestCase
{
    protected RealisasiTik $realisasiA;

    protected RealisasiTik $realisasiB;

    protected function setUp(): void
    {
        parent::setUp();

        $rencana = $this->buatRencana(2026, 'disetujui', [
            [$this->unitA, 'perangkat', 10],
            [$this->unitB, 'jaringan', 5],
        ]);

        [$itemA, $itemB] = $rencana->items->sortBy('id')->values()->all();

        $this->realisasiA = $this->buatRealisasi($itemA, 2);
        $this->realisasiB = $this->buatRealisasi($itemB, 1);
    }

    // ---------------------------------------------------------------- Operator: cakupan unit

    public function test_operator_hanya_melihat_realisasi_unitnya(): void
    {
        $this->actingAs($this->operatorA);

        Livewire::test(ListRealisasiTiks::class)
            ->assertCanSeeTableRecords([$this->realisasiA])
            ->assertCanNotSeeTableRecords([$this->realisasiB]);
    }

    public function test_operator_tidak_bisa_membuka_edit_realisasi_unit_lain(): void
    {
        $this->actingAs($this->operatorA)
            ->get(RealisasiTikResource::getUrl('edit', ['record' => $this->realisasiB]))
            ->assertNotFound();
    }

    public function test_operator_bisa_membuka_edit_realisasi_unitnya_yang_menunggu(): void
    {
        $this->actingAs($this->operatorA)
            ->get(RealisasiTikResource::getUrl('edit', ['record' => $this->realisasiA]))
            ->assertOk();
    }

    public function test_operator_tidak_bisa_menghapus_realisasi_unit_lain_lewat_server(): void
    {
        $this->assertFalse($this->operatorA->can('delete', $this->realisasiB));
        $this->assertFalse($this->operatorA->can('update', $this->realisasiB));
        $this->assertTrue($this->operatorA->can('delete', $this->realisasiA));
    }

    public function test_operator_tidak_bisa_membuat_realisasi_untuk_item_unit_lain(): void
    {
        $this->actingAs($this->operatorA);

        Livewire::test(CreateRealisasiTik::class)
            ->fillForm([
                'rencana_item_id' => $this->realisasiB->rencana_item_id,
                'jumlah_realisasi' => 1,
                'tanggal_realisasi' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasFormErrors(['rencana_item_id']);

        $this->assertSame(2, RealisasiTik::count());
    }

    public function test_operator_bisa_membuat_realisasi_untuk_item_unitnya(): void
    {
        $this->actingAs($this->operatorA);

        Livewire::test(CreateRealisasiTik::class)
            ->fillForm([
                'rencana_item_id' => $this->realisasiA->rencana_item_id,
                'jumlah_realisasi' => 1,
                'tanggal_realisasi' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('realisasi_tik', [
            'rencana_item_id' => $this->realisasiA->rencana_item_id,
            'jumlah_realisasi' => 1,
            'status_verifikasi' => 'menunggu',
            'dibuat_oleh' => $this->operatorA->id,
        ]);
    }

    public function test_operator_tidak_bisa_menyetujui_realisasi(): void
    {
        $this->actingAs($this->operatorA);

        $halaman = Livewire::test(ListRealisasiTiks::class)
            ->assertTableActionHidden('setujui', $this->realisasiA);

        $this->paksaAksiTabel($halaman, 'setujui', $this->realisasiA);

        $this->assertSame('menunggu', $this->realisasiA->fresh()->status_verifikasi);
    }

    // ---------------------------------------------------------------- Operator tanpa unit

    public function test_operator_tanpa_unit_tidak_melihat_data_dan_tidak_bisa_membuat(): void
    {
        $this->actingAs($this->operatorTanpaUnit);

        Livewire::test(ListRealisasiTiks::class)
            ->assertCanNotSeeTableRecords([$this->realisasiA, $this->realisasiB])
            ->assertCountTableRecords(0);

        $this->get(RealisasiTikResource::getUrl('create'))->assertForbidden();
        $this->get(RealisasiTikResource::getUrl('edit', ['record' => $this->realisasiA]))->assertNotFound();
    }

    // ---------------------------------------------------------------- Auditor: read-only

    public function test_auditor_melihat_semua_realisasi(): void
    {
        $this->actingAs($this->auditor);

        Livewire::test(ListRealisasiTiks::class)
            ->assertCanSeeTableRecords([$this->realisasiA, $this->realisasiB]);
    }

    public function test_auditor_tidak_bisa_membuat_atau_membuka_edit(): void
    {
        $this->actingAs($this->auditor);

        $this->get(RealisasiTikResource::getUrl('create'))->assertForbidden();
        $this->get(RealisasiTikResource::getUrl('edit', ['record' => $this->realisasiA]))->assertForbidden();
    }

    public function test_auditor_tidak_punya_aksi_tulis_dan_aksi_server_ditolak(): void
    {
        $this->actingAs($this->auditor);

        $halaman = Livewire::test(ListRealisasiTiks::class)
            ->assertTableActionHidden('edit', $this->realisasiA)
            ->assertTableActionHidden('delete', $this->realisasiA)
            ->assertTableActionHidden('setujui', $this->realisasiA)
            ->assertTableActionHidden('tolak', $this->realisasiA)
            ->assertTableBulkActionHidden('delete');

        // Jalur server: aksi dipanggil paksa meskipun tombolnya tersembunyi
        $this->paksaAksiTabel($halaman, 'delete', $this->realisasiA);
        $this->paksaAksiTabel($halaman, 'setujui', $this->realisasiA);
        $this->paksaAksiBulk($halaman, 'delete', [$this->realisasiA, $this->realisasiB]);

        $this->assertSame(2, RealisasiTik::count());
        $this->assertSame('menunggu', $this->realisasiA->fresh()->status_verifikasi);

        foreach (['update', 'delete', 'verify'] as $ability) {
            $this->assertFalse($this->auditor->can($ability, $this->realisasiA), "Auditor tidak boleh {$ability}");
        }
        $this->assertFalse($this->auditor->can('create', RealisasiTik::class));
    }

    // ---------------------------------------------------------------- Admin tetap berfungsi

    public function test_admin_bisa_menyetujui_dan_menolak(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListRealisasiTiks::class)
            ->assertCanSeeTableRecords([$this->realisasiA, $this->realisasiB])
            ->callTableAction('setujui', $this->realisasiA)
            ->callTableAction('tolak', $this->realisasiB, data: ['catatan_verifikasi' => 'Eviden kurang']);

        $this->assertSame('disetujui', $this->realisasiA->fresh()->status_verifikasi);
        $this->assertSame($this->admin->id, $this->realisasiA->fresh()->diverifikasi_oleh);
        $this->assertSame('ditolak', $this->realisasiB->fresh()->status_verifikasi);
    }

    public function test_admin_bisa_edit_dan_hapus(): void
    {
        $this->actingAs($this->admin);

        $this->get(RealisasiTikResource::getUrl('edit', ['record' => $this->realisasiB]))->assertOk();

        Livewire::test(ListRealisasiTiks::class)
            ->callTableAction('delete', $this->realisasiB);

        $this->assertModelMissing($this->realisasiB);
    }

    public function test_kontrol_helper_aksi_paksa_berjalan_untuk_admin(): void
    {
        // Memastikan helper paksaAksi* benar-benar mengeksekusi aksi bila diizinkan,
        // sehingga hasil negatif pada test Auditor/Operator bukan karena helper tidak bekerja.
        $this->actingAs($this->admin);

        $halaman = Livewire::test(ListRealisasiTiks::class);
        $this->paksaAksiTabel($halaman, 'setujui', $this->realisasiA);
        $this->assertSame('disetujui', $this->realisasiA->fresh()->status_verifikasi);

        $this->paksaAksiBulk($halaman, 'delete', [$this->realisasiA, $this->realisasiB]);
        $this->assertSame(0, RealisasiTik::count());
    }

    public function test_realisasi_disetujui_terkunci_untuk_edit(): void
    {
        $this->realisasiA->update(['status_verifikasi' => 'disetujui']);

        $this->actingAs($this->operatorA)
            ->get(RealisasiTikResource::getUrl('edit', ['record' => $this->realisasiA]))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(RealisasiTikResource::getUrl('edit', ['record' => $this->realisasiA]))
            ->assertForbidden();
    }

    public function test_simpan_edit_ditolak_bila_status_berubah_setelah_halaman_dibuka(): void
    {
        $this->actingAs($this->operatorA);

        $halaman = Livewire::test(EditRealisasiTik::class, ['record' => $this->realisasiA->getRouteKey()]);

        // Admin menyetujui saat Operator masih membuka halaman edit
        $this->realisasiA->update(['status_verifikasi' => 'disetujui']);

        $halaman->fillForm(['jumlah_realisasi' => 3])
            ->call('save')
            ->assertForbidden();

        $this->assertSame(2, $this->realisasiA->fresh()->jumlah_realisasi);
    }
}
