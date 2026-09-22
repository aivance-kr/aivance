<?php
/**
 * SQLite 저장소 — 문의 원본과 답장 발송 이력.
 *
 * DB 파일은 writable/db/ 아래에 둔다. writable/ 에는 전부 거부하는 .htaccess 가 있어
 * 웹에서 직접 내려받을 수 없다.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** 문의 처리 상태. */
enum InquiryStatus: string
{
    case New     = 'new';       // 접수됨, 아직 확인 전
    case Read    = 'read';      // 확인함
    case Replied = 'replied';   // 답장 발송함
    case Spam    = 'spam';      // 스팸으로 판정 (자동 필터 또는 수동)

    public function label(): string
    {
        return match ($this) {
            self::New     => '신규',
            self::Read    => '확인',
            self::Replied => '답장완료',
            self::Spam    => '스팸',
        };
    }
}

/** 열려 있는 PDO 핸들을 재사용한다 (요청당 1개). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PHP 확장 pdo_sqlite 가 설치되어 있지 않습니다.');
    }

    $pdo = new PDO('sqlite:' . writable_path('db') . '/inquiries.sqlite', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    /* 읽기(관리자 목록)와 쓰기(문의 접수)가 겹쳐도 서로 막지 않게 WAL 사용.
     * 지원하지 않는 파일시스템이면 SQLite 가 조용히 기존 저널 모드를 유지한다. */
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');

    db_migrate($pdo);

    return $pdo;
}

/** 스키마 생성 (IF NOT EXISTS 이므로 매 요청 호출해도 안전하고 별도 마이그레이션 명령이 없다). */
function db_migrate(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS inquiries (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            email       TEXT NOT NULL,
            phone       TEXT NOT NULL DEFAULT '',
            product     TEXT NOT NULL DEFAULT '',
            message     TEXT NOT NULL,
            ip          TEXT NOT NULL DEFAULT '',
            user_agent  TEXT NOT NULL DEFAULT '',
            referer     TEXT NOT NULL DEFAULT '',
            status      TEXT NOT NULL DEFAULT 'new',
            spam_reason TEXT NOT NULL DEFAULT '',
            created_at  TEXT NOT NULL
        )
    SQL);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inquiries_created_at ON inquiries(created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inquiries_status ON inquiries(status)');

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS replies (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            inquiry_id  INTEGER NOT NULL REFERENCES inquiries(id) ON DELETE CASCADE,
            to_email    TEXT NOT NULL,
            subject     TEXT NOT NULL,
            body        TEXT NOT NULL,
            is_ok       INTEGER NOT NULL DEFAULT 1,
            detail      TEXT NOT NULL DEFAULT '',
            sent_at     TEXT NOT NULL
        )
    SQL);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_replies_inquiry_id ON replies(inquiry_id)');
}

/**
 * 문의 1건 저장. 저장된 행의 id 를 돌려준다.
 *
 * @param array<string, string> $f name/email/phone/product/message
 */
function inquiry_insert(array $f, InquiryStatus $status, string $spamReason = ''): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO inquiries (name, email, phone, product, message, ip, user_agent, referer, status, spam_reason, created_at)
         VALUES (:name, :email, :phone, :product, :message, :ip, :ua, :referer, :status, :spam_reason, :created_at)'
    );
    $stmt->execute([
        ':name'        => $f['name'] ?? '',
        ':email'       => $f['email'] ?? '',
        ':phone'       => $f['phone'] ?? '',
        ':product'     => $f['product'] ?? '',
        ':message'     => $f['message'] ?? '',
        ':ip'          => client_ip(),
        ':ua'          => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ':referer'     => mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
        ':status'      => $status->value,
        ':spam_reason' => $spamReason,
        ':created_at'  => date('c'),
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * 목록 조회.
 *
 * @return array{rows: list<array<string, mixed>>, total: int}
 */
function inquiry_list(?InquiryStatus $status, string $search, int $page, int $perPage): array
{
    $where = [];
    $params = [];
    if ($status !== null) {
        $where[] = 'status = :status';
        $params[':status'] = $status->value;
    }
    if ($search !== '') {
        $where[] = '(name LIKE :q OR email LIKE :q OR message LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $pdo = db();
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM inquiries' . $whereSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    /* LIMIT/OFFSET 은 바인딩 대신 정수 캐스팅 — SQLite 는 플레이스홀더를 허용하지만
     * PDO 가 문자열로 바인딩하면 구문 오류가 나므로 여기서 정수임을 보장한다. */
    $offset = max(0, ($page - 1) * $perPage);
    $sql = 'SELECT id, name, email, phone, product, substr(message, 1, 120) AS excerpt,
                   status, spam_reason, created_at
            FROM inquiries' . $whereSql . '
            ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** @return array<string, mixed>|null */
function inquiry_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM inquiries WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function inquiry_set_status(int $id, InquiryStatus $status): void
{
    $stmt = db()->prepare('UPDATE inquiries SET status = :status WHERE id = :id');
    $stmt->execute([':status' => $status->value, ':id' => $id]);
}

/** @return array<string, int> 상태값 => 건수 */
function inquiry_status_counts(): array
{
    $counts = [];
    foreach (db()->query('SELECT status, COUNT(*) AS c FROM inquiries GROUP BY status') as $row) {
        $counts[(string) $row['status']] = (int) $row['c'];
    }
    return $counts;
}

function reply_insert(int $inquiryId, string $to, string $subject, string $body, bool $ok, string $detail): void
{
    $stmt = db()->prepare(
        'INSERT INTO replies (inquiry_id, to_email, subject, body, is_ok, detail, sent_at)
         VALUES (:iid, :to, :subject, :body, :ok, :detail, :sent_at)'
    );
    $stmt->execute([
        ':iid'     => $inquiryId,
        ':to'      => $to,
        ':subject' => $subject,
        ':body'    => $body,
        ':ok'      => $ok ? 1 : 0,
        ':detail'  => $detail,
        ':sent_at' => date('c'),
    ]);
}

/** @return list<array<string, mixed>> */
function reply_list(int $inquiryId): array
{
    $stmt = db()->prepare('SELECT * FROM replies WHERE inquiry_id = :iid ORDER BY id DESC');
    $stmt->execute([':iid' => $inquiryId]);
    return $stmt->fetchAll();
}
