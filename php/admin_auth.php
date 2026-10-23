<?php
require_once __DIR__ . '/session-bootstrap.php';

function adminEnsurePasswordHashColumn($conn) {
    $column = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'passHash'");
    if (!$column) return false;
    if (mysqli_num_rows($column) === 0) {
        if (!mysqli_query($conn, 'ALTER TABLE admin ADD COLUMN passHash VARCHAR(255) NULL DEFAULT NULL')) return false;
    }
    return true;
}

function adminEnsureOtpSettingColumn($conn) {
    $column = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'requireAdminOtp'");
    if (!$column) return false;
    if (mysqli_num_rows($column) === 0) {
        if (!mysqli_query($conn, 'ALTER TABLE admin ADD COLUMN requireAdminOtp TINYINT(1) NOT NULL DEFAULT 1')) return false;
    }
    return true;
}

function adminIsOtpRequired($conn) {
    if (!adminEnsureOtpSettingColumn($conn)) return true;
    $result = mysqli_query($conn, "SELECT requireAdminOtp FROM admin WHERE id='1' LIMIT 1");
    $row = $result ? mysqli_fetch_assoc($result) : false;
    return !$row || (int)$row['requireAdminOtp'] !== 0;
}

function adminVerifyAndMigratePassword($conn, $admin, $password) {
    if (!empty($admin['passHash'])) {
        if (!password_verify($password, $admin['passHash'])) return false;
        if (password_needs_rehash($admin['passHash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = mysqli_prepare($conn, "UPDATE admin SET passHash=? WHERE id=?");
            if (!$update) return false;
            mysqli_stmt_bind_param($update, 'si', $newHash, $admin['id']);
            $ok = mysqli_stmt_execute($update);
            mysqli_stmt_close($update);
            if (!$ok) return false;
        }
        return true;
    }

    $legacyHash = (string)($admin['pass'] ?? '');
    if ($legacyHash === '' || !hash_equals($legacyHash, md5($password))) return false;
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = mysqli_prepare($conn, "UPDATE admin SET passHash=?, pass='' WHERE id=?");
    if (!$update) return false;
    mysqli_stmt_bind_param($update, 'si', $newHash, $admin['id']);
    $ok = mysqli_stmt_execute($update);
    mysqli_stmt_close($update);
    return $ok;
}

function adminFindAndVerify($conn, $username, $password) {
    if (!adminEnsurePasswordHashColumn($conn)) return false;
    $stmt = mysqli_prepare($conn, 'SELECT id,username,pass,passHash,adminEmail FROM admin WHERE username=? LIMIT 1');
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 's', $username);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    $result = mysqli_stmt_get_result($stmt);
    $admin = $result ? mysqli_fetch_assoc($result) : false;
    mysqli_stmt_close($stmt);
    if (!$admin || !adminVerifyAndMigratePassword($conn, $admin, $password)) return false;
    return $admin;
}

function adminStartEmailOtp($admin) {
    $email = trim((string)($admin['adminEmail'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    if (!empty($_SESSION['admin_otp_last_sent']) && time() - (int)$_SESSION['admin_otp_last_sent'] < 60) return false;

    require_once __DIR__ . '/../PHPMailer/Exception.php';
    require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/SMTP.php';
    require_once __DIR__ . '/mail_config.php';
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->CharSet = 'UTF-8';
        configureAppMailer($mail);
        $mail->addAddress($email);
        $mail->Subject = 'Admin sign-in verification code';
        $mail->Body = "Your admin sign-in verification code is: " . $code . "\n\nThis code expires in 5 minutes. If you did not try to sign in, you can ignore this email.";
        $mail->isHTML(false);
        if (!$mail->send()) return false;
    } catch (Throwable $exception) {
        return false;
    }

    $_SESSION['admin_otp_last_sent'] = time();
    $_SESSION['admin_login_pending'] = array(
        'id' => (int)$admin['id'],
        'username' => (string)$admin['username'],
        'email' => $email,
        'hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires' => time() + 300,
        'attempts' => 0
    );
    return true;
}

function adminCompleteLogin($adminID, $username, $siteUrl) {
    session_regenerate_id(true);
    $_SESSION['isAdmin'] = 'yes';
    $_SESSION['adminID'] = (int)$adminID;
    $_SESSION['adminUsername'] = (string)$username;
    $_SESSION['adminOtpVerifiedAt'] = time();
    $_SESSION['adminLastActivity'] = time();
    unset($_SESSION['admin_login_pending'], $_SESSION['admin_login_csrf']);
    header('Location: ' . $siteUrl);
    exit;
}

function adminProcessLogin($conn, $siteUrl) {
    if (empty($_SESSION['admin_login_csrf'])) $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return $error;
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['admin_login_csrf'], $submittedToken)) return 'Your sign-in session expired. Please try again.';

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'cancel_otp') {
        unset($_SESSION['admin_login_pending']);
        return '';
    }
    if ($action === 'login') {
        unset($_SESSION['admin_login_pending']);
        if (!adminEnsurePasswordHashColumn($conn) || !adminEnsureOtpSettingColumn($conn)) return 'Sign-in is temporarily unavailable. Please contact the administrator.';
        $admin = adminFindAndVerify($conn, (string)($_POST['username'] ?? ''), (string)($_POST['pasword'] ?? ''));
        if (!$admin) return 'Please correct username or password.';
        if (!adminIsOtpRequired($conn)) adminCompleteLogin($admin['id'], $admin['username'], $siteUrl);
        if (!adminStartEmailOtp($admin)) return 'A verification email could not be sent. Check the admin email address and mail service settings.';
        return '';
    }
    if ($action === 'verify_otp') {
        $pending = $_SESSION['admin_login_pending'] ?? null;
        if (!is_array($pending) || (int)($pending['expires'] ?? 0) < time()) {
            unset($_SESSION['admin_login_pending']);
            return 'The verification code expired. Sign in again to receive a new code.';
        }
        $_SESSION['admin_login_pending']['attempts'] = (int)$pending['attempts'] + 1;
        $code = (string)($_POST['otp'] ?? '');
        if (!preg_match('/^[0-9]{6}$/', $code) || !password_verify($code, (string)$pending['hash'])) {
            if ($_SESSION['admin_login_pending']['attempts'] >= 5) {
                unset($_SESSION['admin_login_pending']);
                return 'Too many incorrect codes. Sign in again to receive a new code.';
            }
            return 'The verification code is incorrect.';
        }
        adminCompleteLogin($pending['id'], $pending['username'], $siteUrl);
    }
    return 'Please sign in again.';
}

function adminMaskedEmail($email) {
    $parts = explode('@', (string)$email, 2);
    if (count($parts) !== 2) return '';
    $local = $parts[0];
    return (strlen($local) > 2 ? substr($local, 0, 2) : substr($local, 0, 1)) . str_repeat('*', max(3, strlen($local) - 2)) . '@' . $parts[1];
}
