<?php

namespace App\Services;

use App\Models\CallLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Telephony\TelephonyFactory;
use Illuminate\Support\Facades\Log;

/**
 * Unified call service — delegates to the provider selected in admin settings.
 *
 * Provider is read from Setting::get('primary_call_provider') on every call,
 * so admin can switch providers at runtime without a code deploy.
 */
class CallService
{
    /** Return the currently configured provider (twilio | exotel). */
    public function getProvider(): string
    {
        return (string) Setting::get('primary_call_provider', 'twilio');
    }

    /**
     * Initiate a server-side Exotel call.
     *
     * Creates a CallLog record first, then asks Exotel to bridge
     * the telecaller's mobile to the lead's phone.
     *
     * @param  User   $telecaller
     * @param  string $leadPhone
     * @param  int    $leadId
     * @return array  ['success', 'call_log_id', 'call_sid', 'message'] or ['success' => false, 'error']
     */

    /**
     * Normalize a phone number to Exotel-compatible format: 91XXXXXXXXXX (no +, no leading 0).
     * Handles: +91XXXXXXXXXX, 91XXXXXXXXXX, 0XXXXXXXXXX, XXXXXXXXXX (10-digit Indian numbers).
     */
    private function normalizePhone(string $phone): string
    {
        // Strip everything except digits
        $digits = preg_replace('/\D/', '', $phone);

        // 10-digit Indian mobile → prepend 91
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        // +91XXXXXXXXXX stored as 9110-digit after strip → already 12 digits starting with 91
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }

        // 0XXXXXXXXXX (11 digits, leading 0) → replace leading 0 with 91
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91' . substr($digits, 1);
        }

        // Return as-is (international numbers, already correct, etc.)
        return $digits;
    }

    public function makeExotelCall(User $telecaller, string $leadPhone, int $leadId): array
    {
        $telecallerPhone = $telecaller->phone;

        if (empty($telecallerPhone)) {
            return [
                'success' => false,
                'error'   => 'Telecaller does not have a phone number configured in their profile.',
            ];
        }

        // Normalize both numbers to Exotel format: 91XXXXXXXXXX
        $fromNormalized = $this->normalizePhone($telecallerPhone);
        $toNormalized   = $this->normalizePhone($leadPhone);

        Log::info('[CallService] Exotel number normalization', [
            'telecaller_raw'  => $telecallerPhone,
            'telecaller_norm' => $fromNormalized,
            'lead_raw'        => $leadPhone,
            'lead_norm'       => $toNormalized,
        ]);

        // Create call log before calling so we have an ID for the status callback
        $callLog = CallLog::create([
            'lead_id'         => $leadId,
            'user_id'         => $telecaller->id,
            'customer_number' => $toNormalized,
            'provider'        => 'exotel',
            'direction'       => 'outbound',
            'status'          => 'initiated',
        ]);

        try {
            $service  = TelephonyFactory::make('exotel');
            $callback = route('exotel.webhook', ['call_log_id' => $callLog->id]);
            $result   = $service->makeCall($fromNormalized, $toNormalized, $callback);

            // Exotel may return 'Sid', 'SId', or 'sid' depending on API version
            $callData = $result['Call'] ?? [];
            $callSid  = $callData['Sid'] ?? $callData['SId'] ?? $callData['sid'] ?? null;
            $status   = $callData['Status'] ?? $callData['status'] ?? 'initiated';

            if (isset($result['RestException'])) {
                // Exotel returns a RestException object on failure
                $errMsg = $result['RestException']['Message'] ?? 'Exotel API error';
                Log::error('[CallService] Exotel call failed', ['result' => $result, 'call_log_id' => $callLog->id]);

                $callLog->update(['status' => 'failed', 'end_reason' => $errMsg]);

                return ['success' => false, 'error' => $errMsg];
            }

            $callLog->update([
                'call_sid' => $callSid,
                'status'   => $status,
            ]);

            Log::info('[CallService] Exotel call initiated', [
                'call_log_id' => $callLog->id,
                'call_sid'    => $callSid,
                'status'      => $status,
            ]);

            return [
                'success'     => true,
                'call_log_id' => $callLog->id,
                'call_sid'    => $callSid,
                'message'     => 'Exotel is calling your phone. Once you answer, we will connect you to the lead.',
            ];
        } catch (\Throwable $e) {
            Log::error('[CallService] Exotel call exception', [
                'error'       => $e->getMessage(),
                'call_log_id' => $callLog->id,
            ]);

            $callLog->update(['status' => 'failed', 'end_reason' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
