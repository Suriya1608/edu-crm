<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Course extends Model
{
    use Auditable;

    protected $fillable = ['name', 'code', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function intakes()
    {
        return $this->hasMany(CourseIntake::class);
    }
}
