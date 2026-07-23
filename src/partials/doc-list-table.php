<?php
/**
 * Reusable document-list table.
 *
 * Expects in scope:
 *   $docs        — array of document rows (id, title, description, document_date, file_size, ...)
 *   $emptyKey    — translation key for the "no docs" message (e.g. 'doc.no_policies')
 *   $headerLabel — optional first-column header text (e.g. t('doc.policy')); pass null for no header row
 *
 * Use format_date() and the api/download.php endpoint just like the standalone pages did.
 */
use function Mori\e;
use function Mori\asset;
use function Mori\format_date;
use function Mori\t;
$docListIsDe = \Mori\I18n::locale() === 'de';
?>
<?php if (empty($docs)): ?>
<div style="background:#fff;border:1px dashed var(--mori-border,#E1E7EE);border-radius:10px;padding:48px;text-align:center;color:var(--mori-muted,#7A8B99);font-size:14px;">
    <?= e(t($emptyKey ?? 'doc.no_other')) ?>
</div>
<?php else: ?>
<div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <?php if (!empty($headerLabel)): ?>
        <thead>
            <tr style="background:var(--mori-bg-soft,#F5F7FA);">
                <th style="text-align:left;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;"><?= e($headerLabel) ?></th>
                <th style="text-align:right;padding:14px 18px;"></th>
            </tr>
        </thead>
        <?php endif; ?>
        <tbody>
            <?php foreach ($docs as $d):
                $docTitle = $docListIsDe && !empty($d['title_de']) ? $d['title_de'] : $d['title'];
            ?>
            <tr style="border-top:1px solid var(--mori-border,#E1E7EE);">
                <td style="padding:14px 18px;width:100%;">
                    <div style="display:flex;align-items:flex-start;gap:12px;">
                        <i class="fa-regular fa-file-pdf" style="color:var(--accent-color,#1ABC9C);font-size:18px;margin-top:2px;"></i>
                        <div style="flex:1;">
                            <div style="font-weight:600;color:var(--primary-color,#1B3A5C);"><?= e($docTitle) ?></div>
                            <?php if (!empty($d['description'])): ?>
                            <div style="font-size:12.5px;color:var(--mori-text-soft,#5A6B7B);margin-top:4px;line-height:1.55;"><?= e($d['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td style="padding:14px 18px;text-align:right;white-space:nowrap;">
                    <a href="<?= asset('api/download.php?id=' . (int)$d['id'] . '&view=1') ?>" target="_blank" rel="noopener noreferrer"
                       class="pdf-preview-link" style="margin-right:6px;"
                       data-pdf-preview data-pdf-title="<?= e($docTitle) ?>"
                       data-pdf-download="<?= asset('api/download.php?id=' . (int)$d['id']) ?>"
                       title="<?= e(t('doc.preview')) ?>" aria-label="<?= e(t('doc.preview')) ?>">
                        <i class="fa-regular fa-eye"></i> <span><?= e(t('doc.preview')) ?></span>
                    </a>
                    <a href="<?= asset('api/download.php?id=' . (int)$d['id']) ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:6px;background:var(--accent-color,#1ABC9C);color:#fff;padding:8px 14px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;">
                        <i class="fa-solid fa-download"></i> PDF
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
