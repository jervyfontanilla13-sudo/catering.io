<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    protected $fillable = ['full_name', 'contact_number', 'email', 'subject', 'category', 'message', 'status', 'admin_reply', 'replied_at', 'viewed_at'];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime', 'viewed_at' => 'datetime'];
    }

    /** True when this inquiry has not yet had an admin response: New, or In Progress with no reply sent. */
    public function needsAttention(): bool
    {
        return $this->status === 'new' || ($this->status === 'in_progress' && $this->admin_reply === null);
    }

    public function isUnread(): bool
    {
        return $this->viewed_at === null;
    }

    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', 'new')
                ->orWhere(function (Builder $inProgress) {
                    $inProgress->where('status', 'in_progress')->whereNull('admin_reply');
                });
        });
    }

}
