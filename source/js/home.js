/* AIvance 메인 — 모바일 메뉴 · 바우처 문의유형 사전선택 · GA4 이벤트 */
(function () {
  'use strict';

  var burger = document.getElementById('nav-burger');
  var links = document.getElementById('nav-links');
  if (burger && links) {
    var setOpen = function (open) {
      links.classList.toggle('open', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      burger.setAttribute('aria-label', open ? '메뉴 닫기' : '메뉴 열기');
    };
    burger.addEventListener('click', function () { setOpen(!links.classList.contains('open')); });
    links.addEventListener('click', function (e) { if (e.target.tagName === 'A') setOpen(false); });
  }

  /* 바우처 버튼 — 새로고침 없이 문의 유형만 맞추고 폼으로 이동 (JS 꺼진 환경은 ?inquiry= 링크가 처리) */
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('[data-inquiry-type]') : null;
    var select = document.getElementById('f-inquiry-type');
    if (!a || !select) return;
    e.preventDefault();
    select.value = a.getAttribute('data-inquiry-type');
    var contact = document.getElementById('contact');
    if (contact) contact.scrollIntoView({ behavior: 'smooth' });
  });

  /* GA4 — gtag 가 없으면(측정 ID 미설정) 아무것도 하지 않는다 */
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('[data-ga]') : null;
    if (!el || typeof window.gtag !== 'function') return;
    var label = el.getAttribute('data-ga-label') || '';
    var name = el.getAttribute('data-ga');
    var params = name === 'product_card_click' ? { product_name: label } : { location: label };
    window.gtag('event', name, params);
  });
})();
