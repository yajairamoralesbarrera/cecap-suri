<?php

namespace App\Mail;

use App\Models\Enrollment;
use App\Models\InstitutionSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrollmentRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Enrollment $enrollment,
        public string $reasonLabel,
        public ?string $additionalNotes,
        public InstitutionSetting $settings
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Resolución de solicitud %s — %s',
                $this->enrollment->enrollment_code,
                $this->settings->institution_name
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enrollment-rejected',
        );
    }
}
