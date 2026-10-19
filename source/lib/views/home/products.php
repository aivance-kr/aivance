<?php
/** @var list<array<string, mixed>> $products */

declare(strict_types=1);
?>
  <section id="products" class="container section" aria-labelledby="products-title">
    <div class="section-center">
      <span class="section-eyebrow">Products</span>
      <h2 id="products-title" class="section-title">직접 만들고 운영하는 제품</h2>
      <p class="section-sub">클로드코드로 만든 실서비스 SaaS 포트폴리오입니다.</p>
    </div>
    <div class="h-products">
<?php foreach ($products as $p): $live = $p['url'] !== null; $slug = (string) $p['slug']; ?>
      <article class="card h-product<?= $live ? ' interactive' : ' is-soon' ?>">
        <div class="prod-head">
          <div class="prod-icon" style="background:var(--<?= esc($slug) ?>-50);color:var(--<?= esc($slug) ?>-600)"><i class="bi <?= esc($p['icon']) ?>" aria-hidden="true"></i></div>
          <h3 class="prod-name"><span style="color:var(--<?= esc($slug) ?>)">AI</span><?= esc(substr((string) $p['name'], 2)) ?></h3>
<?php if (!$live): ?>
          <span class="h-soon">준비 중</span>
<?php endif; ?>
        </div>
        <p class="prod-desc"><?= esc($p['tagline']) ?></p>
        <p class="h-target"><i class="bi bi-person-check" aria-hidden="true"></i> <?= esc($p['target']) ?></p>
        <div class="prod-links">
          <a class="prod-view" style="color:var(--<?= esc($slug) ?>-600)" href="<?= esc($p['sample']) ?>">샘플 디자인 <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
<?php if ($live): ?>
          <a class="prod-home" href="<?= esc($p['url']) ?>" target="_blank" rel="noopener" data-ga="product_card_click" data-ga-label="<?= esc($p['name']) ?>"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> <?= esc(preg_replace('#^https://#', '', (string) $p['url'])) ?></a>
<?php else: ?>
          <span class="prod-home" aria-disabled="true">서비스 오픈 준비 중</span>
<?php endif; ?>
        </div>
      </article>
<?php endforeach; ?>
    </div>
  </section>
