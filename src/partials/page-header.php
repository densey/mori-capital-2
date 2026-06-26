<?php
/**
 * Page header (breadcrumb banner) used by sub-pages.
 * Expects: $page['title'], $page['breadcrumb'] (array of ['label','url'])
 */
use function Mori\e;
use function Mori\asset;
use function Mori\t;

$crumb = $page['breadcrumb'] ?? [];
$bg = $page['header_bg'] ?? asset('images/page-header-bg.jpg');
// Visible H1 falls back to title minus the " — Mori Capital" / " · Mori Capital"
// suffix that we keep in the browser <title> for SEO.
$rawTitle = (string)($page['title'] ?? '');
$heading  = $page['heading'] ?? preg_replace('/\s+[—·]\s+Mori\s+Capital(\s+Management)?\s*$/u', '', $rawTitle);
?>
<div class="page-header" style="background: linear-gradient(135deg, rgba(8,18,33,.82) 0%, rgba(27,58,92,.66) 100%), url('<?= e($bg) ?>') center/cover no-repeat;">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="page-header-box" style="text-align:center;color:#fff;">
                    <h1 style="color:#fff;font-size:clamp(28px,3.4vw,44px);margin-bottom:14px;letter-spacing:-0.01em;"><?= e($heading) ?></h1>
                    <?php if (!empty($crumb)): ?>
                    <nav aria-label="<?= e(t('aria.breadcrumb')) ?>">
                        <ol style="list-style:none;padding:0;margin:0;display:inline-flex;flex-wrap:wrap;justify-content:center;gap:6px;font-size:clamp(11px,2vw,13px);color:rgba(255,255,255,.78);">
                            <?php foreach ($crumb as $i => $c): ?>
                                <?php if ($i > 0): ?><li style="opacity:.6;">/</li><?php endif; ?>
                                <li>
                                    <?php if (!empty($c['url'])): ?>
                                        <a href="<?= e($c['url']) ?>" style="color:#1ABC9C;text-decoration:none;"><?= e($c['label']) ?></a>
                                    <?php else: ?>
                                        <span style="color:#fff;"><?= e($c['label']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
