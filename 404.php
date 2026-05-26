<?php
require __DIR__ . '/src/bootstrap.php';

use function Mori\e;
use function Mori\asset;
use function Mori\t;

http_response_code(404);

$page = [
    'title'       => '404 — Page Not Found',
    'description' => 'The page you are looking for could not be found.',
    'body_class'  => 'error-page-body',
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
?>

<div class="page-header" style="background:linear-gradient(135deg,rgba(8,18,33,.85),rgba(27,58,92,.7)),url('<?= asset('images/page-header-bg.jpg') ?>') center/cover no-repeat;">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="page-header-box" style="text-align:center;color:#fff;">
                    <h1 style="color:#fff;font-size:clamp(28px,3.4vw,44px);margin-bottom:14px;">Page Not Found</h1>
                    <nav aria-label="Breadcrumb">
                        <ol style="list-style:none;padding:0;margin:0;display:inline-flex;flex-wrap:wrap;justify-content:center;gap:6px;font-size:clamp(11px,2vw,13px);color:rgba(255,255,255,.78);">
                            <li><a href="<?= asset('/') ?>" style="color:#1ABC9C;text-decoration:none;">Home</a></li>
                            <li style="opacity:.6;">/</li>
                            <li><span style="color:#fff;">404</span></li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<div style="padding:clamp(50px,10vw,100px) 0;text-align:center;">
    <div class="container">
        <div style="max-width:520px;margin:0 auto;">
            <div style="font-size:clamp(80px,18vw,160px);font-weight:800;color:var(--accent-color,#1ABC9C);line-height:1;margin-bottom:16px;letter-spacing:-4px;opacity:.15;">404</div>
            <h2 style="font-size:clamp(22px,3vw,32px);color:var(--primary-color,#1B3A5C);margin-bottom:16px;">Oops! Page not found</h2>
            <p style="font-size:15px;color:var(--text-color,#5A6B7B);line-height:1.7;margin-bottom:30px;">
                The page you're looking for may have been moved, deleted, or never existed.
                If you believe this is an error, please <a href="<?= asset('contact.php') ?>" style="color:var(--accent-color,#1ABC9C);font-weight:600;">contact us</a>.
            </p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <a href="<?= asset('/') ?>" class="btn-default">Back to Home</a>
                <a href="<?= asset('documents.php') ?>" class="btn-default btn-highlighted" style="margin-right:50px;">Document Hub</a>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
?>
