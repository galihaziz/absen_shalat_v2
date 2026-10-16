<?php

namespace AbsenShalat\Models;

use App\Services\MajorCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    protected $table = 'kelas';

    public $timestamps = false;

    protected $fillable = ['nama'];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'id_kelas');
    }

    public function majorLabel(): string
    {
        return app(MajorCatalog::class)->labelForClassroom($this->nama);
    }

    public function gradeLabel(): ?string
    {
        return preg_match('/^(?:KELAS\s+)?(XII|XI|X)(?![A-Z0-9])/i', $this->nama, $matches) === 1
            ? strtoupper($matches[1])
            : null;
    }
}
