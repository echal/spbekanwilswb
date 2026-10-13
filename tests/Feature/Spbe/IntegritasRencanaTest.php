<?php

namespace Tests\Feature\Spbe;

use App\Filament\Resources\RencanaTiks\Pages\EditRencanaTik;
use App\Filament\Widgets\SpbeGlobalProgressWidget;
use App\Filament\Widgets\SpbeKpiWidget;
use App\Filament\Widgets\SpbeUnitChart;
use App\Models\RencanaTik;
use App\Services\SpbeEvaluationService;
use App\Services\SpbeQuarterlyPerformanceService;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use ReflectionMethod;

/**
 * X1: tahun rencana berealisasi terkunci.
 * X2: rencana berealisasi tidak dapat diturunkan ke draft.
 * X3: target hanya dari tepat satu rencana disetujui/final per tahun (tanpa penjumlahan/fallback).
 */
class IntegritasRencanaTest extends SpbeTestCase
{
    protected function rencanaBerealisasi(string $status = 'disetujui'): RencanaTik
    {
        $rencana = $this->buatRencana(2026, $status, [[$this->unitA, 'perangkat', 20]]);
        $this->buatRealisasi($rencana->items->first(), 5, 'disetujui', '2026-03-31');

        return $rencana->fresh();
    }

    protected function edit(RencanaTik $rencana)
    {
        $this->actingAs($this->admin);

        return Livewire::test(EditRencanaTik::class, ['record' => $rencana->getRouteKey()]);
    }

    // ---------------------------------------------------------------- X1

    public function test_x1_rencana_tanpa_realisasi_dapat_mengubah_tahun(): void
    {
        $rencana = $this->buatRencana(2026, 'disetujui', [[$this->unitA, 'perangkat', 20]]);

        $this->edit($rencana)
            ->assertFormFieldEnabled('tahun')
            ->set('data.tahun', 2027)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2027, $rencana->fresh()->tahun);
    }

    public function test_x1_rencana_berealisasi_tidak_dapat_mengubah_tahun_termasuk_manipulasi_request(): void
    {
        $rencana = $this->rencanaBerealisasi();

        $this->edit($rencana)
            ->assertFormFieldDisabled('tahun')
            ->set('data.tahun', 2027)
            ->set('data.nama_rencana', 'Diubah bersamaan')
            ->call('save')
            ->assertHasErrors(['data.tahun']);

        $this->assertSame(2026, $rencana->fresh()->tahun);
        // Tidak ada penulisan parsial
        $this->assertNotSame('Diubah bersamaan', $rencana->fresh()->nama_rencana);
    }

    public function test_x1_perubahan_field_lain_yang_sah_tetap_berjalan(): void
    {
        $rencana = $this->rencanaBerealisasi();

        $this->edit($rencana)
            ->set('data.nama_rencana', 'Nama baru')
            ->set('data.keterangan', 'Keterangan baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nama baru', $rencana->fresh()->nama_rencana);
        $this->assertSame('Keterangan baru', $rencana->fresh()->keterangan);
        $this->assertSame(2026, $rencana->fresh()->tahun);
    }

    public function test_x1_perubahan_tahun_lewat_model_ditolak(): void
    {
        $rencana = $this->rencanaBerealisasi();

        try {
            $rencana->update(['tahun' => 2027]);
            $this->fail('Perubahan tahun lewat model seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tahun', $e->errors());
        }

        $this->assertSame(2026, $rencana->fresh()->tahun);
    }

    // ---------------------------------------------------------------- X2

    public function test_x2_rencana_tanpa_realisasi_tetap_dapat_kembali_ke_draft(): void
    {
        $rencana = $this->buatRencana(2026, 'disetujui', [[$this->unitA, 'perangkat', 20]]);

        $this->edit($rencana)->set('data.status', 'draft')->call('save')->assertHasNoErrors();

        $this->assertSame('draft', $rencana->fresh()->status);
    }

    public function test_x2_rencana_berealisasi_tidak_dapat_diturunkan_ke_draft(): void
    {
        foreach (['disetujui', 'final'] as $status) {
            $rencana = $this->rencanaBerealisasi($status);

            if ($status === 'disetujui') {
                // Lewat form (Admin), termasuk bila opsi Draft dipaksa lewat request
                $this->edit($rencana)->set('data.status', 'draft')->call('save')->assertHasErrors(['data.status']);
                $this->assertSame('disetujui', $rencana->fresh()->status);
            }

            // Lewat model (rencana final tidak bisa disimpan lewat form sama sekali)
            try {
                $rencana->fresh()->update(['status' => 'draft']);
                $this->fail("Penurunan {$status} → draft lewat model seharusnya ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('status', $e->errors());
            }

            $this->assertSame($status, $rencana->fresh()->status);
        }
    }

    public function test_x2_transisi_disetujui_ke_final_tetap_berjalan(): void
    {
        $rencana = $this->rencanaBerealisasi();

        $this->edit($rencana)->set('data.status', 'final')->call('save')->assertHasNoErrors();

        $this->assertSame('final', $rencana->fresh()->status);
    }

    // ---------------------------------------------------------------- X3

    protected function progress(int $tahun = 2026): array
    {
        return app(SpbeQuarterlyPerformanceService::class)->getAnnualProgress($tahun);
    }

    protected function rencanaLengkap(string $status, int $skala = 1): RencanaTik
    {
        return $this->buatRencana(2026, $status, [
            [$this->unitA, 'jaringan', 10 * $skala],
            [$this->unitA, 'aplikasi', 20 * $skala],
            [$this->unitA, 'server', 2 * $skala],
            [$this->unitB, 'perangkat', 40 * $skala],
        ]);
    }

    public function test_x3_nol_rencana_memenuhi_syarat_target_tidak_tersedia(): void
    {
        $this->rencanaLengkap('draft');

        $hasil = $this->progress();

        $this->assertSame(0, $hasil['jumlah_rencana_memenuhi_syarat']);
        $this->assertNull($hasil['peringatan_target']);
        $this->assertNull($hasil['persentase_rata_rata']);
        foreach ($hasil['komponen'] as $k) {
            $this->assertSame(0, $k['target_tahunan']);
            $this->assertNull($k['persentase']);
        }
    }

    public function test_x3_satu_rencana_menghasilkan_target_rencana_tersebut_dan_draft_tidak_dihitung(): void
    {
        $sah = $this->rencanaLengkap('disetujui');
        $this->rencanaLengkap('draft', 5); // draft tidak ikut dihitung
        $this->buatRealisasi($sah->items->firstWhere('kategori', 'jaringan'), 5, 'disetujui', '2026-02-01');

        $hasil = $this->progress();

        $this->assertSame(1, $hasil['jumlah_rencana_memenuhi_syarat']);
        $this->assertNull($hasil['peringatan_target']);
        $this->assertSame(10, $hasil['komponen']['jaringan']['target_tahunan']);
        $this->assertSame(20, $hasil['komponen']['aplikasi']['target_tahunan']);
        $this->assertSame(2, $hasil['komponen']['server']['target_tahunan']);
        $this->assertSame(40, $hasil['komponen']['perangkat']['target_tahunan']);
        $this->assertSame(50.0, $hasil['komponen']['jaringan']['persentase']);
        $this->assertNotNull($hasil['persentase_rata_rata']);
    }

    public function test_x3_dua_rencana_memenuhi_syarat_tidak_dijumlahkan_dan_memberi_peringatan(): void
    {
        $a = $this->rencanaLengkap('disetujui');
        $this->rencanaLengkap('final', 2);
        $this->buatRealisasi($a->items->firstWhere('kategori', 'jaringan'), 5, 'disetujui', '2026-02-01');

        $hasil = $this->progress();

        $this->assertSame(2, $hasil['jumlah_rencana_memenuhi_syarat']);
        $this->assertStringContainsString('2 Rencana TIK', $hasil['peringatan_target']);
        $this->assertStringContainsString('2026', $hasil['peringatan_target']);

        // Tidak dijumlahkan (bukan 30) dan tidak memilih salah satu (bukan 10 / 20)
        foreach ($hasil['komponen'] as $k) {
            $this->assertSame(0, $k['target_tahunan']);
            $this->assertNull($k['persentase']);
        }
        $this->assertNull($hasil['persentase_rata_rata']);

        // Grafik unit juga tidak menjumlahkan
        $this->assertTrue(app(SpbeEvaluationService::class)->getByUnit(2026)->isEmpty());

        // Tahun lain tidak terpengaruh
        $this->assertSame(0, $this->progress(2025)['jumlah_rencana_memenuhi_syarat']);
        $this->assertNull($this->progress(2025)['peringatan_target']);
    }

    public function test_x3_peringatan_tampil_di_widget_dashboard(): void
    {
        $this->rencanaLengkap('disetujui');
        $this->rencanaLengkap('disetujui', 3);

        $this->actingAs($this->admin);

        $global = Livewire::test(SpbeGlobalProgressWidget::class, ['tahun' => 2026])->instance();
        $stats = collect((new ReflectionMethod($global, 'getStats'))->invoke($global));
        $capaian = $stats->first(fn (Stat $s) => (string) $s->getLabel() === 'Capaian SPBE');
        $this->assertSame('–', (string) $capaian->getValue());
        $this->assertStringContainsString('2 Rencana TIK', (string) $capaian->getDescription());

        Livewire::test(SpbeKpiWidget::class, ['tahun' => 2026])
            ->assertSee('2 Rencana TIK')
            ->assertSee('2026');

        $grafik = Livewire::test(SpbeUnitChart::class, ['tahun' => 2026])->instance();
        $this->assertStringContainsString('2 Rencana TIK', $grafik->getDescription());
    }
}
