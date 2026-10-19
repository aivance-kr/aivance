<?php
/** @var array<string, mixed> $site */

declare(strict_types=1);
?>
  <section id="voucher" class="container section h-voucher-wrap" aria-labelledby="voucher-title">
    <div class="h-voucher">
      <div>
        <span class="section-eyebrow">Voucher</span>
        <h2 id="voucher-title" class="section-title"><?= esc($site['voucher']['title']) ?></h2>
        <p class="section-sub"><?= esc($site['voucher']['text']) ?></p>
      </div>
      <a href="?inquiry=voucher#contact" class="btn btn-primary btn-lg" data-inquiry-type="voucher" data-ga="cta_consult_click" data-ga-label="voucher">바우처 도입 문의 <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
  </section>
