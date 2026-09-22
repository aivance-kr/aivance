<?php
/**
 * Slack Incoming Webhook 알림.
 *
 * 문의는 이미 SQLite 에 저장된 뒤에 호출한다. Slack 이 죽어 있어도 문의 접수 자체는
 * 성공해야 하므로 실패는 파일 로그로만 남기고 호출부에 영향을 주지 않는다.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * @param array<string, string> $fields 라벨 => 값
 */
function slack_notify_inquiry(int $inquiryId, array $fields, string $message): void
{
    $webhook = env_val('SLACK_WEBHOOK_URL');
    if ($webhook === '') {
        return; // 미설정이면 조용히 건너뛴다 (로컬 개발 등)
    }

    $blocks = [
        [
            'type' => 'header',
            'text' => ['type' => 'plain_text', 'text' => '새 도입 문의 #' . $inquiryId, 'emoji' => true],
        ],
    ];

    $fieldBlocks = [];
    foreach ($fields as $label => $value) {
        $fieldBlocks[] = [
            'type' => 'mrkdwn',
            'text' => '*' . $label . "*\n" . ($value !== '' ? $value : '-'),
        ];
    }
    /* Slack section 의 fields 는 최대 10개까지만 렌더링된다. */
    foreach (array_chunk($fieldBlocks, 10) as $chunk) {
        $blocks[] = ['type' => 'section', 'fields' => $chunk];
    }

    $blocks[] = [
        'type' => 'section',
        'text' => ['type' => 'mrkdwn', 'text' => "*문의 내용*\n" . slack_quote($message)],
    ];

    $adminUrl = env_val('ADMIN_BASE_URL');
    if ($adminUrl !== '') {
        $blocks[] = [
            'type' => 'actions',
            'elements' => [[
                'type'  => 'button',
                'text'  => ['type' => 'plain_text', 'text' => '관리자 페이지에서 열기'],
                'url'   => rtrim($adminUrl, '/') . '/admin.php?r=view&id=' . $inquiryId,
                'style' => 'primary',
            ]],
        ];
    }

    slack_post($webhook, [
        'text'   => '새 도입 문의 #' . $inquiryId . ' — ' . ($fields['이름 / 회사'] ?? ''),
        'blocks' => $blocks,
    ]);
}

/** Slack mrkdwn 안전 처리 + 인용 블록으로 감싸기 (길면 자른다). */
function slack_quote(string $text): string
{
    $text = strtr(mb_substr($text, 0, 1500), ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    return '>' . str_replace("\n", "\n>", $text);
}

/**
 * @param array<string, mixed> $payload
 */
function slack_post(string $webhook, array $payload): void
{
    $ch = curl_init($webhook);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        /* 문의자 응답을 붙잡지 않도록 짧게. Slack 이 느리면 알림을 포기한다. */
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $res = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_errno($ch);
    curl_close($ch);

    if ($err !== 0 || $status < 200 || $status >= 300) {
        app_log('slack-failed', sprintf(
            'curl_errno=%d http=%d body=%s',
            $err,
            $status,
            substr((string) $res, 0, 200)
        ));
    }
}
