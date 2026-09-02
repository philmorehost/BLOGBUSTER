<?php
namespace App\Services;

class MailService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function getSetting($key, $default = '') {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM options WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }

    public function send($to, $subject, $htmlMessage) {
        $host = $this->getSetting('smtp_host');
        $port = (int)$this->getSetting('smtp_port', 587);
        $user = $this->getSetting('smtp_user');
        $pass = $this->getSetting('smtp_pass');
        $fromEmail = $this->getSetting('smtp_from_email', $user);
        $fromName = $this->getSetting('smtp_from_name', 'BLOGBUSTER System');
        $encryption = $this->getSetting('smtp_encryption', 'tls'); // tls, ssl, none

        if (empty($host) || empty($user)) {
            // Fallback to PHP mail if SMTP is unconfigured
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
            return mail($to, $subject, $htmlMessage, $headers);
        }

        // Custom lightweight SMTP socket client
        $remote = $host;
        if ($encryption === 'ssl') {
            $remote = "ssl://{$host}";
        }

        $socket = @fsockopen($remote, $port, $errno, $errstr, 10);
        if (!$socket) {
            return false;
        }

        $this->serverResponse($socket);

        // EHLO
        fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $this->serverResponse($socket);

        // STARTTLS if needed
        if ($encryption === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $this->serverResponse($socket);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $this->serverResponse($socket);
        }

        // AUTH LOGIN
        fwrite($socket, "AUTH LOGIN\r\n");
        $this->serverResponse($socket);
        fwrite($socket, base64_encode($user) . "\r\n");
        $this->serverResponse($socket);
        fwrite($socket, base64_encode($pass) . "\r\n");
        $this->serverResponse($socket);

        // MAIL FROM
        fwrite($socket, "MAIL FROM: <{$fromEmail}>\r\n");
        $this->serverResponse($socket);

        // RCPT TO
        fwrite($socket, "RCPT TO: <{$to}>\r\n");
        $this->serverResponse($socket);

        // DATA
        fwrite($socket, "DATA\r\n");
        $this->serverResponse($socket);

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n\r\n";

        fwrite($socket, $headers . $htmlMessage . "\r\n.\r\n");
        $this->serverResponse($socket);

        // QUIT
        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    private function serverResponse($socket) {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") {
                break;
            }
        }
        return $response;
    }
}