<?php
/** @var array<string, mixed> $site */

declare(strict_types=1);

$f = $site['founder'];
?>
  <section id="founder" class="container section h-founder" aria-labelledby="founder-title">
    <div class="h-founder-card">
      <div class="h-founder-photo" aria-hidden="true"><?= esc($f['initial']) ?></div><?php /* TODO: 대표 확인 필요 — 프로필 사진 (WebP, width/height 명시) */ ?>
      <div>
        <span class="section-eyebrow">Founder</span>
        <h2 id="founder-title" class="section-title"><?= esc($f['name']) ?> (<?= esc($f['nickname']) ?>) <small class="h-role"><?= esc($f['role']) ?></small></h2>
        <ul class="h-checklist">
<?php foreach ($f['bio'] as $line): ?>
          <li><i class="bi bi-check2-circle" aria-hidden="true"></i><?= esc($line) ?></li>
<?php endforeach; ?>
        </ul>
        <p class="h-quote">“<?= esc($f['message']) ?>”</p>
      </div>
    </div>
  </section>
