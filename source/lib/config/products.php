<?php
/**
 * 제품 포트폴리오.
 *
 * url 이 null 이면 "준비 중" 배지가 붙고 카드 링크가 비활성화된다.
 * JSON-LD 에는 url 이 있는(공개된) 제품만 나간다.
 * name 은 contact.php 의 관심 제품 허용 목록과 같아야 한다.
 */

declare(strict_types=1);

return [
    ['name' => 'AICura',    'slug' => 'cura',    'icon' => 'bi-heart-pulse',   'tagline' => '성형외과 대상 AI 광고 솔루션',        'target' => '성형외과·병원·광고대행사',     'url' => 'https://cura.aivance.kr',    'sample' => 'products/aicura.html',    'category' => '성형·의료 광고 솔루션'],
    ['name' => 'AICopia',   'slug' => 'copia',   'icon' => 'bi-bag-heart',     'tagline' => '쇼핑몰(이커머스) 솔루션',             'target' => '온라인 쇼핑몰 운영자',          'url' => 'https://copia.aivance.kr',   'sample' => 'products/aicopia.html',   'category' => 'AI 쇼핑몰 솔루션'],
    ['name' => 'AICreo',    'slug' => 'creo',    'icon' => 'bi-window-stack',  'tagline' => '기업 홈페이지 CMS',                  'target' => '홈페이지가 필요한 기업',        'url' => 'https://creo.aivance.kr',    'sample' => 'products/aicreo.html',    'category' => '기업 홈페이지·CMS 솔루션'],
    ['name' => 'AITessera', 'slug' => 'tessera', 'icon' => 'bi-shield-lock',   'tagline' => '토큰 기반 인증 API',                 'target' => '인증 API가 필요한 개발팀',      'url' => 'https://tessera.aivance.kr','sample' => 'products/aitessera.html', 'category' => 'JWT 인증·인가 API'],
    ['name' => 'AILicet',   'slug' => 'licet',   'icon' => 'bi-key',           'tagline' => '라이선스 관리 솔루션',               'target' => '소프트웨어를 판매하는 사업자',  'url' => 'https://licet.aivance.kr','sample' => 'products/ailicet.html',   'category' => '라이선스 발급·인증·관리'],
    ['name' => 'AICassa',   'slug' => 'cassa',   'icon' => 'bi-journal-text',  'tagline' => '간이사업자용 간편 장부 ERP',         'target' => '간이사업자·소규모 사업자',      'url' => 'https://cassa.aivance.kr','sample' => 'products/aicassa.html',   'category' => '간편장부 웹 ERP'],
    ['name' => 'AIPicto',   'slug' => 'picto',   'icon' => 'bi-book-half',     'tagline' => '그림책 구독·열람 플랫폼',            'target' => '그림책 구독 서비스 운영자',     'url' => 'https://picto.aivance.kr',   'sample' => 'products/aipicto.html',   'category' => '그림책 구독·열람 플랫폼'],
    ['name' => 'AIFid',     'slug' => 'fid',     'icon' => 'bi-chat-square-dots', 'tagline' => '실시간 고객 상담 솔루션',         'target' => '웹사이트로 상담받는 사업자',    'url' => 'https://fid.aivance.kr',     'sample' => 'products/aifid.html',     'category' => '실시간 고객 상담 솔루션'],
    ['name' => 'AILign',    'slug' => 'lign',    'icon' => 'bi-tools',         'tagline' => '목공·CNC 견적 솔루션',               'target' => '목공·CNC 공방',                  'url' => 'https://lign.aivance.kr',    'sample' => 'products/ailign.html',    'category' => '목공·CNC 견적 솔루션'],
    ['name' => 'AILego',    'slug' => 'lego',    'icon' => 'bi-camera-video',  'tagline' => 'AI 화상면접 SaaS',                   'target' => '수시채용 기업·병원·대량채용 기관', 'url' => 'https://lego.aivance.kr',  'sample' => null,                      'category' => 'AI 화상면접 솔루션'],
    ['name' => 'AICella',   'slug' => 'cella',   'icon' => 'bi-house-door',    'tagline' => '2D·3D 방 배치 플래너',               'target' => '가구 구매 전 배치를 확인하려는 사용자', 'url' => 'https://cella.aivance.kr', 'sample' => null,                      'category' => '방 배치 플래너'],
];
