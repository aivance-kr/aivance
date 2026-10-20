<?php
/**
 * 메인 페이지 렌더링 도우미 — 설정 로드 · 이스케이프 · partial 렌더 · JSON-LD 조립.
 *
 * index.php 와 테스트가 함께 쓴다. 여기서는 출력하지 않고 문자열만 돌려준다.
 */

declare(strict_types=1);

/** HTML 이스케이프 (출력은 반드시 이 함수를 거친다). */
function esc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 설정 파일(lib/config/{name}.php) 로드.
 *
 * @return array<mixed>
 */
function home_config(string $name): array
{
    /** @var array<mixed> */
    return require __DIR__ . '/config/' . $name . '.php';
}

/**
 * partial 하나를 렌더링해 문자열로 돌려준다.
 *
 * @param array<string, mixed> $vars
 */
function home_partial(string $name, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/home/' . $name . '.php';
    return (string) ob_get_clean();
}

/**
 * 메인 페이지 전체 HTML.
 *
 * @param array<string, string> $query 쿼리스트링 (?inquiry= 로 문의 유형 사전 선택)
 */
function home_render(array $query = [], string $gaId = ''): string
{
    $site     = home_config('site');
    $products = home_config('products');
    $faq      = home_config('faq');
    $blog     = home_config('blog');

    /* 신뢰 지표의 {saas} 는 site.php 의 saas_count 로 채운다 */
    $site['trust'] = array_map(
        static fn (array $t): array => ['value' => str_replace('{saas}', (string) $site['saas_count'], $t['value']), 'label' => $t['label']],
        $site['trust']
    );

    $selectedType = (string) ($query['inquiry'] ?? '');
    if (!array_key_exists($selectedType, $site['inquiry_types'])) {
        $selectedType = '';
    }

    return home_partial('layout', [
        'site'         => $site,
        'products'     => $products,
        'faq'          => $faq,
        'blog'         => $blog,
        'gaId'         => $gaId,
        'selectedType' => $selectedType,
        'productCount' => (int) $site['saas_count'],
    ]);
}

/**
 * JSON-LD 그래프 (Organization · Person · ItemList/SoftwareApplication · FAQPage).
 *
 * @param array<string, mixed>             $site
 * @param list<array<string, mixed>>       $products
 * @param list<array{q: string, a: string}> $faq
 * @return list<array<string, mixed>> 각각 하나의 <script type="application/ld+json"> 이 된다
 */
function home_jsonld(array $site, array $products, array $faq): array
{
    $base = (string) $site['url'];
    $org  = $base . '/#organization';
    $per  = $base . '/#founder';
    $f    = $site['founder'];
    $c    = $site['company'];

    $public = array_values(array_filter($products, static fn (array $p): bool => $p['url'] !== null));

    $person = [
        '@type'         => 'Person',
        '@id'           => $per,
        'name'          => $f['name'],
        'alternateName' => $f['nickname'],
        'jobTitle'      => '개발자 · AIvance Advisor',
        'worksFor'      => ['@id' => $org],
    ];
    if ($f['same_as'] !== []) {
        $person['sameAs'] = $f['same_as'];
    }

    return [
        [
            '@context' => 'https://schema.org',
            '@graph'   => [
                [
                    '@type'        => 'Organization',
                    '@id'          => $org,
                    'name'         => $site['name'],
                    'url'          => $base . '/',
                    'logo'         => $base . '/assets/logo-mark.svg',
                    'description'  => $site['description'],
                    'email'        => $c['email'],
                    'telephone'    => $c['phone_e164'],
                    'founder'      => ['@id' => $per],
                    'areaServed'   => 'KR',
                    'contactPoint' => ['@type' => 'ContactPoint', 'contactType' => 'sales', 'email' => $c['email'], 'telephone' => $c['phone_e164'], 'availableLanguage' => ['ko']],
                ],
                $person,
            ],
        ],
        [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => 'AIvance 제품 포트폴리오',
            'itemListElement' => array_map(
                static fn (array $p, int $i): array => [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'item'     => [
                        '@type'               => 'SoftwareApplication',
                        'name'                => $p['name'],
                        'url'                 => $p['url'],
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem'     => 'Web',
                        'description'         => $p['tagline'] . ' — 대상: ' . $p['target'],
                        'publisher'           => ['@id' => $org],
                    ],
                ],
                $public,
                array_keys($public)
            ),
        ],
        [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(
                static fn (array $item): array => [
                    '@type'          => 'Question',
                    'name'           => $item['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
                ],
                $faq
            ),
        ],
    ];
}
