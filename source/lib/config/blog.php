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
        ['title' => '1인 개발자가 클로드코드로 SaaS 11개 만든 기록', 'date' => '2026-10-20', 'summary' => '구현·테스트·문서화는 AI에게 맡기고, 제품 범위와 아키텍처·가격·보안 결정은 직접 쥔 26년 차 개발자의 작업 방식을 정리했습니다.', 'url' => 'https://jblove.net/index.php/board/up0/1823'],
        ['title' => 'CLAUDE.md로 팀 규칙을 AI에게 가르치는 법', 'date' => '2026-10-20', 'summary' => '채팅으로 반복하던 당부를 CLAUDE.md에 옮겨 세션이 바뀌어도 같은 기준을 유지하는 방법과, 지켜야 할 규칙을 훅·CI로 강제하는 방식을 다룹니다.', 'url' => 'https://jblove.net/index.php/board/up0/1824'],
        ['title' => 'cloudflare 삽질', 'date' => '2026-10-12', 'summary' => '무료 플랜에서 국내 접속이 해외 경유지를 거치는 문제를 겪고, 홈서버 구성을 단순하게 줄이기로 한 기록입니다.', 'url' => 'https://jblove.net/board/up0/1822'],
    ],
];
