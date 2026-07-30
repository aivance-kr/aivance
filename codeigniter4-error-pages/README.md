# CodeIgniter4 에러 페이지 — AIvance 브랜드 템플릿

AIvance 브랜드(로고·컬러·타이포)에 맞춘 CodeIgniter4용 에러 뷰 5종(400/403/404/500/503)입니다.
CI4로 만드는 백엔드 프로젝트에 그대로 복사해 넣으면 됩니다.

## 설치

1. 이 폴더의 `app/Views/errors/html/` 전체를 대상 CI4 프로젝트의 같은 경로에 복사합니다.
   (기존 `error_404.php`가 있다면 덮어씁니다.)
2. `app/Config/Exceptions.php`의 `$views` 배열에 400/403/500/503을 매핑합니다.
   CI4는 기본적으로 404만 전용 뷰로 연결하고 나머지는 공용 `production` 뷰를 쓰기 때문입니다.

   ```php
   public array $views = [
       400          => 'errors/html/error_400',
       403          => 'errors/html/error_403',
       404          => 'errors/html/error_404',
       500          => 'errors/html/error_500',
       503          => 'errors/html/error_503',
       'production'  => 'errors/html/production',
       'development' => 'errors/html/error_exception',
   ];
   ```

3. 확인: 운영 환경(`CI_ENVIRONMENT=production`)에서 존재하지 않는 라우트로 접속해 404 페이지가 뜨는지 확인합니다.
   403/500/503은 각각 `abort(403)`, 강제 예외 발생, `abort(503)` 등으로 로컬에서 재현해 확인하세요.

## 구조

```
app/Views/errors/html/
  _partials/
    style.php   # 공통 CSS(색상·폰트·버튼 등) — 네이티브 PHP include로 모든 페이지가 공유
    page.php    # 공통 HTML 골격 — $aivCode/$aivHeading/$aivMessage 를 렌더링
  error_400.php  # 각 파일은 기본 제목·문구를 정의하고 page.php 를 include
  error_403.php
  error_404.php
  error_500.php
  error_503.php
```

`_partials/*.php`는 CI4의 뷰 렌더러를 거치지 않고 PHP 네이티브 `include`로 불러오므로,
CI4 버전이나 뷰 캐시 설정과 무관하게 항상 동작합니다.

## 커스터마이징

- **문구 변경**: 각 `error_XXX.php` 상단의 `$aivHeading`/`$aivMessage` 기본값만 수정하면 됩니다.
  `$message`(CI4가 예외에서 넘겨주는 값)가 있으면 그 값을 우선 사용하고, 없으면 기본값을 씁니다.
- **디자인 변경**: `_partials/style.php` 하나만 고치면 5개 페이지에 모두 반영됩니다.
- **로고/컬러**: `_partials/page.php`의 인라인 SVG와 `style.php`의 `--brand`/`--accent`가
  AIvance 홈페이지(`source/index.html`, `source/css/styles.css`)와 동일한 값입니다.
  프로젝트별 브랜드가 다르면 두 곳만 바꾸면 됩니다.

## 참고

- 개발 모드 전용 상세 예외 페이지(`error_exception.php`, 스택트레이스 노출용)는 포함하지 않았습니다.
  운영 환경에서 사용자에게 보이는 페이지만 다룹니다.
- 출력은 모두 `esc()`로 이스케이프되어 있어 `$message`에 예상치 못한 값이 들어와도 XSS 위험이 없습니다.
