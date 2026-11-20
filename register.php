<?php
/**
 * Attendee registration.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(getBaseUrl() . 'attendee/dashboard.php');
}

$errors = [];
$fullName = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        $errors[] = 'Enter a name between 2 and 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($phone) > 20) {
        $errors[] = 'Phone number must be 20 characters or fewer.';
    }
    if (strlen($password) < 12) {
        $errors[] = 'Use a password with at least 12 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'The passwords do not match.';
    }

    if (!$errors) {
        try {
            $existingAccount = fetchOne(
                'SELECT email FROM Attendees_007 WHERE LOWER(email) = :email
                 UNION ALL
                 SELECT email FROM Organizers_007 WHERE LOWER(email) = :organizer_email
                 LIMIT 1',
                [':email' => $email, ':organizer_email' => $email]
            );

            if ($existingAccount) {
                $errors[] = 'An account already uses that email. Try signing in instead.';
            } else {
                $pdo = getDbConnection();
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO Attendees_007 (email, password, full_name, phone)
                         VALUES (:email, :password, :full_name, :phone)
                         RETURNING attendee_id'
                    );
                    $stmt->execute([
                        ':email' => $email,
                        ':password' => password_hash($password, PASSWORD_DEFAULT),
                        ':full_name' => $fullName,
                        ':phone' => $phone !== '' ? $phone : null,
                    ]);
                    $attendeeId = (int)$stmt->fetchColumn();
                    logAuditAction($attendeeId, 'attendee', 'REGISTER', 'Attendees_007', $attendeeId);
                    $pdo->commit();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                session_regenerate_id(true);
                $_SESSION['user_id'] = $attendeeId;
                $_SESSION['user_role'] = 'attendee';
                $_SESSION['user_name'] = $fullName;
                $_SESSION['user_email'] = $email;
                $_SESSION['loyalty_points'] = 0;

                setFlashMessage('success', 'Your attendee account is ready.');
                redirect(getBaseUrl() . 'attendee/dashboard.php');
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $errors[] = 'An account already uses that email. Try signing in instead.';
            } else {
                error_log('Attendee registration error: ' . $e->getMessage());
                $errors[] = 'We could not create your account right now. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create attendee account - EventMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-yEPZ7jP9S0QKiUQ0m3jjsO4ProIW7wvR9gXkI7JOGa2XVsw53Yke2bsdkBbjmmiX" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo getAssetUrl('css/style.css'); ?>" rel="stylesheet">
</head>
<body class="auth-page d-flex align-items-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card">
                    <div class="text-center mb-4">
                        <i class="fas fa-calendar-alt text-primary mb-3"></i>
                        <h1 class="h3 fw-bold">Join EventMS</h1>
                        <p class="text-muted mb-0">Create an attendee account to book events.</p>
                    </div>

                    <?php if ($errors): ?>
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo getBaseUrl(); ?>register.php">
                        <?php echo csrfTokenField(); ?>
                        <div class="mb-3">
                            <label class="form-label" for="full_name">Full name</label>
                            <input class="form-control" id="full_name" name="full_name" autocomplete="name"
                                   maxlength="100" value="<?php echo htmlspecialchars($fullName); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <input class="form-control" type="email" id="email" name="email" autocomplete="email"
                                   maxlength="100" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="phone">Phone <span class="text-muted">(optional)</span></label>
                            <input class="form-control" type="tel" id="phone" name="phone" autocomplete="tel"
                                   maxlength="20" value="<?php echo htmlspecialchars($phone); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" type="password" id="password" name="password"
                                   autocomplete="new-password" minlength="12" required>
                            <small class="form-text text-muted">Use at least 12 characters.</small>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="confirm_password">Confirm password</label>
                            <input class="form-control" type="password" id="confirm_password" name="confirm_password"
                                   autocomplete="new-password" minlength="12" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Create account</button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0">
                        Already have an account?
                        <a href="<?php echo getBaseUrl(); ?>login.php">Sign in</a>
                    </p>
                    <p class="text-center mt-3 mb-0">
                        <a class="small text-muted" href="<?php echo getBaseUrl(); ?>">Back to events</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
