<?php
/**
 * Fund detail body — included by fund-eastern-european.php and fund-ottoman.php.
 * Expects: $fund (array), $shareClasses (array), $documents (array)
 */
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\format_date;
use function Mori\format_bytes;
use function Mori\t;

if (!isset($fund) || !$fund) { echo '<div class="container" style="padding:80px 0;"><p>Fund not found.</p></div>'; return; }
?>

<!-- Fund overview -->
<div class="about-us" style="padding-top:80px;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-xl-6">
                <figure class="image-anime" style="border-radius:14px;overflow:hidden;">
                    <img src="<?= asset(e($fund['cover_image_path'] ?? 'assets/images/service/h6-service-1.webp')) ?>" alt="<?= e(I18n::fieldFor($fund, 'name')) ?>">
                </figure>
            </div>
            <div class="col-xl-6">
                <div class="section-title" style="padding-left:20px;">
                    <span class="section-sub-title wow fadeInUp"><?= e(t('nav.funds')) ?></span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e(I18n::fieldFor($fund, 'name')) ?></h2>
                    <p class="wow fadeInUp" data-wow-delay="0.2s"><?= e(I18n::fieldFor($fund, 'description')) ?></p>
                </div>
                <div class="wow fadeInUp" data-wow-delay="0.3s" style="padding-left:20px;">
                    <h3 style="font-size:15px;color:var(--primary-color,#1B3A5C);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:10px;">Investment objective</h3>
                    <p style="font-size:15px;line-height:1.7;color:var(--mori-text-soft,#5A6B7B);"><?= e(I18n::fieldFor($fund, 'objective')) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Key facts -->
<div class="our-services" style="background:var(--mori-bg-soft,#F5F7FA);padding:60px 0;">
    <div class="container">
        <div class="section-title" style="margin-bottom:24px;">
            <span class="section-sub-title">Key facts</span>
            <h2 style="font-size:clamp(22px,2.4vw,30px);">Fund overview at a glance</h2>
        </div>
        <div class="row">
            <div class="col-md-3 col-sm-6" style="margin-bottom:16px;">
                <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;padding:22px;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;">Launch date</div>
                    <div style="font-size:18px;color:var(--primary-color,#1B3A5C);font-weight:700;"><?= e(format_date($fund['launch_date'], 'M Y')) ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6" style="margin-bottom:16px;">
                <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;padding:22px;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;">Base currency</div>
                    <div style="font-size:18px;color:var(--primary-color,#1B3A5C);font-weight:700;"><?= e($fund['base_currency']) ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6" style="margin-bottom:16px;">
                <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;padding:22px;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;">Share classes</div>
                    <div style="font-size:18px;color:var(--primary-color,#1B3A5C);font-weight:700;"><?= count($shareClasses) ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6" style="margin-bottom:16px;">
                <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;padding:22px;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;">Benchmark</div>
                    <div style="font-size:14px;color:var(--primary-color,#1B3A5C);font-weight:700;"><?= e($fund['benchmark'] ?? '—') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Share classes table -->
<?php if (!empty($shareClasses)): ?>
<div class="our-services" style="padding:60px 0;">
    <div class="container">
        <div class="section-title" style="margin-bottom:20px;">
            <span class="section-sub-title">Share classes</span>
            <h2 style="font-size:clamp(22px,2.4vw,30px);">Available share classes</h2>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;overflow:hidden;">
                <thead>
                    <tr style="background:var(--mori-bg-soft,#F5F7FA);">
                        <th style="text-align:left;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;">Class</th>
                        <th style="text-align:left;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;">ISIN</th>
                        <th style="text-align:left;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;">Currency</th>
                        <th style="text-align:left;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;">Inception</th>
                        <th style="text-align:left;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shareClasses as $sc): ?>
                    <tr style="border-top:1px solid var(--mori-border,#E1E7EE);">
                        <td style="padding:14px 18px;font-weight:600;color:var(--primary-color,#1B3A5C);"><?= e($sc['name']) ?></td>
                        <td style="padding:14px 18px;font-family:monospace;color:var(--mori-text-soft,#5A6B7B);"><?= e($sc['isin'] ?? '—') ?></td>
                        <td style="padding:14px 18px;"><?= e($sc['currency']) ?></td>
                        <td style="padding:14px 18px;"><?= e(format_date($sc['inception_date'], 'M Y')) ?></td>
                        <td style="padding:14px 18px;">
                            <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;<?= $sc['status']==='active'?'background:rgba(26,188,156,.12);color:#16A085;':'background:rgba(122,139,153,.12);color:var(--mori-muted,#7A8B99);' ?>"><?= e(ucfirst($sc['status'])) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Documents -->
<?php if (!empty($documents)): ?>
<div class="our-services" style="background:var(--mori-bg-soft,#F5F7FA);padding:60px 0;">
    <div class="container">
        <div class="row section-row align-items-end">
            <div class="col-xl-6">
                <div class="section-title">
                    <span class="section-sub-title">Documents</span>
                    <h2 style="font-size:clamp(22px,2.4vw,30px);">Fund documentation</h2>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="section-btn">
                    <a class="btn-default" href="<?= asset('documents.php?fund=' . urlencode($fund['slug'])) ?>">All fund documents</a>
                </div>
            </div>
        </div>

        <div class="row" style="margin-top:24px;">
            <?php foreach (array_slice($documents, 0, 6) as $doc): ?>
            <div class="col-xl-4 col-md-6" style="margin-bottom:16px;">
                <a href="<?= asset('api/download.php?id=' . (int)$doc['id']) ?>" target="_blank" rel="noopener" style="display:block;background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;padding:20px;text-decoration:none;transition:all .25s ease;">
                    <div style="display:flex;align-items:flex-start;gap:14px;">
                        <div style="width:40px;height:40px;border-radius:8px;background:rgba(26,188,156,.12);color:var(--accent-color,#1ABC9C);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fa-regular fa-file-pdf"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.1em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:3px;"><?= e(strtoupper(str_replace('_',' ',$doc['document_type']))) ?></div>
                            <div style="font-size:14px;font-weight:600;color:var(--primary-color,#1B3A5C);line-height:1.4;margin-bottom:4px;"><?= e($doc['title']) ?></div>
                            <div style="font-size:11px;color:var(--mori-muted,#7A8B99);"><?= e(format_date($doc['document_date'])) ?> · <?= e(format_bytes((int) $doc['file_size'])) ?></div>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
