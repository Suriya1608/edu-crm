<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'name', 'subject', 'body', 'blocks_json', 'status', 'created_by',
    ];

    protected $casts = [
        'blocks_json' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function emailLogs()
    {
        return $this->hasMany(CampaignEmailLog::class, 'template_id');
    }

    public static function active()
    {
        return static::where('status', 'active')->orderBy('name')->get();
    }
}
