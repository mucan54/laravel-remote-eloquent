<?php

namespace RemoteEloquent\Security;

use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Anti-Replay Attack Validator
 *
 * Prevents replay attacks by validating:
 * 1. Timestamp - Rejects requests older than configured minutes
 * 2. UUID/Nonce - Each request UUID can only be used once
 *
 * Security Benefits:
 * - Even if attacker captures encrypted payload, cannot replay it
 * - Timestamp: Payload expires after X minutes
 * - UUID: Payload can only be sent once (even within time window)
 * - Combined: Maximum protection against replay attacks
 */
class AntiReplayValidator
{
    /**
     * Validate request timestamp and UUID
     *
     * @param array $payload Decoded request payload
     * @throws \Exception
     */
    public static function validate(array $payload): void
    {
        // Validate timestamp if enabled
        if (config('remote-eloquent.anti_replay.timestamp_enabled', false)) {
            static::validateTimestamp($payload);
        }

        // Validate UUID if enabled
        if (config('remote-eloquent.anti_replay.uuid_enabled', false)) {
            static::validateUuid($payload);
        }
    }

    /**
     * Validate request timestamp
     *
     * @param array $payload
     * @throws \Exception
     */
    protected static function validateTimestamp(array $payload): void
    {
        if (!isset($payload['_timestamp'])) {
            throw new \Exception('Request timestamp missing. Possible replay attack.');
        }

        if (!isset($payload['_timezone'])) {
            throw new \Exception('Request timezone missing.');
        }

        // Parse timestamp with timezone
        try {
            $requestTime = Carbon::parse($payload['_timestamp'], $payload['_timezone']);
        } catch (\Exception $e) {
            throw new \Exception('Invalid timestamp format: ' . $e->getMessage());
        }

        // Get configured expiration time
        $expirationMinutes = config('remote-eloquent.anti_replay.timestamp_minutes', 5);

        // Calculate age of request
        $now = Carbon::now($payload['_timezone']);
        $ageInMinutes = $requestTime->diffInMinutes($now, false);

        // Check if request is from the future (clock skew attack)
        if ($ageInMinutes < 0) {
            throw new \Exception('Request timestamp is in the future. Possible clock skew or attack.');
        }

        // Check if request is too old
        if ($ageInMinutes > $expirationMinutes) {
            throw new \Exception("Request expired. Maximum age: {$expirationMinutes} minutes, actual: {$ageInMinutes} minutes.");
        }
    }

    /**
     * Validate request UUID (nonce)
     *
     * @param array $payload
     * @throws \Exception
     */
    protected static function validateUuid(array $payload): void
    {
        if (!isset($payload['_uuid'])) {
            throw new \Exception('Request UUID missing. Possible replay attack.');
        }

        $uuid = $payload['_uuid'];

        // Validate UUID format
        if (!static::isValidUuid($uuid)) {
            throw new \Exception('Invalid UUID format.');
        }

        // Build cache key
        $cacheKey = 'remote_eloquent_uuid:' . $uuid;

        // Check if UUID has been used before
        if (Cache::has($cacheKey)) {
            throw new \Exception('Request UUID already used. Replay attack detected.');
        }

        // Store UUID in cache to prevent reuse
        // Cache duration = timestamp expiration (or 60 minutes if timestamp disabled)
        $cacheDuration = config('remote-eloquent.anti_replay.timestamp_enabled', false)
            ? config('remote-eloquent.anti_replay.timestamp_minutes', 5)
            : 60;

        Cache::put($cacheKey, true, now()->addMinutes($cacheDuration));
    }

    /**
     * Validate UUID format
     *
     * @param string $uuid
     * @return bool
     */
    protected static function isValidUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Add timestamp and UUID to payload (client-side)
     *
     * @param array $payload
     * @return array Payload with _timestamp, _timezone, and _uuid added
     */
    public static function addSecurityFields(array $payload): array
    {
        // Add timestamp if enabled
        if (config('remote-eloquent.anti_replay.timestamp_enabled', false)) {
            $now = Carbon::now();
            $payload['_timestamp'] = $now->toIso8601String();
            $payload['_timezone'] = $now->getTimezone()->getName();
        }

        // Add UUID if enabled
        if (config('remote-eloquent.anti_replay.uuid_enabled', false)) {
            $payload['_uuid'] = static::generateUuid();
        }

        return $payload;
    }

    /**
     * Generate UUID v4
     *
     * @return string
     */
    protected static function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Remove security fields from payload after validation
     *
     * @param array $payload
     * @return array Clean payload without _timestamp, _timezone, _uuid
     */
    public static function removeSecurityFields(array $payload): array
    {
        unset($payload['_timestamp'], $payload['_timezone'], $payload['_uuid']);
        return $payload;
    }
}
