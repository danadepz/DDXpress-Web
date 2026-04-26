<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["staff"]);
$staffId = (int)($_SESSION["user_id"] ?? 0);

$stmt = $conn->prepare("SELECT branch_id, full_name FROM staff WHERE id = ? AND staff_role = 'staff' LIMIT 1");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$res = $stmt->get_result();
$me = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$me) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$branchId = (int)($me["branch_id"] ?? 0);
$staffName = $me["full_name"] ?? "Staff";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $parcelId = (int)($_POST["parcel_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if ($parcelId > 0 && ($action === "accept" || $action === "decline")) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT status FROM parcel WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $parcelId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$row) {
                throw new RuntimeException("Parcel not found");
            }

            $newStatus = ($action === "accept") ? "READY_FOR_PICKUP" : "DECLINED";
            $note = ($action === "accept") ? "Inspected and accepted by staff" : "Declined by staff (mismatch/damage)";

            $stmt = $conn->prepare("
                UPDATE parcel
                SET status = ?, inspected_by_staff_id = ?, current_branch_id = ?, last_scan_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("siii", $newStatus, $staffId, $branchId, $parcelId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO parcel_status_history (parcel_id, status, branch_id, note, updated_by_staff_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isisi", $parcelId, $newStatus, $branchId, $note, $staffId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            set_flash("ok", "Parcel " . ($action === "accept" ? "accepted" : "declined") . " successfully.");
        } catch (Throwable $e) {
            $conn->rollback();
            set_flash("error", "Update failed. Please try again.");
        }
        redirect("/DDXpress/staff/dashboard.php");
    }
}

$stmt = $conn->prepare("
    SELECT pr.id, pr.tracking_number, pr.description, pr.weight_kg, pr.declared_value, pr.status, pr.created_at,
           b.id AS booking_id,
           cb.full_name AS customer_name
    FROM parcel pr
    JOIN booking b ON b.id = pr.booking_id
    JOIN customer cb ON cb.id = b.customer_id
    WHERE pr.status IN ('PENDING_INSPECTION','PENDING_DROP_OFF')
      AND (
          pr.current_branch_id = ?
          OR pr.current_branch_id IS NULL
          OR b.origin_branch_id = ?
      )
    ORDER BY pr.id DESC
    LIMIT 100
");
$stmt->bind_param("ii", $branchId, $branchId);
$stmt->execute();
$res = $stmt->get_result();
$queue = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($queue === null) {
    $queue = [];
}

$todayDate = date("Y-m-d");
$newBookingsToday = 0;
foreach ($queue as $q) {
    if (!empty($q["created_at"]) && date("Y-m-d", strtotime((string)$q["created_at"])) === $todayDate) {
        $newBookingsToday++;
    }
}

$pageTitle = "Staff Dashboard";
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
    .queue-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    .queue-card h3 {
        font-family: 'ADLaM Display', serif;
        font-size: 1.3rem;
        margin-bottom: 1rem;
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
    .btn-pill-outline {
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
    .btn-pill-outline:hover {
        background: #6c757d;
        border-color: #6c757d;
        color: white;
    }
    .btn-accept {
        padding: 6px 16px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        border: 2px solid #198754;
        display: inline-block;
        background: #198754;
        color: white;
        font-size: 0.85rem;
        cursor: pointer;
        margin-right: 8px;
    }
    .btn-accept:hover {
        background: transparent;
        border-color: #198754;
        color: #198754;
    }
    .btn-decline {
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
    .btn-decline:hover {
        background: transparent;
        border-color: #dc3545;
        color: #dc3545;
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
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-PENDING_INSPECTION { background: #ffc107; color: #000; }
    .status-PENDING_DROP_OFF { background: #17a2b8; color: #fff; }
    .code-tracking {
        background: #f0f0f0;
        padding: 4px 8px;
        border-radius: 20px;
        font-family: monospace;
        font-size: 0.85rem;
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
    @media (max-width: 768px) {
        .staff-header {
            flex-direction: column;
            gap: 1rem;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
        th, td {
            padding: 8px;
            font-size: 0.8rem;
        }
        .btn-accept, .btn-decline {
            display: block;
            width: 100%;
            margin-bottom: 5px;
        }
    }
</style>

<!-- Header -->
<div class="staff-header">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Staff Dashboard</h1>
        <p class="text-muted mb-0">Inspection queue for your branch.</p>
    </div>
    <div class="d-flex gap-3">
        <a class="btn-pill-outline" href="/DDXpress/staff/parcels.php">📦 All Parcels</a>
        <a class="btn-pill-outline" href="/DDXpress/staff/update_status.php">🔄 Update Parcel Status</a>
    </div>
</div>

<!-- Welcome Card -->
<div class="welcome-card">
    <h2>Welcome, <?= h($staffName) ?>!</h2>
    <p>Branch ID: <?= (int)$branchId ?> | <?= count($queue) ?> parcel(s) waiting for inspection.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-number"><?= count($queue) ?></div>
        <div class="stat-label">Pending Inspection</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= (int)$newBookingsToday ?></div>
        <div class="stat-label">New Bookings Today</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= (int)$branchId ?></div>
        <div class="stat-label">Your Branch ID</div>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($ok = get_flash("ok")): ?>
    <div class="alert alert-success" role="alert">✅ <?= h($ok) ?></div>
<?php endif; ?>
<?php if ($err = get_flash("error")): ?>
    <div class="alert alert-danger" role="alert">❌ <?= h($err) ?></div>
<?php endif; ?>

<!-- Inspection Queue -->
<div class="queue-card">
    <h3>📋 Inspection Queue</h3>
    
    <?php if (empty($queue)): ?>
        <div class="text-muted text-center py-4">
            <p>No parcels waiting for inspection at your branch.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="queue-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Description</th>
                        <th>Weight</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queue as $p): ?>
                        <tr>
                            <td><span class="code-tracking"><?= h($p["tracking_number"]) ?></span></td>
                            <td><?= h($p["description"]) ?></td>
                            <td><?= number_format((float)$p["weight_kg"], 2) ?> kg</td>
                            <td><?= h($p["customer_name"]) ?></td>
                            <td>
                                <span class="status-badge status-<?= h($p["status"]) ?>">
                                    <?= str_replace('_', ' ', h($p["status"])) ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <input type="hidden" name="parcel_id" value="<?= (int)$p["id"] ?>">
                                    <button class="btn-accept" name="action" value="accept" type="submit">✓ Accept</button>
                                    <button class="btn-decline" name="action" value="decline" type="submit">✗ Decline</button>
                                </form>
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