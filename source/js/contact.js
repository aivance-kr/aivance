/* AIvance 도입 문의 폼 — CSRF 토큰 발급 후 contact.php 로 비동기 제출 */
(function () {
  'use strict';

  var form = document.getElementById('contact-form');
  if (!form) return;

  var alertBox = document.getElementById('form-alert');
  var submitBtn = document.getElementById('contact-submit');
  var tokenInput = form.querySelector('input[name="csrf_token"]');
  var fields = ['name', 'email', 'phone', 'message'];

  /* 페이지 로드 시 CSRF 토큰 확보 (세션 쿠키 확립) */
  function loadToken() {
    fetch('contact.php?action=token', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.token) tokenInput.value = d.token; })
      .catch(function () { /* 제출 시 서버가 419로 안내 */ });
  }
  loadToken();

  function showAlert(type, msg) {
    alertBox.className = 'form-alert show ' + type;
    alertBox.innerHTML =
      '<i class="bi bi-' + (type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill') +
      '"></i><span>' + msg + '</span>';
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearErrors() {
    fields.forEach(function (f) {
      var el = document.getElementById('err-' + f);
      if (el) el.textContent = '';
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearErrors();
    alertBox.className = 'form-alert';

    submitBtn.disabled = true;
    submitBtn.dataset.label = submitBtn.textContent;
    submitBtn.textContent = '전송 중…';

    var turnstileInput = form.querySelector('input[name="cf-turnstile-response"]');

    var payload = {
      csrf_token: tokenInput.value,
      company_url: form.querySelector('input[name="company_url"]').value, // 허니팟
      name: form.name.value,
      email: form.email.value,
      phone: form.phone.value,
      product: form.product.value,
      message: form.message.value,
      'cf-turnstile-response': turnstileInput ? turnstileInput.value : ''
    };

    fetch('contact.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': tokenInput.value },
      body: JSON.stringify(payload)
    })
      .then(function (r) { return r.json().then(function (d) { return { status: r.status, body: d }; }); })
      .then(function (res) {
        var d = res.body || {};
        if (res.status === 200 && d.ok) {
          showAlert('success', d.message || '문의가 정상 접수되었습니다.');
          form.reset();
          loadToken(); // 새 토큰 확보
        } else {
          if (d.fields) {
            Object.keys(d.fields).forEach(function (f) {
              var el = document.getElementById('err-' + f);
              if (el) el.textContent = d.fields[f];
            });
          }
          showAlert('error', d.error || '전송 중 오류가 발생했습니다.');
        }
      })
      .catch(function () {
        showAlert('error', '네트워크 오류로 전송하지 못했습니다. 잠시 후 다시 시도해 주세요.');
      })
      .finally(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = submitBtn.dataset.label || '문의 보내기';
        if (window.turnstile) window.turnstile.reset(); // 토큰은 1회용이라 매 제출 후 새로 발급받아야 함
      });
  });
})();
