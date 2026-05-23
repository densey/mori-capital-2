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
<div class="mori-cookie" id="moriCookie" role="region" aria-label="Cookie consent">
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
            || href === '/') return;
        if (/\.php(\?|#|$)/.test(href)) {
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

</body>
</html>
