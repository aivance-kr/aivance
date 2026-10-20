<?php
/** @var array{more_url: string, posts: list<array<string, mixed>>} $blog */

declare(strict_types=1);
?>
  <section id="blog" class="h-band" aria-labelledby="blog-title">
    <div class="container section">
      <div class="section-center">
        <span class="section-eyebrow">Dev Log</span>
        <h2 id="blog-title" class="section-title">개발 기록</h2>
        <p class="section-sub">AI와 함께 개발하며 배운 것을 기록합니다.</p>
      </div>
      <div class="grid-3">
<?php foreach ($blog['posts'] as $post): ?>
        <article class="card h-post">
          <time datetime="<?= esc($post['date']) ?>"><?= esc($post['date']) ?></time>
          <h3><?php if ($post['url'] !== null): ?><a href="<?= esc($post['url']) ?>" target="_blank" rel="noopener"><?= esc($post['title']) ?></a><?php else: ?><?= esc($post['title']) ?><?php endif; ?></h3>
          <p><?= esc($post['summary']) ?></p>
        </article>
<?php endforeach; ?>
      </div>
      <div class="h-center-action">
        <a href="<?= esc($blog['more_url']) ?>" class="btn btn-outline btn-lg" target="_blank" rel="noopener">블로그 전체 보기 <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
      </div>
    </div>
  </section>
