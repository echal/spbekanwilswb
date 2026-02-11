<?php

namespace App\Policies;

use App\Models\PegawaiAplikasi;
use App\Models\User;

class PegawaiAplikasiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Auditor']);
    }

    public function view(User $user, PegawaiAplikasi $pegawaiAplikasi): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Auditor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator']);
    }

    public function update(User $user, PegawaiAplikasi $pegawaiAplikasi): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator']);
    }

    public function delete(User $user, PegawaiAplikasi $pegawaiAplikasi): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }
}
