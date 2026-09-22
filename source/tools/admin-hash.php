<?php
/**
 * 관리자 비밀번호 해시 생성기 (CLI 전용).
 *
 *   php tools/admin-hash.php
 *
 * 출력된 해시를 .env 의 ADMIN_PASSWORD_HASH 에 그대로 넣는다.
 * 해시에는 $ 가 들어가므로 .env 에서는 반드시 작은따옴표로 감싼다.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/* Argon2id 가 빌드에 없는 PHP 도 있으므로 사용 가능할 때만 쓰고 아니면 bcrypt 로 떨어진다.
 * 검증(password_verify)은 어느 쪽이든 동일하게 동작한다. */
$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

echo '비밀번호 입력 (화면에 표시되지 않음): ';
shell_exec('stty -echo 2>/dev/null');
$pass = rtrim((string) fgets(STDIN), "\r\n");
shell_exec('stty echo 2>/dev/null');
echo PHP_EOL;

if (mb_strlen($pass) < 12) {
    fwrite(STDERR, "비밀번호는 12자 이상으로 지정하세요.\n");
    exit(1);
}

$hash = password_hash($pass, $algo);
if ($hash === false) {
    fwrite(STDERR, "해시 생성에 실패했습니다.\n");
    exit(1);
}

echo "\n.env 에 아래 두 줄을 넣으세요 (해시는 작은따옴표 필수):\n\n";
echo "ADMIN_USER=admin\n";
echo "ADMIN_PASSWORD_HASH='" . $hash . "'\n\n";
