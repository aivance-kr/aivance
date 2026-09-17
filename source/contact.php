<?php
/**
 * AIvance — 도입 문의 폼 엔드포인트 (Gmail SMTP)
 *
 *  GET  /contact.php?action=token   → CSRF 토큰 발급 (세션 저장 + JSON 반환)
 *  POST /contact.php                → 문의 접수 후 advisor@aivance.kr 로 메일 발송
 *
 * 자격증명은 .env 에서만 로드합니다 (하드코딩 금지).
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

/* 스택 트레이스 노출 금지 — 예외는 JSON 500 으로 변환하고 서버 로그에만 기록 */
ini_set('display_errors', '0');
set_exception_handler(static function (\Throwable $e): void {
    error_log('contact.php fatal: ' . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['ok' => false, 'error' => '서버 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.'], JSON_UNESCAPED_UNICODE);
    exit;
});

session_start();

/* ── env 로드 ─────────────────────────────────────────────────────────── */
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

/** 환경변수 헬퍼 (없으면 기본값). */
function env_val(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : (string) $v;
}

/* ── 공통 응답 유틸 ───────────────────────────────────────────────────── */
/* CORS: 콤마로 구분된 허용 오리진 목록 중 요청 Origin 과 일치할 때만 echo-back.
 * 와일드카드(*)는 Allow-Credentials:true 와 함께 쓰면 스펙 위반(브라우저 무시)이고
 * 타 도메인에서 엔드포인트를 직접 호출하는 것도 막지 못하므로 사용하지 않는다. */
$allowedOrigins = array_filter(array_map('trim', explode(',', env_val('ALLOWED_ORIGIN', ''))));
$requestOrigin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
header('Content-Type: application/json; charset=utf-8');
header('Vary: Origin');
if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('X-Content-Type-Options: nosniff');

/** JSON 응답 후 종료. */
function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/* preflight */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(204, []);
}

/* ── CSRF 토큰 발급 ───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'token') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_issued_at'] = time();
    }
    respond(200, ['token' => $_SESSION['csrf_token']]);
}

/* 이후는 POST 만 허용 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => '허용되지 않은 요청 방식입니다.']);
}

/* ── 입력 파싱 (JSON 또는 form) ───────────────────────────────────────── */
$raw = file_get_contents('php://input');
$data = [];
if ($raw !== '' && str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $data = json_decode($raw, true) ?: [];
} else {
    $data = $_POST;
}

/** 문자열 필드 추출 + 트림. */
function field(array $d, string $k): string
{
    return trim((string) ($d[$k] ?? ''));
}

/* ── IP 기준 요청 제한 저장소 (writable/ratelimit/, 웹 접근 차단) ──────── */
function rl_dir(): string
{
    $dir = __DIR__ . '/writable/ratelimit';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir;
}

/** IP 당 최소 간격(초) / 시간당 최대 횟수 검사. 허용되면 즉시 기록 후 true. */
function rl_allow(string $ip, int $minIntervalSec, int $maxPerHour): bool
{
    $path = rl_dir() . '/' . hash('sha256', $ip) . '.json';
    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return true; // 저장소 장애로 정상 문의까지 막지 않는다 (가용성 우선)
    }
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $state = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
    $now = time();
    if (!is_array($state) || ($now - (int) ($state['window_start'] ?? 0)) > 3600) {
        $state = ['window_start' => $now, 'count' => 0, 'last_sent' => 0];
    }
    $allowed = ($now - (int) $state['last_sent']) >= $minIntervalSec && (int) $state['count'] < $maxPerHour;
    if ($allowed) {
        $state['count'] = (int) $state['count'] + 1;
        $state['last_sent'] = $now;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state));
        fflush($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    return $allowed;
}

/** 차단 이벤트를 날짜별 파일로 기록 (writable/logs/, 웹 접근 차단). */
function log_spam_block(string $reason, array $extra = []): void
{
    $dir = __DIR__ . '/writable/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $line = sprintf(
        "[%s] ip=%s reason=%s %s\n",
        date('c'),
        (string) ($_SERVER['REMOTE_ADDR'] ?? '-'),
        $reason,
        json_encode($extra, JSON_UNESCAPED_UNICODE)
    );
    file_put_contents($dir . '/spam-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/* ── 허니팟 (봇 차단) — 채워져 있으면 조용히 성공 처리 ─────────────────── */
if (field($data, 'company_url') !== '') {
    log_spam_block('honeypot');
    respond(200, ['ok' => true, 'message' => '접수되었습니다.']);
}

/* ── 제출 속도 트랩 — 토큰 발급 3초 이내 제출은 봇으로 간주, 조용히 성공 처리 ── */
$issuedAt = (int) ($_SESSION['csrf_issued_at'] ?? 0);
if ($issuedAt > 0 && (time() - $issuedAt) < 3) {
    log_spam_block('speed_trap', ['elapsed_sec' => time() - $issuedAt]);
    respond(200, ['ok' => true, 'message' => '접수되었습니다.']);
}

/* ── Origin/Referer 강제 검증 — CORS 헤더는 브라우저 표시용일 뿐 서버 처리를 막지 않으므로
 * 직접 curl/스크립트로 쏘는 POST 를 여기서 실제로 차단한다 ─────────────────────────── */
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$originOk = $requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true);
$refererOk = $referer !== '' && array_reduce(
    $allowedOrigins,
    static fn (bool $ok, string $o) => $ok || str_starts_with($referer, $o . '/') || $referer === $o,
    false
);
if (!$originOk && !$refererOk) {
    log_spam_block('origin_mismatch', ['origin' => $requestOrigin, 'referer' => $referer]);
    respond(403, ['ok' => false, 'error' => '허용되지 않은 요청입니다.']);
}

/* ── User-Agent 검증 — 비어있거나 스크립트/툴 UA 는 즉시 차단 ─────────────────────── */
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$botUaPatterns = ['curl', 'wget', 'python-requests', 'python-urllib', 'go-http-client', 'postmanruntime', 'axios', 'okhttp', 'scrapy', 'sqlmap', 'libwww-perl'];
if ($userAgent === '') {
    log_spam_block('empty_user_agent');
    respond(403, ['ok' => false, 'error' => '허용되지 않은 요청입니다.']);
}
foreach ($botUaPatterns as $pat) {
    if (stripos($userAgent, $pat) !== false) {
        log_spam_block('bot_user_agent', ['ua' => $userAgent]);
        respond(403, ['ok' => false, 'error' => '허용되지 않은 요청입니다.']);
    }
}

/* ── CSRF 검증 (1회용) — 검증 성공 여부와 무관하게 토큰은 즉시 폐기해 재사용을 막는다 ── */
$sentToken = field($data, 'csrf_token') ?: (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$sessToken = (string) ($_SESSION['csrf_token'] ?? '');
$tokenAge = time() - (int) ($_SESSION['csrf_issued_at'] ?? 0);
unset($_SESSION['csrf_token'], $_SESSION['csrf_issued_at']);
if ($sessToken === '' || $sentToken === '' || !hash_equals($sessToken, $sentToken) || $tokenAge > 1800) {
    respond(419, ['ok' => false, 'error' => '보안 토큰이 유효하지 않습니다. 페이지를 새로고침 후 다시 시도해 주세요.']);
}

/* ── Cloudflare Turnstile 검증 — 사람만 통과할 수 있는 챌린지 (siteverify API 호출) ──
 * error_log() 는 호스팅 환경에 따라 어디로 가는지 알 수 없어 신뢰할 수 없으므로,
 * 실패 사유는 반드시 파일에 직접 쓰는 log_spam_block() 으로만 남긴다. */
function turnstile_verify(string $token, string $ip): array
{
    $secret = env_val('TURNSTILE_SECRET_KEY');
    if ($secret === '') {
        return ['ok' => false, 'detail' => 'secret_not_configured'];
    }
    if ($token === '') {
        return ['ok' => false, 'detail' => 'empty_token'];
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $res = curl_exec($ch);
    $err = curl_errno($ch);
    if ($err !== 0 || $res === false) {
        return ['ok' => false, 'detail' => 'curl_error_' . $err];
    }
    $json = json_decode((string) $res, true);
    $ok = is_array($json) && ($json['success'] ?? false) === true;
    if ($ok) {
        return ['ok' => true, 'detail' => ''];
    }
    $codes = is_array($json) ? implode(',', (array) ($json['error-codes'] ?? [])) : 'invalid_response';
    return ['ok' => false, 'detail' => $codes];
}

$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$turnstileToken = field($data, 'cf-turnstile-response');
$turnstileResult = turnstile_verify($turnstileToken, $clientIp);
if (!$turnstileResult['ok']) {
    log_spam_block('turnstile_failed', ['detail' => $turnstileResult['detail']]);
    respond(403, ['ok' => false, 'error' => '사람 확인에 실패했습니다. 다시 시도해 주세요.']);
}

/* ── IP 기준 요청 제한 (60초 1회 · 시간당 5회) — 세션 초기화로 우회 못하게 IP 로 고정 ── */
if ($clientIp === '' || !rl_allow($clientIp, 60, 5)) {
    log_spam_block('rate_limit');
    respond(429, ['ok' => false, 'error' => '잠시 후 다시 시도해 주세요.']);
}

/* ── 사이트 전체 레이트리밋 (요청 간 최소 2초 · 시간당 최대 30회) — IP 분산(봇넷) 공격의 전체 볼륨 상한 ── */
if (!rl_allow('__global__', 2, 30)) {
    log_spam_block('global_rate_limit');
    respond(429, ['ok' => false, 'error' => '잠시 후 다시 시도해 주세요.']);
}

/* ── 입력 검증 ────────────────────────────────────────────────────────── */
$name    = field($data, 'name');
$email   = field($data, 'email');
$phone   = field($data, 'phone');
$product = field($data, 'product');
$message = field($data, 'message');

$errors = [];
if ($name === '' || mb_strlen($name) > 100) {
    $errors['name'] = '이름/회사명을 입력해 주세요.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = '올바른 이메일 주소를 입력해 주세요.';
}
if ($phone !== '' && !preg_match('/^[0-9()+\-\s]{6,20}$/', $phone)) {
    $errors['phone'] = '연락처 형식을 확인해 주세요.';
}
if ($message === '' || mb_strlen($message) < 5 || mb_strlen($message) > 5000) {
    $errors['message'] = '문의 내용을 5자 이상 5000자 이하로 입력해 주세요.';
}
$allowedProducts = ['AICura', 'AICopia', 'AICreo', '기타 / 미정'];
if ($product !== '' && !in_array($product, $allowedProducts, true)) {
    $product = '기타 / 미정';
}
if ($errors) {
    respond(422, ['ok' => false, 'error' => '입력값을 확인해 주세요.', 'fields' => $errors]);
}

/* ── 일회용/스팸용 이메일 도메인 차단 ─────────────────────────────────── */
$disposableDomains = [
    'mailinator.com', 'guerrillamail.com', 'guerrillamail.info', '10minutemail.com',
    'tempmail.com', 'temp-mail.org', 'yopmail.com', 'trashmail.com', 'throwawaymail.com',
    'getnada.com', 'discard.email', 'fakeinbox.com', 'sharklasers.com', 'maildrop.cc',
];
$emailDomain = strtolower(substr((string) strrchr($email, '@'), 1));
if (in_array($emailDomain, $disposableDomains, true)) {
    log_spam_block('disposable_email', ['domain' => $emailDomain]);
    respond(200, ['ok' => true, 'message' => '접수되었습니다.']);
}

/* ── 내용 스팸 필터 — URL 2개 이상 또는 스팸 키워드 포함 시 조용히 성공 처리 ── */
function looks_spammy(string $text): bool
{
    if ((int) preg_match_all('#https?://|www\.#i', $text) >= 2) {
        return true;
    }
    static $keywords = [
        'viagra', 'cialis', 'casino', 'crypto airdrop', 'loan approved', 'click here to claim',
        '비아그라', '시알리스', '카지노', '토토', '바카라', '대출광고', '성인용품', '도박사이트',
    ];
    foreach ($keywords as $kw) {
        if (mb_stripos($text, $kw) !== false) {
            return true;
        }
    }
    return false;
}
if (looks_spammy($name . ' ' . $message)) {
    log_spam_block('content_filter', ['name' => $name, 'message_excerpt' => mb_substr($message, 0, 100)]);
    respond(200, ['ok' => true, 'message' => '접수되었습니다.']);
}

/* ── 메일 본문 구성 (모든 사용자 입력은 이스케이프) ───────────────────── */
$esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$submittedAt = date('Y-m-d H:i:s');
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

$rowsHtml = '';
foreach ([
    '이름 / 회사' => $name,
    '이메일'       => $email,
    '연락처'       => $phone !== '' ? $phone : '-',
    '관심 제품'    => $product !== '' ? $product : '-',
    '접수 시각'    => $submittedAt,
    '요청 IP'      => $ip,
] as $label => $val) {
    $rowsHtml .= '<tr>'
        . '<td style="padding:8px 12px;background:#f1f5f9;font-weight:600;white-space:nowrap">' . $esc($label) . '</td>'
        . '<td style="padding:8px 12px">' . $esc($val) . '</td></tr>';
}

$htmlBody = '<div style="font-family:Arial,\'Noto Sans KR\',sans-serif;color:#334155;max-width:640px">'
    . '<h2 style="color:#0f172a;margin:0 0 4px">새 도입 문의가 접수되었습니다</h2>'
    . '<p style="color:#64748b;margin:0 0 16px">AIvance 홈페이지 문의 폼</p>'
    . '<table style="border-collapse:collapse;width:100%;border:1px solid #e2e8f0;font-size:14px">' . $rowsHtml . '</table>'
    . '<h3 style="color:#0f172a;margin:20px 0 6px">문의 내용</h3>'
    . '<div style="white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;font-size:14px;line-height:1.7">'
    . nl2br($esc($message)) . '</div></div>';

$textBody = "새 도입 문의\n\n"
    . "이름/회사: {$name}\n이메일: {$email}\n연락처: " . ($phone ?: '-') . "\n"
    . "관심 제품: " . ($product ?: '-') . "\n접수 시각: {$submittedAt}\n요청 IP: {$ip}\n\n"
    . "문의 내용:\n{$message}\n";

/* ── Gmail SMTP 발송 ──────────────────────────────────────────────────── */
$smtpUser = env_val('SMTP_USER');
$smtpPass = env_val('SMTP_PASS');
$mailFrom = env_val('MAIL_FROM', $smtpUser);
$mailTo   = env_val('MAIL_TO', 'advisor@aivance.kr');

if ($smtpUser === '' || $smtpPass === '') {
    error_log('contact.php: SMTP 자격증명 미설정 (.env 확인)');
    respond(500, ['ok' => false, 'error' => '메일 설정이 완료되지 않았습니다. 관리자에게 문의해 주세요.']);
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = env_val('SMTP_HOST', 'smtp.gmail.com');
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->Port       = (int) env_val('SMTP_PORT', '465');
    $mail->CharSet    = 'UTF-8';
    $secure = strtolower(env_val('SMTP_SECURE', 'ssl'));
    $mail->SMTPSecure = $secure === 'tls'
        ? PHPMailer::ENCRYPTION_STARTTLS
        : PHPMailer::ENCRYPTION_SMTPS;

    $mail->setFrom($mailFrom, env_val('MAIL_FROM_NAME', 'AIvance 홈페이지 문의'));
    $mail->addAddress($mailTo);
    $mail->addReplyTo($email, $name);   // 회신 시 문의자에게

    $mail->isHTML(true);
    $mail->Subject = '[AIvance 문의] ' . ($product !== '' ? $product . ' · ' : '') . $name;
    $mail->Body    = $htmlBody;
    $mail->AltBody = $textBody;

    $mail->send();

    respond(200, ['ok' => true, 'message' => '문의가 정상 접수되었습니다. 영업일 기준 1일 내 회신드리겠습니다.']);
} catch (MailException $e) {
    error_log('contact.php mail error: ' . $mail->ErrorInfo);
    respond(502, ['ok' => false, 'error' => '메일 발송에 실패했습니다. 잠시 후 다시 시도해 주세요.']);
}
