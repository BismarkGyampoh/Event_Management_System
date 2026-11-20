<?php
interface PaymentGatewayInterface
{
    public function initialize(array $transaction): array;
    public function verify(string $reference): array;
    public function resolveBankAccount(string $bankCode, string $accountNumber): array;
    public function createSubaccount(array $account): array;
    public function getSubaccount(string $subaccountCode): array;
    public function refund(string $reference, int $amountMinor, string $reason, string $refundReference): array;
    public function getRefund(string $refundReference): array;
    public function settlements(array $filters = []): array;
}
