    </div><!-- /a-content -->
  </main><!-- /a-main -->
</div><!-- /a-shell -->

<!-- jQuery (used by TinyMCE init and some admin scripts) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<?php if (!empty($adminPage['tinymce'])): ?>
<!-- TinyMCE (open-source) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.4.1/tinymce.min.js" referrerpolicy="origin"></script>
<script>
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
    license_key: 'gpl'
});
</script>
<?php endif; ?>

</body>
</html>
