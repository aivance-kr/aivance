# CodeIgniter4 에러 페이지 — AIvance 브랜드 템플릿

## 배경

`source/`는 CI4 프레임워크가 아닌 정적 PHP 사이트(`index.html` + `contact.php`)다. 다만 AIvance 제품군은 "검증된 CodeIgniter 4 백엔드"를 표방하므로, 실제 CI4 백엔드 프로젝트에 그대로 복사해 넣을 수 있는 AIvance 브랜드 에러 페이지 템플릿 패키지를 제작한다. `source/` 정적 사이트 자체는 변경하지 않는다.

## 산출물 위치

저장소 루트 `codeigniter4-error-pages/`에 CI4가 기대하는 실제 경로를 그대로 재현한다:

```
codeigniter4-error-pages/
  app/Views/errors/html/
    _partials/style.php   # 공통 CSS — 네이티브 PHP include로 공유
    error_400.php
    error_403.php
    error_404.php
    error_500.php
    error_503.php
  README.md                # 설치법 + Config/Exceptions.php $views 매핑 안내
```

## 범위

CI4 기본 에러 뷰 세트에 대응하는 5종: 400(잘못된 요청), 403(접근 권한 없음), 404(페이지 없음), 500(서버 오류), 503(점검 중).

CI4는 기본적으로 404만 전용 뷰(`errors/html/error_404`)로 매핑하고 나머지는 `production`/`development` 공용 뷰를 쓴다. README에 `Config/Exceptions.php`의 `$views` 배열에 400/403/500/503을 각 파일로 매핑하는 스니펫을 포함한다.

## 뷰 컨텍스트

CI4가 에러 뷰에 넘겨주는 `$code`(int)와 `$message`(string)를 사용한다. `$message`가 비어 있거나 프레임워크가 넘기지 않는 경우를 대비해 코드별 기본 문구를 폴백으로 둔다. 출력 시 `esc()`로 이스케이프한다.

## 디자인 (AIvance Design System 토큰 기반)

- 배경: 홈페이지 히어로와 동일한 `linear-gradient(165deg, #fff 0%, #eff5ff 55%, #e6e4fd 100%)`
- 로고: 홈페이지와 동일한 인라인 SVG 로고 마크(brand `#2563EB` + accent `#6D5EF6`), 클릭 시 `/`로 이동
- 타이포: 상태 코드는 `Space Grotesk` 그라디언트 텍스트로 크게, 제목/본문은 `Noto Sans KR`
- 문구: 코드별 한글 제목 + 설명 기본값
  - 400 — "잘못된 요청입니다" / "요청 형식이 올바르지 않습니다. 입력값을 확인한 뒤 다시 시도해 주세요."
  - 403 — "접근 권한이 없습니다" / "이 페이지에 접근할 수 있는 권한이 없습니다."
  - 404 — "페이지를 찾을 수 없습니다" / "주소를 다시 확인하시거나 홈으로 이동해 주세요."
  - 500 — "일시적인 오류가 발생했습니다" / "서버에서 문제가 발생했습니다. 잠시 후 다시 시도해 주세요."
  - 503 — "서비스 점검 중입니다" / "더 나은 서비스를 위해 점검 중입니다. 잠시 후 다시 이용해 주세요."
- CTA: "홈으로 가기"(primary, `href="/"`) + "이전 페이지로"(outline, `onclick="history.back()"` — JS 없이도 홈 버튼으로 대체 가능한 progressive enhancement)
- 반응형(모바일 1컬럼), 다크 모드 대응 없음(라이트 고정)
- 각 파일은 완전히 독립된 HTML 문서(CI4 레이아웃에 의존하지 않음) — CI4 기본 `production.php`와 동일한 관례

## 비범위

- `source/` 정적 사이트 변경 없음
- CI4 개발 모드 전용 `error_exception.php`(디버그 스택트레이스 뷰)는 대상 아님 — 운영 환경에서 사용자에게 노출되는 페이지만 다룬다
