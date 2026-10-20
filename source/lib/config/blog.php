<?php
/**
 * 개발 기록 티저 — 개인 블로그(jblove.net) 최신 글 3개를 수동으로 옮겨 적은 정적 데이터.
 *
 * url 이 null 이면 링크 없는 카드로 나간다. 외부 링크는 새 탭으로 열린다.
 * TODO: 글이 올라올 때마다 갱신 (자동화하려면 블로그 RSS/API 로 읽어오도록 교체).
 */

declare(strict_types=1);

return [
    'more_url' => 'https://jblove.net/board/up0',
    'posts'    => [
        ['title' => 'cloudflare 삽질', 'date' => '2026-10-12', 'summary' => '무료 플랜에서 국내 접속이 해외 경유지를 거치는 문제를 겪고, 홈서버 구성을 단순하게 줄이기로 한 기록입니다.', 'url' => 'https://jblove.net/board/up0/1822'],
        ['title' => '클로드 사용시 토큰 절약 팁', 'date' => '2026-09-10', 'summary' => 'CLAUDE.md 같은 세션 문서를 영어로 쓰면 토큰을 아낄 수 있는 이유를 정리했습니다.', 'url' => 'https://jblove.net/board/up0/1821'],
        ['title' => '블로그 주체 변경', 'date' => '2026-09-09', 'summary' => '자주 해킹당하고 무거운 워드프레스에서, 직접 AI로 만든 AICreo 블로그 테마로 옮긴 이야기입니다.', 'url' => 'https://jblove.net/board/up0/1820'],
    ],
];
