<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EventDeposit;
use App\Models\EventProposal;
use App\Services\EventDateHoldService;
use App\Services\EventInquiryMailService;
use App\Support\EventPaymentSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View as ViewContract;

class EventProposalController extends Controller
{
    private function findProposal(string $token): EventProposal
    {
        $proposal = EventProposal::query()->where('token_hash', hash('sha256', $token))->whereNull('superseded_at')->firstOrFail();
        abort_if($proposal->expires_at->isPast() && $proposal->accepted_at === null, 410, 'This proposal has expired. Contact the restaurant for an updated proposal.');
        return $proposal->load('inquiry.package');
    }

    public function show(string $token): View
    {
        $proposal = $this->findProposal($token);
        return view('landing.event-proposal', [
            'proposal' => $proposal, 'inquiry' => $proposal->inquiry, 'quote' => $proposal->snapshot['quote'],
            'payment' => $proposal->snapshot['payment'],
            'qrImage' => EventPaymentSettings::qrDataUri($proposal->snapshot['payment']['qr_image_path'] ?? null),
            'depositRecord' => $proposal->latestDeposit,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $request->validate(['accept_terms' => ['accepted']]);
        $proposal = $this->findProposal($token);
        abort_if($proposal->accepted_at, 409, 'This proposal has already been accepted.');
        DB::transaction(function () use ($proposal): void {
            $proposal->update(['accepted_at' => now()]);
            $proposal->inquiry->update(['status' => 'proposal_accepted']);
        });
        return back()->with('success', 'Proposal accepted. Submit your deposit proof below to continue.');
    }

    public function submitDeposit(Request $request, string $token): RedirectResponse
    {
        $proposal = $this->findProposal($token);
        abort_unless($proposal->accepted_at, 403, 'Accept the proposal before submitting deposit proof.');
        abort_if($proposal->expires_at->isPast(), 410, 'This proposal hold has expired. Contact the restaurant.');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'method' => ['nullable', 'string', 'max:40'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);
        $path = $request->file('proof')->store('event-deposit-proofs', 'local');
        $proposal->deposits()->create(['amount' => $data['amount'], 'paid_at' => $data['paid_at'] ?? null, 'method' => $data['method'] ?? null, 'status' => 'submitted', 'proof_path' => $path]);
        $proposal->inquiry->update(['status' => 'deposit_submitted']);
        return back()->with('success', 'Deposit proof submitted. The restaurant will review it and email you after review.');
    }

    public function review(Request $request, EventDeposit $deposit, EventDateHoldService $holds, EventInquiryMailService $mail): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:verified,rejected'],
            'review_note' => ['nullable', 'string', 'max:2000'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $proposal = $deposit->proposal()->with('inquiry')->firstOrFail();
        if ($data['decision'] === 'rejected') {
            $deposit->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null]);
            $proposal->inquiry->update(['status' => 'proposal_accepted']);
            return back()->with('status', 'Deposit proof rejected; customer can submit updated proof.');
        }
        if ((float) $deposit->amount < (float) $proposal->snapshot['quote']['deposit']) {
            throw ValidationException::withMessages(['decision' => 'The submitted amount is below the required deposit.']);
        }
        if ($proposal->expires_at->isPast() && blank($data['override_reason'])) {
            throw ValidationException::withMessages(['override_reason' => 'Explain why this expired proposal is being approved.']);
        }
        DB::transaction(function () use ($deposit, $proposal, $data, $holds, $request): void {
            if ($proposal->expires_at->isPast()) $holds->acquire($proposal->inquiry, $proposal, now()->addHour());
            $holds->markBooked($proposal->inquiry);
            $deposit->update(['status' => 'verified', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null, 'override_reason' => $data['override_reason'] ?? null]);
            $proposal->inquiry->update(['status' => 'booked']);
        });
        $emailLog = $mail->sendBookingConfirmation($proposal->fresh());
        $message = $emailLog->status === 'sent'
            ? 'Deposit verified, booking confirmed, and customer emailed.'
            : 'Deposit verified and booking confirmed, but confirmation email was not delivered. Check SMTP configuration.';
        return back()->with('status', $message);
    }

    public function proof(EventDeposit $deposit)
    {
        abort_unless($deposit->proof_path && Storage::disk('local')->exists($deposit->proof_path), 404);
        return Storage::disk('local')->download($deposit->proof_path, basename($deposit->proof_path));
    }

    public function settings(): ViewContract
    {
        $deposits = EventDeposit::query()->with(['proposal.inquiry'])->where('status', 'submitted')->latest()->get();
        return view('admin.event-payment-settings', ['settings' => EventPaymentSettings::all(), 'deposits' => $deposits]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'deposit_percent' => ['required', 'numeric', 'min:1', 'max:100'], 'hold_duration_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'bank_name' => ['nullable', 'string', 'max:120'], 'account_name' => ['nullable', 'string', 'max:120'], 'account_number' => ['nullable', 'string', 'max:80'],
            'contact_email' => ['nullable', 'email', 'max:255'], 'contact_phone' => ['nullable', 'string', 'max:40'],
            'deposit_policy' => ['nullable', 'string', 'max:2000'], 'cancellation_policy' => ['nullable', 'string', 'max:2000'],
            'qr_image' => ['nullable', 'image', 'mimes:png', 'max:4096'],
        ]);
        foreach ($data as $key => $value) if ($key !== 'qr_image') EventPaymentSettings::put($key, (string) ($value ?? ''));
        if ($request->hasFile('qr_image')) EventPaymentSettings::put('qr_image_path', $request->file('qr_image')->store('event-payments', 'local'));
        return back()->with('status', 'Event deposit settings saved.');
    }
}
