<?php

require_once __DIR__ . '/PaymentGatewayInterface.php';

final class PaystackGateway implements PaymentGatewayInterface
{
    private $secretKey;

    public function __construct()
    {
        if (getenv('PAYSTACK_MODE') !== 'test') {
            throw new RuntimeException('Online payments are available in test mode only.');
        }

        $this->secretKey = getenv('PAYSTACK_TEST_SECRET_KEY') ?: '';
        if (!preg_match('/\Ask_test_[A-Za-z0-9]+\z/', $this->secretKey)) {
            throw new RuntimeException('Paystack test configuration is incomplete.');
        }
    }

    public function initialize(array $transaction): array
    {
        return $this->request('POST', '/transaction/initialize', $transaction);
    }

    public function verify(string $reference): array
    {
        return $this->request('GET', '/transaction/verify/' . rawurlencode($reference));
    }

    public function resolveBankAccount(string $bankCode, string $accountNumber): array
    {
        return $this->request('GET', '/bank/resolve?' . http_build_query([
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => 'GHS',
        ]));
    }

    public function createSubaccount(array $account): array
    {
        return $this->request('POST', '/subaccount', [
            'business_name' => $account['business_name'],
            'settlement_bank' => $account['bank_code'],
            'account_number' => $account['account_number'],
            'percentage_charge' => 0,
            'primary_contact_email' => $account['contact_email'],
            'primary_contact_name' => $account['contact_name'],
            'primary_contact_phone' => $account['contact_phone'],
        ]);
    }

    public function getSubaccount(string $subaccountCode): array
    {
        if (!preg_match('/\AACCT_[A-Za-z0-9]+\z/', $subaccountCode)) throw new InvalidArgumentException('Invalid settlement account reference.');
        return $this->request('GET', '/subaccount/' . rawurlencode($subaccountCode));
    }

    public function refund(string $reference, int $amountMinor, string $reason, string $refundReference): array
    {
        return $this->request('POST', '/refund', [
            'transaction' => $reference,
            'amount' => $amountMinor,
            'currency' => 'GHS',
            'reference' => $refundReference,
            'customer_note' => substr($reason, 0, 150),
            'merchant_note' => 'Admin-approved refund request',
        ]);
    }

    public function getRefund(string $refundReference): array
    {
        $data = $this->request('GET', '/refund?' . http_build_query(['reference' => $refundReference]));
        if (isset($data[0]) && is_array($data[0])) return $data[0];
        return $data;
    }

    public function settlements(array $filters = []): array
    {
        return $this->request('GET', '/settlement?' . http_build_query($filters));
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Accept: application/json',
        ];
        $content = '';
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $content = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'timeout' => 20,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $responseBody = @file_get_contents('https://api.paystack.co' . $path, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $statusMatch);
        $statusCode = (int)($statusMatch[1] ?? 0);
        $response = is_string($responseBody) ? json_decode($responseBody, true) : null;

        if ($statusCode < 200 || $statusCode >= 300 || !is_array($response) || empty($response['status'])) {
            error_log('Paystack request failed with HTTP status ' . ($statusCode ?: 'unknown') . '.');
            throw new RuntimeException('Payment provider could not complete the request.');
        }

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }
}
