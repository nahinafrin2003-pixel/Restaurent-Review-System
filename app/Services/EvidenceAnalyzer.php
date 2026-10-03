<?php

namespace App\Services;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Local, free evidence analysis:
 *  - blur detection: variance of the Laplacian on a grey-scale thumbnail (pure PHP + GD)
 *  - receipt OCR: optional Tesseract CLI, then regex extraction of restaurant / date / amount
 */
class EvidenceAnalyzer
{
    /** Below this Laplacian variance an image is flagged as blurry. */
    public const BLUR_THRESHOLD = 60.0;

    public static function blurScore(string $path): ?float
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $data = @file_get_contents($path);
        $image = $data ? @imagecreatefromstring($data) : false;
        if (! $image) {
            return null;
        }

        // Downscale to max 320 px for speed and to normalise the score across resolutions.
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, 320 / max($w, $h));
        $tw = max(3, (int) round($w * $scale));
        $th = max(3, (int) round($h * $scale));
        $thumb = imagecreatetruecolor($tw, $th);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $tw, $th, $w, $h);
        imagedestroy($image);

        $grey = [];
        for ($y = 0; $y < $th; $y++) {
            for ($x = 0; $x < $tw; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $grey[$y][$x] = 0.299 * (($rgb >> 16) & 0xff) + 0.587 * (($rgb >> 8) & 0xff) + 0.114 * ($rgb & 0xff);
            }
        }
        imagedestroy($thumb);

        // 4-neighbour Laplacian kernel [0 1 0; 1 -4 1; 0 1 0]
        $sum = 0.0;
        $sumSq = 0.0;
        $n = 0;
        for ($y = 1; $y < $th - 1; $y++) {
            for ($x = 1; $x < $tw - 1; $x++) {
                $lap = $grey[$y - 1][$x] + $grey[$y + 1][$x] + $grey[$y][$x - 1] + $grey[$y][$x + 1] - 4 * $grey[$y][$x];
                $sum += $lap;
                $sumSq += $lap * $lap;
                $n++;
            }
        }
        if ($n === 0) {
            return null;
        }
        $mean = $sum / $n;

        return round($sumSq / $n - $mean * $mean, 2);
    }

    public static function tesseractPath(): ?string
    {
        $configured = config('services.tesseract.path');
        if ($configured && is_file($configured)) {
            return $configured;
        }

        return (new ExecutableFinder())->find('tesseract');
    }

    public static function ocrAvailable(): bool
    {
        return self::tesseractPath() !== null;
    }

    /** Returns ['text' => ..., 'restaurant' => ..., 'date' => ..., 'amount' => ...] or null when OCR is unavailable. */
    public static function ocr(string $path): ?array
    {
        $binary = self::tesseractPath();
        if (! $binary) {
            return null;
        }

        try {
            $process = new Process([$binary, $path, 'stdout', '--psm', '6']);
            $process->setTimeout(40);
            $process->run();
            if (! $process->isSuccessful()) {
                return null;
            }
            $text = trim($process->getOutput());
        } catch (\Throwable $e) {
            \Log::warning('OCR failed: ' . $e->getMessage());

            return null;
        }

        return ['text' => $text] + self::parseReceipt($text);
    }

    public static function parseReceipt(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), fn ($l) => mb_strlen($l) >= 3));

        // Restaurant: first line that is mostly letters and not a generic header.
        $restaurant = null;
        foreach (array_slice($lines, 0, 5) as $line) {
            $letters = preg_match_all('/\p{L}/u', $line);
            if ($letters >= 3 && $letters / max(1, mb_strlen($line)) > 0.5
                && ! preg_match('/^(invoice|receipt|bill|tax|cash memo|vat)/i', $line)) {
                $restaurant = mb_substr($line, 0, 120);
                break;
            }
        }

        $date = null;
        $datePatterns = [
            '/\b(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4})\b/',
            '/\b(\d{4}[\/\-.]\d{1,2}[\/\-.]\d{1,2})\b/',
            '/\b(\d{1,2}\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*[,\s]+\d{2,4})\b/i',
        ];
        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $date = $m[1];
                break;
            }
        }

        $amount = null;
        if (preg_match_all('/(?:grand\s*total|net\s*total|total|amount|payable)[^\d\n]{0,15}([\d,]+(?:\.\d{1,2})?)/i', $text, $m) && $m[1]) {
            $amount = str_replace(',', '', end($m[1]));
        } elseif (preg_match_all('/(?:tk|bdt|৳)\s*([\d,]+(?:\.\d{1,2})?)/i', $text, $m) && $m[1]) {
            $amount = str_replace(',', '', end($m[1]));
        }

        return ['restaurant' => $restaurant, 'date' => $date, 'amount' => $amount];
    }
}
