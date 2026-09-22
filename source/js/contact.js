/* AIvance 도입 문의 폼 — CSRF 토큰 발급 후 contact.php 로 비동기 제출 */
(function () {
  'use strict';

  var RECAPTCHA_SITE_KEY = '6Ld0rsEtAAAAALYH0-QOjqHa5m5hRCVnBN7lyrHq';

  var form = document.getElementById('contact-form');
  if (!form) return;

  var alertBox = document.getElementById('form-alert');
  var submitBtn = document.getElementById('contact-submit');
  var tokenInput = form.querySelector('input[name="csrf_token"]');
  var fields = ['name', 'email', 'phone', 'message'];
  var submitting = false;

  /* CSRF 토큰 확보 (세션 쿠키 확립).
   * 토큰은 1회용이라 서버가 검증 단계를 지나면 성공/실패와 무관하게 폐기한다.
   * 따라서 레이트리밋(429)처럼 검증 이후에 거절된 응답을 받은 뒤에도 토큰은 이미 죽어 있다 —
   * 제출이 끝날 때마다 다시 받아두지 않으면 재시도가 전부 400 으로 떨어진다. */
  function loadToken() {
    fetch('contact.php?action=token', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.token) tokenInput.value = d.token; })
      .catch(function () { /* 제출 시 서버가 400 으로 안내 */ });
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

  function submitWithToken(recaptchaToken) {
    var payload = {
      csrf_token: tokenInput.value,
      company_url: form.querySelector('input[name="company_url"]').value, // 허니팟
      name: form.name.value,
      email: form.email.value,
      phone: form.phone.value,
      product: form.product.value,
      message: form.message.value,
      'g-recaptcha-response': recaptchaToken
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
        loadToken(); // 성공이든 실패든 토큰은 소모됐다 — 재시도할 수 있게 다시 받아둔다
        submitting = false;
        submitBtn.disabled = false;
        submitBtn.textContent = submitBtn.dataset.label;
      });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (submitting) return;
    clearErrors();
    alertBox.className = 'form-alert';

    if (typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
      showAlert('error', '보안 모듈을 불러오지 못했습니다. 새로고침 후 다시 시도해 주세요.');
      return;
    }

    submitting = true;
    submitBtn.disabled = true;
    submitBtn.dataset.label = submitBtn.dataset.label || submitBtn.textContent;
    submitBtn.textContent = '전송 중…';

    var timedOut = false;
    var timeoutId = setTimeout(function () {
      timedOut = true;
      submitting = false;
      submitBtn.disabled = false;
      submitBtn.textContent = submitBtn.dataset.label;
      showAlert('error', '보안 확인 응답이 지연되고 있습니다. 잠시 후 다시 시도해 주세요.');
    }, 8000);

    grecaptcha.ready(function () {
      grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'contact_submit' })
        .then(function (token) {
          if (timedOut) return;
          clearTimeout(timeoutId);
          submitWithToken(token);
        })
        .catch(function () {
          if (timedOut) return;
          clearTimeout(timeoutId);
          submitting = false;
          submitBtn.disabled = false;
          submitBtn.textContent = submitBtn.dataset.label;
          showAlert('error', '보안 확인에 실패했습니다. 잠시 후 다시 시도해 주세요.');
        });
    });
  });
})();
