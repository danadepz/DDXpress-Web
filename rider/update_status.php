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

$statuses = [
    "OUT_FOR_DELIVERY",
    "IN_TRANSIT",
    "DELIVERED",
];

$stmt = $conn->prepare("
    SELECT id, tracking_number, description, status
    FROM parcel
    WHERE assigned_rider_id = ?
    ORDER BY id DESC
    LIMIT 300
");
$stmt->bind_param("i", $riderId);
$stmt->execute();
$res = $stmt->get_result();
$assigned = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Initialize assigned as array if null
if ($assigned === null) {
    $assigned = [];
}

// Delivered parcels are read-only and must not appear in update dropdown.
$updatableAssigned = array_values(array_filter($assigned, static function (array $p): bool {
    return ($p["status"] ?? "") !== "DELIVERED";
}));

$error = null;
$ok = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tracking = trim($_POST["tracking"] ?? "");
    $newStatus = $_POST["status"] ?? "";
    $note = trim($_POST["note"] ?? "");

    if ($tracking === "" || !in_array($newStatus, $statuses, true)) {
        $error = "Please select a parcel and valid status.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id, booking_id, status FROM parcel WHERE tracking_number = ? AND assigned_rider_id = ? LIMIT 1 FOR UPDATE");
            $stmt->bind_param("si", $tracking, $riderId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$row) throw new RuntimeException("Parcel not found/assigned");
            if (($row["status"] ?? "") === "DELIVERED") {
                throw new RuntimeException("Delivered parcel cannot be updated");
            }
            $parcelId = (int)$row["id"];
            $bookingId = (int)$row["booking_id"];

            $stmt = $conn->prepare("UPDATE parcel SET status = ?, last_scan_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $parcelId);
            $stmt->execute();
            $stmt->close();

            if ($note === "" && $newStatus === "DELIVERED") {
                $note = "Delivered (notify sender/receiver)";
            }

            $stmt = $conn->prepare("
                INSERT INTO parcel_status_history (parcel_id, status, branch_id, note, updated_by_rider_id)
                VALUES (?, ?, NULL, ?, ?)
            ");
            $stmt->bind_param("issi", $parcelId, $newStatus, $note, $riderId);
            $stmt->execute();
            $stmt->close();

            // Keep booking status in sync so customer dashboard/history reflects latest parcel updates.
            $stmt = $conn->prepare("
                SELECT
                    SUM(CASE WHEN status = 'DELIVERED' THEN 1 ELSE 0 END) AS delivered_count,
                    SUM(CASE WHEN status IN ('IN_TRANSIT', 'OUT_FOR_DELIVERY') THEN 1 ELSE 0 END) AS moving_count,
                    SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END) AS confirmed_count,
                    COUNT(*) AS total_count
                FROM parcel
                WHERE booking_id = ?
            ");
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $res = $stmt->get_result();
            $bookingStats = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            $totalCount = (int)($bookingStats["total_count"] ?? 0);
            $deliveredCount = (int)($bookingStats["delivered_count"] ?? 0);
            $movingCount = (int)($bookingStats["moving_count"] ?? 0);
            $confirmedCount = (int)($bookingStats["confirmed_count"] ?? 0);

            $bookingStatus = "CREATED";
            if ($totalCount > 0 && $deliveredCount === $totalCount) {
                $bookingStatus = "DELIVERED";
            } elseif ($movingCount > 0) {
                $bookingStatus = "IN_TRANSIT";
            } elseif ($confirmedCount > 0) {
                // booking.status enum does not include CONFIRMED; PAID is the closest pre-transit state.
                $bookingStatus = "PAID";
            }

            $stmt = $conn->prepare("UPDATE booking SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $bookingStatus, $bookingId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $ok = "Status updated successfully!";
        } catch (Throwable $e) {
            $conn->rollback();
            $error = "Update failed. Please try again.";
        }
    }
}

$pageTitle = "Update Status";
ob_start();
?>

<style>
    .status-header {
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
    .update-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        max-width: 600px;
        margin: 0 auto;
    }
    .update-card h3 {
        font-family: 'ADLaM Display', serif;
        font-size: 1.3rem;
        margin-bottom: 1rem;
        text-align: center;
    }
    .form-group {
        margin-bottom: 1.25rem;
    }
    .form-label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: block;
        color: #333;
    }
    .form-control, .form-select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 1rem;
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
        padding: 12px 28px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        border: 2px solid #0d6efd;
        display: inline-block;
        background: #0d6efd;
        color: white;
        cursor: pointer;
        font-size: 1rem;
        width: 100%;
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
    .alert-warning {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
    }
    .empty-state {
        text-align: center;
        padding: 2rem;
        background: #f8f9fa;
        border-radius: 15px;
    }
    @media (max-width: 768px) {
        .status-header {
            flex-direction: column;
            gap: 1rem;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .update-card {
            margin: 0 0.5rem;
            padding: 1rem;
        }
    }
</style>

<!-- Header -->
<div class="status-header">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Update Parcel Status</h1>
        <p class="text-muted mb-0">Only parcels assigned to you can be updated here.</p>
    </div>
    <a class="btn-pill-outline" href="/DDXpress/rider/dashboard.php">← Back to Dashboard</a>
</div>

<!-- Welcome Card -->
<div class="welcome-card">
    <h2>Welcome back, <?= h($riderName) ?>!</h2>
    <p>Update the status of parcels you're delivering.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-number"><?= count($assigned) ?></div>
        <div class="stat-label">Assigned to You</div>
    </div>
    <?php
        $outForDelivery = 0;
        $inTransit = 0;
        $delivered = 0;
        foreach ($assigned as $p) {
            if ($p["status"] === "OUT_FOR_DELIVERY") $outForDelivery++;
            elseif ($p["status"] === "IN_TRANSIT") $inTransit++;
            elseif ($p["status"] === "DELIVERED") $delivered++;
        }
    ?>
    <div class="stat-box">
        <div class="stat-number"><?= $outForDelivery ?></div>
        <div class="stat-label">Out for Delivery</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= $inTransit ?></div>
        <div class="stat-label">In Transit</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= $delivered ?></div>
        <div class="stat-label">Delivered</div>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($ok): ?>
    <div class="alert alert-success" role="alert">✅ <?= h($ok) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">❌ <?= h($error) ?></div>
<?php endif; ?>

<!-- Update Form -->
<div class="update-card">
    <h3>📝 Update Status</h3>
    
    <?php if (empty($updatableAssigned)): ?>
        <div class="empty-state">
            <p style="margin-bottom: 1rem; color: #6c757d;">No updatable parcels are currently assigned to you.</p>
            <a class="btn-pill-outline" href="/DDXpress/rider/dashboard.php">Browse Available Parcels</a>
        </div>
    <?php else: ?>
        <form method="post">
            <div class="form-group">
                <label class="form-label">Select Parcel <span class="required-star">*</span></label>
                <select class="form-select" name="tracking" required>
                    <option value="">— Select a parcel —</option>
                    <?php foreach ($updatableAssigned as $p): ?>
                        <option value="<?= h($p["tracking_number"]) ?>">
                            <?= h($p["tracking_number"]) ?> — <?= h($p["description"]) ?> (Current: <?= h($p["status"]) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">New Status <span class="required-star">*</span></label>
                <select class="form-select" name="status" required>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= h($s) ?>"><?= str_replace('_', ' ', h($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Note (Optional)</label>
                <input class="form-control" type="text" name="note" placeholder="e.g., Attempted delivery, left at reception, etc.">
            </div>
            
            <button class="btn-pill" type="submit">🔄 Update Status</button>
        </form>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include("../layout/layout.php");
?>