<?php
/** @var array<string, mixed> $site */

declare(strict_types=1);

$f = $site['founder'];
?>
  <section id="founder" class="container section h-founder" aria-labelledby="founder-title">
    <div class="h-founder-card">
      <img class="h-founder-photo" src="assets/founder.webp" width="160" height="160" alt="<?= esc($f['name']) ?> (<?= esc($f['nickname']) ?>) 프로필 사진" loading="lazy">
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
