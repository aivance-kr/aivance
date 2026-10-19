<?php
/** @var array<string, mixed> $site */

declare(strict_types=1);
?>
  <header class="site-header" id="site-header">
    <div class="container nav">
      <a href="#top" class="brand" aria-label="AIvance 홈">
        <svg width="26" height="26" viewBox="0 0 64 64" fill="none" aria-hidden="true">
          <path d="M14 46 L30 30 L44 40 L58 20" stroke="var(--brand)" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="14" cy="46" r="6" fill="var(--brand)"/>
          <circle cx="30" cy="30" r="6" fill="var(--brand)"/>
          <circle cx="44" cy="40" r="6" fill="var(--accent)"/>
          <circle cx="58" cy="20" r="6" fill="var(--accent)"/>
        </svg>
        <span><span class="ai">AI</span>vance</span>
      </a>
      <nav class="nav-links" id="nav-links" aria-label="주요 메뉴">
        <a href="#consulting">컨설팅</a>
        <a href="#products">제품</a>
        <a href="#blog">개발기록</a>
        <a href="#faq">FAQ</a>
        <a href="#contact">문의</a>
      </nav>
      <div class="nav-cta">
        <a href="#contact" class="btn btn-primary btn-sm" data-ga="cta_consult_click" data-ga-label="header">무료 상담 신청</a>
      </div>
      <button type="button" class="nav-burger" id="nav-burger" aria-label="메뉴 열기" aria-expanded="false" aria-controls="nav-links"><i class="bi bi-list" aria-hidden="true"></i></button>
    </div>
  </header>
