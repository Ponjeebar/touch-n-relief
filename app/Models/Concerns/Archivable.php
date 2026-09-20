<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait Archivable
{
    public function initializeArchivable(): void
    {
        if (! in_array('archived_at', $this->casts, true)) {
            $this->casts['archived_at'] = 'datetime';
        }
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): bool
    {
        if (! $this->hasArchivedColumn()) {
            return false;
        }

        if ($this->isArchived()) {
            return true;
        }

        $this->archived_at = now();

        return $this->save();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        if (! $this->hasArchivedColumn()) {
            return $query;
        }

        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeArchived(Builder $query): Builder
    {
        if (! $this->hasArchivedColumn()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereNotNull('archived_at');
    }

    protected function hasArchivedColumn(): bool
    {
        return Schema::hasColumn($this->getTable(), 'archived_at');
    }
}
