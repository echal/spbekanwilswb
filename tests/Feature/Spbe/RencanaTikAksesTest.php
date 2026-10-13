<?php

namespace Tests\Feature\Spbe;

use App\Filament\Resources\RencanaTiks\Pages\CreateRencanaTik;
use App\Filament\Resources\RencanaTiks\Pages\EditRencanaTik;
use App\Filament\Resources\RencanaTiks\Pages\ListRencanaTiks;
use App\Filament\Resources\RencanaTiks\RencanaTikResource;
use App\Models\RencanaTik;
use App\Models\RencanaTikItem;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

class RencanaTikAksesTest extends SpbeTestCase
{
    // ---------------------------------------------------------------- Status disetujui/final terkunci untuk Operator

    public function test_operator_tidak_bisa_membuka_edit_rencana_disetujui_atau_final(): void
    {
        foreach (['disetujui', 'final'] as $status) {
            $rencana = $this->buatRencana(2026, $status, [[$this->unitA, 'perangkat', 3]], $this->operatorA);

            $this->actingAs($this->operatorA)
                ->get(RencanaTikResource::getUrl('edit', ['record' => $rencana]))
                ->assertForbidden();

            $this->assertFalse($this->operatorA->can('update', $rencana), "Operator tidak boleh mengubah rencana {$status}");
        }
    }

    public function test_operator_tidak_melihat_aksi_edit_dan_hapus_untuk_rencana_disetujui(): void
    {
        $rencana = $this->buatRencana(2026, 'disetujui', [[$this->unitA, 'perangkat', 3]], $this->operatorA);

        $this->actingAs($this->operatorA);

        $halaman = Livewire::test(ListRencanaTiks::class)
            ->assertCanSeeTableRecords([$rencana])
            ->assertTableActionHidden('edit', $rencana)
            ->assertTableActionHidden('delete', $rencana)
            ->assertTableBulkActionHidden('delete');

        $this->paksaAksiTabel($halaman, 'delete', $rencana);
        $this->paksaAksiBulk($halaman, 'delete', [$rencana]);

        $this->assertModelExists($rencana);
    }

    public function test_operator_bisa_edit_rencana_draft_unitnya(): void
    {
        $rencana = $this->buatRencana(2026, 'draft', [[$this->unitA, 'perangkat', 3]], $this->operatorA);

        $this->actingAs($this->operatorA)
            ->get(RencanaTikResource::getUrl('edit', ['record' => $rencana]))
            ->assertOk();
    }

    public function test_simpan_edit_ditolak_bila_rencana_disetujui_setelah_halaman_dibuka(): void
    {
        $rencana = $this->buatRencana(2026, 'draft', [[$this->unitA, 'perangkat', 3]], $this->operatorA);

        $this->actingAs($this->operatorA);

        $halaman = Livewire::test(EditRencanaTik::class, ['record' => $rencana->getRouteKey()]);

        // Admin menyetujui saat Operator masih membuka halaman edit
        RencanaTik::whereKey($rencana->id)->update(['status' => 'disetujui']);

        $halaman->fillForm(['nama_rencana' => 'Diubah setelah disetujui'])
            ->call('save')
            ->assertForbidden();

        $this->assertNotSame('Diubah setelah disetujui', $rencana->fresh()->nama_rencana);
    }

    // ---------------------------------------------------------------- Cakupan unit

    public function test_operator_hanya_melihat_rencana_unitnya(): void
    {
        $rencanaA = $this->buatRencana(2026, 'draft', [[$this->unitA, 'perangkat', 3]]);
        $rencanaB = $this->buatRencana(2026, 'draft', [[$this->unitB, 'perangkat', 3]]);
        $rencanaCampuran = $this->buatRencana(2026, 'draft', [
            [$this->unitA, 'perangkat', 1],
            [$this->unitB, 'jaringan', 1],
        ]);

        $this->actingAs($this->operatorA);

        Livewire::test(ListRencanaTiks::class)
            ->assertCanSeeTableRecords([$rencanaA])
            ->assertCanNotSeeTableRecords([$rencanaB, $rencanaCampuran]);

        $this->get(RencanaTikResource::getUrl('edit', ['record' => $rencanaB]))->assertNotFound();
        $this->get(RencanaTikResource::getUrl('edit', ['record' => $rencanaCampuran]))->assertNotFound();
    }

    public function test_operator_membuat_rencana_selalu_draft_dan_item_terkunci_ke_unitnya(): void
    {
        $undoRepeaterFake = Repeater::fake();

        $this->actingAs($this->operatorA);

        Livewire::test(CreateRencanaTik::class)
            ->fillForm([
                'tahun' => 2026,
                'nama_rencana' => 'Rencana Operator A',
                'status' => 'final',
                'items' => [[
                    'kategori' => 'perangkat',
                    // Upaya manipulasi: mengirim unit lain
                    'unit_kerja_id' => $this->unitB->id,
                    'nama_item' => 'Laptop',
                    'jumlah_direncanakan' => 2,
                    'jumlah_terpenuhi' => 0,
                    'status_realisasi' => 'belum',
                    'prioritas' => 'sedang',
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $rencana = RencanaTik::where('nama_rencana', 'Rencana Operator A')->firstOrFail();

        $this->assertSame('draft', $rencana->status);
        $this->assertSame($this->operatorA->id, (int) $rencana->created_by);
        $this->assertSame(
            [$this->unitA->id],
            RencanaTikItem::where('rencana_tik_id', $rencana->id)->pluck('unit_kerja_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_operator_tanpa_unit_tidak_bisa_membuat_atau_melihat_rencana(): void
    {
        $rencana = $this->buatRencana(2026, 'draft', [[$this->unitA, 'perangkat', 3]], $this->operatorTanpaUnit);

        $this->actingAs($this->operatorTanpaUnit);

        $this->get(RencanaTikResource::getUrl('create'))->assertForbidden();

        Livewire::test(ListRencanaTiks::class)->assertCountTableRecords(0);

        $this->get(RencanaTikResource::getUrl('edit', ['record' => $rencana]))->assertNotFound();
    }

    // ---------------------------------------------------------------- Auditor & Admin

    public function test_auditor_tidak_punya_akses_ke_rencana(): void
    {
        $rencana = $this->buatRencana(2026, 'draft', [[$this->unitA, 'perangkat', 3]]);

        $this->actingAs($this->auditor);

        $this->get(RencanaTikResource::getUrl('index'))->assertForbidden();
        $this->get(RencanaTikResource::getUrl('create'))->assertForbidden();
        // 404: record sudah tersaring oleh scope query non-Admin sebelum policy dicek
        $this->get(RencanaTikResource::getUrl('edit', ['record' => $rencana]))->assertNotFound();
        $this->assertFalse($this->auditor->can('update', $rencana));
    }

    public function test_admin_bisa_mengakses_dan_mengubah_semua_rencana(): void
    {
        $rencanaB = $this->buatRencana(2026, 'disetujui', [[$this->unitB, 'perangkat', 3]]);
        $rencanaCampuran = $this->buatRencana(2026, 'draft', [
            [$this->unitA, 'perangkat', 1],
            [$this->unitB, 'jaringan', 1],
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ListRencanaTiks::class)
            ->assertCanSeeTableRecords([$rencanaB, $rencanaCampuran]);

        $this->get(RencanaTikResource::getUrl('edit', ['record' => $rencanaB]))->assertOk();

        Livewire::test(EditRencanaTik::class, ['record' => $rencanaCampuran->getRouteKey()])
            ->fillForm(['status' => 'disetujui'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('disetujui', $rencanaCampuran->fresh()->status);
        $this->assertSame($this->admin->id, (int) $rencanaCampuran->fresh()->approved_by);

        Livewire::test(ListRencanaTiks::class)->callTableAction('delete', $rencanaB);
        $this->assertModelMissing($rencanaB);
    }
}
