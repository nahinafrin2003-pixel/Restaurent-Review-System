<?php

namespace App\Http\Controllers;

use App\Models\ComplaintEvidence;
use App\Services\AuditLogger;
use App\Services\Crypto\SHA256;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class EvidenceController extends Controller
{
    /** Streams an evidence file to its owner or to staff. Every access is audit-logged. */
    public function show(Request $request, ComplaintEvidence $evidence)
    {
        @set_time_limit(120);
        $user = $request->attributes->get('auth_user');
        $complaint = $evidence->complaint;
        abort_unless($complaint && ($complaint->user_id === $user->user_id || $user->isStaff()), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($evidence->stored_path), 404, 'Evidence file is missing.');
        $absolute = $disk->path($evidence->stored_path);

        // Re-hash small files on every view; large media is checked on demand (?verify=1).
        $integrity = 'not-checked';
        if ($evidence->size <= 8 * 1024 * 1024 || $request->boolean('verify')) {
            $integrity = SHA256::file($absolute) === $evidence->sha256 ? 'ok' : 'MISMATCH';
        }

        AuditLogger::log('evidence.viewed', $evidence, [
            'complaint' => $complaint->reference,
            'type' => $evidence->type,
            'integrity' => $integrity,
        ]);

        if ($integrity === 'MISMATCH') {
            abort(409, 'This evidence file failed its SHA-256 integrity check and may have been tampered with.');
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->file($absolute, [
            'Content-Type' => $evidence->mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, str_replace(['/', '\\', '%'], '_', $evidence->original_name), 'evidence-' . $evidence->id . '.' . pathinfo($evidence->stored_path, PATHINFO_EXTENSION)),
            'X-Evidence-SHA256' => $evidence->sha256,
            'X-Evidence-Integrity' => $integrity,
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
