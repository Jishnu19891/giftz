<?php
/**
 * Visitor Tracker
 * Call trackVisit('catalog'|'product'|'home', $productId?) once per public page.
 */

function detectDevice(string $ua): string {
    $ua = strtolower($ua);
    // Common bot signatures
    $bots = ['bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit', 'ia_archiver'];
    foreach ($bots as $b) {
        if (str_contains($ua, $b)) return 'bot';
    }
    if (preg_match('/tablet|ipad|playbook|silk/i', $ua))        return 'tablet';
    if (preg_match('/mobile|android|iphone|ipod|opera mini|iemobile|wpdesktop/i', $ua)) return 'mobile';
    return 'desktop';
}

function trackVisit(string $page, ?int $pageId = null): void {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device = detectDevice($ua);

        // Don't log bots
        if ($device === 'bot') return;

        $sessionHash = hash('sha256', session_id());
        $ipHash      = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $referrer    = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500) ?: null;

        $pdo = db();

        // Deduplicate: one row per session+page within the same hour
        $dup = $pdo->prepare(
            "SELECT 1 FROM visitor_logs
             WHERE session_hash = ? AND page = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
             LIMIT 1"
        );
        $dup->execute([$sessionHash, $page]);
        if ($dup->fetchColumn()) return;

        $pdo->prepare(
            "INSERT INTO visitor_logs (session_hash, ip_hash, page, page_id, referrer, device, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        )->execute([$sessionHash, $ipHash, $page, $pageId, $referrer, $device]);

    } catch (Throwable $e) {
        // Silently fail — never break the public-facing page
    }
}
