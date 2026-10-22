<?php

namespace App\Mail;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderDue extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('ui.reminders.email_subject', ['title' => $this->reminder->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder-due',
            with: [
                'reminder' => $this->reminder,
                'url' => route('reminders.index'),
            ],
        );
    }
}
