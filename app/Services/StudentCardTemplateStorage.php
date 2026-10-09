<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StudentCardTemplateStorage
{
    private const DIRECTORY = 'template-kartu';

    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const CARD_WIDTH_MM = 53.98;

    public function dataUri(): ?string
    {
        $disk = Storage::disk('local');

        foreach (self::EXTENSIONS as $extension) {
            $path = self::DIRECTORY.'/active.'.$extension;
            if (! $disk->exists($path)) {
                continue;
            }

            $mimeType = $extension === 'jpg' || $extension === 'jpeg' ? 'image/jpeg' : 'image/'.$extension;
            return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($path));
        }

        return null;
    }

    public function cardHeightMm(): ?float
    {
        $disk = Storage::disk('local');

        foreach (self::EXTENSIONS as $extension) {
            $path = self::DIRECTORY.'/active.'.$extension;
            if (! $disk->exists($path)) {
                continue;
            }

            $dimensions = @getimagesizefromstring($disk->get($path));
            if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
                return null;
            }

            return round(self::CARD_WIDTH_MM * $dimensions[1] / $dimensions[0], 2);
        }

        return null;
    }

    public function store(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::EXTENSIONS, true)) {
            return false;
        }

        $disk = Storage::disk('local');
        $saved = $disk->put(self::DIRECTORY.'/active.'.$extension, $file->getContent());
        if (! $saved) {
            return false;
        }

        foreach (self::EXTENSIONS as $oldExtension) {
            if ($oldExtension !== $extension) {
                $disk->delete(self::DIRECTORY.'/active.'.$oldExtension);
            }
        }

        return true;
    }

    public function delete(): void
    {
        $disk = Storage::disk('local');

        foreach (self::EXTENSIONS as $extension) {
            $disk->delete(self::DIRECTORY.'/active.'.$extension);
        }
    }
}