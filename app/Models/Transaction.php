<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'account_id',
        'type',
        'amount_cents',
        'idempotency_key',
        'request_hash',
        'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(
            Transaction::class,
            'reversal_of_id'
        );
    }
}
