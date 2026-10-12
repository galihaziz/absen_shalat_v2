<?php

namespace AbsenShalat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudentFaceRegistration extends Model
{
    protected $fillable = ['student_id', 'face_descriptor', 'face_code'];

    protected function casts(): array
    {
        return ['face_descriptor' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $registration): void {
            $registration->face_code ??= (string) Str::uuid();
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
