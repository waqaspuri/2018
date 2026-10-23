<?php

require_once __DIR__ . '/env.php';

function loadMailEnvironment() {
    loadAppEnvironment();
}

function mailEnvironmentValue($name, $default = '') {
    return appEnvironmentValue($name, $default);
}

function configureAppMailer($mail) {
    loadMailEnvironment();

    $mailer = strtolower(mailEnvironmentValue('MAIL_MAILER', 'mail'));
    if ($mailer === 'smtp') {
        $host = mailEnvironmentValue('MAIL_HOST');
        if ($host === '') {
            throw new RuntimeException('MAIL_HOST must be set when MAIL_MAILER=smtp.');
        }

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int)mailEnvironmentValue('MAIL_PORT', '587');
        $mail->SMTPAuth = filter_var(mailEnvironmentValue('MAIL_SMTP_AUTH', 'false'), FILTER_VALIDATE_BOOLEAN);
        $mail->Timeout = (int)mailEnvironmentValue('MAIL_TIMEOUT', '10');

        $encryption = strtolower(mailEnvironmentValue('MAIL_ENCRYPTION'));
        if ($encryption === 'tls') {
            $mail->SMTPSecure = 'tls';
        } elseif ($encryption === 'ssl') {
            $mail->SMTPSecure = 'ssl';
        } elseif ($encryption !== '' && $encryption !== 'none') {
            throw new RuntimeException('MAIL_ENCRYPTION must be tls, ssl, or none.');
        }

        if ($mail->SMTPAuth) {
            $mail->Username = mailEnvironmentValue('MAIL_USERNAME');
            $mail->Password = mailEnvironmentValue('MAIL_PASSWORD');
        }
    } elseif ($mailer === 'mail') {
        $mail->isMail();
    } else {
        throw new RuntimeException('MAIL_MAILER must be smtp or mail.');
    }

    $fromAddress = mailEnvironmentValue('MAIL_FROM_ADDRESS', 'info@waqaschemicals.com');
    $fromName = mailEnvironmentValue('MAIL_FROM_NAME', 'WAQAS CHEMICALS');
    $mail->setFrom($fromAddress, $fromName);
}

function addConfiguredMailBcc($mail) {
    loadMailEnvironment();
    $address = mailEnvironmentValue('MAIL_BCC_ADDRESS');
    if ($address !== '') {
        $mail->addBCC($address, mailEnvironmentValue('MAIL_BCC_NAME', ''));
    }
}
