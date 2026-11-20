<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['account_action'])) {
    if (!validateCsrfToken()) {
        $message = 'Invalid form submission.';
    } else {
        try {
            $raw = trim((string)($_POST['commission_percent'] ?? ''));
            if (!preg_match('/\A(?:0|[1-9][0-9]?)(?:\.[0-9]{1,2})?\z/', $raw)) throw new InvalidArgumentException('Enter a commission from 0 to 100 with up to two decimal places.');
            $parts = explode('.', $raw, 2);
            $bps = ((int)$parts[0] * 100) + (int)str_pad($parts[1] ?? '0', 2, '0');
            $enabled = isset($_POST['payments_enabled']) && getenv('PAYSTACK_MODE') === 'test'
                && preg_match('/\Ask_test_[A-Za-z0-9]+\z/', getenv('PAYSTACK_TEST_SECRET_KEY') ?: '') === 1;
            $stmt = getDbConnection()->prepare("UPDATE PlatformPaymentSettings_007 SET commission_bps = ?, gateway_fee_payer = 'organizer', payments_enabled = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE settings_id = 1");
            $stmt->execute([$bps, $enabled, getCurrentUserId()]);
            logAuditAction(getCurrentUserId(), 'admin', 'UPDATE', 'PlatformPaymentSettings_007', 1, [], ['commission_bps' => $bps, 'payments_enabled' => $enabled, 'gateway_fee_payer' => 'organizer']);
            $message = $enabled ? 'Test checkout settings saved.' : 'Settings saved. Checkout remains disabled until test mode credentials are configured and enabled.';
        } catch (Throwable $e) {
            $message = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Settings could not be saved.';
        }
    }
}
$accountAction = (string)($_POST['account_action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accountAction !== '') {
    if (!validateCsrfToken()) {
        $message = 'Invalid form submission.';
    } else {
        $organizerId = (int)($_POST['organizer_id'] ?? 0);
        $newStatus = $accountAction === 'approve' ? 'verified' : ($accountAction === 'suspend' ? 'suspended' : '');
        if ($organizerId > 0 && $newStatus !== '') {
            $stmt = getDbConnection()->prepare("UPDATE OrganizerPaymentAccounts_007 SET verification_status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE organizer_id = ? AND (provider_verified = TRUE OR ? = 'suspended')");
            $stmt->execute([$newStatus, getCurrentUserId(), $organizerId, $newStatus]);
            if ($stmt->rowCount() === 1) {
                logAuditAction(getCurrentUserId(), 'admin', 'UPDATE', 'OrganizerPaymentAccounts_007', $organizerId, [], ['verification_status' => $newStatus]);
                $message = 'Organizer payment account review saved.';
            } else $message = 'The account must be verified by the provider before approval.';
        }
    }
}
$settings = fetchOne('SELECT * FROM PlatformPaymentSettings_007 WHERE settings_id = 1') ?: [];
$percent = isset($settings['commission_bps']) ? number_format(((int)$settings['commission_bps']) / 100, 2, '.', '') : '';
$accounts = fetchAll("SELECT a.organizer_id, a.account_name, a.account_last4, a.verification_status, a.provider_verified, o.full_name, o.email FROM OrganizerPaymentAccounts_007 a JOIN Organizers_007 o ON o.organizer_id = a.organizer_id ORDER BY a.updated_at DESC");
foreach ($accounts as &$accountRow) $accountRow['provider_verified'] = isDatabaseBooleanTrue($accountRow['provider_verified']);
unset($accountRow);
$settings['payments_enabled'] = isDatabaseBooleanTrue($settings['payments_enabled'] ?? false);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Payment settings</title><link href="../assets/css/style.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css" rel="stylesheet"></head><body><?php include __DIR__ . '/../includes/header.php'; ?><main class="container py-4"><h1 class="h2">Payment settings</h1><?php if ($message): ?><div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><div class="card mb-4"><div class="card-body"><form method="post"><?php echo csrfTokenField(); ?><div class="mb-3"><label for="commission_percent" class="form-label">Default platform commission (%)</label><input class="form-control" id="commission_percent" name="commission_percent" required inputmode="decimal" value="<?php echo htmlspecialchars($percent); ?>"><div class="form-text">Applies to new checkouts. Existing bookings retain their commission snapshot.</div></div><p>Gateway processing fees are deducted from the organiser's proceeds. Refunds require administrator approval.</p><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="payments_enabled" name="payments_enabled" <?php echo !empty($settings['payments_enabled']) && getenv('PAYSTACK_MODE') === 'test' ? 'checked' : ''; ?>><label class="form-check-label" for="payments_enabled">Enable Paystack test checkout</label></div><button class="btn btn-primary">Save settings</button></form></div></div><p class="text-muted">This screen cannot enable live payments. Provider settlement and organiser verification approval are required before production.</p><h2 class="h4 mt-4">Organizer account review</h2><div class="table-responsive"><table class="table"><thead><tr><th>Organizer</th><th>Settlement account</th><th>Provider verified</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($accounts as $account): ?><tr><td><?php echo htmlspecialchars($account['full_name'] . ' (' . $account['email'] . ')'); ?></td><td><?php echo htmlspecialchars(($account['account_name'] ?? '') . ' •••• ' . ($account['account_last4'] ?? '')); ?></td><td><?php echo $account['provider_verified'] ? 'Yes' : 'No'; ?></td><td><?php echo htmlspecialchars($account['verification_status']); ?></td><td><form method="post" class="d-flex gap-2"><?php echo csrfTokenField(); ?><input type="hidden" name="organizer_id" value="<?php echo (int)$account['organizer_id']; ?>"><button name="account_action" value="approve" class="btn btn-sm btn-success" <?php echo !$account['provider_verified'] || $account['verification_status'] === 'verified' ? 'disabled' : ''; ?>>Approve</button><button name="account_action" value="suspend" class="btn btn-sm btn-outline-danger">Suspend</button></form></td></tr><?php endforeach; ?><?php if (!$accounts): ?><tr><td colspan="5" class="text-muted">No organizer settlement accounts submitted.</td></tr><?php endif; ?></tbody></table></div></main><?php include __DIR__ . '/../includes/footer.php'; ?></body></html>
