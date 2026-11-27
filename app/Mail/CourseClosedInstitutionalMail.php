<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InstitutionSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourseClosedInstitutionalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Course $course,
        public Enrollment $enrollment,
        public string $closureReason,
        public string $studentMessage,
        public InstitutionSetting $settings
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Aviso importante sobre el curso %s (%s) — %s',
                $this->course->name,
                $this->course->code,
                $this->settings->institution_name
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.course-closed-institutional',
        );
    }
}
