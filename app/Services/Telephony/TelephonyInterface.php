<?php

namespace App\Services\Telephony;

interface TelephonyInterface
{
    public function makeCall(string $from, string $to): array;
}
