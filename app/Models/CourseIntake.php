<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseIntake extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id', 'academic_year_id',
        'management_seats', 'counselling_seats',
        'management_enrolled', 'counselling_enrolled',
    ];

    protected $casts = [
        'management_seats'    => 'integer',
        'counselling_seats'   => 'integer',
        'management_enrolled' => 'integer',
        'counselling_enrolled'=> 'integer',
    ];

    protected $appends = [
        'total_seats', 'total_enrolled', 'balance_seats',
        'management_balance', 'counselling_balance',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    // ─── Computed Accessors ──────────────────────────────────────────────────────

    public function getTotalSeatsAttribute(): int
    {
        return $this->management_seats + $this->counselling_seats;
    }

    public function getTotalEnrolledAttribute(): int
    {
        return $this->management_enrolled + $this->counselling_enrolled;
    }

    public function getBalanceSeatsAttribute(): int
    {
        return max(0, $this->getTotalSeatsAttribute() - $this->getTotalEnrolledAttribute());
    }

    public function getManagementBalanceAttribute(): int
    {
        return max(0, $this->management_seats - $this->management_enrolled);
    }

    public function getCounsellingBalanceAttribute(): int
    {
        return max(0, $this->counselling_seats - $this->counselling_enrolled);
    }

    // ─── Static Helpers ──────────────────────────────────────────────────────────

    public static function forLead(Lead $lead): ?self
    {
        if (!$lead->course_id || !$lead->academic_year_id) {
            return null;
        }

        return static::where('course_id', $lead->course_id)
            ->where('academic_year_id', $lead->academic_year_id)
            ->first();
    }

    public static function incrementEnrolled(Lead $lead): void
    {
        $intake = static::forLead($lead);
        if (!$intake) {
            return;
        }

        $col = match ($lead->quota) {
            'management'  => 'management_enrolled',
            'counselling' => 'counselling_enrolled',
            default       => null,
        };

        if ($col) {
            $intake->increment($col);
        }
    }

    public static function decrementEnrolled(Lead $lead): void
    {
        $intake = static::forLead($lead);
        if (!$intake) {
            return;
        }

        $col = match ($lead->quota) {
            'management'  => 'management_enrolled',
            'counselling' => 'counselling_enrolled',
            default       => null,
        };

        if ($col && $intake->{$col} > 0) {
            $intake->decrement($col);
        }
    }
}
