<?php
/**
 * 메인 페이지 레이아웃 — 문서 head + 섹션 partial 조합.
 *
 * @var array<string, mixed>              $site
 * @var list<array<string, mixed>>        $products
 * @var list<array{q: string, a: string}> $faq
 * @var list<array<string, mixed>>        $blog
 * @var string                            $gaId
 * @var string                            $selectedType
 * @var int                               $productCount
 */

declare(strict_types=1);

$base  = (string) $site['url'];
$title = (string) $site['title'];
$desc  = (string) $site['description'];
$gaId  = preg_match('/^G-[A-Z0-9]{4,20}$/', $gaId) === 1 ? $gaId : '';
$shared = compact('site', 'products', 'faq', 'blog', 'selectedType', 'productCount');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($title) ?></title>
  <meta name="description" content="<?= esc($desc) ?>">
  <meta name="author" content="AIvance">
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
  <link rel="canonical" href="<?= esc($base) ?>/">
  <meta name="theme-color" content="#5B3DF0">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="AIvance">
  <meta property="og:locale" content="ko_KR">
  <meta property="og:url" content="<?= esc($base) ?>/">
  <meta property="og:title" content="<?= esc($title) ?>">
  <meta property="og:description" content="<?= esc($desc) ?>">
  <meta property="og:image" content="<?= esc((string) $site['og_image']) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= esc($title) ?>">
  <meta name="twitter:description" content="<?= esc($desc) ?>">
  <meta name="twitter:image" content="<?= esc((string) $site['og_image']) ?>">
  <link rel="icon" type="image/svg+xml" href="assets/logo-mark.svg">
  <link rel="apple-touch-icon" href="assets/logo-mark.svg">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/home.css?v=3">
<?php foreach (home_jsonld($site, $products, $faq) as $graph): ?>
  <script type="application/ld+json"><?= json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach; ?>
<?php if ($gaId !== ''): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= esc($gaId) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= esc($gaId) ?>');</script>
<?php endif; ?>
</head>
<body>
<?= home_partial('header', $shared) ?>
  <main>
<?php /* 바우처 섹션은 비노출 — 공급기업 등록 후 'voucher' 를 blog 와 faq 사이에 다시 넣는다 */ ?>
<?php foreach (['hero', 'trust', 'founder', 'consulting', 'products', 'blog', 'faq', 'contact'] as $section): ?>
<?= home_partial($section, $shared) ?>
<?php endforeach; ?>
  </main>
<?= home_partial('footer', $shared) ?>
  <script src="https://www.google.com/recaptcha/api.js?render=6Ld0rsEtAAAAALYH0-QOjqHa5m5hRCVnBN7lyrHq" defer></script>
  <script src="js/contact.js?v=5" defer></script>
  <script src="js/home.js?v=1" defer></script>
</body>
</html>
