<?php
/**
 * Brevo(구 Sendinblue) API 발송 — SMTP 대신 HTTPS API 호출, 별도 라이브러리 없이 cURL 직접 사용.
 *
 * 문의 폼은 더 이상 메일을 자동 발송하지 않는다. 이 모듈은 관리자가 문의자에게
 * 직접 답장을 보낼 때만 쓰인다.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * @param array<string, mixed> $payload Brevo /v3/smtp/email 요청 본문
 * @return array{ok: bool, detail: string}
 */
function brevo_send(string $apiKey, array $payload): array
{
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $res = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_errno($ch);
    curl_close($ch);

    if ($err !== 0 || $res === false) {
        return ['ok' => false, 'detail' => 'curl_error_' . $err];
    }
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'detail' => ''];
    }
    return ['ok' => false, 'detail' => 'http_' . $status . ':' . substr((string) $res, 0, 300)];
}

/**
 * 관리자 답장 발송. 실패해도 예외를 던지지 않고 사유를 돌려준다 (호출부가 DB 에 기록).
 *
 * @return array{ok: bool, detail: string}
 */
function send_reply_mail(string $toEmail, string $toName, string $subject, string $bodyText): array
{
    $apiKey   = env_val('BREVO_API_KEY');
    $mailFrom = env_val('MAIL_FROM');
    if ($apiKey === '' || $mailFrom === '') {
        app_log('mail-failed', 'BREVO_API_KEY 또는 MAIL_FROM 미설정 (.env 확인)');
        return ['ok' => false, 'detail' => 'not_configured'];
    }

    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $htmlBody = '<div style="font-family:Arial,\'Noto Sans KR\',sans-serif;color:#334155;'
        . 'max-width:640px;font-size:14px;line-height:1.7;white-space:pre-wrap">'
        . nl2br($esc($bodyText))
        . '</div>';

    $result = brevo_send($apiKey, [
        'sender'      => ['name' => env_val('MAIL_FROM_NAME', 'AIvance'), 'email' => $mailFrom],
        'to'          => [array_filter(['email' => $toEmail, 'name' => $toName])],
        'replyTo'     => ['email' => env_val('MAIL_TO', 'advisor@aivance.kr')],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => $bodyText,
    ]);

    if (!$result['ok']) {
        app_log('mail-failed', 'brevo reply 실패 to=' . $toEmail . ' detail=' . $result['detail']);
    }
    return $result;
}
