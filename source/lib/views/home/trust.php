<?php
/** @var array<string, mixed> $site */

declare(strict_types=1);
?>
  <section id="trust" class="h-trust" aria-label="신뢰 지표">
    <div class="container">
      <ul class="h-trust-grid">
<?php foreach ($site['trust'] as $item): ?>
        <li><span class="stat-num"><?= esc($item['value']) ?></span><span class="stat-label"><?= esc($item['label']) ?></span></li>
<?php endforeach; ?>
      </ul>
    </div>
  </section>
