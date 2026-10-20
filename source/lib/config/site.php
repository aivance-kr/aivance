<?php
/**
 * 메인 페이지 사이트 설정 — 신뢰 지표 · 창업자 · 회사 정보 · 컨설팅 카드 · 바우처.
 *
 * 화면 문구 중 수치·사실 관계는 여기서만 고친다 (뷰에 하드코딩 금지).
 * `TODO: 대표 확인 필요` 주석은 대표 확인 전의 임시값이다.
 */

declare(strict_types=1);

return [
    'url'     => 'https://aivance.kr',
    'name'    => 'AIvance',
    'title'   => 'AIvance | AI 개발환경 구축 컨설팅 · 실무형 SaaS',
    'description' => '26년 경력 PHP 개발자 변종원(웅파)이 Claude Code로 만든 실무형 SaaS와, 팀의 AI 개발환경 구축 컨설팅을 제공합니다.',
    'og_image' => 'https://aivance.kr/assets/og/main.png', // TODO: 대표 확인 필요 — OG 이미지 제작 후 교체 (현재 placeholder)

    /* AI로 제작한 SaaS 수 — 히어로 문구와 신뢰 지표의 {saas} 에 들어간다 (공개 제품 카드 수와 별개) */
    'saas_count' => 11,

    /* 신뢰 지표 */
    'trust' => [
        ['value' => '26년',  'label' => 'PHP 개발 경력'],
        ['value' => '저자',  'label' => 'CodeIgniter 서적 (한빛미디어, 2013)'],
        ['value' => '17년', 'label' => 'CodeIgniter 한국 사용자 포럼 운영'],
        ['value' => '{saas}종', 'label' => 'AI로 제작한 SaaS'],
    ],

    'founder' => [
        'name'      => '변종원',
        'nickname'  => '웅파',
        'role'      => 'Advisor',
        'message'   => '직접 써보고 검증한 방식만 제안합니다.',
        'bio'       => [
            '《만들면서 배우는 CodeIgniter》 저자',
            'PHPFest · XCON · 마이크로소프트 개발자 행사 강연',
            'CodeIgniter 한국 사용자 포럼 17년 운영 (필명 ‘웅파’)',
            'CodeIgniter 유료 교육 다수 진행, 현재 AI 입문 교육 진행 중',
            '기획부터 서버 배포·운영까지 1인 수행',
        ],
        'same_as'   => [], // TODO: 대표 확인 필요 — GitHub·블로그·CI Korea 프로필 URL
    ],

    /* AI 개발환경 구축 컨설팅 — 상세 제안서는 기존 consulting.html */
    'consulting' => [
        'url'   => 'consulting.html',
        'steps' => [
            ['icon' => 'bi-search',         'title' => '진단', 'desc' => '현재 개발 프로세스와 스택을 점검하고 AI 도입 우선순위를 정리합니다.'],
            ['icon' => 'bi-gear-wide-connected', 'title' => '구축', 'desc' => 'Claude Code 환경, CLAUDE.md, 프로젝트 규칙, CI/CD(GitHub Actions) 연동을 구성합니다.'],
            ['icon' => 'bi-people',         'title' => '정착', 'desc' => '팀 교육과 운영 가이드 문서화로 구축한 환경이 팀에 자리 잡게 합니다.'],
        ],
    ],

    'voucher' => [
        'title' => '정부 지원사업을 활용한 도입 상담',
        'text'  => 'AI바우처·혁신바우처 등 정부 지원사업을 활용한 도입 상담이 가능합니다.', // 공급기업 등록 전까지 '상담 가능' 표현만 사용
    ],

    /* 문의 유형 — 키가 DB 저장값이며 contact.php 가 이 키를 검증 목록으로 쓴다 */
    'inquiry_types' => [
        'consulting' => 'AI 개발환경 컨설팅',
        'product'    => '제품 도입',
        // 'voucher'    => '바우처 도입', // 비노출 — 공급기업 등록 후 주석 해제
        'etc'        => '기타',
    ],

    /* 푸터 회사 정보 */
    'company' => [
        'name'     => 'AIvance', // TODO: 대표 확인 필요 — 상호(법인명)
        'ceo'      => '변종원',
        'biz_no'   => '000-00-00000', // TODO: 대표 확인 필요 — 사업자등록번호
        'address'  => '주소 확인 필요', // TODO: 대표 확인 필요
        'email'    => 'advisor@aivance.kr',
        'phone'    => '010-5040-0011',
        'phone_e164' => '+821050400011',
    ],
];
