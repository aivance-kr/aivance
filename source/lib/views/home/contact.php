<?php
/**
 * 문의 섹션 — 제출은 js/contact.js 가 contact.php 로 보낸다 (CSRF · reCAPTCHA v3 · 허니팟).
 *
 * @var array<string, mixed>       $site
 * @var list<array<string, mixed>> $products
 * @var string                     $selectedType
 */

declare(strict_types=1);
?>
  <section id="contact" class="contact" aria-labelledby="contact-title">
    <div class="container section">
      <div class="contact-grid">
        <div class="contact-intro">
          <h2 id="contact-title">무료 상담 신청</h2>
          <p>현재 개발 환경과 고민을 남겨주시면 영업일 기준 1일 내에 <strong><?= esc($site['company']['email']) ?></strong> 담당자가 회신드립니다.</p>
          <div class="contact-point">
            <i class="bi bi-clock" aria-hidden="true"></i>
            <div><div class="contact-point-t">회신 시간</div><div class="contact-point-d">영업일 기준 1일 이내</div></div>
          </div>
          <div class="contact-point">
            <i class="bi bi-envelope" aria-hidden="true"></i>
            <div><div class="contact-point-t">직접 메일</div><div class="contact-point-d"><?= esc($site['company']['email']) ?></div></div>
          </div>
        </div>

        <div class="contact-card">
          <div id="form-alert" class="form-alert" role="status" aria-live="polite"></div>
          <form id="contact-form" novalidate>
            <input type="hidden" name="csrf_token" value="">
            <div class="form-hp" aria-hidden="true">
              <label>회사 URL<input type="text" name="company_url" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="form-row two">
              <div>
                <label class="form-label" for="f-name">이름<span class="req">*</span></label>
                <input class="form-control" id="f-name" name="name" type="text" maxlength="100" autocomplete="name" required>
                <span class="form-error" id="err-name"></span>
              </div>
              <div>
                <label class="form-label" for="f-company">회사명</label>
                <input class="form-control" id="f-company" name="company" type="text" maxlength="100" autocomplete="organization">
                <span class="form-error" id="err-company"></span>
              </div>
            </div>

            <div class="form-row two">
              <div>
                <label class="form-label" for="f-email">이메일<span class="req">*</span></label>
                <input class="form-control" id="f-email" name="email" type="email" maxlength="254" placeholder="you@company.com" autocomplete="email" required>
                <span class="form-error" id="err-email"></span>
              </div>
              <div>
                <label class="form-label" for="f-phone">연락처</label>
                <input class="form-control" id="f-phone" name="phone" type="tel" placeholder="010-0000-0000" autocomplete="tel">
                <span class="form-error" id="err-phone"></span>
              </div>
            </div>

            <div class="form-row two">
              <div>
                <label class="form-label" for="f-inquiry-type">문의 유형<span class="req">*</span></label>
                <select class="form-control" id="f-inquiry-type" name="inquiry_type" required>
<?php foreach ($site['inquiry_types'] as $value => $label): ?>
                  <option value="<?= esc((string) $value) ?>"<?= ($selectedType !== '' ? $selectedType : 'consulting') === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
<?php endforeach; ?>
                </select>
                <span class="form-error" id="err-inquiry_type"></span>
              </div>
              <div>
                <label class="form-label" for="f-product">관심 제품</label>
                <select class="form-control" id="f-product" name="product">
                  <option value="">선택 안 함</option>
<?php foreach ($products as $p): ?>
                  <option><?= esc($p['name']) ?></option>
<?php endforeach; ?>
                  <option>기타 / 미정</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <label class="form-label" for="f-message">문의 내용<span class="req">*</span></label>
              <textarea class="form-control" id="f-message" name="message" maxlength="2000" placeholder="현재 개발 환경, 도입하려는 서비스, 규모, 일정 등을 자유롭게 남겨주세요. (최대 2000자)" required></textarea>
              <span class="form-error" id="err-message"></span>
            </div>

            <div class="form-row">
              <label class="h-consent">
                <input type="checkbox" name="consent" value="1" required>
                <span><a href="privacy.html" target="_blank" rel="noopener">개인정보 수집·이용</a>에 동의합니다.<span class="req">*</span></span>
              </label>
              <span class="form-error" id="err-consent"></span>
            </div>

            <button type="submit" id="contact-submit" class="btn btn-primary btn-lg" style="width:100%">상담 신청 보내기</button>
            <p class="h-form-note">수집 항목: 이름·이메일·연락처·회사명·문의 내용 / 목적: 문의 응대. 보내주신 정보는 문의 응대 목적으로만 사용됩니다.</p>
          </form>
        </div>
      </div>
    </div>
  </section>
