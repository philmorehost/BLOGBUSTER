<?php
session_start();
require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Services/MailService.php';

$step = $_SESSION['reset_step'] ?? 1;
$error = '';
$success = '';

// Fetch options
$opts = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM options");
while ($row = $stmt->fetch()) {
    $opts[$row['setting_key']] = $row['setting_value'];
}

$enableOtp = ($opts['enable_otp'] ?? '0') === '1';
$enablePinReset = ($opts['enable_pin_reset'] ?? '0') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_otp') {
        $email = trim($_POST['email'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin) {
            $otp = rand(100000, 999999);
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['otp_expiry'] = time() + 300; // 5 mins

            if ($enableOtp) {
                $mail = new App\Services\MailService($pdo);
                $html = "<h3>Password Reset Verification</h3><p>Your OTP code is: <b>{$otp}</b>. It expires in 5 minutes.</p>";
                $mail->send($email, "BLOGBUSTER Admin Password Reset OTP", $html);
            }

            $_SESSION['reset_step'] = 2;
            header('Location: forgot-password.php');
            exit();
        } else {
            $error = 'Admin email address not found.';
        }
    } elseif ($action === 'verify_and_reset') {
        $enteredOtp = trim($_POST['otp'] ?? '');
        $enteredPin = trim($_POST['security_pin'] ?? '');
        $newPass = $_POST['new_password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$_SESSION['reset_email']]);
        $admin = $stmt->fetch();

        $otpValid = !$enableOtp || ($enteredOtp == ($_SESSION['reset_otp'] ?? '') && time() <= ($_SESSION['otp_expiry'] ?? 0));
        $pinValid = !$enablePinReset || ($admin && password_verify($enteredPin, $admin['security_pin']));

        if ($otpValid && $pinValid && !empty($newPass)) {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $update = $pdo->prepare("UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE email = ?");
            $update->execute([$newHash, $_SESSION['reset_email']]);

            unset($_SESSION['reset_step'], $_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['otp_expiry']);
            $success = "Password successfully reset! You can now login.";
            $step = 1;
        } else {
            $error = 'Invalid OTP, incorrect Security PIN, or expired session.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <title>BLOGBUSTER — Password Recovery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full text-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-800 border border-slate-700/60 rounded-2xl p-8 shadow-2xl space-y-6">
        <div class="text-center space-y-2">
            <h1 class="text-xl font-bold text-white">Admin Recovery & Reset</h1>
            <p class="text-xs text-slate-400">Recover your admin account using OTP and Security PIN</p>
        </div>

        <?php if ($error): ?><div class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-xs text-center"><?= $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-xs text-center"><?= $success; ?></div><a href="login.php" class="block text-center text-blue-400 text-xs">Proceed to Login</a><?php endif; ?>

        <?php if (($step === 1 || !$success)): ?>
            <?php if (($_SESSION['reset_step'] ?? 1) === 1): ?>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="request_otp">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Admin Email Address</label>
                        <input type="email" name="email" required class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white">
                    </div>
                    <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-medium text-sm rounded-xl transition">Send Recovery OTP</button>
                </form>
            <?php else: ?>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="verify_and_reset">
                    <?php if ($enableOtp): ?>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Enter 6-digit OTP Sent to Email</label>
                            <input type="text" name="otp" maxlength="6" required class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white tracking-widest text-center font-bold">
                        </div>
                    <?php endif; ?>
                    <?php if ($enablePinReset): ?>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Permanent Security PIN</label>
                            <input type="password" name="security_pin" maxlength="6" required class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white">
                        </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">New Password</label>
                        <input type="password" name="new_password" minlength="8" required class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white">
                    </div>
                    <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-sm rounded-xl transition">Reset Password & Unlock</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>