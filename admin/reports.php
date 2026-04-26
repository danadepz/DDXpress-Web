<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["admin"]);

// KPI cards
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM parcel");
$stmt->execute();
$res = $stmt->get_result();
$kpiTotalParcels = (int)(($res ? $res->fetch_assoc()["cnt"] : 0) ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM parcel WHERE status <> 'DELIVERED'");
$stmt->execute();
$res = $stmt->get_result();
$kpiPendingDeliveries = (int)(($res ? $res->fetch_assoc()["cnt"] : 0) ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM staff WHERE staff_role = 'rider' AND is_active = 1");
$stmt->execute();
$res = $stmt->get_result();
$kpiActiveRiders = (int)(($res ? $res->fetch_assoc()["cnt"] : 0) ?? 0);
$stmt->close();

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM payment
    WHERE status = 'PAID' AND DATE(paid_at) = CURDATE()
");
$stmt->execute();
$res = $stmt->get_result();
$kpiRevenueToday = (float)(($res ? $res->fetch_assoc()["total"] : 0) ?? 0);
$stmt->close();

// Booking counts
$stmt = $conn->prepare("SELECT status, COUNT(*) AS cnt FROM booking GROUP BY status ORDER BY cnt DESC");
$stmt->execute();
$res = $stmt->get_result();
$bookingCounts = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($bookingCounts === null) {
    $bookingCounts = [];
}

// Parcel counts
$stmt = $conn->prepare("SELECT status, COUNT(*) AS cnt FROM parcel GROUP BY status ORDER BY cnt DESC");
$stmt->execute();
$res = $stmt->get_result();
$parcelCounts = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($parcelCounts === null) {
    $parcelCounts = [];
}

// Payments summary
$stmt = $conn->prepare("SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM payment GROUP BY status ORDER BY cnt DESC");
$stmt->execute();
$res = $stmt->get_result();
$paymentSummary = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($paymentSummary === null) {
    $paymentSummary = [];
}

// Latest activity
$stmt = $conn->prepare("
    SELECT h.status, h.note, h.created_at, pr.tracking_number, br.name AS branch_name
    FROM parcel_status_history h
    JOIN parcel pr ON pr.id = h.parcel_id
    LEFT JOIN branch br ON br.id = h.branch_id
    ORDER BY h.id DESC
    LIMIT 20
");
$stmt->execute();
$res = $stmt->get_result();
$activity = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($activity === null) {
    $activity = [];
}

$pageTitle = "Reports & Analytics";
ob_start();
?>

<style>
    .reports-header {
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
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .kpi-card {
        background: white;
        border-radius: 20px;
        padding: 1.25rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        text-align: center;
        transition: transform 0.2s ease;
    }
    .kpi-card:hover {
        transform: translateY(-3px);
    }
    .kpi-label {
        color: #6c757d;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .kpi-value {
        font-size: 2.2rem;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin-top: 0.5rem;
    }
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .stats-card {
        background: white;
        border-radius: 20px;
        padding: 1.25rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    .stats-card h3 {
        font-family: 'ADLaM Display', serif;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f0f0f0;
    }
    .activity-card {
        background: white;
        border-radius: 20px;
        padding: 1.25rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    .activity-card h3 {
        font-family: 'ADLaM Display', serif;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f0f0f0;
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
        padding: 10px;
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
    .tracking-code {
        font-family: monospace;
        background: #f0f0f0;
        padding: 2px 6px;
        border-radius: 12px;
        font-size: 0.8rem;
    }
    .text-muted {
        color: #6c757d;
    }
    @media (max-width: 768px) {
        .reports-header {
            flex-direction: column;
            gap: 1rem;
        }
        .stats-row {
            grid-template-columns: 1fr;
        }
        th, td {
            padding: 6px;
            font-size: 0.8rem;
        }
    }
</style>

<!-- Header -->
<div class="reports-header">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Reports & Analytics</h1>
        <p class="text-muted mb-0">Quick operational snapshot and business insights.</p>
    </div>
    <a class="btn-back" href="/DDXpress/admin/dashboard.php">← Back to Dashboard</a>
</div>

<!-- Welcome Card -->
<div class="welcome-card">
    <h2>Analytics Dashboard</h2>
    <p>Monitor key metrics, track performance, and view real-time activity.</p>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">📦 Total Parcels</div>
        <div class="kpi-value"><?= (int)$kpiTotalParcels ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">⏳ Pending Deliveries</div>
        <div class="kpi-value"><?= (int)$kpiPendingDeliveries ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">💰 Revenue Today</div>
        <div class="kpi-value">₱<?= number_format((float)$kpiRevenueToday, 2) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">🏍️ Active Riders</div>
        <div class="kpi-value"><?= (int)$kpiActiveRiders ?></div>
    </div>
</div>

<!-- Stats Row -->
<div class="stats-row">
    <!-- Bookings by Status -->
    <div class="stats-card">
        <h3>📋 Bookings by Status</h3>
        <?php if (empty($bookingCounts)): ?>
            <div class="text-muted">No data available.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Status</th><th>Count</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookingCounts as $r): ?>
                            <tr>
                                <td><span class="status-badge status-<?= h($r["status"]) ?>"><?= str_replace('_', ' ', h($r["status"])) ?></span></td>
                                <td><strong><?= (int)$r["cnt"] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Parcels by Status -->
    <div class="stats-card">
        <h3>📦 Parcels by Status</h3>
        <?php if (empty($parcelCounts)): ?>
            <div class="text-muted">No data available.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Status</th><th>Count</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parcelCounts as $r): ?>
                            <tr>
                                <td><span class="status-badge status-<?= h($r["status"]) ?>"><?= str_replace('_', ' ', h($r["status"])) ?></span></td>
                                <td><strong><?= (int)$r["cnt"] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payments Summary -->
<div class="stats-card" style="margin-bottom: 1.5rem;">
    <h3>💰 Payment Summary</h3>
    <?php if (empty($paymentSummary)): ?>
        <div class="text-muted">No data available.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentSummary as $r): ?>
                        <tr>
                            <td><span class="status-badge payment-<?= h($r["status"]) ?>"><?= h($r["status"]) ?></span></td>
                            <td><?= (int)$r["cnt"] ?></td>
                            <td><strong>₱<?= number_format((float)$r["total"], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Latest Activity -->
<div class="activity-card">
    <h3>🔄 Latest Parcel Activity</h3>
    <?php if (empty($activity)): ?>
        <div class="text-muted">No recent activity.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Status</th>
                        <th>Branch</th>
                        <th>Note</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity as $a): ?>
                        <tr>
                            <td><span class="tracking-code"><?= h($a["tracking_number"]) ?></span></td>
                            <td><span class="status-badge status-<?= h($a["status"]) ?>"><?= str_replace('_', ' ', h($a["status"])) ?></span></td>
                            <td><?= h($a["branch_name"] ?? "—") ?></td>
                            <td><?= h($a["note"] ?? "—") ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($a["created_at"])) ?></td>
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