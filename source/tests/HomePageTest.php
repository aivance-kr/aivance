<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/home.php';
require_once __DIR__ . '/../lib/db.php';

final class HomePageTest extends TestCase
{
    public function testHomeHasSingleH1AndAllSections(): void
    {
        $html = home_render();
        self::assertSame(1, substr_count($html, '<h1'));
        foreach (['top', 'trust', 'founder', 'consulting', 'products', 'blog', 'voucher', 'faq', 'contact'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $html);
        }
    }

    public function testJsonLdIsValidAndOnlyPublicProductsListed(): void
    {
        $html = home_render();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        self::assertCount(3, $m[1]);
        $graphs = array_map(static fn (string $j): array => json_decode($j, true, 512, JSON_THROW_ON_ERROR), $m[1]);

        $types = array_column($graphs[0]['@graph'], '@type');
        self::assertSame(['Organization', 'Person'], $types);
        self::assertSame('FAQPage', $graphs[2]['@type']);
        self::assertCount(count(home_config('faq')), $graphs[2]['mainEntity']);

        $live = array_filter(home_config('products'), static fn (array $p): bool => $p['url'] !== null);
        self::assertCount(count($live), $graphs[1]['itemListElement']);
    }

    public function testTrustSaasCountComesFromProducts(): void
    {
        self::assertStringContainsString(count(home_config('products')) . '종', home_render());
    }

    public function testVoucherQueryPreselectsInquiryType(): void
    {
        self::assertMatchesRegularExpression('#<option value="voucher" selected>#', home_render(['inquiry' => 'voucher']));
        self::assertMatchesRegularExpression('#<option value="consulting" selected>#', home_render(['inquiry' => 'bogus']));
    }

    public function testGaScriptOnlyWithValidId(): void
    {
        self::assertStringNotContainsString('googletagmanager', home_render());
        self::assertStringNotContainsString('googletagmanager', home_render([], '"><script>'));
        self::assertStringContainsString('googletagmanager', home_render([], 'G-ABC1234567'));
    }

    public function testSoonProductsHaveNoExternalLinkAndUserTextIsEscaped(): void
    {
        $html = home_render();
        self::assertStringNotContainsString('tessera.aivance.kr', $html);
        self::assertSame('&lt;b&gt;', esc('<b>'));
    }

    public function testMigrationAddsColumnsToLegacyTable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE inquiries (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL DEFAULT '', product TEXT NOT NULL DEFAULT '', message TEXT NOT NULL, ip TEXT NOT NULL DEFAULT '', user_agent TEXT NOT NULL DEFAULT '', referer TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'new', spam_reason TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL)");
        db_migrate($pdo);
        db_migrate($pdo); // 재실행해도 안전
        $cols = array_column($pdo->query('PRAGMA table_info(inquiries)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        self::assertContains('company', $cols);
        self::assertContains('inquiry_type', $cols);
    }
}
