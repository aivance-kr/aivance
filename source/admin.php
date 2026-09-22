<?php
/**
 * AIvance — 문의 관리자 페이지
 *
 *  GET  /admin.php                      → 로그인 폼 또는 문의 목록
 *  GET  /admin.php?r=view&id=123        → 문의 상세 + 답장 작성
 *  POST /admin.php?r=login|logout|reply|status
 *
 * 계정은 .env 의 ADMIN_USER / ADMIN_PASSWORD_HASH 단일 계정이다
 * (해시 생성: php tools/admin-hash.php).
 *
 * 메일 발송은 이 화면에서만 일어난다 — 공개 문의 엔드포인트(contact.php)에는
 * 메일을 내보내는 경로가 아예 없어 폼을 스팸 릴레이로 쓸 수 없다.
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/mailer.php';

ini_set('display_errors', '0');
set_exception_handler(static function (\Throwable $e): void {
    app_log('admin-fatal', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "서버 오류가 발생했습니다. writable/logs/admin-fatal-*.log 를 확인하세요.\n";
    exit;
});

/* ── 세션 ─────────────────────────────────────────────────────────────
 * 공개 폼(contact.php)은 매 POST 마다 CSRF 토큰을 폐기하므로 세션을 공유하면
 * 관리자 로그인 상태가 휘둘린다. 세션 이름을 분리해 완전히 독립시킨다. */
const ADMIN_IDLE_TIMEOUT     = 7200;   // 2시간 무활동 시 로그아웃
const ADMIN_ABSOLUTE_TIMEOUT = 43200;  // 로그인 후 12시간이면 무조건 재로그인
const PER_PAGE               = 20;

function request_is_https(): bool
{
    if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    /* Cloudflare 프록시 뒤에서는 원본 연결이 평문일 수 있으므로 전달 헤더도 본다. */
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return str_contains((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"https"');
}

session_name('AIVADMIN');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => request_is_https(),
]);
session_start();

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate, private');

$styleNonce = base64_encode(random_bytes(16));
header(
    "Content-Security-Policy: default-src 'none'; style-src 'nonce-{$styleNonce}'; "
    . "img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'"
);

/* ── 유틸 ─────────────────────────────────────────────────────────────── */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post(string $k): string
{
    return trim((string) ($_POST[$k] ?? ''));
}

function query(string $k): string
{
    return trim((string) ($_GET[$k] ?? ''));
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['admin_csrf'];
}

/** CSRF 불일치는 조용히 넘기지 않고 즉시 중단한다. */
function csrf_check(): void
{
    $sent = (string) ($_POST['csrf_token'] ?? '');
    $sess = (string) ($_SESSION['admin_csrf'] ?? '');
    if ($sess === '' || $sent === '' || !hash_equals($sess, $sent)) {
        http_response_code(419);
        exit('보안 토큰이 유효하지 않습니다. 새로고침 후 다시 시도해 주세요.');
    }
}

function is_logged_in(): bool
{
    if (empty($_SESSION['admin_user'])) {
        return false;
    }
    $now = time();
    $loginAt  = (int) ($_SESSION['admin_login_at'] ?? 0);
    $lastSeen = (int) ($_SESSION['admin_last_seen'] ?? 0);
    if (($now - $loginAt) > ADMIN_ABSOLUTE_TIMEOUT || ($now - $lastSeen) > ADMIN_IDLE_TIMEOUT) {
        admin_logout();
        return false;
    }
    $_SESSION['admin_last_seen'] = $now;
    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ── 라우팅 ───────────────────────────────────────────────────────────── */
$route  = query('r') !== '' ? query('r') : (string) ($_POST['r'] ?? '');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isPost && $route === 'login') {
    handle_login();
}
if ($isPost && $route === 'logout') {
    csrf_check();
    admin_logout();
    redirect('admin.php');
}

if (!is_logged_in()) {
    render_login();
}

if ($isPost && $route === 'reply') {
    handle_reply();
}
if ($isPost && $route === 'status') {
    handle_status();
}
if ($isPost && $route === 'delete') {
    handle_delete();
}
if ($isPost && $route === 'purge-spam') {
    handle_purge_spam();
}
if ($route === 'view') {
    render_view((int) query('id'));
}
render_list();

/* ── 액션 ─────────────────────────────────────────────────────────────── */
function handle_login(): never
{
    csrf_check();

    $ip = client_ip() ?: 'unknown';
    /* 무차별 대입 방지 — IP 당 2초 간격·시간당 20회, 사이트 전체로 시간당 60회. */
    if (!rl_allow('adminlogin:' . $ip, 2, 20) || !rl_allow('adminlogin:__global__', 0, 60)) {
        app_log('admin-auth', 'rate_limited ip=' . $ip);
        render_login('잠시 후 다시 시도해 주세요.');
    }

    $user = post('username');
    $pass = (string) ($_POST['password'] ?? '');
    $expectedUser = env_val('ADMIN_USER');
    $expectedHash = env_val('ADMIN_PASSWORD_HASH');

    if ($expectedUser === '' || $expectedHash === '') {
        app_log('admin-auth', 'ADMIN_USER 또는 ADMIN_PASSWORD_HASH 미설정 (.env 확인)');
        render_login('관리자 계정이 설정되지 않았습니다. .env 를 확인하세요.');
    }

    /* 아이디가 틀려도 해시 검증을 수행해 응답 시간으로 아이디 존재 여부가 새지 않게 한다. */
    $userOk = hash_equals($expectedUser, $user);
    $passOk = password_verify($pass, $expectedHash);
    if (!$userOk || !$passOk) {
        app_log('admin-auth', 'login_failed ip=' . $ip . ' user=' . $user);
        render_login('아이디 또는 비밀번호가 올바르지 않습니다.');
    }

    /* 세션 고정 공격 방지 — 인증 직후 세션 ID 를 새로 발급한다. */
    session_regenerate_id(true);
    $_SESSION['admin_user']      = $expectedUser;
    $_SESSION['admin_login_at']  = time();
    $_SESSION['admin_last_seen'] = time();
    unset($_SESSION['admin_csrf']);
    app_log('admin-auth', 'login_ok ip=' . $ip . ' user=' . $expectedUser);

    redirect('admin.php');
}

function handle_status(): never
{
    csrf_check();
    $id = (int) post('id');
    $status = InquiryStatus::tryFrom(post('status'));
    if ($id <= 0 || $status === null || inquiry_find($id) === null) {
        http_response_code(422);
        exit('잘못된 요청입니다.');
    }
    inquiry_set_status($id, $status);
    redirect('admin.php?r=view&id=' . $id);
}

/**
 * 문의 1건 삭제. 되돌릴 수 없으므로 confirm 값이 없으면 먼저 확인 화면을 보여준다.
 *
 * 관리자 화면에는 JS 가 없어(CSP `default-src 'none'`) 브라우저 confirm() 을 쓸 수 없다.
 * 대신 서버가 무엇이 지워지는지 보여주고 한 번 더 POST 를 받는다.
 */
function handle_delete(): never
{
    csrf_check();

    $id = (int) post('id');
    $inquiry = $id > 0 ? inquiry_find($id) : null;
    if ($inquiry === null) {
        http_response_code(404);
        exit('문의를 찾을 수 없습니다.');
    }

    if (post('confirm') !== 'yes') {
        render_delete_confirm($inquiry);
    }

    /* 오삭제는 복구할 방법이 없으므로 지우기 전에 내용을 감사 로그로 남긴다. */
    audit_log('inquiry_deleted', [
        'id'      => $id,
        'name'    => (string) $inquiry['name'],
        'email'   => (string) $inquiry['email'],
        'status'  => (string) $inquiry['status'],
        'created' => (string) $inquiry['created_at'],
        'replies' => reply_count($id),
    ]);
    inquiry_delete($id);

    redirect('admin.php?deleted=1');
}

/** 스팸 전체 삭제. 단건 삭제와 같은 2단계 확인을 거친다. */
function handle_purge_spam(): never
{
    csrf_check();

    $count = inquiry_list(InquiryStatus::Spam, '', 1, 1)['total'];
    if ($count === 0) {
        redirect('admin.php?status=spam');
    }

    if (post('confirm') !== 'yes') {
        render_purge_confirm($count);
    }

    audit_log('spam_purged', ['count' => $count]);
    $deleted = inquiry_delete_all_spam();

    redirect('admin.php?status=spam&purged=' . $deleted);
}

/**
 * 관리자의 파괴적 조작을 기록한다. 삭제는 DB 에서 되돌릴 수 없으므로
 * 최소한 무엇을 언제 지웠는지는 파일에 남겨 추적할 수 있게 한다.
 *
 * @param array<string, mixed> $context
 */
function audit_log(string $action, array $context): void
{
    app_log('admin-audit', sprintf(
        'user=%s ip=%s action=%s %s',
        (string) ($_SESSION['admin_user'] ?? '-'),
        client_ip() ?: '-',
        $action,
        json_encode($context, JSON_UNESCAPED_UNICODE)
    ));
}

function handle_reply(): never
{
    csrf_check();

    $id = (int) post('id');
    $inquiry = $id > 0 ? inquiry_find($id) : null;
    if ($inquiry === null) {
        http_response_code(404);
        exit('문의를 찾을 수 없습니다.');
    }

    $subject = post('subject');
    $body    = (string) ($_POST['body'] ?? '');
    $to      = (string) $inquiry['email'];

    if ($subject === '' || mb_strlen($subject) > 200) {
        render_view($id, '제목을 200자 이내로 입력해 주세요.');
    }
    if (trim($body) === '' || mb_strlen($body) > 20000) {
        render_view($id, '본문을 20000자 이내로 입력해 주세요.');
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        render_view($id, '문의자의 이메일 주소가 올바르지 않아 발송할 수 없습니다.');
    }
    /* 관리자 계정이 탈취돼도 대량 발송 도구로 쓰이지 않도록 상한을 둔다. */
    if (!rl_allow('adminreply:__global__', 2, 100)) {
        render_view($id, '발송이 너무 잦습니다. 잠시 후 다시 시도해 주세요.');
    }

    $result = send_reply_mail($to, (string) $inquiry['name'], $subject, $body);
    reply_insert($id, $to, $subject, $body, $result['ok'], $result['detail']);

    if (!$result['ok']) {
        render_view($id, '메일 발송에 실패했습니다: ' . $result['detail']);
    }

    inquiry_set_status($id, InquiryStatus::Replied);
    redirect('admin.php?r=view&id=' . $id . '&sent=1');
}

/* ── 화면 ─────────────────────────────────────────────────────────────── */
function layout_head(string $title): void
{
    global $styleNonce;
    ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> · AIvance 문의 관리</title>
<style nonce="<?= h($styleNonce) ?>">
:root{--bg:#f8fafc;--card:#fff;--line:#e2e8f0;--ink:#0f172a;--mut:#64748b;--brand:#2563eb;--danger:#dc2626}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.6 -apple-system,BlinkMacSystemFont,"Noto Sans KR",Arial,sans-serif}
a{color:var(--brand)}
header{background:var(--ink);color:#fff;padding:14px 20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
header h1{font-size:16px;margin:0;font-weight:700}
header a{color:#cbd5e1;text-decoration:none}
header form{margin-left:auto}
main{max-width:1000px;margin:24px auto;padding:0 16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:16px}
h2{font-size:18px;margin:0 0 14px}
h3{font-size:15px;margin:22px 0 8px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--line);vertical-align:top}
th{background:#f1f5f9;font-weight:600;white-space:nowrap}
td.nowrap,th.nowrap{white-space:nowrap}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600}
.s-new{background:#dbeafe;color:#1d4ed8}
.s-read{background:#e2e8f0;color:#475569}
.s-replied{background:#dcfce7;color:#15803d}
.s-spam{background:#fee2e2;color:#b91c1c}
.filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
input[type=text],input[type=password],input[type=search],select,textarea{
  font:inherit;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;width:100%}
textarea{min-height:220px;resize:vertical;line-height:1.7}
button{font:inherit;font-weight:600;padding:8px 16px;border:0;border-radius:6px;background:var(--brand);color:#fff;cursor:pointer}
button.ghost{background:#e2e8f0;color:var(--ink)}
button.danger{background:var(--danger)}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.muted{color:var(--mut)}
.msg{white-space:pre-wrap;background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:14px}
.alert{padding:11px 14px;border-radius:8px;margin-bottom:14px}
.alert-err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.alert-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.pager{display:flex;gap:6px;flex-wrap:wrap;margin-top:14px}
.pager a,.pager span{padding:6px 11px;border:1px solid var(--line);border-radius:6px;text-decoration:none;background:#fff}
.pager span{background:var(--ink);color:#fff;border-color:var(--ink)}
.login{max-width:360px;margin:12vh auto}
.field{margin-bottom:12px}
.w-md{max-width:280px}
.w-sm{max-width:180px}
label{display:block;font-weight:600;margin-bottom:5px;font-size:13px}
</style>
</head>
<body>
    <?php
}

function layout_header(): void
{
    ?>
<header>
  <h1>AIvance 문의 관리</h1>
  <a href="admin.php">목록</a>
  <form method="post" action="admin.php">
    <input type="hidden" name="r" value="logout">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <button class="ghost" type="submit">로그아웃</button>
  </form>
</header>
    <?php
}

function status_badge(string $status): string
{
    $case = InquiryStatus::tryFrom($status);
    $label = $case?->label() ?? $status;
    return '<span class="badge s-' . h($status) . '">' . h($label) . '</span>';
}

/** 저장된 ISO8601(+09:00) 문자열을 화면용으로 줄인다. */
function fmt_time(string $iso): string
{
    $ts = strtotime($iso);
    return $ts === false ? $iso : date('Y-m-d H:i', $ts);
}

function render_login(string $error = ''): never
{
    layout_head('로그인');
    ?>
<main class="login">
  <div class="card">
    <h2>관리자 로그인</h2>
    <?php if ($error !== ''): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="admin.php">
      <input type="hidden" name="r" value="login">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <div class="field">
        <label for="username">아이디</label>
        <input id="username" name="username" type="text" autocomplete="username" required>
      </div>
      <div class="field">
        <label for="password">비밀번호</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button type="submit">로그인</button>
    </form>
  </div>
</main>
</body></html>
    <?php
    exit;
}

function render_list(): never
{
    $status = InquiryStatus::tryFrom(query('status'));
    $search = mb_substr(query('q'), 0, 100);
    $page   = max(1, (int) query('page'));

    $result = inquiry_list($status, $search, $page, PER_PAGE);
    $lastPage = max(1, (int) ceil($result['total'] / PER_PAGE));
    $counts = inquiry_status_counts();

    $baseQuery = static function (array $over) use ($status, $search): string {
        $q = array_filter([
            'status' => $over['status'] ?? $status?->value ?? '',
            'q'      => $over['q'] ?? $search,
            'page'   => (string) ($over['page'] ?? ''),
        ], static fn (string $v): bool => $v !== '');
        return 'admin.php' . ($q ? '?' . http_build_query($q) : '');
    };

    $deleted = query('deleted') === '1';
    $purged  = query('purged') !== '' ? (int) query('purged') : null;

    layout_head('문의 목록');
    layout_header();
    ?>
<main>
  <?php if ($deleted): ?><div class="alert alert-ok">문의를 삭제했습니다.</div><?php endif; ?>
  <?php if ($purged !== null): ?><div class="alert alert-ok">스팸 <?= $purged ?>건을 삭제했습니다.</div><?php endif; ?>
  <div class="card">
    <h2>문의 목록 <span class="muted">(<?= (int) $result['total'] ?>건)</span></h2>

    <div class="filters">
      <a href="<?= h($baseQuery(['status' => ''])) ?>">전체</a>
      <?php foreach (InquiryStatus::cases() as $case): ?>
        <a href="<?= h($baseQuery(['status' => $case->value])) ?>">
          <?= h($case->label()) ?> (<?= (int) ($counts[$case->value] ?? 0) ?>)
        </a>
      <?php endforeach; ?>
    </div>

    <form method="get" action="admin.php" class="filters">
      <?php if ($status !== null): ?>
        <input type="hidden" name="status" value="<?= h($status->value) ?>">
      <?php endif; ?>
      <input type="search" name="q" value="<?= h($search) ?>" placeholder="이름·이메일·내용 검색" class="w-md">
      <button type="submit">검색</button>
    </form>

    <?php if ($status === InquiryStatus::Spam && $result['total'] > 0): ?>
      <form method="post" action="admin.php" class="filters">
        <input type="hidden" name="r" value="purge-spam">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <button class="danger" type="submit">스팸 <?= (int) $result['total'] ?>건 전체 삭제</button>
        <span class="muted">검색어와 무관하게 스팸 전체가 대상입니다. 삭제 전에 확인 화면을 거칩니다.</span>
      </form>
    <?php endif; ?>

    <?php if (!$result['rows']): ?>
      <p class="muted">해당하는 문의가 없습니다.</p>
    <?php else: ?>
      <table>
        <tr>
          <th class="nowrap">#</th><th class="nowrap">접수</th><th>이름 / 회사</th>
          <th>이메일</th><th class="nowrap">제품</th><th>내용</th><th class="nowrap">상태</th>
        </tr>
        <?php foreach ($result['rows'] as $row): ?>
          <tr>
            <td class="nowrap"><a href="admin.php?r=view&amp;id=<?= (int) $row['id'] ?>">#<?= (int) $row['id'] ?></a></td>
            <td class="nowrap muted"><?= h(fmt_time((string) $row['created_at'])) ?></td>
            <td><?= h((string) $row['name']) ?></td>
            <td><?= h((string) $row['email']) ?></td>
            <td class="nowrap"><?= h((string) $row['product'] ?: '-') ?></td>
            <td class="muted"><?= h((string) $row['excerpt']) ?></td>
            <td class="nowrap"><?= status_badge((string) $row['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>

      <?php if ($lastPage > 1): ?>
        <div class="pager">
          <?php for ($p = 1; $p <= $lastPage; $p++): ?>
            <?php if ($p === $page): ?>
              <span><?= $p ?></span>
            <?php else: ?>
              <a href="<?= h($baseQuery(['page' => $p])) ?>"><?= $p ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
</body></html>
    <?php
    exit;
}

/**
 * 삭제 확인 화면 — 무엇이 사라지는지 보여주고 한 번 더 POST 를 받는다.
 *
 * @param array<string, mixed> $inquiry
 */
function render_delete_confirm(array $inquiry): never
{
    $id = (int) $inquiry['id'];
    $replies = reply_count($id);

    layout_head('문의 #' . $id . ' 삭제');
    layout_header();
    ?>
<main>
  <div class="card">
    <h2>문의 #<?= $id ?> 을(를) 삭제할까요?</h2>
    <div class="alert alert-err">
      <strong>되돌릴 수 없습니다.</strong> 이 문의와 딸린 발송 이력<?= $replies > 0 ? ' ' . $replies . '건' : '' ?>이 영구히 지워집니다.
    </div>
    <table>
      <tr><th class="nowrap">이름 / 회사</th><td><?= h((string) $inquiry['name']) ?></td></tr>
      <tr><th class="nowrap">이메일</th><td><?= h((string) $inquiry['email']) ?></td></tr>
      <tr><th class="nowrap">접수 시각</th><td><?= h(fmt_time((string) $inquiry['created_at'])) ?></td></tr>
      <tr><th class="nowrap">상태</th><td><?= status_badge((string) $inquiry['status']) ?></td></tr>
    </table>
    <h3>문의 내용</h3>
    <div class="msg"><?= h((string) $inquiry['message']) ?></div>

    <div class="row">
      <form method="post" action="admin.php">
        <input type="hidden" name="r" value="delete">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="confirm" value="yes">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <button class="danger" type="submit">영구 삭제</button>
      </form>
      <a href="admin.php?r=view&amp;id=<?= $id ?>">취소하고 돌아가기</a>
    </div>
  </div>
</main>
</body></html>
    <?php
    exit;
}

/** 스팸 전체 삭제 확인 화면. */
function render_purge_confirm(int $count): never
{
    layout_head('스팸 전체 삭제');
    layout_header();
    ?>
<main>
  <div class="card">
    <h2>스팸 <?= $count ?>건을 모두 삭제할까요?</h2>
    <div class="alert alert-err">
      <strong>되돌릴 수 없습니다.</strong> 상태가 <em>스팸</em>인 문의 <?= $count ?>건과 딸린 발송 이력이 영구히 지워집니다.
      오탐이 섞여 있을 수 있으니 목록을 먼저 확인하세요.
    </div>
    <div class="row">
      <form method="post" action="admin.php">
        <input type="hidden" name="r" value="purge-spam">
        <input type="hidden" name="confirm" value="yes">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <button class="danger" type="submit">스팸 <?= $count ?>건 영구 삭제</button>
      </form>
      <a href="admin.php?status=spam">취소하고 목록으로</a>
    </div>
  </div>
</main>
</body></html>
    <?php
    exit;
}

function render_view(int $id, string $error = ''): never
{
    $inquiry = $id > 0 ? inquiry_find($id) : null;
    if ($inquiry === null) {
        http_response_code(404);
        layout_head('없음');
        layout_header();
        echo '<main><div class="card"><p>문의를 찾을 수 없습니다.</p></div></main></body></html>';
        exit;
    }

    /* 목록에서 처음 열면 '확인' 으로 올린다 (답장완료·스팸 상태는 건드리지 않는다). */
    if ((string) $inquiry['status'] === InquiryStatus::New->value) {
        inquiry_set_status($id, InquiryStatus::Read);
        $inquiry['status'] = InquiryStatus::Read->value;
    }

    $replies = reply_list($id);
    $sent = query('sent') === '1';

    $defaultSubject = '[AIvance] 문의에 대한 답변드립니다';
    $defaultBody = sprintf(
        "%s님, 안녕하세요.\nAIvance 입니다.\n\n문의 주셔서 감사합니다.\n\n\n\n"
        . "──────────────────────────────\n[%s 접수하신 문의]\n%s\n",
        (string) $inquiry['name'],
        fmt_time((string) $inquiry['created_at']),
        (string) $inquiry['message']
    );

    layout_head('문의 #' . $id);
    layout_header();
    ?>
<main>
  <?php if ($sent): ?><div class="alert alert-ok">답장을 발송했습니다.</div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>

  <div class="card">
    <h2>문의 #<?= (int) $inquiry['id'] ?> <?= status_badge((string) $inquiry['status']) ?></h2>
    <table>
      <tr><th class="nowrap">이름 / 회사</th><td><?= h((string) $inquiry['name']) ?></td></tr>
      <tr><th class="nowrap">이메일</th><td><?= h((string) $inquiry['email']) ?></td></tr>
      <tr><th class="nowrap">연락처</th><td><?= h((string) $inquiry['phone'] ?: '-') ?></td></tr>
      <tr><th class="nowrap">관심 제품</th><td><?= h((string) $inquiry['product'] ?: '-') ?></td></tr>
      <tr><th class="nowrap">접수 시각</th><td><?= h(fmt_time((string) $inquiry['created_at'])) ?></td></tr>
      <tr><th class="nowrap">요청 IP</th><td><?= h((string) $inquiry['ip'] ?: '-') ?></td></tr>
      <tr><th class="nowrap">User-Agent</th><td class="muted"><?= h((string) $inquiry['user_agent'] ?: '-') ?></td></tr>
      <?php if ((string) $inquiry['spam_reason'] !== ''): ?>
        <tr><th class="nowrap">스팸 판정 사유</th><td><?= h((string) $inquiry['spam_reason']) ?></td></tr>
      <?php endif; ?>
    </table>

    <h3>문의 내용</h3>
    <div class="msg"><?= h((string) $inquiry['message']) ?></div>

    <h3>상태 변경</h3>
    <form method="post" action="admin.php" class="row">
      <input type="hidden" name="r" value="status">
      <input type="hidden" name="id" value="<?= (int) $inquiry['id'] ?>">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <select name="status" class="w-sm">
        <?php foreach (InquiryStatus::cases() as $case): ?>
          <option value="<?= h($case->value) ?>" <?= (string) $inquiry['status'] === $case->value ? 'selected' : '' ?>>
            <?= h($case->label()) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="ghost" type="submit">변경</button>
    </form>

    <h3>삭제</h3>
    <form method="post" action="admin.php">
      <input type="hidden" name="r" value="delete">
      <input type="hidden" name="id" value="<?= (int) $inquiry['id'] ?>">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <button class="danger" type="submit">이 문의 삭제</button>
      <span class="muted">삭제 전에 확인 화면을 한 번 더 거칩니다.</span>
    </form>
  </div>

  <div class="card">
    <h2>답장 보내기</h2>
    <p class="muted">받는 사람: <?= h((string) $inquiry['email']) ?></p>
    <form method="post" action="admin.php">
      <input type="hidden" name="r" value="reply">
      <input type="hidden" name="id" value="<?= (int) $inquiry['id'] ?>">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <div class="field">
        <label for="subject">제목</label>
        <input id="subject" name="subject" type="text" maxlength="200"
               value="<?= h((string) ($_POST['subject'] ?? $defaultSubject)) ?>" required>
      </div>
      <div class="field">
        <label for="body">본문</label>
        <textarea id="body" name="body" maxlength="20000" required><?= h((string) ($_POST['body'] ?? $defaultBody)) ?></textarea>
      </div>
      <button type="submit">메일 발송</button>
    </form>
  </div>

  <?php if ($replies): ?>
    <div class="card">
      <h2>발송 이력 (<?= count($replies) ?>건)</h2>
      <?php foreach ($replies as $reply): ?>
        <h3>
          <?= h(fmt_time((string) $reply['sent_at'])) ?> → <?= h((string) $reply['to_email']) ?>
          <?= ((int) $reply['is_ok'] === 1)
              ? '<span class="badge s-replied">성공</span>'
              : '<span class="badge s-spam">실패</span>' ?>
        </h3>
        <p class="muted"><?= h((string) $reply['subject']) ?></p>
        <?php if ((string) $reply['detail'] !== ''): ?>
          <p class="muted">사유: <?= h((string) $reply['detail']) ?></p>
        <?php endif; ?>
        <div class="msg"><?= h((string) $reply['body']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body></html>
    <?php
    exit;
}
