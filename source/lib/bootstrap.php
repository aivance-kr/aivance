<?php
/**
 * AIvance 공통 부트스트랩 — env 로드 / 타임존 / 클라이언트 IP / 레이트리밋 / 파일 로그.
 *
 * contact.php(공개 엔드포인트)와 admin.php(관리자 페이지)가 함께 쓴다.
 * 웹에서 이 파일을 직접 열어도 함수 정의만 있고 실행되는 출력이 없다.
 */

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

/* 앱 타임존은 KST 고정. SQLite 에는 오프셋을 포함한 ISO8601(date('c'))로 저장하므로
 * 문자열 정렬이 곧 시간 정렬이 되고, 나중에 서버 로케일이 바뀌어도 값이 흔들리지 않는다. */
date_default_timezone_set('Asia/Seoul');

$aivanceDotenv = Dotenv::createImmutable(dirname(__DIR__));
$aivanceDotenv->safeLoad();

/** 환경변수 헬퍼 (없으면 기본값). */
function env_val(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : (string) $v;
}

/** 웹에서 접근할 수 없는 런타임 디렉터리(writable/…)를 만들고 경로를 돌려준다. */
function writable_path(string $sub): string
{
    $dir = dirname(__DIR__) . '/writable/' . trim($sub, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir;
}

/* Cloudflare 공식 엣지 IP 대역 (https://www.cloudflare.com/ips/) — CF-Connecting-IP 헤더는
 * 실제 연결이 이 대역에서 온 경우에만 신뢰한다. 그렇지 않으면 서버에 직접 요청을 보내면서
 * 헤더만 위조해 레이트리밋·로그의 IP를 속일 수 있다. */
const CLOUDFLARE_CIDRS = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
    '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
    '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
    '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
];

function ip_in_cidr(string $ip, string $cidr): bool
{
    [$subnet, $bits] = array_pad(explode('/', $cidr), 2, null);
    $bits = (int) $bits;
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    $remBits = $bits % 8;
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
        return false;
    }
    if ($remBits === 0) {
        return true;
    }
    $mask = chr((0xFF << (8 - $remBits)) & 0xFF);
    return (substr($ipBin, $bytes, 1) & $mask) === (substr($subnetBin, $bytes, 1) & $mask);
}

function is_cloudflare_ip(string $ip): bool
{
    foreach (CLOUDFLARE_CIDRS as $cidr) {
        if (ip_in_cidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

/** 실제 연결(REMOTE_ADDR)이 Cloudflare 대역에서 온 경우에만 CF-Connecting-IP 헤더를 신뢰한다. */
function client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $cf = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && is_cloudflare_ip($remote)) {
        return $cf;
    }
    return $remote;
}

/**
 * 버킷별 최소 간격(초) / 시간당 최대 횟수 검사. 허용되면 즉시 기록 후 true.
 *
 * $bucket 은 "용도:식별자" 형태로 넣어 문의 폼과 관리자 로그인의 카운터가 섞이지 않게 한다.
 */
function rl_allow(string $bucket, int $minIntervalSec, int $maxPerHour): bool
{
    $path = writable_path('ratelimit') . '/' . hash('sha256', $bucket) . '.json';
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

/**
 * 날짜별 파일 로그.
 *
 * error_log() 는 이 호스팅(cPanel)에서 어디로 가는지 알 수 없어 신뢰할 수 없으므로
 * 운영 중 확인해야 하는 사유는 반드시 파일에 직접 쓴다.
 */
function app_log(string $channel, string $line): void
{
    /* 로그 줄에는 공격자가 고른 값(아이디, User-Agent 등)이 섞인다. 개행이 그대로 들어가면
     * 가짜 로그 줄을 심을 수 있으므로 줄바꿈을 없애고 길이를 자른 뒤 기록한다. */
    $line = mb_substr(strtr($line, ["\r\n" => ' ', "\n" => ' ', "\r" => ' ']), 0, 2000);

    file_put_contents(
        writable_path('logs') . '/' . $channel . '-' . date('Y-m-d') . '.log',
        sprintf("[%s] %s\n", date('c'), $line),
        FILE_APPEND | LOCK_EX
    );
}

/** 차단 이벤트 기록 (문의 폼 전용 채널). */
function log_spam_block(string $reason, array $extra = []): void
{
    app_log('spam', sprintf(
        'ip=%s reason=%s %s',
        client_ip() ?: '-',
        $reason,
        json_encode($extra, JSON_UNESCAPED_UNICODE)
    ));
}
