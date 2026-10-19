<?php
/** @var array<string, mixed> $site */

declare(strict_types=1);

$c = $site['consulting'];
?>
  <section id="consulting" class="h-band" aria-labelledby="consulting-title">
    <div class="container section">
      <div class="section-center">
        <span class="section-eyebrow">Consulting</span>
        <h2 id="consulting-title" class="section-title">AI 개발환경 구축 컨설팅</h2>
        <p class="section-sub">진단부터 정착까지, 직접 쓰는 방식 그대로 팀에 이식합니다.</p>
      </div>
      <div class="grid-3">
<?php foreach ($c['steps'] as $i => $step): ?>
        <article class="card h-step">
          <div class="h-step-head"><span class="h-step-no"><?= $i + 1 ?></span><i class="bi <?= esc($step['icon']) ?>" aria-hidden="true"></i></div>
          <h3><?= esc($step['title']) ?></h3>
          <p><?= esc($step['desc']) ?></p>
        </article>
<?php endforeach; ?>
      </div>
      <div class="h-center-action">
        <a href="<?= esc($c['url']) ?>" class="btn btn-outline btn-lg">컨설팅 제안서 보기 <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
      </div>
    </div>
  </section>
