<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["rider"]);
$riderId = (int)($_SESSION["user_id"] ?? 0);

$stmt = $conn->prepare("SELECT branch_id, full_name FROM staff WHERE id = ? AND staff_role = 'rider' AND is_active = 1 LIMIT 1");
$stmt->bind_param("i", $riderId);
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
$riderName = $me["full_name"] ?? "Rider";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $parcelId = (int)($_POST["parcel_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if ($parcelId > 0 && ($action === "accept" || $action === "decline")) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT status, assigned_rider_id FROM parcel WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $parcelId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if (!$row) throw new RuntimeException("Parcel not found");

            if ($action === "accept") {
                $newStatus = "OUT_FOR_DELIVERY";
                $note = "Accepted by rider";
                $stmt = $conn->prepare("
                    UPDATE parcel
                    SET status = ?, assigned_rider_id = ?, last_scan_at = NOW()
                    WHERE id = ? AND status = 'READY_FOR_PICKUP'
                ");
                $stmt->bind_param("sii", $newStatus, $riderId, $parcelId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("
                    INSERT INTO parcel_status_history (parcel_id, status, branch_id, note, updated_by_rider_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isisi", $parcelId, $newStatus, $branchId, $note, $riderId);
                $stmt->execute();
                $stmt->close();
                $message = "Parcel accepted successfully!";
            } else {
                // Decline is just a no-op record (keeps it available).
                $note = "Declined by rider";
                $status = (string)$row["status"];
                $stmt = $conn->prepare("
                    INSERT INTO parcel_status_history (parcel_id, status, branch_id, note, updated_by_rider_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isisi", $parcelId, $status, $branchId, $note, $riderId);
                $stmt->execute();
                $stmt->close();
                $message = "Parcel declined.";
            }

            $conn->commit();
            set_flash("ok", $message);
        } catch (Throwable $e) {
            $conn->rollback();
            set_flash("error", "Action failed. Please try again.");
        }
        redirect("/DDXpress/rider/dashboard.php");
    }
}

$stmt = $conn->prepare("
    SELECT pr.id, pr.tracking_number, pr.description, pr.weight_kg, pr.status, pr.created_at,
           br.name AS branch_name
    FROM parcel pr
    LEFT JOIN branch br ON br.id = pr.current_branch_id
    WHERE pr.status = 'READY_FOR_PICKUP'
      AND (pr.current_branch_id = ? OR pr.current_branch_id IS NULL)
    ORDER BY pr.id DESC
    LIMIT 100
");
$stmt->bind_param("i", $branchId);
$stmt->execute();
$res = $stmt->get_result();
$available = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Initialize available as array if null
if ($available === null) {
    $available = [];
}

$pageTitle = "Rider Dashboard";
ob_start();
?>

<style>
    .rider-header {
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
    .parcels-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    .parcels-card h3 {
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
    .status-READY_FOR_PICKUP { background: #ffc107; color: #000; }
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
        .rider-header {
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
<div class="rider-header">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Rider Dashboard</h1>
        <p class="text-muted mb-0">Available parcels to accept from your branch.</p>
    </div>
    <a class="btn-pill-outline" href="/DDXpress/rider/parcels.php">📦 My Parcels</a>
</div>

<!-- Welcome Card -->
<div class="welcome-card">
    <h2>Welcome back, <?= h($riderName) ?>!</h2>
    <p>Branch ID: <?= (int)$branchId ?> | You have <?= count($available) ?> parcel(s) available for pickup.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-number"><?= count($available) ?></div>
        <div class="stat-label">Available Parcels</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= (int)$branchId ?></div>
        <div class="stat-label">Your Branch ID</div>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($ok = get_flash("ok")): ?>
    <div class="alert alert-success" role="alert"><?= h($ok) ?></div>
<?php endif; ?>
<?php if ($err = get_flash("error")): ?>
    <div class="alert alert-danger" role="alert"><?= h($err) ?></div>
<?php endif; ?>

<!-- Available Parcels Table -->
<div class="parcels-card">
    <h3>📋 Available Parcels</h3>
    
    <?php if (empty($available)): ?>
        <div class="text-muted text-center py-4">
            <p>No parcels available for pickup at your branch.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="parcel-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Description</th>
                        <th>Weight</th>
                        <th>Status</th>
                        <th>Branch</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($available as $p): ?>
                        <tr>
                            <td><span class="code-tracking"><?= h($p["tracking_number"]) ?></span></td>
                            <td>
                                <?= h($p["description"]) ?>
                            </td>
                            <td><?= number_format((float)$p["weight_kg"], 2) ?> kg</td>
                            <td>
                                <span class="status-badge status-<?= h($p["status"]) ?>">
                                    <?= str_replace('_', ' ', h($p["status"])) ?>
                                </span>
                            </td>
                            <td><?= h($p["branch_name"] ?? "—") ?></td>
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