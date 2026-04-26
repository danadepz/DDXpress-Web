<?php
require_once("../config/db.php");
require_once("../includes/util.php");

$tracking = trim($_GET["tracking"] ?? "");
$parcel = null;
$history = [];
$error = null;

if ($tracking !== "") {
    $stmt = $conn->prepare("
        SELECT pr.id, pr.tracking_number, pr.description, pr.status, pr.weight_kg, pr.declared_value,
               b.id AS booking_id,
               ob.name AS origin_branch,
               db.name AS destination_branch,
               cb.full_name AS customer_name
        FROM parcel pr
        JOIN booking b ON b.id = pr.booking_id
        JOIN customer cb ON cb.id = b.customer_id
        JOIN branch ob ON ob.id = b.origin_branch_id
        JOIN branch db ON db.id = b.destination_branch_id
        WHERE pr.tracking_number = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $tracking);
    $stmt->execute();
    $res = $stmt->get_result();
    $parcel = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$parcel) {
        $error = "Tracking number not found.";
    } else {
        $parcelId = (int)$parcel["id"];
        $stmt = $conn->prepare("
            SELECT h.status, h.note, h.created_at, br.name AS branch_name
            FROM parcel_status_history h
            LEFT JOIN branch br ON br.id = h.branch_id
            WHERE h.parcel_id = ?
            ORDER BY h.id DESC
            LIMIT 50
        ");
        $stmt->bind_param("i", $parcelId);
        $stmt->execute();
        $res = $stmt->get_result();
        $history = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}

// Initialize history as array if null
if ($history === null) {
    $history = [];
}

$pageTitle = "Track Parcel";
ob_start();
?>

<style>
    .track-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    .track-card {
        background: white;
        border-radius: 25px;
        padding: 2rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        text-align: center;
    }
    .track-card h1 {
        font-family: "ADLaM Display", serif;
        font-size: 2rem;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .track-card p {
        color: #6c757d;
        margin-bottom: 1.5rem;
    }
    .track-input {
        width: 100%;
        padding: 14px 20px;
        border: 2px solid #e0e0e0;
        border-radius: 50px;
        font-size: 1rem;
        margin-bottom: 1rem;
        transition: all 0.3s;
    }
    .track-input:focus {
        border-color: #0d6efd;
        outline: none;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
    }
    .track-btn {
        width: 25%;
        padding: 14px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border: none;
        border-radius: 50px;
        color: white;
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
    }
    .track-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
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
    .parcel-details {
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }
    .detail-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #f0f0f0;
    }
    .detail-label {
        font-weight: 600;
        color: #6c757d;
    }
    .detail-value {
        font-weight: 500;
        color: #333;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .status-CREATED { background: #ffc107; color: #000; }
    .status-CONFIRMED { background: #17a2b8; color: #fff; }
    .status-IN_TRANSIT { background: #0d6efd; color: #fff; }
    .status-DELIVERED { background: #198754; color: #fff; }
    .status-CANCELLED { background: #dc3545; color: #fff; }
    .status-PENDING_DROP_OFF { background: #fd7e14; color: #fff; }
    .history-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }
    .history-table th,
    .history-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    .history-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
    }
    code {
        background: #f0f0f0;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.85rem;
    }
    .alert {
        padding: 12px 16px;
        border-radius: 12px;
        margin-top: 1rem;
    }
    .alert-danger {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }
    @media (max-width: 768px) {
        .track-header {
            flex-direction: column;
            gap: 1rem;
        }
        .track-card {
            padding: 1.5rem;
        }
        .detail-row {
            flex-direction: column;
        }
        .detail-value {
            margin-top: 0.25rem;
        }
        .history-table th,
        .history-table td {
            padding: 6px;
            font-size: 0.8rem;
        }
    }
</style>

<!-- Header -->
<div class="track-header">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Track Parcel</h1>
        <p class="text-muted mb-0">Enter a tracking number like <code>DDX-000001</code></p>
    </div>
    <?php if (!empty($_SESSION["role"]) && ($_SESSION["role"] === "customer")): ?>
    <?php else: ?>
        <a class="btn-back" href="/DDXpress/index.php">← Back to Home</a>
    <?php endif; ?>
</div>

<!-- Tracking Card -->
<div class="track-card">
    <form method="get" action="/DDXpress/customer/track.php" autocomplete="on">
        <input type="text" class="track-input" name="tracking" placeholder="Tracking number (e.g. DDX-000001)" value="<?= h($tracking) ?>" required>
        <button type="submit" class="track-btn">🔍 Track Parcel</button>
    </form>
</div>

<!-- Error Message -->
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
<?php endif; ?>

<!-- Parcel Details (if found) -->
<?php if ($parcel): ?>
    <div class="parcel-details">
        <div class="detail-row">
            <span class="detail-label">Tracking Number:</span>
            <span class="detail-value"><strong><?= h($parcel["tracking_number"]) ?></strong></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Current Status:</span>
            <span class="detail-value">
                <span class="status-badge status-<?= h($parcel["status"]) ?>">
                    <?= str_replace('_', ' ', h($parcel["status"])) ?>
                </span>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Description:</span>
            <span class="detail-value"><?= h($parcel["description"]) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Route:</span>
            <span class="detail-value"><?= h($parcel["origin_branch"]) ?> → <?= h($parcel["destination_branch"]) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Weight:</span>
            <span class="detail-value"><?= number_format((float)$parcel["weight_kg"], 2) ?> kg</span>
        </div>
        <?php if (!empty($parcel["declared_value"]) && (float)$parcel["declared_value"] > 0): ?>
            <div class="detail-row">
                <span class="detail-label">Declared Value:</span>
                <span class="detail-value">$<?= number_format((float)$parcel["declared_value"], 2) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($parcel["customer_name"])): ?>
            <div class="detail-row">
                <span class="detail-label">Customer:</span>
                <span class="detail-value"><?= h($parcel["customer_name"]) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Status History -->
    <h3 style="margin: 1.5rem 0 0.5rem; font-family: 'ADLaM Display', serif;">Status History</h3>
    <?php if (empty($history)): ?>
        <div class="text-muted" style="padding: 1rem 0;">No history yet.</div>
    <?php else: ?>
        <table class="history-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Branch</th>
                    <th>Note</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $hrow): ?>
                    <tr>
                        <td>
                            <span class="status-badge status-<?= h($hrow["status"]) ?>">
                                <?= str_replace('_', ' ', h($hrow["status"])) ?>
                            </span>
                        </td>
                        <td><?= h($hrow["branch_name"] ?? "-") ?></td>
                        <td><?= h($hrow["note"] ?? "-") ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($hrow["created_at"])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include("../layout/layout.php");
?>