<?php

namespace App\Services;

use App\Mail\ComplaintStatusChangedMail;
use App\Models\Complaint;
use App\Models\ComplaintEvidence;
use App\Models\ComplaintStatusHistory;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Crypto\SHA256;
use App\Support\ComplaintStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** All complaint state changes go through here so history, e-mail, trust score, rewards and audit stay consistent. */
class ComplaintWorkflow
{
    public static function submit(User $user, Restaurant $restaurant, array $data, array $files = []): Complaint
    {
        $classification = ComplaintClassifier::classify($data['title'] . ' ' . $data['description']);
        $category = ($data['category'] ?? 'auto') === 'auto' ? $classification['category'] : $data['category'];

        $complaint = DB::transaction(function () use ($user, $restaurant, $data, $classification, $category) {
            $complaint = Complaint::create([
                'user_id' => $user->user_id,
                'restaurant_id' => $restaurant->id,
                'category' => $category,
                'auto_category' => $classification['category'],
                'category_confidence' => $classification['confidence'],
                'title' => $data['title'],
                'description' => $data['description'],
                'incident_date' => $data['incident_date'] ?? null,
                'district' => $restaurant->district,
                'status' => ComplaintStatus::SUBMITTED,
            ]);

            $complaint->reference = Complaint::makeReference($complaint->id);
            $complaint->public_token = Complaint::makePublicToken($complaint->reference);
            $complaint->seal();
            $complaint->save();

            ComplaintStatusHistory::create([
                'complaint_id' => $complaint->id,
                'from_status' => null,
                'to_status' => ComplaintStatus::SUBMITTED,
                'changed_by' => $user->user_id,
                'note' => 'Complaint submitted by customer.',
                'created_at' => now(),
            ]);

            return $complaint;
        });

        foreach ($files as $type => $list) {
            foreach ((array) $list as $file) {
                if ($file instanceof UploadedFile) {
                    self::storeEvidence($complaint, $file, $type, $user);
                }
            }
        }

        DuplicateDetector::detect($complaint);
        AuditLogger::log('complaint.submitted', $complaint, ['reference' => $complaint->reference, 'category' => $category], $user);
        self::notify($complaint, null, ComplaintStatus::SUBMITTED, 'We have received your complaint.');

        return $complaint;
    }

    public static function storeEvidence(Complaint $complaint, UploadedFile $file, string $type, User $uploader): ComplaintEvidence
    {
        @set_time_limit(120);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs('evidence/' . $complaint->id, Str::random(32) . '.' . $extension, 'local');
        $absolute = Storage::disk('local')->path($path);
        $mime = $file->getMimeType() ?: 'application/octet-stream';

        $evidence = new ComplaintEvidence([
            'complaint_id' => $complaint->id,
            'uploaded_by' => $uploader->user_id,
            'type' => $type,
            'original_name' => $file->getClientOriginalName() ?: basename($path),
            'stored_path' => $path,
            'mime' => $mime,
            'size' => filesize($absolute),
            'sha256' => SHA256::file($absolute),
            'created_at' => now(),
        ]);

        if (str_starts_with($mime, 'image/')) {
            $score = EvidenceAnalyzer::blurScore($absolute);
            $evidence->blur_score = $score;
            $evidence->is_blurry = $score !== null && $score < EvidenceAnalyzer::BLUR_THRESHOLD;

            if ($type === 'receipt' && ($ocr = EvidenceAnalyzer::ocr($absolute))) {
                $evidence->ocr_text = mb_substr($ocr['text'], 0, 4000);
                $evidence->ocr_restaurant = $ocr['restaurant'] ? mb_substr($ocr['restaurant'], 0, 200) : null;
                $evidence->ocr_date = $ocr['date'];
                $evidence->ocr_amount = $ocr['amount'];
            }
        }

        $evidence->save();
        AuditLogger::log('evidence.uploaded', $evidence, ['complaint' => $complaint->reference, 'type' => $type, 'sha256' => $evidence->sha256], $uploader);

        return $evidence;
    }

    public static function transition(Complaint $complaint, string $to, User $actor, ?string $note = null, ?string $outcome = null): Complaint
    {
        $from = $complaint->status;
        if (! ComplaintStatus::canTransition($from, $to)) {
            throw new InvalidArgumentException('Cannot move a complaint from "' . ComplaintStatus::label($from) . '" to "' . ComplaintStatus::label($to) . '".');
        }

        DB::transaction(function () use ($complaint, $from, $to, $actor, $note, $outcome) {
            $complaint->status = $to;
            if ($to === ComplaintStatus::VERIFIED && ! $complaint->verified_at) {
                $complaint->verified_at = now();
            }
            if (in_array($to, [ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED, ComplaintStatus::REJECTED], true)) {
                $complaint->resolved_at = now();
            }
            if ($outcome !== null && $outcome !== '') {
                $complaint->outcome = mb_substr($outcome, 0, 255);
            }
            if (! $complaint->assigned_officer_id && $actor->isOfficer()) {
                $complaint->assigned_officer_id = $actor->user_id;
            }
            $complaint->save();

            ComplaintStatusHistory::create([
                'complaint_id' => $complaint->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor->user_id,
                'note' => $note,
                'created_at' => now(),
            ]);
        });

        if (in_array($to, [ComplaintStatus::VERIFIED, ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED], true)) {
            TrustScoreService::recalculate($complaint->restaurant);
            RewardService::onStatusChange($complaint, $to);
        }

        if ($to === ComplaintStatus::VERIFIED && ! $complaint->report_sha256) {
            try {
                ReportService::generate($complaint, $actor);
            } catch (\Throwable $e) {
                \Log::warning('Report generation failed: ' . $e->getMessage());
            }
        }

        AuditLogger::log('complaint.status_changed', $complaint, ['from' => $from, 'to' => $to, 'reference' => $complaint->reference], $actor);
        self::notify($complaint, $from, $to, $note);

        return $complaint;
    }

    public static function notify(Complaint $complaint, ?string $from, string $to, ?string $note): void
    {
        try {
            $email = $complaint->user?->email;
            if ($email && ! str_ends_with($email, '.test')) {   // .test = demo accounts, never deliverable
                Mail::to($email)->send(new ComplaintStatusChangedMail($complaint, $from, $to, $note));
            }
        } catch (\Throwable $e) {
            \Log::warning('Complaint status e-mail failed: ' . $e->getMessage());
        }
    }
}
