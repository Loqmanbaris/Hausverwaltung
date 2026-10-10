<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Owner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopesOwners
{
    protected function me(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    protected function visibleOwners(): Builder
    {
        return Owner::visibleTo($this->me())->orderBy('name');
    }

    /** @return int[] */
    protected function visibleOwnerIds(): array
    {
        return array_map('intval', $this->visibleOwners()->pluck('owners.id')->all());
    }

    /** Wer den Eigentümer nicht sehen darf, bekommt „nicht gefunden“. */
    protected function assertOwnerAccess(int $ownerId): void
    {
        abort_unless(in_array($ownerId, $this->visibleOwnerIds(), true), 404);
    }
}
