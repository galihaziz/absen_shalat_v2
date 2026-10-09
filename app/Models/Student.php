<?php

namespace AbsenShalat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class Student extends Model
{
    protected $table = 'siswa';

    public $timestamps = false;

    protected $hidden = ['portal_pin'];

    protected $fillable = ['induk', 'nama', 'jenis_kelamin', 'is_irma', 'is_nonis', 'id_kelas', 'is_alumni'];

    protected function casts(): array
    {
        return ['is_irma' => 'boolean', 'is_nonis' => 'boolean', 'is_alumni' => 'boolean', 'portal_pin' => 'hashed'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $student): void {
            if (! filled($student->portal_pin)) {
                $student->portal_pin = (string) config('app.student_default_pin', 'siswa123');
            }
        });
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'id_kelas');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'id_siswa');
    }
}
