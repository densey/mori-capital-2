<?php
/**
 * "Signatory of PRI" logo band. Included on the fund detail pages and the
 * FundHub (documents) page. Logo converted from the official PRI RGB
 * full-colour signatory artwork to SVG (with a PNG fallback).
 */
use function Mori\e;
use function Mori\asset;
use function Mori\t;
?>
<div class="pri-signatory">
    <div class="container">
        <a href="https://www.unpri.org/" target="_blank" rel="noopener noreferrer" aria-label="<?= e(t('pri.alt')) ?>">
            <img src="<?= asset('assets/images/logos/pri-signatory.svg') ?>"
                 onerror="this.onerror=null;this.src='<?= asset('assets/images/logos/pri-signatory.png') ?>';"
                 alt="<?= e(t('pri.alt')) ?>" class="pri-signatory__img" width="260" height="104" loading="lazy">
        </a>
    </div>
</div>
