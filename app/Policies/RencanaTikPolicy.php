<?php

namespace App\Policies;

use App\Models\RencanaTik;
use App\Models\User;

class RencanaTikPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator']);
    }

    public function view(User $user, RencanaTik $rencana): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return $this->isDalamUnitOperator($user, $rencana);
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Operator wajib punya unit kerja yang dikelola
        return $user->hasRole('Operator') && $user->managed_unit_kerja_id !== null;
    }

    public function update(User $user, RencanaTik $rencana): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Operator hanya boleh mengubah rencana draft di unitnya
        return $rencana->status === 'draft' && $this->isDalamUnitOperator($user, $rencana);
    }

    public function delete(User $user, RencanaTik $rencana): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    protected function isDalamUnitOperator(User $user, RencanaTik $rencana): bool
    {
        return $user->hasRole('Operator')
            && RencanaTik::query()->whereKey($rencana->getKey())->dalamUnitOperator($user)->exists();
    }
}
