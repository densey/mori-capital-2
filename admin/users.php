<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\format_date;
use function Mori\redirect;

Auth::requireRole('super_admin');
$db = Database::instance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    Csrf::requireValid();
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        'email'  => strtolower(trim($_POST['email'])),
        'name'   => trim($_POST['name']),
        'role'   => in_array($_POST['role'], ['super_admin','editor','doc_manager'], true) ? $_POST['role'] : 'editor',
        'status' => in_array($_POST['status'], ['active','inactive'], true) ? $_POST['status'] : 'active',
    ];
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || $data['name'] === '') {
        flash('error', 'Valid email and name required.');
    } else {
        try {
            if ($id > 0) {
                if (!empty($_POST['password']) && strlen($_POST['password']) >= 12) {
                    $data['password_hash'] = Auth::hash($_POST['password']);
                }
                $db->update('users', $data, ['id' => $id]);
                AuditLog::log(Auth::userId(), 'user_updated', 'users', $id, $data['email']);
                flash('ok', 'User updated.');
            } else {
                if (empty($_POST['password']) || strlen($_POST['password']) < 12) {
                    throw new \Exception('Password (12+ chars) required for new users.');
                }
                $data['password_hash'] = Auth::hash($_POST['password']);
                $newId = $db->insert('users', $data);
                AuditLog::log(Auth::userId(), 'user_created', 'users', $newId, $data['email']);
                flash('ok', 'User created.');
            }
        } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    }
    redirect(asset('admin/users.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Csrf::requireValid();
    $id = (int)$_POST['id'];
    if ($id === Auth::userId()) flash('error', 'You cannot delete your own account.');
    else {
        $u = $db->fetchOne('SELECT * FROM users WHERE id=:id', ['id'=>$id]);
        if ($u) {
            $db->delete('users', ['id' => $id]);
            AuditLog::log(Auth::userId(), 'user_deleted', 'users', $id, $u['email']);
            flash('ok', 'User deleted.');
        }
    }
    redirect(asset('admin/users.php'));
}

$users = $db->fetchAll('SELECT id, email, name, role, status, last_login_at, created_at FROM users ORDER BY created_at DESC');

$adminPage = ['title' => 'Users', 'crumb' => 'Manage admin panel users and roles'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><?= e($err) ?></div><?php endif; ?>

<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head">
        <h2><?= count($users) ?> admin users</h2>
        <button class="a-btn" onclick="document.getElementById('newUserModal').classList.add('show')"><i class="fa-solid fa-plus"></i> New user</button>
    </div>
    <div class="a-card__body" style="padding:0;">
        <table class="a-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['name']) ?></strong></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="a-badge <?= $u['role']==='super_admin'?'info':'muted' ?>"><?= e(str_replace('_',' ',$u['role'])) ?></span></td>
                    <td><span class="a-badge <?= $u['status']==='active'?'success':'muted' ?>"><?= e($u['status']) ?></span></td>
                    <td><small><?= $u['last_login_at'] ? e(format_date($u['last_login_at'], 'd M Y H:i')) : '—' ?></small></td>
                    <td style="text-align:right;">
                        <button class="a-btn ghost sm" onclick='editUser(<?= json_encode($u) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <?php if ($u['id'] != Auth::userId()): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete user?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                            <button class="a-btn danger sm" type="submit"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="a-modal-backdrop" id="newUserModal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="a-modal">
        <div class="a-modal__head"><h3 id="modalTitle">New user</h3><button class="a-modal__close" onclick="this.closest('.a-modal-backdrop').classList.remove('show')">&times;</button></div>
        <div class="a-modal__body">
            <form method="post" class="a-form" id="userForm">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="u_id" value="">
                <label>Name *</label>
                <input type="text" name="name" id="u_name" required>
                <label>Email *</label>
                <input type="email" name="email" id="u_email" required>
                <label>Password (min 12 chars) <span id="pw_note" class="hint">required for new users</span></label>
                <input type="password" name="password" id="u_password" minlength="12">
                <div class="row">
                    <div><label>Role</label><select name="role" id="u_role"><option value="editor">Editor</option><option value="doc_manager">Document Manager</option><option value="super_admin">Super admin</option></select></div>
                    <div><label>Status</label><select name="status" id="u_status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
                <button class="a-btn lg" type="submit" style="margin-top:18px;width:100%;justify-content:center;">Save user</button>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(u) {
    document.getElementById('modalTitle').textContent = 'Edit ' + u.name;
    document.getElementById('u_id').value = u.id;
    document.getElementById('u_name').value = u.name;
    document.getElementById('u_email').value = u.email;
    document.getElementById('u_password').value = '';
    document.getElementById('pw_note').textContent = '(leave blank to keep current password)';
    document.getElementById('u_role').value = u.role;
    document.getElementById('u_status').value = u.status;
    document.getElementById('newUserModal').classList.add('show');
}
document.querySelector('[onclick*="newUserModal"]')?.addEventListener('click', function () {
    document.getElementById('modalTitle').textContent = 'New user';
    document.getElementById('userForm').reset();
    document.getElementById('u_id').value = '';
    document.getElementById('pw_note').textContent = 'required for new users';
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
