# AIvance 홈페이지 — 배포 가이드

AIvance 모(母) 브랜드 원페이지 홈페이지입니다. 정적 페이지(HTML/CSS/JS)에 **도입 문의 폼**(PHP + SQLite + Slack 알림)과 **문의 관리자 페이지**가 결합된 구조로, 웹 서버 문서 루트(document root)를 **`source/`** 로 지정해 배포합니다.

- 홈페이지: 히어로 · 3제품(AICura/AICopia/AICreo) · 철학 · 기술 · CTA · 문의 폼 · 푸터
- 제품 샘플 디자인: `source/products/aicura.html` · `aicopia.html` · `aicreo.html`
- 회사소개서 PDF 다운로드: `source/downloads/AIvance_회사소개서.pdf`
- 문의 폼 → **SQLite 저장 + Slack 알림** (`source/contact.php`)
- 문의 관리자 페이지 → 목록·상세 조회 및 문의자에게 답장 발송 (`source/admin.php`)

> **문의 엔드포인트는 메일을 발송하지 않습니다.** 폼이 외부 주소로 메일을 내보내는 경로가 남아 있는 한
> 스팸 릴레이로 악용될 수 있어, 접수는 저장 + Slack 알림까지만 하고 실제 메일 발송은 로그인이 필요한
> 관리자 페이지에서 사람이 직접 합니다.

---

## 1. 요구 사항

| 항목 | 버전/조건 |
|------|-----------|
| PHP | **8.1 이상** (세션·`filter_var`·`hash_equals` 사용) |
| Composer | 2.x (의존성 설치) |
| PHP 확장 | `pdo_sqlite`, `curl`, `mbstring`, `session` |
| 웹 서버 | Nginx 또는 Apache + PHP-FPM (프로덕션) / `php -S` (로컬) |
| 메일 | Brevo 계정 + API Key (발신 주소 인증 필요) |
| 알림 | Slack Incoming Webhook (선택 — 비워두면 알림만 건너뜀) |

의존성(`source/composer.json`): `vlucas/phpdotenv`

---

## 2. 디렉터리 구조

```
source/                     ← 웹 문서 루트 (docroot)
├── index.html              메인 페이지
├── contact.php             문의 폼 엔드포인트 (SQLite 저장 + Slack 알림)
├── admin.php               문의 관리자 페이지 (로그인 · 목록 · 답장 발송)
├── lib/                    공용 모듈 (bootstrap · db · mailer · slack) — 웹 접근 차단
├── tools/                  CLI 도구 (admin-hash.php) — 웹 접근 차단
├── writable/               SQLite DB · 레이트리밋 상태 · 로그 — 웹 접근 차단
├── css/                    styles.css · samples.css
├── js/                     contact.js (폼 제출)
├── products/               제품 샘플 디자인 3종
├── downloads/              회사소개서 PDF
├── composer.json / .lock   PHP 의존성 정의
├── vendor/                 Composer 설치물 (git 미추적 — 배포 시 생성)
├── .env                    실제 자격증명 (git 미추적 — 서버에서 직접 작성)
└── .env.example            .env 템플릿
```

> `.env` 와 `vendor/` 는 `source/.gitignore` 로 저장소에 포함되지 않습니다. 배포 서버에서 각각 생성합니다.

---

## 3. 로컬 실행

```bash
cd source

# 1) 의존성 설치
composer install

# 2) 환경변수 파일 생성 후 값 채우기
cp .env.example .env

# 3) 관리자 비밀번호 해시 생성 → 출력값을 .env 에 붙여넣기
php tools/admin-hash.php

# 4) 개발 서버 실행 (PHP 내장 서버)
php -S 127.0.0.1:4173 -t .
```

브라우저에서 <http://127.0.0.1:4173> 접속. 관리자 페이지는 <http://127.0.0.1:4173/admin.php>.

> SQLite DB(`writable/db/inquiries.sqlite`)와 스키마는 첫 요청 때 자동 생성됩니다 — 별도 마이그레이션 명령이 없습니다.
>
> ⚠️ PHP 내장 서버는 `.htaccess` 를 무시하므로 로컬에서는 `writable/`·`lib/` 가 그대로 노출됩니다. 로컬 개발 전용 한계이며 프로덕션(Apache/Nginx)에서는 아래 설정으로 차단됩니다.

> 이 저장소에는 편의를 위한 `.claude/launch.json`(`aivance-source`, `php -S 127.0.0.1:4173 -t source`)이 포함되어 있어 미리보기 도구로도 바로 구동됩니다.

---

## 4. 환경변수 (`source/.env`)

```dotenv
# 메일 발송 (Brevo API) — 관리자 답장 발송에만 사용
BREVO_API_KEY=xxxxxxxxxxxxxxxx
MAIL_FROM=noreply@aivance.kr            # Brevo Senders 에서 인증된 주소여야 함
MAIL_FROM_NAME="AIvance"
MAIL_TO=advisor@aivance.kr              # 답장 메일의 Reply-To

# CORS 허용 오리진 — 콤마 구분, 와일드카드(*) 금지
ALLOWED_ORIGIN=https://aivance.kr,https://www.aivance.kr

# Google reCAPTCHA v3 (Secret Key 만)
RECAPTCHA_SECRET_KEY=xxxxxxxxxxxxxxxx

# Slack Incoming Webhook — 비워두면 알림만 건너뛰고 저장은 정상 동작
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/…
ADMIN_BASE_URL=https://aivance.kr       # Slack 알림 버튼이 가리킬 주소

# 관리자 계정 (단일) — 해시는 `php tools/admin-hash.php` 로 생성
ADMIN_USER=admin
ADMIN_PASSWORD_HASH='$argon2id$v=19$m=65536,t=4,p=1$…'
```

> ⚠️ **`ADMIN_PASSWORD_HASH` 는 반드시 작은따옴표로 감쌉니다.** 해시에 들어있는 `$` 를 phpdotenv 가
> 변수 참조로 해석해 값이 조용히 깨집니다. 값에 공백이 있으면 큰따옴표로 감쌉니다(`MAIL_FROM_NAME` 처럼).

**Brevo 발신 주소 인증**: Brevo 대시보드 → Senders 에서 `MAIL_FROM` 주소를 인증해야 합니다.
인증하지 않으면 **API 는 2xx 를 반환해도 실제 발송은 조용히 거부**됩니다.

**Slack Webhook 발급**: Slack → Apps → Incoming Webhooks → Add to Slack → 채널 선택 후 URL 복사.

---

## 5. 프로덕션 배포

### 5-1. 서버에 코드 배치 + 의존성 설치

```bash
# 예: /var/www/aivance 에 배포
git clone <repo-url> /var/www/aivance
cd /var/www/aivance/source

composer install --no-dev --optimize-autoloader
cp .env.example .env      # 이후 .env 에 실제 값 입력
chmod 640 .env            # 소유자/그룹만 읽기

php tools/admin-hash.php  # 관리자 비밀번호 해시 생성 → .env 에 붙여넣기
```

> SQLite DB 는 `writable/db/` 아래에 첫 요청 때 자동 생성됩니다. 웹 서버 사용자가 `writable/` 에
> 쓸 수 있어야 하며(cPanel 처럼 PHP 가 계정 권한으로 도는 환경에서는 기본으로 충족),
> DB 파일은 `.gitignore` 대상이라 `git pull` 로 덮이지 않습니다.

문서 루트는 반드시 **`/var/www/aivance/source`** 로 지정합니다.

### 5-2. Nginx + PHP-FPM 예시

```nginx
server {
    listen 443 ssl http2;
    server_name aivance.kr www.aivance.kr;

    root /var/www/aivance/source;
    index index.html;

    # SSL 인증서 (Let's Encrypt 등)
    ssl_certificate     /etc/letsencrypt/live/aivance.kr/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/aivance.kr/privkey.pem;

    # 민감 파일 접근 차단 (.env, .gitignore, composer.*, vendor, 소스)
    location ~ /\.(?!well-known) { deny all; }
    location ~* /(composer\.(json|lock))$ { deny all; }
    location ^~ /vendor/ { deny all; }

    # ⚠️ Nginx 는 .htaccess 를 읽지 않으므로 여기서 반드시 직접 차단한다.
    #    writable/ 에는 문의 원본이 담긴 SQLite DB 가 있다.
    location ^~ /writable/ { deny all; }
    location ^~ /lib/      { deny all; }
    location ^~ /tools/    { deny all; }

    # PHP 처리 (contact.php)
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 정적 파일
    location / { try_files $uri $uri/ =404; }
}

# HTTP → HTTPS 리다이렉트
server {
    listen 80;
    server_name aivance.kr www.aivance.kr;
    return 301 https://$host$request_uri;
}
```

### 5-3. Apache 예시

`source/` 에 `.htaccess` 를 두어 민감 파일을 차단합니다(문서 루트가 `source/` 인 경우):

```apache
# source/.htaccess
<FilesMatch "^\.env|composer\.(json|lock)$">
    Require all denied
</FilesMatch>
RedirectMatch 403 ^/vendor/
```

`writable/`, `lib/`, `tools/` 는 각 디렉터리에 전부 거부하는 `.htaccess` 가 이미 커밋돼 있습니다
(`AllowOverride` 가 켜져 있어야 적용됩니다).

VirtualHost 의 `DocumentRoot` 를 `/var/www/aivance/source` 로 설정하고, PHP-FPM(mod_proxy_fcgi) 또는 mod_php 로 PHP를 처리합니다. HTTPS는 `mod_ssl` + Let's Encrypt 권장.

---

## 6. 보안 체크리스트

- [ ] `.env` 가 웹으로 노출되지 않는지 확인 → `https://도메인/.env` 접속 시 **403/404** 여야 함
- [ ] `vendor/`, `composer.json`, `composer.lock` 웹 접근 차단
- [ ] HTTPS 적용 (문의 폼은 개인정보를 전송)
- [ ] `ALLOWED_ORIGIN` 을 실제 도메인으로 고정 (`*` 지양)
- [ ] `.env` 파일 권한 `640` 이하
- [ ] **`writable/` 웹 접근 차단** → `https://도메인/writable/db/inquiries.sqlite` 가 **403/404** 여야 함 (문의 원본 DB)
- [ ] `lib/`, `tools/` 웹 접근 차단
- [ ] `ADMIN_PASSWORD_HASH` 가 작은따옴표로 감싸져 있고 로그인이 실제로 되는지 확인
- [ ] PHP 프로덕션 설정: `display_errors = Off` (자체적으로도 끄고 있으나 서버 차원에서도 비활성 권장)

**문의 폼 방어 스택**(`contact.php`, 위에서부터 순서대로): 허니팟 → 제출 속도 트랩 → Origin/Referer 강제 검증
→ User-Agent 차단 → 1회용 CSRF 토큰(30분 만료) → IP 레이트리밋(60초 1회·시간당 5회)
→ 전체 레이트리밋(3초 간격·시간당 30회) → reCAPTCHA v3(score 0.5) → 입력 검증
→ 일회용 이메일 도메인·내용 스팸 필터(버리지 않고 `status=spam` 으로 저장, Slack 알림은 생략).

**관리자 페이지 보안**(`admin.php`): 단일 계정 + `password_verify`, 세션 고정 방지(로그인 시 ID 재발급),
무활동 2시간·절대 12시간 만료, 전 POST CSRF 검증, 로그인 무차별 대입 레이트리밋(IP 당 시간당 20회),
답장 발송 상한(전체 시간당 100통), CSP `default-src 'none'`(JS 없음) + `X-Frame-Options: DENY` + `noindex`.
문의 삭제(단건·스팸 전체)는 되돌릴 수 없어 2단계 확인(POST 두 번)을 거치고, 삭제 직전 내용을
`writable/logs/admin-audit-*.log` 에 남긴다 — DB 에서 사라진 뒤에도 무엇을 언제 지웠는지 추적할 수 있게.

---

## 7. 배포 후 동작 확인

```bash
# CSRF 토큰 발급 (200 + JSON)
curl -s "https://aivance.kr/contact.php?action=token"

# 민감 파일 차단 확인 (403/404 여야 정상)
curl -s -o /dev/null -w "%{http_code}\n" "https://aivance.kr/.env"

# PDF 다운로드 확인 (200 · application/pdf)
curl -sI "https://aivance.kr/downloads/AIvance_회사소개서.pdf" | grep -i "HTTP\|content-type"
```

```bash
# 문의 DB 가 웹으로 노출되지 않는지 확인 (403/404 여야 정상)
curl -s -o /dev/null -w "%{http_code}\n" "https://aivance.kr/writable/db/inquiries.sqlite"
```

이후 실제 브라우저에서:

1. 문의 폼을 제출 → Slack 채널에 **새 도입 문의 #N** 알림이 도착하는지 확인
2. <https://aivance.kr/admin.php> 로그인 → 목록에 방금 접수한 문의가 보이는지 확인
3. 상세에서 답장을 발송해 문의자 주소로 메일이 실제 도착하는지 확인

> 발송 실패 사유는 `writable/logs/mail-failed-*.log`, Slack 실패는 `writable/logs/slack-failed-*.log`,
> 봇 차단 이벤트는 `writable/logs/spam-*.log` 에 기록됩니다
> (이 호스팅에서는 `error_log()` 가 어디로 가는지 알 수 없어 파일에 직접 씁니다).

---

## 8. 참고

- 디자인 시스템 원본·토큰·컴포넌트: `AIvance Design System/` (홈페이지는 이 토큰을 계승한 자체 완결형 스타일 `source/css/styles.css` 사용)
- 문의 담당자: 변종원 · 010-5040-0011 · advisor@aivance.kr
