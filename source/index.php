<?php
/**
 * AIvance 메인 페이지.
 *
 * 설정은 lib/config/*.php, 화면은 lib/views/home/*.php partial 로 나뉘어 있다.
 * (정적 index.html 은 legacy/index_legacy.html 로 백업)
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/home.php';

$query = array_map('strval', array_filter($_GET, 'is_string'));

echo home_render($query, env_val('GA_MEASUREMENT_ID'));
