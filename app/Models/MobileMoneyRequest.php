<?php

namespace App\Models;

use App\Enums\MobileMoneyNetwork;
use App\Enums\MobileMoneyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileMoneyRequest extends Model
{
    protected $fillable = [
        'invoice_id', 'requested_by', 'payment_id', 'provider', 'tx_ref', 'network', 'phone', 'amount', 'currency',
        'status', 'provider_id', 'provider_ref', 'redirect_url', 'message', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'network' => MobileMoneyNetwork::class,
            'status' => MobileMoneyStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPending(): bool
    {
        return $this->status === MobileMoneyStatus::Pending;
    }

    /** 256772418093 → +256 772 418 093 */
    public function getDisplayPhoneAttribute(): string
    {
        return preg_replace('/^(256)(\d{3})(\d{3})(\d{3})$/', '+$1 $2 $3 $4', $this->phone) ?? $this->phone;
    }
}
