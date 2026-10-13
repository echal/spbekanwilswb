<?php

namespace App\Support;

use App\Models\RealisasiTik;
use App\Models\RencanaTik;
use App\Models\RencanaTikItem;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Perlindungan histori realisasi TIK.
 *
 * Foreign key ke realisasi_tik saat ini memakai CASCADE, sehingga menghapus item rencana,
 * rencana, unit kerja, atau pengguna dapat ikut menghapus realisasi. Pengaman di sini
 * mencegahnya pada level model Eloquent (berlaku untuk semua jalur aplikasi: form, repeater,
 * aksi tabel, bulk action). Penghapusan lewat query builder/SQL langsung tidak tercakup;
 * untuk itu diperlukan perubahan foreign key (lihat rancangan migrasi di laporan Fase 2A).
 *
 * Realisasi dihitung dalam semua status (menunggu, disetujui, ditolak).
 */
class IntegritasRealisasi
{
    public static function daftarkanPengaman(): void
    {
        RencanaTikItem::updating(function (RencanaTikItem $item) {
            if ($item->isDirty('kategori') && static::itemPunyaRealisasi($item)) {
                throw ValidationException::withMessages([
                    'kategori' => static::pesanKategori($item->getOriginal('nama_item') ?? $item->nama_item),
                ]);
            }
        });

        RencanaTikItem::deleting(fn (RencanaTikItem $item) => static::itemPunyaRealisasi($item)
            ? static::tolak(static::pesanHapusItem($item->nama_item))
            : null);

        // Tahun dan status rencana menentukan apakah target/realisasi ikut dihitung dashboard
        RencanaTik::updating(function (RencanaTik $rencana) {
            $pesan = static::pesanPerubahanRencana(
                $rencana,
                (int) $rencana->getOriginal('tahun'),
                (string) $rencana->getOriginal('status'),
                (int) $rencana->tahun,
                (string) $rencana->status,
            );

            if ($pesan !== []) {
                throw ValidationException::withMessages($pesan);
            }
        });

        RencanaTik::deleting(fn (RencanaTik $rencana) => static::rencanaPunyaRealisasi($rencana)
            ? static::tolak("Rencana \"{$rencana->nama_rencana}\" tidak dapat dihapus karena memiliki item dengan histori realisasi.")
            : null);

        // rencana_tik_items.unit_kerja_id → CASCADE
        UnitKerja::deleting(fn (UnitKerja $unit) => RealisasiTik::query()
            ->whereHas('rencanaItem', fn ($q) => $q->where('unit_kerja_id', $unit->getKey()))
            ->exists()
            ? static::tolak("Unit kerja \"{$unit->nama_unit}\" tidak dapat dihapus karena memiliki item rencana dengan histori realisasi.")
            : null);

        // realisasi_tik.dibuat_oleh → CASCADE, rencana_tik.created_by → CASCADE
        User::deleting(fn (User $user) => RealisasiTik::query()
            ->where('dibuat_oleh', $user->getKey())
            ->orWhereHas('rencanaItem.rencana', fn ($q) => $q->where('created_by', $user->getKey()))
            ->exists()
            ? static::tolak("Pengguna \"{$user->name}\" tidak dapat dihapus karena terkait dengan histori realisasi TIK.")
            : null);
    }

    public static function itemPunyaRealisasi(RencanaTikItem|int|null $item): bool
    {
        $id = $item instanceof RencanaTikItem ? $item->getKey() : $item;

        return $id !== null && RealisasiTik::query()->where('rencana_item_id', $id)->exists();
    }

    public static function rencanaPunyaRealisasi(RencanaTik $rencana): bool
    {
        return RealisasiTik::query()
            ->whereHas('rencanaItem', fn ($q) => $q->where('rencana_tik_id', $rencana->getKey()))
            ->exists();
    }

    /**
     * Validasi state repeater item sebelum disimpan: item berealisasi tidak boleh hilang
     * (dihapus) dan kategorinya tidak boleh berubah. Dipanggil sebelum relasi disimpan.
     *
     * @param  array<string, array<string, mixed>>  $stateItems  state repeater (kunci "record-{id}" untuk item lama)
     *
     * @throws ValidationException
     */
    public static function validasiStateItems(RencanaTik $rencana, array $stateItems, string $statePath = 'data.items'): void
    {
        $pesan = [];

        $items = RencanaTikItem::query()
            ->where('rencana_tik_id', $rencana->getKey())
            ->whereHas('realisasi')
            ->get();

        foreach ($items as $item) {
            $kunci = "record-{$item->getKey()}";

            if (! array_key_exists($kunci, $stateItems)) {
                $pesan[$statePath][] = static::pesanHapusItem($item->nama_item);

                continue;
            }

            $kategoriBaru = $stateItems[$kunci]['kategori'] ?? $item->kategori;

            if ($kategoriBaru !== $item->kategori) {
                $pesan["{$statePath}.{$kunci}.kategori"] = static::pesanKategori($item->nama_item);
            }
        }

        if ($pesan !== []) {
            throw ValidationException::withMessages($pesan);
        }
    }

    /**
     * X1/X2: rencana yang sudah memiliki realisasi tidak boleh berganti tahun
     * dan tidak boleh diturunkan ke status draft. Mengembalikan pesan per field (kosong bila sah).
     *
     * @return array<string, string>
     */
    public static function pesanPerubahanRencana(RencanaTik $rencana, int $tahunLama, string $statusLama, int $tahunBaru, string $statusBaru): array
    {
        $gantiTahun = $tahunBaru !== $tahunLama;
        $turunKeDraft = $statusBaru === 'draft' && $statusLama !== 'draft';

        if ((! $gantiTahun && ! $turunKeDraft) || ! static::rencanaPunyaRealisasi($rencana)) {
            return [];
        }

        $pesan = [];

        if ($gantiTahun) {
            $pesan['tahun'] = "Tahun rencana \"{$rencana->nama_rencana}\" tidak dapat diubah karena rencana sudah memiliki histori realisasi.";
        }

        if ($turunKeDraft) {
            $pesan['status'] = "Status rencana \"{$rencana->nama_rencana}\" tidak dapat dikembalikan ke Draft karena rencana sudah memiliki histori realisasi.";
        }

        return $pesan;
    }

    public static function pesanHapusItem(?string $namaItem): string
    {
        return "Item \"{$namaItem}\" tidak dapat dihapus karena memiliki histori realisasi.";
    }

    public static function pesanKategori(?string $namaItem): string
    {
        return "Kategori item \"{$namaItem}\" tidak dapat diubah karena item sudah memiliki histori realisasi.";
    }

    /**
     * Membatalkan operasi Eloquent (event `deleting` mengembalikan false) dan memberi tahu pengguna.
     */
    protected static function tolak(string $pesan): bool
    {
        Notification::make()->danger()->title('Penghapusan dibatalkan')->body($pesan)->send();

        return false;
    }
}
