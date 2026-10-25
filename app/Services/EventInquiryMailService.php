<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventEmailLog;
use App\Models\EventInquiry;
use App\Models\EventProposal;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class EventInquiryMailService
{
    public function sendAcknowledgement(EventInquiry $inquiry): EventEmailLog
    {
        $log = EventEmailLog::create(['event_inquiry_id' => $inquiry->id, 'type' => 'inquiry_acknowledgement', 'recipient' => $inquiry->email, 'status' => 'queued', 'queued_at' => now()]);
        if ($this->nonDeliveringMailer()) {
            $log->update(['status' => 'not_sent', 'error' => 'Configured mailer does not deliver email; configure SMTP.']);
            return $log->refresh();
        }
        try {
            Mail::send('emails.event-inquiry-received', ['inquiry' => $inquiry], function ($mail) use ($inquiry): void {
                $mail->to($inquiry->email, trim($inquiry->first_name.' '.$inquiry->last_name))->subject('We received your event inquiry · '.$inquiry->reference);
            });
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 4000)]);
            report($e);
        }
        return $log->refresh();
    }

    public function sendBookingConfirmation(EventProposal $proposal): EventEmailLog
    {
        $inquiry = $proposal->inquiry;
        $log = EventEmailLog::create(['event_inquiry_id' => $inquiry->id, 'event_proposal_id' => $proposal->id, 'type' => 'booking_confirmation', 'recipient' => $inquiry->email, 'status' => 'queued', 'queued_at' => now()]);
        if ($this->nonDeliveringMailer()) {
            $log->update(['status' => 'not_sent', 'error' => 'Configured mailer does not deliver email; configure SMTP.']);
            return $log->refresh();
        }
        try {
            Mail::send('emails.event-booking-confirmed', ['proposal' => $proposal, 'inquiry' => $inquiry], function ($mail) use ($inquiry): void {
                $mail->to($inquiry->email, trim($inquiry->first_name.' '.$inquiry->last_name))->subject('Your event booking is confirmed · '.$inquiry->reference);
            });
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 4000)]);
            report($e);
        }
        return $log->refresh();
    }

    private function nonDeliveringMailer(): bool
    {
        return in_array(config('mail.default'), ['log', 'array'], true);
    }
}
