<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\SequenceEnrollment;
use App\Services\BusinessHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Webhook verification GET request required by Meta Cloud API.
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expectedToken = config('services.whatsapp.verify_token', env('WHATSAPP_VERIFY_TOKEN', 'crm_whatsapp_verify_token_2026'));

        if ($mode === 'subscribe' && $token === $expectedToken) {
            return response($challenge, 200);
        }

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * Webhook incoming POST event handler from Meta Cloud API.
     */
    public function handle(Request $request)
    {
        $data = $request->all();

        // Extract incoming message details from Meta webhook payload structure
        $entries = $data['entry'] ?? [];
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $messages = $value['messages'] ?? [];
                
                foreach ($messages as $message) {
                    $fromPhone = '+' . ltrim($message['from'] ?? '', '+');
                    $text = $message['text']['body'] ?? ($message['type'] ?? 'message');

                    $this->processIncomingMessage($fromPhone, $text);
                }
            }
        }

        return response()->json(['status' => 'EVENT_RECEIVED'], 200);
    }

    /**
     * Process incoming message, update qualification condition q_replied_at, 
     * record response time SLA, and stop active sequences.
     */
    protected function processIncomingMessage(string $fromPhone, string $text): void
    {
        $contact = Contact::withoutGlobalScopes()->where('phone_e164', $fromPhone)->first();

        if (!$contact) {
            Log::info("WhatsApp message received from unknown number: {$fromPhone}");
            return;
        }

        session(['tenant_id' => $contact->tenant_id]);

        // Record incoming message in activity log
        Activity::create([
            'tenant_id' => $contact->tenant_id,
            'contact_id' => $contact->id,
            'type' => 'whatsapp',
            'channel' => 'whatsapp',
            'body' => "Message reçu: {$text}",
            'occurred_at' => now(),
        ]);

        $updates = [];

        // Condition #1 for qualification: q_replied_at
        if (!$contact->q_replied_at) {
            $updates['q_replied_at'] = now();
        }

        // SLA First Response tracking
        if (!$contact->first_response_at) {
            $firstResponseAt = now();
            $minutes = BusinessHours::diffInMinutes($contact->created_at, $firstResponseAt);
            
            $updates['first_response_at'] = $firstResponseAt;
            $updates['first_response_minutes'] = $minutes;
        }

        if (!empty($updates)) {
            $contact->update($updates);
        }

        // ABSOLUTE RULE: Any reply from the prospect immediately STOPS all active sequences
        SequenceEnrollment::withoutGlobalScopes()
            ->where('contact_id', $contact->id)
            ->where('status', 'active')
            ->update([
                'status' => 'stopped',
                'stopped_at' => now(),
                'stop_reason' => 'prospect_replied',
            ]);
    }
}
