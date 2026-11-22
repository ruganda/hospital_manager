<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no', 'patient_id', 'appointment_id', 'description', 'amount', 'amount_paid', 'status', 'due_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->invoice_no)) {
                $invoice->invoice_no = 'INV-'.now()->format('Y').'-'.str_pad((string) ((static::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_at');
    }

    public function mobileMoneyRequests(): HasMany
    {
        return $this->hasMany(MobileMoneyRequest::class)->latest();
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->amount - (float) $this->amount_paid, 2);
    }

    /** Recalculate amount_paid and status from the payments table. */
    public function refreshTotals(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $this->amount_paid = $paid;
        $this->status = match (true) {
            $paid <= 0 => InvoiceStatus::Unpaid,
            $paid + 0.001 >= (float) $this->amount => InvoiceStatus::Paid,
            default => InvoiceStatus::Partial,
        };
        $this->save();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasRole('patient') ? $query->where('patient_id', $user->patient?->id ?? 0) : $query;
    }
}
