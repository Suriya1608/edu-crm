<?php

namespace App\Services;

use App\Models\Lead;

/**
 * Race-condition-free lead code generator.
 *
 * Strategy: create the lead first (with a temporary unique placeholder),
 * then derive the final code from the actual auto-incremented ID.
 * Because IDs are guaranteed unique by the DB, no two leads can ever
 * receive the same code — even under high concurrency.
 *
 * Usage:
 *   $lead = Lead::create(['lead_code' => LeadCodeGenerator::placeholder(), ...]);
 *   LeadCodeGenerator::assignCode($lead);
 */
class LeadCodeGenerator
{
    private const PREFIX = 'SMIT';

    /**
     * Generate a temporary placeholder that satisfies the NOT NULL + UNIQUE
     * constraint on lead_code while the real code has not yet been computed.
     */
    public static function placeholder(): string
    {
        return 'TMP-' . uniqid('', true);
    }

    /**
     * Derive the final lead code from the lead's auto-increment ID and
     * persist it immediately.  Call this right after Lead::create().
     */
    public static function assignCode(Lead $lead): void
    {
        $lead->lead_code = self::fromId($lead->id);
        $lead->saveQuietly();
    }

    /**
     * Build the formatted code string from a numeric ID.
     */
    public static function fromId(int $id): string
    {
        return self::PREFIX . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}
