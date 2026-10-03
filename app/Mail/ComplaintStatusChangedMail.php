<?php

namespace App\Mail;

use App\Models\Complaint;
use App\Support\ComplaintStatus;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ComplaintStatusChangedMail extends Mailable
{
    public function __construct(public Complaint $complaint, public ?string $from, public string $to, public ?string $note)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->complaint->reference}] Status: " . ComplaintStatus::label($this->to));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.complaint_status', with: [
            'complaint' => $this->complaint,
            'fromLabel' => $this->from ? ComplaintStatus::label($this->from) : null,
            'toLabel' => ComplaintStatus::label($this->to),
            'note' => $this->note,
            'url' => url('/complaints/' . $this->complaint->id),
        ]);
    }
}
