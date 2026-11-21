<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payments/PaystackGateway.php';
requireRole(['organizer']);
$id = (int)getCurrentUserId();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $message = 'Invalid form submission.';
    } elseif (($_POST['action'] ?? '') === 'refresh') {
        try {
            $existingAccount = fetchOne('SELECT subaccount_code FROM OrganizerPaymentAccounts_007 WHERE organizer_id = ?', [$id]);
            if (!$existingAccount || !$existingAccount['subaccount_code']) throw new RuntimeException('No provider subaccount has been submitted yet.');
            $providerAccount = (new PaystackGateway())->getSubaccount($existingAccount['subaccount_code']);
            $providerVerified = isDatabaseBooleanTrue($providerAccount['is_verified'] ?? false);
            $stmt = getDbConnection()->prepare("UPDATE OrganizerPaymentAccounts_007 SET provider_verified = ?, verification_status = CASE
                WHEN verification_status IN ('verified','suspended','rejected') THEN verification_status ELSE 'pending' END, updated_at = CURRENT_TIMESTAMP WHERE organizer_id = ?");
            $stmt->execute([$providerVerified, $id]);
            $message = $providerVerified ? 'Provider verification refreshed. An administrator still needs to review the account.' : 'The provider has not marked this account verified yet.';
        } catch (Throwable $e) {
            error_log('Provider settlement verification refresh failed.');
            $message = 'Provider verification could not be refreshed right now.';
        }
    } else {
        $accountNumber = preg_replace('/\s+/', '', (string)($_POST['account_number'] ?? ''));
        $bankCode = trim((string)($_POST['bank_code'] ?? ''));
        $business = trim((string)($_POST['business_name'] ?? ''));
        $phone = trim((string)($_POST['contact_phone'] ?? ''));
        try {
            if (!preg_match('/\A[0-9]{6,20}\z/', $accountNumber) || !preg_match('/\A[a-zA-Z0-9_-]{2,20}\z/', $bankCode)
                || $business === '' || strlen($business) > 100 || strlen($phone) > 30) {
                throw new InvalidArgumentException('Check the bank, account number, business name, and phone fields.');
            }
            $organizer = fetchOne('SELECT email, full_name FROM Organizers_007 WHERE organizer_id = ?', [$id]);
            if (!$organizer) throw new RuntimeException('Organizer not found.');
            $gateway = new PaystackGateway();
            $resolved = $gateway->resolveBankAccount($bankCode, $accountNumber);
            if (empty($resolved['account_name'])) throw new RuntimeException('The bank account could not be verified by Paystack.');
            $sub = $gateway->createSubaccount([
                'business_name' => $business, 'bank_code' => $bankCode, 'account_number' => $accountNumber,
                'contact_email' => $organizer['email'], 'contact_name' => $organizer['full_name'], 'contact_phone' => $phone,
            ]);
            $code = (string)($sub['subaccount_code'] ?? '');
            if (!preg_match('/\AACCT_[A-Za-z0-9]+\z/', $code)) throw new RuntimeException('Payment provider did not return a valid settlement account.');
            $isVerified = isDatabaseBooleanTrue($sub['is_verified'] ?? false);
            $stmt = getDbConnection()->prepare("INSERT INTO OrganizerPaymentAccounts_007 (organizer_id, subaccount_code, account_name, account_last4, verification_status, provider_verified, updated_at)
                VALUES (?, ?, ?, ?, 'pending', ?, CURRENT_TIMESTAMP)
                ON CONFLICT (organizer_id) DO UPDATE SET subaccount_code = EXCLUDED.subaccount_code, account_name = EXCLUDED.account_name,
                account_last4 = EXCLUDED.account_last4, verification_status = 'pending', provider_verified = EXCLUDED.provider_verified, updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$id, $code, substr((string)$resolved['account_name'], 0, 120), substr($accountNumber, -4), $isVerified]);
            logAuditAction($id, 'organizer', 'UPDATE', 'OrganizerPaymentAccounts_007', $id, [], ['subaccount_code' => $code, 'account_last4' => substr($accountNumber, -4), 'verification_status' => 'pending']);
            $message = 'Settlement account submitted. Provider verification and administrator review are required before checkout can use it.';
        } catch (Throwable $e) {
            error_log('Organizer settlement configuration failed.');
            $message = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Settlement account could not be configured. Check the details or contact support.';
        }
    }
}

$account = fetchOne('SELECT account_name, account_last4, verification_status, provider_verified FROM OrganizerPaymentAccounts_007 WHERE organizer_id = ?', [$id]);
if ($account) $account['provider_verified'] = isDatabaseBooleanTrue($account['provider_verified']);
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Settlement account</title><link href="../assets/css/main.css" rel="stylesheet"><link rel="icon" type="image/svg+xml" href="<?php echo getAssetUrl('favicon.svg'); ?>">
</head>
<body><?php include __DIR__ . '/../includes/header.php'; ?>
<main class="container py-4">
    <h1 class="h2">Settlement account</h1>
    <?php if ($message): ?><div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($account): ?>
        <div class="alert alert-secondary">Current account: <?php echo htmlspecialchars($account['account_name'] . ' **** ' . $account['account_last4']); ?> - <?php echo htmlspecialchars($account['verification_status']); ?><?php if (!$account['provider_verified']): ?> (provider verification pending)<?php endif; ?></div>
        <form method="post" class="mb-3"><?php echo csrfTokenField(); ?><button class="btn btn-outline-primary" name="action" value="refresh">Refresh provider verification</button></form>
    <?php endif; ?>
    <div class="card"><div class="card-body">
        <p>Only the provider subaccount code and masked account details are stored. Submitting bank information does not guarantee eligibility; provider verification and platform review are required.</p>
        <form method="post" autocomplete="off">
            <?php echo csrfTokenField(); ?>
            <div class="mb-3"><label class="form-label" for="business_name">Business name</label><input class="form-control" required maxlength="100" name="business_name" id="business_name"></div>
            <div class="mb-3"><label class="form-label" for="bank_code">Paystack bank code</label><input class="form-control" required maxlength="20" name="bank_code" id="bank_code"></div>
            <div class="mb-3"><label class="form-label" for="account_number">Bank account number</label><input class="form-control" required inputmode="numeric" maxlength="20" name="account_number" id="account_number" autocomplete="off"></div>
            <div class="mb-3"><label class="form-label" for="contact_phone">Business contact phone</label><input class="form-control" maxlength="30" name="contact_phone" id="contact_phone"></div>
            <button class="btn btn-primary">Submit for verification</button>
        </form>
    </div></div>
</main><?php include __DIR__ . '/../includes/footer.php'; ?></body></html>
