<?php

namespace App\Services;

use AbsenShalat\Models\Student;
use AbsenShalat\Models\StudentFaceRegistration;

class StudentFaceRecognition
{
    private const MATCH_THRESHOLD = 0.35;

    private const MATCH_MARGIN = 0.10;

    private const DUPLICATE_MIN_MATCHES = 2;

    /**
     * Find a different student's registration that matches multiple enrollment samples.
     *
     * @param array<int, array<int, float>> $descriptors
     */
    public function duplicateRegistration(int $studentId, array $descriptors): ?Student
    {
        $registrations = StudentFaceRegistration::query()
            ->with('student')
            ->where('student_id', '!=', $studentId)
            ->whereHas('student', fn ($query) => $query->where('is_alumni', false))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($registrations as $registration) {
            $savedDescriptors = $this->registrationDescriptors($registration->face_descriptor);
            $matchingSamples = 0;

            foreach ($descriptors as $descriptor) {
                foreach ($savedDescriptors as $savedDescriptor) {
                    if ($this->distance($descriptor, $savedDescriptor) <= self::MATCH_THRESHOLD) {
                        $matchingSamples++;
                        break;
                    }
                }
            }

            if ($matchingSamples >= self::DUPLICATE_MIN_MATCHES) {
                return $registration->student;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<int, float>> $descriptors
     */
    public function identify(array $descriptors): ?Student
    {
        $registrations = StudentFaceRegistration::query()
            ->with(['student.classroom'])
            ->whereHas('student', fn ($query) => $query->where('is_alumni', false))
            ->get()
            ->map(fn (StudentFaceRegistration $registration): array => [
                'student' => $registration->student,
                'descriptors' => $this->registrationDescriptors($registration->face_descriptor),
            ])
            ->filter(fn (array $registration): bool => $registration['descriptors'] !== [])
            ->values();
        $matches = [];

        foreach ($descriptors as $descriptor) {
            $candidates = [];

            foreach ($registrations as $registration) {
                $distances = array_map(
                    fn (array $savedDescriptor): float => $this->distance($descriptor, $savedDescriptor),
                    $registration['descriptors']
                );
                $distance = min($distances);
                $candidates[] = ['student' => $registration['student'], 'distance' => $distance];
            }

            usort($candidates, fn (array $left, array $right): int => $left['distance'] <=> $right['distance']);

            if ($candidates === [] || $candidates[0]['distance'] > self::MATCH_THRESHOLD) {
                return null;
            }

            if (isset($candidates[1]) && ($candidates[1]['distance'] - $candidates[0]['distance']) < self::MATCH_MARGIN) {
                return null;
            }

            $matches[] = $candidates[0]['student'];
        }

        $studentIds = array_unique(array_map(fn (Student $student): int => $student->id, $matches));

        return count($studentIds) === 1 ? $matches[0] : null;
    }

    /**
     * @return array<int, array<int, float>>
     */
    private function registrationDescriptors(string $encodedDescriptors): array
    {
        $savedDescriptors = json_decode($encodedDescriptors, true);
        if (! is_array($savedDescriptors)) {
            return [];
        }
        if (count($savedDescriptors) === 128 && ! is_array(reset($savedDescriptors))) {
            $savedDescriptors = [$savedDescriptors];
        }

        return array_values(array_map(
            fn (array $descriptor): array => array_map('floatval', $descriptor),
            array_filter($savedDescriptors, fn ($descriptor): bool => is_array($descriptor) && count($descriptor) === 128)
        ));
    }

    /**
     * @param array<int, float> $left
     * @param array<int, float> $right
     */
    private function distance(array $left, array $right): float
    {
        $sum = 0.0;
        for ($index = 0; $index < 128; $index++) {
            $difference = $left[$index] - $right[$index];
            $sum += $difference * $difference;
        }

        return sqrt($sum);
    }
}
