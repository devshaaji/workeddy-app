Your BrowseMX login OTP is <?= htmlspecialchars((string) ($otp ?? ''), ENT_QUOTES, 'UTF-8') ?>. Expires in <?= (int) ($expiresInMinutes ?? 0) ?> min.
