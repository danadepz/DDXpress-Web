<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["rider"]);
$riderId = (int)($_SESSION["user_id"] ?? 0);

// Get rider info
$stmt = $conn->prepare("SELECT full_name FROM staff WHERE id = ? AND staff_role = 'rider' LIMIT 1");
$stmt->bind_param("i", $riderId);
$stmt->execute();
$res = $stmt->get_result();
$rider = $res ? $res->fetch_assoc() : null;
$stmt->close();
$riderName = $rider["full_name"] ?? "Rider";

$stmt = $conn->prepare("
    SELECT pr.id, pr.tracking_number, pr.description, pr.status, pr.last_scan_at, pr.weight_kg,
           br.name AS branch_name
    FROM parcel pr
    LEFT JOIN branch br ON br.id = pr.current_branch_id
    WHERE pr.assigned_rider_id = ?
    ORDER BY pr.id DESC
    LIMIT 200
");
$stmt->bind_param("i", $riderId);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Initialize rows as array if null
if ($rows === null) {
    $rows = [];
}

$pageTitle = "My Parcels";
ob_start();
?>

<style>
    .parcels-header {
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
    .status-OUT_FOR_DELIVERY { background: #0d6efd; color: #fff; }
    .status-DELIVERED { background: #198754; color: #fff; }
    .status-READY_FOR_PICKUP { background: #ffc107; color: #000; }
    .status-PENDING_DROP_OFF { background: #fd7e14; color: #fff; }
    .status-CREATED { background: #6c757d; color: #fff; }
    .code-tracking {
        background: #f0f0f0;
        padding: 4px 8px;
        border-radius: 20px;
        font-family: monospace;
        font-size: 0.85rem;
    }
    .weight-badge {
        background: #e9ecef;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.75rem;
    }
    @media (max-width: 768px) {
        .parcels-header {
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
    }
</style>

<!-- Header -->
<div class="parcels-header">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">My Accepted Parcels</h1>
        <p class="text-muted mb-0">Parcels that have been assigned to you for delivery.</p>
    </div>
</div>

<!-- Welcome Card -->
<div class="welcome-card">
    <h2>Your Assigned Parcels</h2>
    <p>You have <?= count($rows) ?> parcel(s) currently assigned to you.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-number"><?= count($rows) ?></div>
        <div class="stat-label">Total Assigned</div>
    </div>
    <div class="stat-box">
        <div class="stat-number">
            <?php 
                $outForDelivery = 0;
                $delivered = 0;
                foreach ($rows as $r) {
                    if ($r["status"] === "OUT_FOR_DELIVERY") $outForDelivery++;
                    if ($r["status"] === "DELIVERED") $delivered++;
                }
                echo $outForDelivery;
            ?>
        </div>
        <div class="stat-label">Out for Delivery</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= $delivered ?? 0 ?></div>
        <div class="stat-label">Delivered</div>
    </div>
</div>

<!-- Parcels Table -->
<div class="parcels-card">
    <h3>📦 Your Parcels</h3>
    
    <?php if (empty($rows)): ?>
        <div class="text-muted text-center py-4">
            <p>No parcels have been assigned to you yet.</p>
            <a class="btn-pill-outline" href="/DDXpress/rider/dashboard.php" style="margin-top: 10px;">Browse Available Parcels</a>
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
                        <th>Last Scan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><span class="code-tracking"><?= h($r["tracking_number"]) ?></span></td>
                            <td><?= h($r["description"]) ?></td>
                            <td><span class="weight-badge"><?= number_format((float)($r["weight_kg"] ?? 0), 2) ?> kg</span></td>
                            <td>
                                <span class="status-badge status-<?= h($r["status"]) ?>">
                                    <?= str_replace('_', ' ', h($r["status"])) ?>
                                </span>
                            </td>
                            <td><?= h($r["branch_name"] ?? "—") ?></td>
                            <td><?= h($r["last_scan_at"] ?? "—") ?></td>
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