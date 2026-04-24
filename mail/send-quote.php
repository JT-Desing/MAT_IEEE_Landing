<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];
$autoload = '';
$autoloadFound = false;
foreach ($autoloadCandidates as $candidate) {
    if (file_exists($candidate)) {
        $autoload = $candidate;
        $autoloadFound = true;
        break;
    }
}
$configPath = __DIR__ . '/config.php';

if (!$autoloadFound) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'PHPMailer autoload.php was not found. Check the vendor path.']);
    exit;
}

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Mail configuration is missing.']);
    exit;
}

require $autoload;
$config = require $configPath;

function field(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function verifyTurnstileToken(string $token, string $secret, string $remoteIp = ''): bool
{
    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $remoteIp,
    ]);

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 10,
        ],
    ];

    $result = @file_get_contents(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        false,
        stream_context_create($options)
    );

    if (!is_string($result) || $result === '') {
        return false;
    }

    $decoded = json_decode($result, true);
    return is_array($decoded) && !empty($decoded['success']);
}

if (field('website') !== '') {
    echo json_encode(['ok' => true, 'message' => 'Thank you.']);
    exit;
}

$turnstileConfig = (array)($config['turnstile'] ?? []);
$turnstileEnabled = (bool)($turnstileConfig['enabled'] ?? false);
if ($turnstileEnabled) {
    $turnstileToken = field('cf-turnstile-response');
    $turnstileSecret = trim((string)($turnstileConfig['secret_key'] ?? ''));
    $remoteIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

    if ($turnstileToken === '' || $turnstileSecret === '' || !verifyTurnstileToken($turnstileToken, $turnstileSecret, $remoteIp)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Please complete the security check.']);
        exit;
    }
}

$data = [
    'First name' => field('fname'),
    'Last name' => field('lname'),
    'Work email' => field('email'),
    'Phone number' => field('phone'),
    'Company' => field('company'),
    'Job title' => field('jobtitle'),
    'Industry' => field('industry'),
];

$missing = [];
foreach ($data as $label => $value) {
    if ($value === '') {
        $missing[] = $label;
    }
}

if ($missing !== []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Please complete all required fields.']);
    exit;
}

if (!filter_var($data['Work email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$rows = '';
foreach ($data as $label => $value) {
    $rows .= '<tr>'
        . '<td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">' . escape($label) . '</td>'
        . '<td style="padding:8px 12px;border:1px solid #e5e7eb;">' . nl2br(escape($value)) . '</td>'
        . '</tr>';
}

$plainText = "New quote request from MAT IEEE Landing\n\n";
foreach ($data as $label => $value) {
    $plainText .= $label . ': ' . $value . "\n";
}

$subject = 'New quote request - MAT IEEE Landing';

try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = (string)$config['smtp']['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string)$config['smtp']['username'];
    $mail->Password = (string)$config['smtp']['password'];
    $mail->SMTPSecure = (string)$config['smtp']['encryption'];
    $mail->Port = (int)$config['smtp']['port'];

    $mail->setFrom((string)$config['from']['email'], (string)$config['from']['name']);
    $mail->addReplyTo($data['Work email'], trim($data['First name'] . ' ' . $data['Last name']));

    foreach ((array)$config['recipients'] as $recipient) {
        $recipient = trim((string)$recipient);
        if ($recipient !== '') {
            $mail->addAddress($recipient);
        }
    }

    if (count($mail->getToAddresses()) < 2) {
        throw new RuntimeException('Two recipient email addresses are required.');
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = '<h2 style="font-family:Arial,sans-serif;">New quote request</h2>'
        . '<p style="font-family:Arial,sans-serif;">A visitor submitted the MAT IEEE landing form.</p>'
        . '<table style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;">'
        . $rows
        . '</table>';
    $mail->AltBody = $plainText;
    $mail->send();

    echo json_encode(['ok' => true, 'message' => 'Request sent.']);
} catch (Throwable $error) {
    error_log('MAT IEEE mail error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'We could not send your request. Please try again.']);
}
