<?php

namespace AbsenShalat\Models;

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
        $name = strtoupper($this->nama);
        $aliases = [
            'PPLG/RPL' => ['PPLG', 'RPL'],
            'TJKT/TKJ' => ['TJKT', 'TKJ'],
            'TSM/TO' => ['TSM', 'TO'],
            'TPM/TM' => ['TPM', 'TM'],
            'AKL/AK' => ['AKL', 'AK'],
            'MPLB/MP' => ['MPLB', 'MP'],
        ];

        foreach ($aliases as $label => $tokens) {
            foreach ($tokens as $token) {
                if (preg_match('/(?<![A-Z0-9])'.preg_quote($token, '/').'(?![A-Z0-9])/', $name)) {
                    return $label;
                }
            }
        }

        if (preg_match('/(?<![A-Z0-9])(PS|BR|BD)(?![A-Z0-9])/', $name)) {
            return 'PS/BR/BD';
        }

        if (preg_match('/^(?:KELAS\s+)?(?:XII|XI|X)\s+(.+)$/', $name, $match)) {
            $major = preg_replace('/\s+\d+[A-Z]?$/', '', $match[1]);
            if ($major !== '') {
                return trim($major);
            }
        }

        return $this->nama;
    }

    public function gradeLabel(): ?string
    {
        return preg_match('/^(?:KELAS\s+)?(XII|XI|X)(?![A-Z0-9])/i', $this->nama, $matches) === 1
            ? strtoupper($matches[1])
            : null;
    }
}
