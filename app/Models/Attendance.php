<?php

namespace AbsenShalat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $table = 'absensi';

    public $timestamps = false;

    protected $fillable = ['id_siswa', 'tanggal', 'status', 'waktu_scan'];

    protected function casts(): array
    {
        return ['waktu_scan' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'id_siswa');
    }
}
