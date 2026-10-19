<?php
/** @var list<array<string, mixed>> $blog */

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
<?php foreach ($blog as $post): ?>
        <article class="card h-post">
          <time datetime="<?= esc($post['date']) ?>"><?= esc($post['date']) ?></time>
          <h3><?php if ($post['url'] !== null): ?><a href="<?= esc($post['url']) ?>"><?= esc($post['title']) ?></a><?php else: ?><?= esc($post['title']) ?><?php endif; ?></h3>
          <p><?= esc($post['summary']) ?></p>
        </article>
<?php endforeach; ?>
      </div>
    </div>
  </section>
