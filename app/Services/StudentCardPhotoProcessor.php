<?php

namespace App\Services;

class StudentCardPhotoProcessor
{
    private const MAX_UPLOAD_WIDTH = 1200;

    private const MAX_UPLOAD_HEIGHT = 1600;

    private const MAX_UPLOAD_BYTES = 102400;

    private const OUTPUT_WIDTH = 500;

    private const OUTPUT_HEIGHT = 637;

    public function optimizeForStorage(string $imageContents, string $fallbackExtension): ?array
    {
        if (strlen($imageContents) < self::MAX_UPLOAD_BYTES) {
            return ['contents' => $imageContents, 'extension' => $fallbackExtension];
        }

        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($imageContents);
        if ($source === false) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, self::MAX_UPLOAD_WIDTH / $sourceWidth, self::MAX_UPLOAD_HEIGHT / $sourceHeight);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $bestContents = $imageContents;
        $bestExtension = $fallbackExtension;

        while (true) {
            for ($quality = 82; $quality >= 35; $quality -= 5) {
                $jpegContents = $this->encodeJpeg($source, $width, $height, $quality);
                if ($jpegContents === null) {
                    imagedestroy($source);

                    return strlen($bestContents) < self::MAX_UPLOAD_BYTES
                        ? ['contents' => $bestContents, 'extension' => $bestExtension]
                        : null;
                }

                if (strlen($jpegContents) < strlen($bestContents)) {
                    $bestContents = $jpegContents;
                    $bestExtension = 'jpg';
                }

                if (strlen($jpegContents) < self::MAX_UPLOAD_BYTES) {
                    if ($width === $sourceWidth && $height === $sourceHeight && strlen($jpegContents) >= strlen($imageContents)) {
                        imagedestroy($source);

                        return ['contents' => $imageContents, 'extension' => $fallbackExtension];
                    }

                    imagedestroy($source);

                    return ['contents' => $jpegContents, 'extension' => 'jpg'];
                }
            }

            if ($width <= 500 && $height <= 637) {
                break;
            }

            $width = max(1, (int) floor($width * 0.9));
            $height = max(1, (int) floor($height * 0.9));
        }

        imagedestroy($source);

        return strlen($bestContents) < self::MAX_UPLOAD_BYTES
            ? ['contents' => $bestContents, 'extension' => $bestExtension]
            : null;
    }

    private function encodeJpeg($source, int $width, int $height, int $quality): ?string
    {
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        ob_start();
        $encoded = imagejpeg($image, null, $quality);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $encoded && $contents !== '' ? $contents : null;
    }

    public function coverDataUri(
        string $imageContents,
        string $fallbackMimeType,
        int $outputWidth = self::OUTPUT_WIDTH,
        int $outputHeight = self::OUTPUT_HEIGHT
    ): string
    {
        if ($outputWidth < 1 || $outputHeight < 1) {
            throw new \InvalidArgumentException('Photo output dimensions must be greater than zero.');
        }

        if (! function_exists('imagecreatefromstring')) {
            return $this->fallbackDataUri($imageContents, $fallbackMimeType);
        }

        $source = @imagecreatefromstring($imageContents);
        if ($source === false) {
            return $this->fallbackDataUri($imageContents, $fallbackMimeType);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetRatio = $outputWidth / $outputHeight;

        if ($sourceWidth / $sourceHeight > $targetRatio) {
            $cropWidth = max(1, (int) round($sourceHeight * $targetRatio));
            $crop = [
                'x' => (int) floor(($sourceWidth - $cropWidth) / 2),
                'y' => 0,
                'width' => $cropWidth,
                'height' => $sourceHeight,
            ];
        } else {
            $cropHeight = max(1, (int) round($sourceWidth / $targetRatio));
            $crop = [
                'x' => 0,
                'y' => (int) floor(($sourceHeight - $cropHeight) / 2),
                'width' => $sourceWidth,
                'height' => $cropHeight,
            ];
        }

        $cropped = imagecrop($source, $crop);
        imagedestroy($source);

        if ($cropped === false) {
            return $this->fallbackDataUri($imageContents, $fallbackMimeType);
        }

        $cover = imagecreatetruecolor($outputWidth, $outputHeight);
        $white = imagecolorallocate($cover, 255, 255, 255);
        imagefill($cover, 0, 0, $white);
        imagecopyresampled(
            $cover,
            $cropped,
            0,
            0,
            0,
            0,
            $outputWidth,
            $outputHeight,
            imagesx($cropped),
            imagesy($cropped)
        );

        ob_start();
        $encoded = imagejpeg($cover, null, 90);
        $jpegContents = (string) ob_get_clean();
        imagedestroy($cropped);
        imagedestroy($cover);

        if (! $encoded || $jpegContents === '') {
            return $this->fallbackDataUri($imageContents, $fallbackMimeType);
        }

        return 'data:image/jpeg;base64,'.base64_encode($jpegContents);
    }

    private function fallbackDataUri(string $imageContents, string $mimeType): string
    {
        return 'data:'.$mimeType.';base64,'.base64_encode($imageContents);
    }
}