<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["admin"]);

$error = null;

$stmt = $conn->prepare("SELECT id, name FROM branch ORDER BY id ASC");
$stmt->execute();
$res = $stmt->get_result();
$branches = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($branches === null) {
    $branches = [];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    if ($action === "add") {
        $fullName = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $role = $_POST["staff_role"] ?? "staff";
        $branchId = (int)($_POST["branch_id"] ?? 0);
        $password = $_POST["password"] ?? "password";

        if ($fullName === "" || $email === "") {
            $error = "Full name and email are required.";
        } elseif (!in_array($role, ["staff","rider","admin"], true)) {
            $error = "Invalid role.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO staff (full_name, email, password_hash, staff_role, branch_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $fullName, $email, $hash, $role, $branchId);
            $stmt->execute();
            $stmt->close();
            set_flash("ok", "Staff account created successfully.");
            redirect("/DDXpress/admin/staff.php");
        }
    } elseif ($action === "deactivate") {
        $id = (int)($_POST["id"] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE staff SET is_active = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            set_flash("ok", "Staff deactivated.");
            redirect("/DDXpress/admin/staff.php");
        }
    }
}

$stmt = $conn->prepare("
    SELECT s.id, s.full_name, s.email, s.staff_role, s.is_active, b.name AS branch_name, s.created_at
    FROM staff s
    LEFT JOIN branch b ON b.id = s.branch_id
    ORDER BY s.id ASC
");
$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($rows === null) {
    $rows = [];
}

$pageTitle = "Manage Staff";
ob_start();
?>

<style>
    .staff-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    .welcome-card {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 20px;
        padding: 1.5rem;
        color: white;
        margin-bottom: 1.5rem;
    }
    .welcome-card h2 {
        font-family: 'ADLaM Display', serif;
        margin: 0;
        font-size: 1.5rem;
    }
    .welcome-card p {
        margin: 0.5rem 0 0;
        opacity: 0.9;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .stat-box {
        background: white;
        border-radius: 15px;
        padding: 1rem;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: #0d6efd;
    }
    .stat-label {
        color: #6c757d;
        font-size: 0.85rem;
    }
    .form-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
    }
    .form-card h3 {
        font-family: 'ADLaM Display', serif;
        font-size: 1.3rem;
        margin-bottom: 1rem;
    }
    .staff-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    .staff-card h3 {
        font-family: 'ADLaM Display', serif;
        font-size: 1.3rem;
        margin-bottom: 1rem;
    }
    .form-group {
        margin-bottom: 0.5rem;
    }
    .form-label {
        font-weight: 500;
        margin-bottom: 0.25rem;
        display: block;
        color: #333;
        font-size: 0.85rem;
    }
    .form-control, .form-select {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 0.95rem;
        transition: all 0.3s;
    }
    .form-control:focus, .form-select:focus {
        border-color: #0d6efd;
        outline: none;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
    }
    .required-star {
        color: #dc3545;
    }
    .btn-pill {
        padding: 10px 24px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        border: 2px solid #0d6efd;
        display: inline-block;
        background: #0d6efd;
        color: white;
        cursor: pointer;
    }
    .btn-pill:hover {
        background: transparent;
        border-color: #0d6efd;
        color: #0d6efd;
    }
    .btn-danger {
        padding: 6px 16px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        border: 2px solid #dc3545;
        display: inline-block;
        background: #dc3545;
        color: white;
        font-size: 0.85rem;
        cursor: pointer;
    }
    .btn-danger:hover {
        background: transparent;
        border-color: #dc3545;
        color: #dc3545;
    }
    .btn-back {
        padding: 10px 24px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        border: 2px solid #6c757d;
        display: inline-block;
        background: transparent;
        color: #6c757d;
    }
    .btn-back:hover {
        background: #6c757d;
        border-color: #6c757d;
        color: white;
    }
    .table-responsive {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    th {
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
    }
    .alert {
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 1rem;
    }
    .alert-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }
    .alert-danger {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }
    .role-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .role-admin { background: #dc3545; color: #fff; }
    .role-staff { background: #0d6efd; color: #fff; }
    .role-rider { background: #198754; color: #fff; }
    .status-active { color: #198754; font-weight: 600; }
    .status-inactive { color: #dc3545; font-weight: 600; }
    .row-flex {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
    }
    .flex-grow {
        flex: 1;
        min-width: 180px;
    }
    @media (max-width: 768px) {
        .staff-header {
            flex-direction: column;
            gap: 1rem;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .row-flex {
            flex-direction: column;
        }
        .btn-pill, .btn-back {
            width: 100%;
            text-align: center;
        }
        th, td {
            padding: 8px;
            font-size: 0.8rem;
        }
    }
</style>

<!-- Header -->
<div class="staff-header">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Manage Staff</h1>
        <p class="text-muted mb-0">Create staff, rider, and admin accounts.</p>
    </div>
    <a class="btn-back" href="/DDXpress/admin/dashboard.php">← Back to Dashboard</a>
</div>

<!-- Welcome Card -->
<div class="welcome-card">
    <h2>Staff Management</h2>
    <p>Create and manage staff accounts for your delivery network.</p>
</div>

<!-- Stats -->
<?php
    $totalStaff = 0;
    $totalRiders = 0;
    $totalAdmins = 0;
    $activeCount = 0;
    foreach ($rows as $r) {
        if ($r["staff_role"] === "staff") $totalStaff++;
        elseif ($r["staff_role"] === "rider") $totalRiders++;
        elseif ($r["staff_role"] === "admin") $totalAdmins++;
        if ($r["is_active"] == 1) $activeCount++;
    }
?>
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-number"><?= count($rows) ?></div>
        <div class="stat-label">Total Staff</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= $activeCount ?></div>
        <div class="stat-label">Active</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= $totalStaff ?></div>
        <div class="stat-label">Staff</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= $totalRiders ?></div>
        <div class="stat-label">Riders</div>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($ok = get_flash("ok")): ?>
    <div class="alert alert-success" role="alert">✅ <?= h($ok) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">❌ <?= h($error) ?></div>
<?php endif; ?>

<!-- Add Staff Form -->
<div class="form-card">
    <h3>➕ Create Staff Account</h3>
    <form method="post">
        <input type="hidden" name="action" value="add">
        
        <div class="row-flex">
            <div class="flex-grow">
                <label class="form-label">Full Name <span class="required-star">*</span></label>
                <input class="form-control" type="text" name="full_name" placeholder="e.g., Juan Dela Cruz" required>
            </div>
            
            <div class="flex-grow">
                <label class="form-label">Email <span class="required-star">*</span></label>
                <input class="form-control" type="email" name="email" placeholder="staff@ddxpress.com" required>
            </div>
            
            <div class="flex-grow">
                <label class="form-label">Role</label>
                <select class="form-select" name="staff_role">
                    <option value="staff">👔 Staff</option>
                    <option value="rider">🏍️ Rider</option>
                    <option value="admin">👑 Admin</option>
                </select>
            </div>
            
            <div class="flex-grow">
                <label class="form-label">Branch</label>
                <select class="form-select" name="branch_id">
                    <option value="0">— No branch —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b["id"] ?>"><?= h($b["name"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex-grow">
                <label class="form-label">Temp Password</label>
                <input class="form-control" type="text" name="password" value="password">
            </div>
            
            <div>
                <button class="btn-pill" type="submit" style="margin-top: 28px;">Create Account</button>
            </div>
        </div>
    </form>
    <div class="text-muted" style="margin-top: 0.75rem; font-size: 0.8rem;">
        💡 Tip: Default password is <code>password</code>. Staff can change it after first login.
    </div>
</div>

<!-- Staff List -->
<div class="staff-card">
    <h3>📋 Staff List</h3>
    
    <?php if (empty($rows)): ?>
        <div class="text-muted text-center py-4">
            <p>No staff accounts found. Create your first staff account using the form above.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><strong><?= (int)$r["id"] ?></strong></td>
                            <td><?= h($r["full_name"]) ?></td>
                            <td><?= h($r["email"]) ?></td>
                            <td>
                                <span class="role-badge role-<?= h($r["staff_role"]) ?>">
                                    <?= ucfirst(h($r["staff_role"])) ?>
                                </span>
                            </td>
                            <td><?= h($r["branch_name"] ?? "—") ?></td>
                            <td>
                                <?php if ((int)$r["is_active"] === 1): ?>
                                    <span class="status-active">● Active</span>
                                <?php else: ?>
                                    <span class="status-inactive">● Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($r["created_at"])) ?></td>
                            <td>
                                <?php if ((int)$r["is_active"] === 1): ?>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to deactivate <?= h($r["full_name"]) ?>?');">
                                        <input type="hidden" name="action" value="deactivate">
                                        <input type="hidden" name="id" value="<?= (int)$r["id"] ?>">
                                        <button class="btn-danger" type="submit">Deactivate</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                             </td>
                         </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include("../layout/layout.php");
?>