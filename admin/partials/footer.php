    </div><!-- /a-content -->
  </main><!-- /a-main -->
</div><!-- /a-shell -->

<!-- jQuery (used by TinyMCE init and some admin scripts) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Admin file-upload widget -->
<script>
document.querySelectorAll('.a-upload').forEach(function(widget) {
    var input    = widget.querySelector('input[type=file]');
    var hidden   = widget.querySelector('input[type=hidden]');
    var preview  = widget.querySelector('.a-upload__preview');
    var pathSpan = widget.querySelector('.a-upload__path');
    var csrf     = <?= json_encode(\Mori\Csrf::token()) ?>;
    var folder   = widget.dataset.folder || 'uploads';

    // Show existing image if path is pre-filled
    if (hidden && hidden.value && preview) {
        var existing = hidden.value;
        if (existing && !existing.startsWith('http')) existing = '/' + existing;
        if (existing) { preview.src = existing; preview.style.display = 'block'; widget.classList.add('has-file'); }
        if (pathSpan) pathSpan.textContent = hidden.value;
    }

    if (!input) return;
    input.addEventListener('change', function() {
        if (!this.files || !this.files[0]) return;
        var file = this.files[0];
        var fd = new FormData();
        fd.append('file', file);
        fd.append('_csrf', csrf);
        fd.append('folder', folder);

        // Show local preview immediately
        if (preview && file.type.startsWith('image/')) {
            var reader = new FileReader();
            reader.onload = function(e) { preview.src = e.target.result; preview.style.display = 'block'; };
            reader.readAsDataURL(file);
        }
        widget.classList.add('has-file');
        if (pathSpan) pathSpan.textContent = 'Uploading...';

        fetch('/admin/api/upload-file.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.ok) {
                if (hidden) hidden.value = json.path;
                if (pathSpan) pathSpan.textContent = json.path;
                if (preview && json.url) { preview.src = json.url; preview.style.display = 'block'; }
            } else {
                if (pathSpan) pathSpan.textContent = 'Error: ' + (json.error || 'upload failed');
                widget.classList.remove('has-file');
            }
        })
        .catch(function(err) {
            if (pathSpan) pathSpan.textContent = 'Network error';
            widget.classList.remove('has-file');
        });
    });
});
</script>

<?php if (!empty($adminPage['tinymce'])): ?>
<!-- TinyMCE (open-source) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.4.1/tinymce.min.js" referrerpolicy="origin"></script>
<script>
var moriCsrfToken = <?= json_encode(\Mori\Csrf::token()) ?>;
tinymce.init({
    selector: 'textarea.wysiwyg',
    height: 480,
    menubar: false,
    branding: false,
    promotion: false,
    plugins: 'lists link autolink table code image media wordcount paste',
    toolbar: 'undo redo | h2 h3 | bold italic underline | bullist numlist | link image media table | blockquote | code',
    content_style: "body { font-family: 'Inter', sans-serif; font-size: 15px; color: #2C3E50; line-height: 1.65; }",
    images_upload_url: '<?= \Mori\asset('admin/api/upload-image.php') ?>',
    automatic_uploads: true,
    images_upload_handler: function (blobInfo, progress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?= \Mori\asset('admin/api/upload-image.php') ?>');
            xhr.setRequestHeader('X-CSRF-Token', moriCsrfToken);
            xhr.upload.onprogress = function (e) { if (progress && e.lengthComputable) progress(e.loaded / e.total * 100); };
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try { resolve(JSON.parse(xhr.responseText).location); }
                    catch (e) { reject({ message: 'Invalid JSON response' }); }
                } else {
                    reject({ message: 'HTTP ' + xhr.status });
                }
            };
            xhr.onerror = function () { reject({ message: 'Network error' }); };
            var fd = new FormData();
            fd.append('file', blobInfo.blob(), blobInfo.filename());
            xhr.send(fd);
        });
    },
    license_key: 'gpl'
});
</script>
<?php endif; ?>

</body>
</html>
