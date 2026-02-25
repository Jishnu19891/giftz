<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for includes/visitor_tracker.php
 *
 * Focuses on detectDevice(), which is a pure function with no side effects.
 */
class VisitorTrackerTest extends TestCase
{
    // ── Bot detection ─────────────────────────────────────────────────────────

    #[Test]
    public function detectDeviceIdentifiesGooglebot(): void
    {
        $ua = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        $this->assertSame('bot', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesBingbot(): void
    {
        $ua = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';
        $this->assertSame('bot', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesSpider(): void
    {
        $ua = 'AhrefsBot/7.0 (compatible; spider; +http://ahrefs.com/robot/)';
        $this->assertSame('bot', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesGenericCrawler(): void
    {
        $ua = 'Some generic crawl service v2.0';
        $this->assertSame('bot', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesFacebookExternalHit(): void
    {
        $ua = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
        $this->assertSame('bot', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesInternetArchiveBot(): void
    {
        $ua = 'ia_archiver (+http://www.alexa.com/site/help/webmasters; crawler@alexa.com)';
        $this->assertSame('bot', detectDevice($ua));
    }

    // ── Tablet detection ──────────────────────────────────────────────────────

    #[Test]
    public function detectDeviceIdentifiesIPad(): void
    {
        $ua = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15';
        $this->assertSame('tablet', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesAndroidTablet(): void
    {
        $ua = 'Mozilla/5.0 (Linux; Android 13; SM-T870 tablet) AppleWebKit/537.36';
        $this->assertSame('tablet', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesPlaybook(): void
    {
        $ua = 'Mozilla/5.0 (PlayBook; U; RIM Tablet OS 2.1.0)';
        $this->assertSame('tablet', detectDevice($ua));
    }

    // ── Mobile detection ──────────────────────────────────────────────────────

    #[Test]
    public function detectDeviceIdentifiesIPhone(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';
        $this->assertSame('mobile', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesAndroidPhone(): void
    {
        $ua = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Mobile Safari/537.36';
        $this->assertSame('mobile', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesIPod(): void
    {
        $ua = 'Mozilla/5.0 (iPod touch; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit Mobile/15E148';
        $this->assertSame('mobile', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesOperaMini(): void
    {
        $ua = 'Opera/9.80 (Android; Opera Mini/46.0.2254/191.303; U; en) Presto/2.12.407 Version/12.50';
        $this->assertSame('mobile', detectDevice($ua));
    }

    // ── Desktop detection ─────────────────────────────────────────────────────

    #[Test]
    public function detectDeviceIdentifiesWindowsChrome(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $this->assertSame('desktop', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesWindowsFirefox(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0';
        $this->assertSame('desktop', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceIdentifiesMacSafari(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
        $this->assertSame('desktop', detectDevice($ua));
    }

    #[Test]
    public function detectDeviceDefaultsToDesktopForEmptyUserAgent(): void
    {
        $this->assertSame('desktop', detectDevice(''));
    }

    // ── Edge cases ────────────────────────────────────────────────────────────

    #[Test]
    public function detectDeviceIsCaseInsensitiveForBotDetection(): void
    {
        // The UA contains uppercase; the function uses strtolower internally.
        $this->assertSame('bot', detectDevice('GOOGLEBOT/2.1'));
    }

    #[Test]
    public function detectDevicePrioritizesBotOverMobileSignals(): void
    {
        // facebookexternalhit contains "mobile" substring — bot should win
        $ua = 'facebookexternalhit/1.1 mobile';
        $this->assertSame('bot', detectDevice($ua));
    }

    #[Test]
    public function detectDevicePrioritizesTabletOverMobile(): void
    {
        // Some tablet UAs include "mobile" — tablet check runs first
        $ua = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';
        $this->assertSame('tablet', detectDevice($ua));
    }
}
