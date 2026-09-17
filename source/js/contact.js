/* AIvance 도입 문의 폼 — CSRF 토큰 발급 후 contact.php 로 비동기 제출 */
(function () {
  'use strict';

  var form = document.getElementById('contact-form');
  if (!form) return;

  var alertBox = document.getElementById('form-alert');
  var submitBtn = document.getElementById('contact-submit');
  var tokenInput = form.querySelector('input[name="csrf_token"]');
  var fields = ['name', 'email', 'phone', 'message'];
  var submitting = false;

  /* 페이지 로드 시 CSRF 토큰 확보 (세션 쿠키 확립) */
  function loadToken() {
    fetch('contact.php?action=token', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.token) tokenInput.value = d.token; })
      .catch(function () { /* 제출 시 서버가 419로 안내 */ });
  }
  loadToken();

  /* Turnstile 위젯이 검증을 마치기 전엔 제출 버튼을 눌러도 토큰이 비어 서버에서 거부됨 —
   * 완료 전까지 버튼을 막는다. Turnstile api.js는 async라 로드/실행 순서를 보장할 수 없어
   * data-callback 이름 참조 대신 히든 인풋 값을 직접 폴링해서 동기화한다. */
  submitBtn.disabled = true;
  submitBtn.dataset.label = submitBtn.textContent;
  submitBtn.textContent = '보안 확인 중…';
  setInterval(function () {
    if (submitting) return;
    var t = form.querySelector('input[name="cf-turnstile-response"]');
    var ready = !!(t && t.value);
    submitBtn.disabled = !ready;
    submitBtn.textContent = ready ? submitBtn.dataset.label : '보안 확인 중…';
  }, 400);

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

    var turnstileInput = form.querySelector('input[name="cf-turnstile-response"]');
    if (!turnstileInput || !turnstileInput.value) {
      showAlert('error', '보안 확인이 아직 끝나지 않았습니다. 잠시 후 다시 시도해 주세요.');
      return;
    }

    submitting = true;
    submitBtn.disabled = true;
    submitBtn.textContent = '전송 중…';

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
        submitting = false;
        if (window.turnstile) window.turnstile.reset(); // 토큰은 1회용이라 매 제출 후 새로 발급받아야 함
        submitBtn.disabled = true; // 새 토큰이 나올 때까지 폴링이 다시 잠가둔다
        submitBtn.textContent = '보안 확인 중…';
      });
  });
})();
