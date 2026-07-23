<?php
use Mori\Csrf;
use function Mori\e;
use function Mori\asset;
use function Mori\t;
?>

<!-- ===== Investor Gate Modal (first-visit disclaimer) ===== -->
<div class="mori-gate" id="moriGate" role="dialog" aria-modal="true" aria-labelledby="moriGateTitle">
    <div class="mori-gate__panel">
        <img class="mori-gate__logo" src="<?= asset('assets/images/mori-capital-logo-dark.fw.png') ?>" alt="Mori Capital Management">
        <p class="mori-gate__eyebrow"><?= e(t('gate.eyebrow')) ?></p>
        <h2 class="mori-gate__title" id="moriGateTitle"><?= e(t('gate.title')) ?></h2>
        <p class="mori-gate__intro"><?= e(t('gate.intro')) ?></p>

        <label class="mori-gate__check">
            <input type="checkbox" id="moriGateConfirm">
            <span><?= e(t('gate.confirm')) ?></span>
        </label>

        <div class="mori-gate__buttons">
            <button type="button" class="mori-gate__btn mori-gate__btn--secondary" data-gate-role="retail"><?= e(t('gate.btn_retail')) ?></button>
            <button type="button" class="mori-gate__btn mori-gate__btn--primary" data-gate-role="professional" disabled><?= e(t('gate.btn_pro')) ?></button>
        </div>

        <button type="button" class="mori-gate__decline" id="moriGateDecline"><?= e(t('gate.decline')) ?></button>

        <div class="mori-gate__msg-decline"><?= e(t('gate.decline_msg')) ?></div>
    </div>
</div>

<!-- ===== Cookie Consent Banner ===== -->
<div class="mori-cookie" id="moriCookie" role="region" aria-label="<?= e(t('cookie.consent_aria')) ?>">
    <div class="mori-cookie__icon"><i class="fa-solid fa-cookie-bite"></i></div>
    <div class="mori-cookie__text"><?= e(t('cookie.text')) ?></div>
    <div class="mori-cookie__actions">
        <button type="button" class="mori-cookie__btn mori-cookie__btn--decline" id="moriCookieDecline"><?= e(t('cookie.decline')) ?></button>
        <button type="button" class="mori-cookie__btn mori-cookie__btn--accept" id="moriCookieAccept"><?= e(t('cookie.accept')) ?></button>
    </div>
</div>

<!-- Theme libraries -->
<script src="<?= asset('js/jquery-3.7.1.min.js') ?>"></script>
<script src="<?= asset('js/bootstrap.min.js') ?>"></script>
<script src="<?= asset('js/validator.min.js') ?>"></script>
<script src="<?= asset('js/jquery.slicknav.js') ?>"></script>
<script src="<?= asset('js/swiper-bundle.min.js') ?>"></script>
<script src="<?= asset('js/jquery.waypoints.min.js') ?>"></script>
<script src="<?= asset('js/jquery.counterup.min.js') ?>"></script>
<script src="<?= asset('js/jquery.magnific-popup.min.js') ?>"></script>
<script src="<?= asset('js/SmoothScroll.js') ?>"></script>
<script src="<?= asset('js/parallaxie.js') ?>"></script>
<script src="<?= asset('js/gsap.min.js') ?>"></script>
<script src="<?= asset('js/magiccursor.js') ?>"></script>
<script src="<?= asset('js/SplitText.min.js') ?>"></script>
<script src="<?= asset('js/ScrollTrigger.min.js') ?>"></script>
<script src="<?= asset('js/jquery.mb.YTPlayer.min.js') ?>"></script>
<script src="<?= asset('js/wow.min.js') ?>"></script>
<script src="<?= asset('js/function.js') ?>"></script>

<!-- ===== Mobile menu ===== -->
<script>
(function($){
    var btn = $('#moriMenuToggle');
    var nav = $('.responsive-menu .slicknav_nav');
    if (!btn.length || !nav.length) return;

    var overlay = $('<div class="mori-mobile-nav"></div>');

    // Close (X) button, top-right
    var closeBtn = $('<button type="button" class="mori-mobile-close" aria-label="<?= e(t('header.menu_aria')) ?>">&times;</button>');
    overlay.append(closeBtn);

    nav.clone(true).appendTo(overlay);

    // "Home" lives only on mobile (it was removed from the desktop bar since
    // the logo already links home). Prepend it to the cloned menu.
    overlay.find('.slicknav_nav, ul').first().prepend(
        '<li><a href="<?= e(asset('/')) ?>"><?= e(t('nav.home')) ?></a></li>'
    );

    // Add language switcher to mobile nav (keep clean, extension-less path)
    var path = location.pathname.replace(/\.php$/, '');
    if (path === '' || path === '/index') path = '/';
    var qs = new URLSearchParams(location.search);
    qs.delete('lang');
    var base = path + (qs.toString() ? '?' + qs.toString() + '&' : '?');
    var curLang = document.documentElement.lang || 'en';
    overlay.append(
        '<div class="mori-nav-lang">' +
        '<a href="' + base + 'lang=en"' + (curLang==='en' ? ' class="active"' : '') + '>EN</a>' +
        '<a href="' + base + 'lang=de"' + (curLang==='de' ? ' class="active"' : '') + '>DE</a>' +
        '</div>'
    );

    $('body').append(overlay);

    var open = false;
    function toggle() {
        open = !open;
        btn.toggleClass('active', open);
        overlay.toggleClass('open', open);
        $('body').css('overflow', open ? 'hidden' : '');
    }
    btn.on('click', toggle);
    closeBtn.on('click', function(e) { e.preventDefault(); e.stopPropagation(); if (open) toggle(); });
    overlay.on('click', 'a:not(.mori-nav-lang a)', function() { if (open) toggle(); });
    overlay.on('click', function(e) { if (e.target === this) toggle(); });
})(jQuery);
</script>

<!-- ===== Gate + cookie consent logic ===== -->
<script>
(function () {
    var STORAGE = {
        gate: 'mori.investorAcknowledged.v1',
        cookie: 'mori.cookieConsent.v1'
    };
    var safeGet = function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } };
    var safeSet = function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} };

    var gate    = document.getElementById('moriGate');
    var confirm = document.getElementById('moriGateConfirm');
    var btnPro  = gate && gate.querySelector('[data-gate-role="professional"]');
    var btnRet  = gate && gate.querySelector('[data-gate-role="retail"]');
    var decline = document.getElementById('moriGateDecline');
    var gateAcked = !!safeGet(STORAGE.gate);

    function openGate() { gate.classList.add('show'); document.body.style.overflow = 'hidden'; }
    function closeGate(role) {
        safeSet(STORAGE.gate, role + '|' + new Date().toISOString());
        gate.classList.remove('show');
        document.body.style.overflow = '';
        setTimeout(maybeShowCookie, 250);
    }
    function syncButtons() { var ok = confirm.checked; btnPro.disabled = !ok; btnRet.disabled = !ok; }
    if (gate && !gateAcked) {
        confirm.addEventListener('change', syncButtons);
        btnPro.addEventListener('click', function () { if (confirm.checked) closeGate('professional'); });
        btnRet.addEventListener('click', function () { if (confirm.checked) closeGate('retail'); });
        decline.addEventListener('click', function () { gate.classList.add('declined'); });
        syncButtons();
        openGate();
    }

    var cookieBanner = document.getElementById('moriCookie');
    var cookieAccept = document.getElementById('moriCookieAccept');
    var cookieDecline = document.getElementById('moriCookieDecline');
    var cookieDecided = !!safeGet(STORAGE.cookie);

    function maybeShowCookie() {
        if (!cookieBanner || cookieDecided) return;
        if (gate && gate.classList.contains('show')) return;
        cookieBanner.classList.add('show');
    }
    function dismissCookie(decision) {
        safeSet(STORAGE.cookie, decision + '|' + new Date().toISOString());
        cookieBanner.classList.remove('show');
        cookieDecided = true;
    }
    if (cookieBanner) {
        cookieAccept.addEventListener('click', function () { dismissCookie('accepted'); });
        cookieDecline.addEventListener('click', function () { dismissCookie('necessary'); });
        if (gateAcked) setTimeout(maybeShowCookie, 600);
    }
})();
</script>

<?php if (\Mori\Config::get('DEMO_MODE', 'false') === 'true'): ?>
<!-- ===== Demo mode: disable sub-page links ===== -->
<script>
(function () {
    var toast;
    function showToast() {
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'mori-demo-toast';
            toast.innerHTML = '<i class="fa-solid fa-circle-info"></i> <?= e(t('demo.toast')) ?>';
            document.body.appendChild(toast);
        }
        toast.classList.add('show');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () { toast.classList.remove('show'); }, 1800);
    }
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a');
        if (!a) return;
        var href = a.getAttribute('href') || '';
        if (href === '' || href.charAt(0) === '#'
            || /^(tel:|mailto:|javascript:|https?:\/\/)/i.test(href)
            || /(^|\/)index\.php(\?|#|$)/.test(href)
            || href === '/' || href === '/index') return;
        // Sub-page navigation is now extension-less (/about, /contact …). Intercept
        // both the clean slugs and any legacy .php link, but leave real files
        // (.pdf/.css/…) and api/admin/upload paths alone.
        var isLegacyPhp = /\.php(\?|#|$)/.test(href);
        var isCleanPage = href.charAt(0) === '/'
            && !/\.[a-z0-9]{2,4}(\?|#|$)/i.test(href)
            && !/^\/(api|admin|uploads|assets|css|js|fonts)\//i.test(href);
        if (isLegacyPhp || isCleanPage) {
            e.preventDefault();
            showToast();
        }
    }, true);
})();
</script>
<?php endif; ?>

<?php
$customFooter = \Mori\setting('custom_footer_code', '');
if ($customFooter):
?>
<!-- Admin-managed custom footer code (Settings → Custom Code) -->
<?= $customFooter ?>
<?php endif; ?>


<!-- PDF preview modal (eye buttons in document lists) -->
<script>
(function () {
    var L = {
        openTab:  <?= json_encode(\Mori\t('preview.open_tab')) ?>,
        close:    <?= json_encode(\Mori\t('preview.close')) ?>,
        download: <?= json_encode(\Mori\t('btn.download')) ?>
    };
    var overlay = null, frame = null, titleEl = null, dlBtn = null, tabBtn = null, spin = null;

    function isMobile() {
        return window.matchMedia('(max-width: 900px)').matches
            || /iPad|iPhone|iPod/.test(navigator.userAgent);
    }
    function build() {
        overlay = document.createElement('div');
        overlay.className = 'pdfv-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML =
            '<div class="pdfv-panel">' +
              '<div class="pdfv-head">' +
                '<div class="pdfv-title"></div>' +
                '<a class="pdfv-btn pdfv-btn--ghost pdfv-tab" target="_blank" rel="noopener" title="' + L.openTab + '"><i class="fa-solid fa-up-right-from-square"></i><span>' + L.openTab + '</span></a>' +
                '<a class="pdfv-btn pdfv-btn--dl pdfv-dl" title="' + L.download + '"><i class="fa-solid fa-download"></i><span>' + L.download + '</span></a>' +
                '<button type="button" class="pdfv-btn pdfv-btn--close" aria-label="' + L.close + '" title="' + L.close + '"><i class="fa-solid fa-xmark"></i></button>' +
              '</div>' +
              '<div class="pdfv-body">' +
                '<div class="pdfv-spin"><i class="fa-solid fa-circle-notch fa-spin"></i></div>' +
                '<iframe title="PDF"></iframe>' +
              '</div>' +
            '</div>';
        document.body.appendChild(overlay);
        frame   = overlay.querySelector('iframe');
        titleEl = overlay.querySelector('.pdfv-title');
        dlBtn   = overlay.querySelector('.pdfv-dl');
        tabBtn  = overlay.querySelector('.pdfv-tab');
        spin    = overlay.querySelector('.pdfv-spin');
        frame.addEventListener('load', function () { spin.classList.add('is-done'); });
        overlay.querySelector('.pdfv-btn--close').addEventListener('click', close);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) close();
        });
    }
    function open(url, title, dlUrl) {
        if (isMobile()) { window.open(url, '_blank', 'noopener'); return; }
        if (!overlay) build();
        titleEl.textContent = title || 'PDF';
        tabBtn.href = url;
        dlBtn.href = dlUrl || url;
        spin.classList.remove('is-done');
        frame.src = url;
        overlay.classList.add('is-open');
        document.body.classList.add('pdfv-lock');
        overlay.querySelector('.pdfv-btn--close').focus();
    }
    function close() {
        overlay.classList.remove('is-open');
        document.body.classList.remove('pdfv-lock');
        setTimeout(function () { if (frame) frame.src = 'about:blank'; }, 200);
    }
    document.addEventListener('click', function (e) {
        var t = e.target.closest && e.target.closest('[data-pdf-preview]');
        if (!t) return;
        e.preventDefault();
        e.stopPropagation();
        open(t.getAttribute('data-pdf-url') || t.getAttribute('href'),
             t.getAttribute('data-pdf-title') || '',
             t.getAttribute('data-pdf-download') || '');
    }, true);
})();
</script>

</body>
</html>
