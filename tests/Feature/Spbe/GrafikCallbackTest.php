<?php

namespace Tests\Feature\Spbe;

use App\Filament\Widgets\SpbeKategoriChart;
use App\Filament\Widgets\SpbeUnitChart;
use Filament\Support\RawJs;
use Livewire\Livewire;
use ReflectionMethod;

/**
 * K5: callback sumbu pada grafik posisi tahunan dikirim sebagai fungsi JavaScript (RawJs),
 * bukan string JSON yang tidak dijalankan Chart.js. Konfigurasi lain tidak berubah.
 */
class GrafikCallbackTest extends SpbeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->admin);
        $this->travelTo('2026-10-12');

        $this->buatRencana(2026, 'disetujui', [
            [$this->unitA, 'jaringan', 12],
            [$this->unitA, 'aplikasi', 230],
            [$this->unitA, 'server', 3],
            [$this->unitB, 'perangkat', 230],
        ]);
    }

    protected function opsi(string $widget): RawJs
    {
        $instance = Livewire::test($widget, ['tahun' => 2026])->instance();

        return (new ReflectionMethod($instance, 'getOptions'))->invoke($instance);
    }

    public function test_opsi_kedua_grafik_berupa_rawjs_dengan_callback_fungsi(): void
    {
        foreach ([SpbeKategoriChart::class, SpbeUnitChart::class] as $widget) {
            $opsi = $this->opsi($widget);
            $js = (string) $opsi;

            $this->assertInstanceOf(RawJs::class, $opsi, $widget);
            $this->assertMatchesRegularExpression("/callback:\s*\(value\)\s*=>\s*value \+ '%'/", $js, $widget);
            $this->assertStringNotContainsString('"callback"', $js, $widget);
        }
    }

    public function test_konfigurasi_lain_tidak_berubah(): void
    {
        $kategori = (string) $this->opsi(SpbeKategoriChart::class);
        $this->assertMatchesRegularExpression('/scales:\s*\{\s*y:\s*\{/', $kategori);
        $this->assertStringContainsString('beginAtZero: true', $kategori);
        $this->assertStringContainsString('max: 100', $kategori);
        $this->assertMatchesRegularExpression('/legend:\s*\{\s*display: false/', $kategori);

        $unit = (string) $this->opsi(SpbeUnitChart::class);
        $this->assertStringContainsString("indexAxis: 'y'", $unit);
        $this->assertMatchesRegularExpression('/scales:\s*\{\s*x:\s*\{/', $unit);
        $this->assertStringContainsString('beginAtZero: true', $unit);
        $this->assertStringContainsString('max: 100', $unit);
        $this->assertMatchesRegularExpression('/legend:\s*\{\s*display: false/', $unit);
    }

    public function test_html_widget_memuat_callback_sebagai_javascript_bukan_string(): void
    {
        Livewire::withoutLazyLoading();

        foreach ([SpbeKategoriChart::class, SpbeUnitChart::class] as $widget) {
            $html = Livewire::test($widget, ['tahun' => 2026])->html();

            $this->assertMatchesRegularExpression("/callback:\s*\(value\)\s*=>\s*value \+ '%'/", $html, $widget);
            $this->assertStringNotContainsString('"callback":"(value)', $html, $widget);
            $this->assertStringNotContainsString('\u0022callback\u0022', $html, $widget);
        }
    }
}
