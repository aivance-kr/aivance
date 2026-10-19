<?php
/** @var list<array{q: string, a: string}> $faq */

declare(strict_types=1);
?>
  <section id="faq" class="faq" aria-labelledby="faq-title">
    <div class="container section">
      <div class="section-center">
        <h2 id="faq-title" class="section-title">자주 묻는 질문</h2>
      </div>
      <div class="faq-list">
<?php foreach ($faq as $item): ?>
        <details class="faq-item">
          <summary><?= esc($item['q']) ?></summary>
          <div class="faq-answer"><?= esc($item['a']) ?></div>
        </details>
<?php endforeach; ?>
      </div>
    </div>
  </section>
