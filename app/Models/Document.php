<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'title',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'uploaded_by',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function getIconAttribute(): string
    {
        $type = strtolower($this->file_type ?? '');
        if (str_contains($type, 'pdf')) return 'picture_as_pdf';
        if (str_contains($type, 'word') || str_contains($type, 'document')) return 'description';
        if (str_contains($type, 'sheet') || str_contains($type, 'excel')) return 'table_chart';
        if (str_contains($type, 'presentation') || str_contains($type, 'powerpoint')) return 'slideshow';
        if (str_contains($type, 'image')) return 'image';
        return 'insert_drive_file';
    }
}
