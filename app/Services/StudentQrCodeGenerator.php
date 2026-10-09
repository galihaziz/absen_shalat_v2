<?php

namespace App\Services;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class StudentQrCodeGenerator
{
    public function svg(string $induk): string
    {
        return $this->render($induk);
    }

    public function svgForHaid(string $induk): string
    {
        return $this->render($induk);
    }

    private function render(string $data): string
    {
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 320,
            margin: 16,
        );

        return (new SvgWriter)->write($qrCode)->getString();
    }
}