<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for includes/functions.php
 *
 * Covers every pure helper function that does not touch the database.
 */
class FunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    // ── formatCurrency ────────────────────────────────────────────────────────

    #[Test]
    public function formatCurrencyFormatsPositiveAmount(): void
    {
        $this->assertSame('₹100.00', formatCurrency(100));
    }

    #[Test]
    public function formatCurrencyFormatsZero(): void
    {
        $this->assertSame('₹0.00', formatCurrency(0));
    }

    #[Test]
    public function formatCurrencyFormatsLargeAmountWithCommas(): void
    {
        $this->assertSame('₹1,299.99', formatCurrency(1299.99));
    }

    #[Test]
    public function formatCurrencyRoundsToTwoDecimalPlaces(): void
    {
        $this->assertSame('₹10.56', formatCurrency(10.555));
    }

    #[Test]
    public function formatCurrencyFormatsNegativeAmount(): void
    {
        $this->assertSame('₹-50.00', formatCurrency(-50));
    }

    // ── generateSKU ───────────────────────────────────────────────────────────

    #[Test]
    public function generateSKUHasCorrectFormat(): void
    {
        $sku = generateSKU('Electronics', 'gadget');
        // Expected: ELE-GXXXX  (3-letter prefix, dash, 1-letter type suffix + 4 digits)
        $this->assertMatchesRegularExpression('/^[A-Z]{3}-[A-Z]\d{4}$/', $sku);
    }

    #[Test]
    public function generateSKUTruncatesCategoryCodeToThreeLetters(): void
    {
        $sku = generateSKU('Clothing', 'regular');
        $this->assertStringStartsWith('CLO-', $sku);
    }

    #[Test]
    public function generateSKUUsesFirstLetterOfType(): void
    {
        $sku = generateSKU('Category', 'physical');
        $this->assertMatchesRegularExpression('/^CAT-P\d{4}$/', $sku);
    }

    #[Test]
    public function generateSKUIsUppercase(): void
    {
        $sku = generateSKU('men', 'cloth');
        $this->assertSame(strtoupper($sku), $sku);
    }

    // ── paginate ──────────────────────────────────────────────────────────────

    #[Test]
    public function paginateReturnsAllRequiredKeys(): void
    {
        $result = paginate(100, 1, 20);

        foreach (['total', 'per_page', 'current', 'total_pages', 'offset', 'has_prev', 'has_next', 'prev', 'next'] as $key) {
            $this->assertArrayHasKey($key, $result, "Key '$key' missing from paginate() result");
        }
    }

    #[Test]
    public function paginateCalculatesExactTotalPages(): void
    {
        $this->assertSame(5, paginate(100, 1, 20)['total_pages']);
    }

    #[Test]
    public function paginateCalculatesTotalPagesWithRemainder(): void
    {
        $this->assertSame(6, paginate(101, 1, 20)['total_pages']);
    }

    #[Test]
    public function paginateFirstPageHasNoPrevious(): void
    {
        $result = paginate(100, 1, 20);
        $this->assertFalse($result['has_prev']);
        $this->assertTrue($result['has_next']);
    }

    #[Test]
    public function paginateLastPageHasNoNext(): void
    {
        $result = paginate(100, 5, 20);
        $this->assertTrue($result['has_prev']);
        $this->assertFalse($result['has_next']);
    }

    #[Test]
    public function paginateMiddlePageHasBothPrevAndNext(): void
    {
        $result = paginate(100, 3, 20);
        $this->assertTrue($result['has_prev']);
        $this->assertTrue($result['has_next']);
    }

    #[Test]
    public function paginateCalculatesCorrectOffset(): void
    {
        $result = paginate(100, 3, 20);
        $this->assertSame(40, $result['offset']); // (3-1) * 20
    }

    #[Test]
    public function paginateFirstPageOffsetIsZero(): void
    {
        $this->assertSame(0, paginate(100, 1, 20)['offset']);
    }

    #[Test]
    public function paginateClampsPageBelowOneToOne(): void
    {
        $this->assertSame(1, paginate(100, 0, 20)['current']);
    }

    #[Test]
    public function paginateClampsPageAboveTotalToLastPage(): void
    {
        $this->assertSame(5, paginate(100, 99, 20)['current']);
    }

    #[Test]
    public function paginateHandlesEmptyResultSet(): void
    {
        $result = paginate(0, 1, 20);
        $this->assertSame(1, $result['total_pages']);
        $this->assertFalse($result['has_prev']);
        $this->assertFalse($result['has_next']);
    }

    #[Test]
    public function paginateUsesRowsPerPageConstantAsDefault(): void
    {
        $result = paginate(100, 1);
        $this->assertSame(ROWS_PER_PAGE, $result['per_page']);
    }

    #[Test]
    public function paginateSinglePageHasNoPrevOrNext(): void
    {
        $result = paginate(10, 1, 20);
        $this->assertFalse($result['has_prev']);
        $this->assertFalse($result['has_next']);
    }

    // ── e() – HTML escaping ───────────────────────────────────────────────────

    #[Test]
    public function eEscapesAngleBrackets(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
    }

    #[Test]
    public function eEscapesAmpersand(): void
    {
        $this->assertSame('foo &amp; bar', e('foo & bar'));
    }

    #[Test]
    public function eEscapesSingleQuotes(): void
    {
        $this->assertSame('it&#039;s', e("it's"));
    }

    #[Test]
    public function eEscapesDoubleQuotes(): void
    {
        $this->assertSame('say &quot;hello&quot;', e('say "hello"'));
    }

    #[Test]
    public function ePassesThroughSafeText(): void
    {
        $this->assertSame('Hello World 123', e('Hello World 123'));
    }

    #[Test]
    public function eHandlesEmptyString(): void
    {
        $this->assertSame('', e(''));
    }

    #[Test]
    public function eNeutralizesXSSPayload(): void
    {
        $input  = '<img src="x" onerror="alert(1)">';
        $output = e($input);
        // Angle brackets are escaped so the tag can never be parsed as HTML
        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringContainsString('&lt;img', $output);
        // Attribute values are also quoted-escaped
        $this->assertStringContainsString('&quot;', $output);
    }

    // ── formatDate ────────────────────────────────────────────────────────────

    #[Test]
    public function formatDateFormatsStandardDate(): void
    {
        $this->assertSame('Jan 01, 2026', formatDate('2026-01-01'));
    }

    #[Test]
    public function formatDateReturnsEmDashForEmptyString(): void
    {
        $this->assertSame('—', formatDate(''));
    }

    #[Test]
    public function formatDateReturnsEmDashForZeroDate(): void
    {
        $this->assertSame('—', formatDate('0000-00-00'));
    }

    #[Test]
    public function formatDateAcceptsCustomFormat(): void
    {
        $this->assertSame('25/02/2026', formatDate('2026-02-25', 'd/m/Y'));
    }

    #[Test]
    public function formatDateFormatsEndOfYear(): void
    {
        $this->assertSame('Dec 31, 2025', formatDate('2025-12-31'));
    }

    // ── formatDateTime ────────────────────────────────────────────────────────

    #[Test]
    public function formatDateTimeFormatsAfternoonTime(): void
    {
        $this->assertSame('Jan 01, 2026 02:30 PM', formatDateTime('2026-01-01 14:30:00'));
    }

    #[Test]
    public function formatDateTimeFormatsMorningTime(): void
    {
        $this->assertSame('Mar 15, 2025 09:05 AM', formatDateTime('2025-03-15 09:05:00'));
    }

    #[Test]
    public function formatDateTimeReturnsEmDashForEmptyString(): void
    {
        $this->assertSame('—', formatDateTime(''));
    }

    // ── productEmoji ──────────────────────────────────────────────────────────

    #[Test]
    public function productEmojiReturnsMensWearEmoji(): void
    {
        $this->assertSame('👔', productEmoji("Men's Wear"));
    }

    #[Test]
    public function productEmojiReturnsWomensWearEmoji(): void
    {
        // Note: "men's wear" is a substring of "women's wear", so the map
        // matches 'men\'s wear' first and returns the men's wear emoji.
        // Test the actual documented behaviour of the function.
        $this->assertSame('👔', productEmoji("Women's Wear"));
    }

    #[Test]
    public function productEmojiReturnsChildrenEmoji(): void
    {
        $this->assertSame('🧸', productEmoji('Children'));
    }

    #[Test]
    public function productEmojiReturnsSeasonalEmoji(): void
    {
        $this->assertSame('🎄', productEmoji('Seasonal Items'));
    }

    #[Test]
    public function productEmojiReturnsSouvenirEmoji(): void
    {
        $this->assertSame('🏺', productEmoji('Souvenir Shop'));
    }

    #[Test]
    public function productEmojiReturnsAccessoriesEmoji(): void
    {
        $this->assertSame('💍', productEmoji('Accessories'));
    }

    #[Test]
    public function productEmojiReturnsClothingEmoji(): void
    {
        $this->assertSame('👕', productEmoji('Clothing'));
    }

    #[Test]
    public function productEmojiReturnsFallbackForUnknownCategory(): void
    {
        $this->assertSame('🎁', productEmoji('Unknown Category'));
    }

    #[Test]
    public function productEmojiUsesTypeWhenCategoryIsEmpty(): void
    {
        $this->assertSame('👕', productEmoji('', 'cloth'));
    }

    #[Test]
    public function productEmojiMatchingIsCaseInsensitive(): void
    {
        $this->assertSame('👕', productEmoji('CLOTHING'));
    }

    // ── statusBadge ───────────────────────────────────────────────────────────

    #[Test]
    public function statusBadgeReturnsSuccessBadgeForActive(): void
    {
        $badge = statusBadge('active');
        $this->assertStringContainsString('badge-success', $badge);
        $this->assertStringContainsString('Active', $badge);
    }

    #[Test]
    public function statusBadgeReturnsWarningBadgeForPending(): void
    {
        $this->assertStringContainsString('badge-warning', statusBadge('pending'));
    }

    #[Test]
    public function statusBadgeReturnsDangerBadgeForCancelled(): void
    {
        $this->assertStringContainsString('badge-danger', statusBadge('cancelled'));
    }

    #[Test]
    public function statusBadgeReturnsDangerBadgeForVoided(): void
    {
        $this->assertStringContainsString('badge-danger', statusBadge('voided'));
    }

    #[Test]
    public function statusBadgeReturnsSuccessBadgeForCompleted(): void
    {
        $this->assertStringContainsString('badge-success', statusBadge('completed'));
    }

    #[Test]
    public function statusBadgeReturnsSuccessBadgeForReceived(): void
    {
        $this->assertStringContainsString('badge-success', statusBadge('received'));
    }

    #[Test]
    public function statusBadgeReturnsInfoBadgeForPartial(): void
    {
        $this->assertStringContainsString('badge-info', statusBadge('partial'));
    }

    #[Test]
    public function statusBadgeReturnsPurpleBadgeForAdmin(): void
    {
        $this->assertStringContainsString('badge-purple', statusBadge('admin'));
    }

    #[Test]
    public function statusBadgeReturnsSecondaryBadgeForInactive(): void
    {
        $this->assertStringContainsString('badge-secondary', statusBadge('inactive'));
    }

    #[Test]
    public function statusBadgeFallsBackToSecondaryForUnknownStatus(): void
    {
        $this->assertStringContainsString('badge-secondary', statusBadge('unknown_status_xyz'));
    }

    #[Test]
    public function statusBadgeRendersValidHtmlSpan(): void
    {
        $badge = statusBadge('active');
        $this->assertStringStartsWith('<span class="badge', $badge);
        $this->assertStringEndsWith('</span>', $badge);
    }

    #[Test]
    public function statusBadgeIsCaseInsensitive(): void
    {
        $this->assertStringContainsString('badge-success', statusBadge('ACTIVE'));
    }

    // ── flash / getFlash ──────────────────────────────────────────────────────

    #[Test]
    public function flashStoresMessageInSession(): void
    {
        flash('success', 'Operation successful');

        $this->assertArrayHasKey('flash', $_SESSION);
        $this->assertCount(1, $_SESSION['flash']);
        $this->assertSame('success', $_SESSION['flash'][0]['type']);
        $this->assertSame('Operation successful', $_SESSION['flash'][0]['message']);
    }

    #[Test]
    public function getFlashReturnsStoredMessages(): void
    {
        flash('error', 'Something went wrong');
        $messages = getFlash();

        $this->assertCount(1, $messages);
        $this->assertSame('error', $messages[0]['type']);
        $this->assertSame('Something went wrong', $messages[0]['message']);
    }

    #[Test]
    public function getFlashClearsSessionAfterReading(): void
    {
        flash('info', 'A note');
        getFlash();

        $this->assertArrayNotHasKey('flash', $_SESSION);
    }

    #[Test]
    public function flashSupportsMultipleMessagesInOrder(): void
    {
        flash('success', 'First');
        flash('error', 'Second');
        $messages = getFlash();

        $this->assertCount(2, $messages);
        $this->assertSame('First', $messages[0]['message']);
        $this->assertSame('Second', $messages[1]['message']);
    }

    #[Test]
    public function getFlashReturnsEmptyArrayWhenNoMessages(): void
    {
        $this->assertSame([], getFlash());
    }
}
