<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=utf-8');
$requestId = 'mat_' . uniqid();
try {
    $requestId = 'mat_' . bin2hex(random_bytes(6));
} catch (Throwable $ignored) {
    $requestId = 'mat_' . uniqid();
}

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function failJson(int $status, string $message, string $requestId, array $context = []): void
{
    $logContext = $context !== [] ? ' | ' . json_encode($context) : '';
    error_log('[MAT][' . $requestId . '] ' . $message . $logContext);
    respondJson($status, ['ok' => false, 'message' => $message, 'request_id' => $requestId]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    failJson(405, 'Method not allowed.', $requestId, ['method' => $_SERVER['REQUEST_METHOD'] ?? '']);
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
    failJson(500, 'PHPMailer autoload.php was not found. Check the vendor path.', $requestId, ['candidates' => $autoloadCandidates]);
}

if (!file_exists($configPath)) {
    failJson(500, 'Mail configuration is missing.', $requestId, ['config_path' => $configPath]);
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
    respondJson(200, ['ok' => true, 'message' => 'Thank you.', 'request_id' => $requestId]);
}

$turnstileConfig = (array)($config['turnstile'] ?? []);
$turnstileEnabled = (bool)($turnstileConfig['enabled'] ?? false);
if ($turnstileEnabled) {
    $turnstileToken = field('cf-turnstile-response');
    $turnstileSecret = trim((string)($turnstileConfig['secret_key'] ?? ''));
    $remoteIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

    if ($turnstileToken === '' || $turnstileSecret === '' || !verifyTurnstileToken($turnstileToken, $turnstileSecret, $remoteIp)) {
        failJson(422, 'Please complete the security check.', $requestId);
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
    failJson(422, 'Please complete all required fields.', $requestId, ['missing' => $missing]);
}

if (!filter_var($data['Work email'], FILTER_VALIDATE_EMAIL)) {
    failJson(422, 'Please enter a valid email address.', $requestId);
}

$dbConfig = (array)($config['db'] ?? []);
$dbHost = trim((string)($dbConfig['host'] ?? ''));
$dbName = trim((string)($dbConfig['name'] ?? ''));
$dbUser = trim((string)($dbConfig['user'] ?? ''));
$dbPass = (string)($dbConfig['pass'] ?? '');
$dbCharset = trim((string)($dbConfig['charset'] ?? 'utf8mb4'));
$dbCharset = preg_replace('/[^a-zA-Z0-9_]/', '', $dbCharset) ?: 'utf8mb4';

if ($dbHost === '' || $dbName === '' || $dbUser === '') {
    failJson(500, 'Database configuration is missing.', $requestId);
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $dbHost,
    $dbName,
    $dbCharset
);

$remoteIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
$remoteIp = strlen($remoteIp) <= 45 ? $remoteIp : '';
$userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$userAgent = substr($userAgent, 0, 512);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $insertLead = $pdo->prepare(
        'INSERT INTO quote_requests (
            first_name,
            last_name,
            work_email,
            phone_number,
            company,
            job_title,
            industry,
            source,
            ip_address,
            user_agent
        ) VALUES (
            :first_name,
            :last_name,
            :work_email,
            :phone_number,
            :company,
            :job_title,
            :industry,
            :source,
            :ip_address,
            :user_agent
        )'
    );

    $insertLead->execute([
        ':first_name' => $data['First name'],
        ':last_name' => $data['Last name'],
        ':work_email' => $data['Work email'],
        ':phone_number' => $data['Phone number'],
        ':company' => $data['Company'],
        ':job_title' => $data['Job title'],
        ':industry' => $data['Industry'],
        ':source' => 'MAT IEEE Landing',
        ':ip_address' => $remoteIp !== '' ? $remoteIp : null,
        ':user_agent' => $userAgent !== '' ? $userAgent : null,
    ]);
} catch (Throwable $error) {
    failJson(500, 'We could not store your request. Please try again.', $requestId, ['db_error' => $error->getMessage()]);
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

    respondJson(200, ['ok' => true, 'message' => 'Request sent.', 'request_id' => $requestId]);
} catch (Throwable $error) {
    failJson(500, 'We could not send your request. Please try again.', $requestId, ['mail_error' => $error->getMessage()]);
}
