<?php

namespace App\Policies;

use App\Models\RealisasiTik;
use App\Models\User;

class RealisasiTikPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Auditor']);
    }

    public function view(User $user, RealisasiTik $realisasi): bool
    {
        if ($user->hasAnyRole(['Admin', 'Auditor'])) {
            return true;
        }

        return $this->isDalamUnitOperator($user, $realisasi);
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Operator wajib punya unit kerja yang dikelola
        return $user->hasRole('Operator') && $user->managed_unit_kerja_id !== null;
    }

    public function update(User $user, RealisasiTik $realisasi): bool
    {
        // Realisasi yang sudah disetujui terkunci untuk semua role
        if ($realisasi->isVerified()) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        return $realisasi->isEditable() && $this->isDalamUnitOperator($user, $realisasi);
    }

    public function delete(User $user, RealisasiTik $realisasi): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return $realisasi->isEditable() && $this->isDalamUnitOperator($user, $realisasi);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Setujui / tolak realisasi: hanya Admin, hanya untuk status menunggu.
     */
    public function verify(User $user, RealisasiTik $realisasi): bool
    {
        return $user->hasRole('Admin') && $realisasi->isEditable();
    }

    protected function isDalamUnitOperator(User $user, RealisasiTik $realisasi): bool
    {
        return $user->hasRole('Operator')
            && $user->managed_unit_kerja_id !== null
            && (int) $realisasi->rencanaItem?->unit_kerja_id === (int) $user->managed_unit_kerja_id;
    }
}
