<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorSchedule extends Model
{
    use HasFactory;

    public const DAYS = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    protected $fillable = ['doctor_id', 'day_of_week', 'start_time', 'end_time', 'slot_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'day_of_week' => 'integer', 'slot_minutes' => 'integer'];
    }

    /** Always store times as HH:MM:SS so comparisons behave the same on MySQL and SQLite. */
    public function setStartTimeAttribute($value): void
    {
        $this->attributes['start_time'] = self::normalizeTime($value);
    }

    public function setEndTimeAttribute($value): void
    {
        $this->attributes['end_time'] = self::normalizeTime($value);
    }

    public static function normalizeTime(?string $value): ?string
    {
        return $value === null ? null : (strlen($value) === 5 ? $value.':00' : $value);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? '?';
    }

    public function getTimeRangeAttribute(): string
    {
        return substr($this->start_time, 0, 5).' – '.substr($this->end_time, 0, 5);
    }
}
