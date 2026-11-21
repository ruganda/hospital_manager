<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    protected $fillable = [
        'user_id', 'patient_no', 'date_of_birth', 'gender', 'national_id', 'address', 'blood_group',
        'allergies', 'emergency_contact_name', 'emergency_contact_phone',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(function (Patient $patient) {
            if (empty($patient->patient_no)) {
                $patient->patient_no = static::nextNumber();
            }
        });
    }

    public static function nextNumber(): string
    {
        $last = static::max('id') ?? 0;

        return 'CC-'.str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getNameAttribute(): string
    {
        return $this->user?->name ?? 'Unknown';
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return (float) $this->invoices()->selectRaw('COALESCE(SUM(amount - amount_paid),0) as bal')->value('bal');
    }
}
