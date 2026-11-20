<?php

final class PaystackWebhookVerifier
{
    public static function verify(string $rawBody, array $server, string $timestampSecret, string $legacySecret): bool
    {
        $signatureHeader = $server['HTTP_PAYSTACK_SIGNATURE'] ?? '';
        if ($signatureHeader !== '' && $timestampSecret !== '') {
            $timestamp = null;
            $signatures = [];
            foreach (explode(',', $signatureHeader) as $part) {
                $pair = explode('=', trim($part), 2);
                if (count($pair) !== 2) continue;
                if ($pair[0] === 't' && ctype_digit($pair[1])) {
                    if ($timestamp !== null) return false;
                    $timestamp = (int)$pair[1];
                } elseif ($pair[0] === 'v1') {
                    $keyAndSignature = explode(':', $pair[1], 2);
                    if (count($keyAndSignature) === 2 && preg_match('/\A[a-f0-9]{128}\z/i', $keyAndSignature[1])) {
                        $signatures[] = strtolower($keyAndSignature[1]);
                    }
                }
            }
            if ($timestamp === null || abs(time() - $timestamp) > 300 || !$signatures) {
                return false;
            }
            $expected = hash_hmac('sha512', $timestamp . '.' . $rawBody, $timestampSecret);
            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) return true;
            }
            return false;
        }

        $legacySignature = $server['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        if ($legacySignature !== '' && $legacySecret !== '') {
            $expected = hash_hmac('sha512', $rawBody, $legacySecret);
            return preg_match('/\A[a-f0-9]{128}\z/i', $legacySignature) === 1
                && hash_equals($expected, strtolower($legacySignature));
        }
        return false;
    }
}
