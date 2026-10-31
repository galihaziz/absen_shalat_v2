<?php

namespace App\Services;

use AbsenShalat\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MajorCatalog
{
    public const SETTING_KEY = 'student_major_labels';

    private const DEFAULT_GROUPS = [
        ['id' => 'pplg-rpl', 'name' => 'PPLG/RPL', 'majors' => ['PPLG', 'RPL']],
        ['id' => 'tjkt-tkj', 'name' => 'TJKT/TKJ', 'majors' => ['TJKT', 'TKJ']],
        ['id' => 'tsm-to', 'name' => 'TSM/TO', 'majors' => ['TSM', 'TO']],
        ['id' => 'tpm-tm', 'name' => 'TPM/TM', 'majors' => ['TPM', 'TM']],
        ['id' => 'akl-ak', 'name' => 'AKL/AK', 'majors' => ['AKL', 'AK']],
        ['id' => 'mplb-mp', 'name' => 'MPLB/MP', 'majors' => ['MPLB', 'MP']],
        ['id' => 'ps-br-bd', 'name' => 'PS/BR/BD', 'majors' => ['PS', 'BR', 'BD']],
    ];

    private ?array $groups = null;
    private bool $hasStoredGroups = false;
    private array $legacyLabels = [];

    public function groups(iterable $classroomNames = []): array
    {
        if ($this->groups !== null) {
            if (! $this->hasStoredGroups) {
                $this->appendDiscoveredGroups($classroomNames);
            }

            return $this->groups;
        }

        $stored = AppSetting::query()->where('key', self::SETTING_KEY)->value('value');
        $decoded = is_string($stored) ? json_decode($stored, true) : null;
        if (is_array($decoded) && array_is_list($decoded)) {
            $this->groups = $this->normalizeStoredGroups($decoded);
            $this->hasStoredGroups = true;

            return $this->groups;
        }

        $legacyLabels = is_array($decoded) ? $decoded : [];
        $this->legacyLabels = $legacyLabels;
        $this->groups = array_map(
            fn (array $group): array => [
                ...$group,
                'name' => (string) ($legacyLabels[$group['name']] ?? $group['name']),
            ],
            self::DEFAULT_GROUPS
        );

        $this->appendDiscoveredGroups($classroomNames);

        return $this->groups;
    }

    public function availableMajors(iterable $classroomNames = []): array
    {
        $majors = [];
        foreach ($this->groups($classroomNames) as $group) {
            foreach ($group['majors'] as $major) {
                $majors[$major] = true;
            }
        }
        foreach ($classroomNames as $classroomName) {
            $major = $this->rawMajorForClassroom((string) $classroomName);
            if ($major !== '') {
                $majors[$major] = true;
            }
        }

        $majors = array_keys($majors);
        sort($majors, SORT_NATURAL | SORT_FLAG_CASE);

        return $majors;
    }

    public function labelForClassroom(string $classroomName): string
    {
        $group = $this->groupForClassroom($classroomName, $this->groups());

        return $group['name'] ?? $this->rawMajorForClassroom($classroomName);
    }

    public function createGroup(string $name, array $relatedMajors, iterable $classroomNames = []): void
    {
        $groups = $this->groups($classroomNames);
        $groups[] = [
            'id' => 'major-'.Str::uuid()->toString(),
            'name' => trim($name),
            'majors' => $this->normalizeMajors($relatedMajors),
        ];

        $this->saveGroups($groups);
    }

    public function updateGroup(string $id, string $name, array $relatedMajors, iterable $classroomNames = []): void
    {
        $groups = $this->groups($classroomNames);
        $updated = false;
        foreach ($groups as &$group) {
            if ($group['id'] !== $id) {
                continue;
            }
            $group['name'] = trim($name);
            $group['majors'] = $this->normalizeMajors($relatedMajors);
            $updated = true;
            break;
        }
        unset($group);

        if (! $updated) {
            throw ValidationException::withMessages([
                'major_group' => 'Grup jurusan tidak ditemukan. Muat ulang halaman lalu coba kembali.',
            ]);
        }

        $this->saveGroups($groups);
    }

    public function deleteGroup(string $id, iterable $classroomNames = []): void
    {
        $groups = $this->groups($classroomNames);
        $remaining = array_values(array_filter($groups, fn (array $group): bool => $group['id'] !== $id));
        if (count($remaining) === count($groups)) {
            throw ValidationException::withMessages([
                'major_group' => 'Grup jurusan tidak ditemukan. Muat ulang halaman lalu coba kembali.',
            ]);
        }

        $this->saveGroups($remaining);
    }

    private function saveGroups(array $groups): void
    {
        $ids = [];
        $assignedMajors = [];
        foreach ($groups as &$group) {
            $group['name'] = trim((string) $group['name']);
            $group['majors'] = $this->normalizeMajors($group['majors']);
            if ($group['name'] === '' || $group['majors'] === []) {
                throw ValidationException::withMessages([
                    'major_group' => 'Setiap grup harus memiliki nama dan minimal satu jurusan terkait.',
                ]);
            }
            if (isset($ids[$group['id']])) {
                throw ValidationException::withMessages([
                    'major_group' => 'ID grup jurusan harus unik.',
                ]);
            }
            $ids[$group['id']] = true;
            foreach ($group['majors'] as $major) {
                if (isset($assignedMajors[$major])) {
                    throw ValidationException::withMessages([
                        'major_group' => 'Jurusan '.$major.' sudah terhubung ke grup lain. Setiap jurusan hanya boleh berada dalam satu grup.',
                    ]);
                }
                $assignedMajors[$major] = true;
            }
        }
        unset($group);

        $normalized = array_values($groups);
        DB::transaction(function () use ($normalized): void {
            AppSetting::query()->updateOrCreate(
                ['key' => self::SETTING_KEY],
                ['value' => json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]
            );
        });

        $this->groups = $normalized;
        $this->hasStoredGroups = true;
        $this->legacyLabels = [];
    }

    private function normalizeStoredGroups(array $groups): array
    {
        $normalized = [];
        foreach ($groups as $group) {
            if (! is_array($group) || ! isset($group['id'], $group['name']) || ! is_array($group['majors'] ?? null)) {
                continue;
            }

            $majors = $this->normalizeMajors($group['majors']);
            $name = trim((string) $group['name']);
            $id = trim((string) $group['id']);
            if ($id !== '' && $name !== '' && $majors !== []) {
                $normalized[] = ['id' => $id, 'name' => $name, 'majors' => $majors];
            }
        }

        return $normalized;
    }

    private function normalizeMajors(array $majors): array
    {
        $normalized = [];
        foreach ($majors as $major) {
            if (! is_string($major)) {
                continue;
            }
            $major = mb_strtoupper(trim($major));
            if ($major !== '' && ! $this->isGradeOnly($major)) {
                $normalized[$major] = true;
            }
        }

        return array_keys($normalized);
    }

    private function appendDiscoveredGroups(iterable $classroomNames): void
    {
        foreach ($classroomNames as $classroomName) {
            $major = $this->rawMajorForClassroom((string) $classroomName);
            if ($major === '' || $this->groupForMajor($major, $this->groups ?? []) !== null) {
                continue;
            }

            $this->groups[] = [
                'id' => 'major-'.substr(hash('sha256', $major), 0, 16),
                'name' => (string) ($this->legacyLabels[$major] ?? $major),
                'majors' => [$major],
            ];
        }
    }

    private function groupForClassroom(string $classroomName, array $groups): ?array
    {
        $name = mb_strtoupper(trim($classroomName));
        foreach ($groups as $group) {
            foreach ($group['majors'] as $major) {
                $pattern = '/(?<![A-Z0-9])'.preg_quote($major, '/').'(?![A-Z0-9])/u';
                if (preg_match($pattern, $name) === 1) {
                    return $group;
                }
            }
        }

        return $this->groupForMajor($this->rawMajorForClassroom($classroomName), $groups);
    }

    private function groupForMajor(string $major, array $groups): ?array
    {
        $major = mb_strtoupper(trim($major));
        foreach ($groups as $group) {
            if (in_array($major, $group['majors'], true)) {
                return $group;
            }
        }

        return null;
    }

    private function rawMajorForClassroom(string $classroomName): string
    {
        $name = mb_strtoupper(trim($classroomName));
        if ($this->isGradeOnly($name)) {
            return '';
        }
        if (preg_match('/^(?:KELAS\s+)?(?:XII|XI|X)\s+(.+)$/u', $name, $match) === 1) {
            $name = $match[1];
        }
        $name = preg_replace('/\s+\d+[A-Z]?$/u', '', $name) ?? $name;

        return trim($name);
    }

    private function isGradeOnly(string $value): bool
    {
        return preg_match('/^(?:KELAS\s+)?(?:XII|XI|X|12|11|10)$/u', trim($value)) === 1;
    }
}
