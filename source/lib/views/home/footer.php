<?php
/** @var array<string, mixed> $site */

declare(strict_types=1);

$c = $site['company'];
?>
  <footer class="site-footer">
    <div class="container footer-inner">
      <div class="footer-top">
        <div class="footer-brand-block">
          <div class="footer-brand">
            <svg width="22" height="22" viewBox="0 0 64 64" fill="none" aria-hidden="true">
              <path d="M14 46 L30 30 L44 40 L58 20" stroke="var(--brand)" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="14" cy="46" r="6" fill="var(--brand)"/>
              <circle cx="30" cy="30" r="6" fill="var(--brand)"/>
              <circle cx="44" cy="40" r="6" fill="var(--accent)"/>
              <circle cx="58" cy="20" r="6" fill="var(--accent)"/>
            </svg>
            <span><span style="color:var(--brand)">AI</span>vance</span>
          </div>
          <a class="h-privacy-link" href="privacy.html">개인정보처리방침</a>
        </div>
        <dl class="h-company">
          <div><dt>상호</dt><dd><?= esc($c['name']) ?></dd></div>
          <div><dt>대표자</dt><dd><?= esc($c['ceo']) ?></dd></div>
          <?php /* 확인 후 노출: <div><dt>사업자등록번호</dt><dd><?= esc($c['biz_no']) ?></dd></div> */ ?>
          <?php /* 확인 후 노출: <div><dt>주소</dt><dd><?= esc($c['address']) ?></dd></div> */ ?>
          <div><dt>이메일</dt><dd><a href="mailto:<?= esc($c['email']) ?>"><?= esc($c['email']) ?></a></dd></div>
        </dl>
      </div>
      <div class="footer-copy">© <?= date('Y') ?> AIvance. All rights reserved.</div>
    </div>
  </footer>
