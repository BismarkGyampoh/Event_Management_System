<?php

final class ResendMailer
{
    public function send(string $to, string $subject, string $html, string $idempotencyKey): string
    {
        $apiKey = getenv('RESEND_API_KEY') ?: '';
        $from = getenv('RESEND_FROM_EMAIL') ?: '';
        if ($apiKey === '' || $from === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email delivery is not configured.');
        }

        $payload = json_encode([
            'from' => $from,
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ], JSON_THROW_ON_ERROR);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Idempotency-Key: ' . substr($idempotencyKey, 0, 200),
                ]),
                'content' => $payload,
                'timeout' => 15,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
                'protocol_version' => 1.1,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $responseBody = @file_get_contents('https://api.resend.com/emails', false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $statusMatch);
        $statusCode = (int)($statusMatch[1] ?? 0);
        $response = is_string($responseBody) ? json_decode($responseBody, true) : null;
        if ($statusCode < 200 || $statusCode >= 300 || !is_array($response) || empty($response['id'])) {
            error_log('Resend email request failed with HTTP status ' . ($statusCode ?: 'unknown') . '.');
            throw new RuntimeException('Email could not be sent.');
        }
        return $response['id'];
    }
}
