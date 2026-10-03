<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\User;
use App\Services\Crypto\SHA256;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Verification report (PDF). Generated once, when the complaint is verified,
 * and stored. Its SHA-256 is recorded on the complaint so later downloads can
 * prove the file was not altered.
 */
class ReportService
{
    public static function path(Complaint $complaint): string
    {
        return 'reports/' . $complaint->reference . '.pdf';
    }

    public static function generate(Complaint $complaint, ?User $actor = null): string
    {
        @set_time_limit(300);
        $complaint->loadMissing(['restaurant', 'evidence', 'history.actor', 'officer']);

        $integrity = $complaint->integrity();
        $pdf = Pdf::loadView('reports.complaint', [
            'complaint' => $complaint,
            'integrity' => $integrity,
            'contentHash' => $complaint->contentHash(),
            'qr' => QrService::dataUri($complaint->publicUrl(), 160),
            'generatedAt' => now(),
            'generatedBy' => $actor,
        ])->setPaper('a4');

        $bytes = $pdf->output();
        Storage::disk('local')->put(self::path($complaint), $bytes);

        $complaint->forceFill([
            'report_sha256' => SHA256::hash($bytes),
            'report_generated_at' => now(),
        ])->save();

        AuditLogger::log('report.generated', $complaint, ['reference' => $complaint->reference, 'sha256' => $complaint->report_sha256], $actor);

        return $bytes;
    }

    public static function download(Complaint $complaint, User $user)
    {
        $disk = Storage::disk('local');
        $path = self::path($complaint);

        if (! $complaint->report_sha256 || ! $disk->exists($path)) {
            $bytes = self::generate($complaint, $user);
        } else {
            $bytes = $disk->get($path);
            if (SHA256::hash($bytes) !== $complaint->report_sha256) {
                AuditLogger::log('report.tamper_detected', $complaint, ['reference' => $complaint->reference], $user);
                abort(409, 'The stored verification report failed its integrity check. Please contact the administrator.');
            }
        }

        AuditLogger::log('report.downloaded', $complaint, ['reference' => $complaint->reference], $user);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $complaint->reference . '-verification-report.pdf"',
            'X-Report-SHA256' => $complaint->report_sha256,
        ]);
    }
}
