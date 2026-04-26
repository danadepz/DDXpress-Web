<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["staff"]);
$staffId = (int)($_SESSION["user_id"] ?? 0);
$q = trim($_GET["q"] ?? "");
$filter = strtolower(trim($_GET["filter"] ?? "all"));

$activeStatuses = [
    "PENDING_DROP_OFF",
    "PENDING_INSPECTION",
    "READY_FOR_PICKUP",
    "OUT_FOR_DELIVERY",
    "IN_TRANSIT",
];
$completedStatuses = [
    "DELIVERED",
    "DECLINED",
];

$where = [];
$types = "";
$params = [];

// Restrict list to parcels dropped off at the logged-in staff's branch.
$stmt = $conn->prepare("SELECT branch_id FROM staff WHERE id = ? AND staff_role = 'staff' LIMIT 1");
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
$where[] = "b.origin_branch_id = ?";
$types .= "i";
$params[] = $branchId;

if ($q !== "") {
    $like = "%" . $q . "%";
    $where[] = "(pr.tracking_number LIKE ? OR pr.description LIKE ?)";
    $types .= "ss";
    $params[] = $like;
    $params[] = $like;
}

if ($filter === "active") {
    $in = "'" . implode("','", $activeStatuses) . "'";
    $where[] = "pr.status IN (" . $in . ")";
} elseif ($filter === "completed") {
    $in = "'" . implode("','", $completedStatuses) . "'";
    $where[] = "pr.status IN (" . $in . ")";
} else {
    $filter = "all";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$stmt = $conn->prepare("
    SELECT pr.id, pr.tracking_number, pr.description, pr.status, pr.current_branch_id, pr.assigned_rider_id, pr.last_scan_at, pr.weight_kg,
           br.name AS branch_name,
           s.full_name AS rider_name,
           b.id AS booking_id,
           cb.full_name AS customer_name
    FROM parcel pr
    JOIN booking b ON b.id = pr.booking_id
    JOIN customer cb ON cb.id = b.customer_id
    LEFT JOIN branch br ON br.id = pr.current_branch_id
    LEFT JOIN staff s ON s.id = pr.assigned_rider_id
    $whereSql
    ORDER BY
      CASE
        WHEN pr.status = 'DELIVERED' THEN 2
        WHEN pr.status = 'DECLINED' THEN 2
        ELSE 1
      END ASC,
      pr.id DESC
    LIMIT 200
");

if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($rows === null) {
    $rows = [];
}

$pageTitle = "All Parcels";
ob_start();
?>

<style>
    .parcels-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    .search-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    .search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: flex-end;
    }
    .search-input {
        flex: 1;
        min-width: 250px;
        padding: 10px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 0.95rem;
        transition: all 0.3s;
    }
    .search-input:focus {
        border-color: #0d6efd;
        outline: none;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
    }
    .filter-select {
        padding: 10px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 0.95rem;
        background: white;
        min-width: 150px;
    }
    .btn-primary {
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
    .btn-primary:hover {
        background: transparent;
        border-color: #0d6efd;
        color: #0d6efd;
    }
    .btn-outline {
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
    .btn-outline:hover {
        background: #6c757d;
        border-color: #6c757d;
        color: white;
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
    .parcels-table {
        margin-top: 1rem;
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
    .tracking-code {
        font-family: monospace;
        background: #f0f0f0;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 0.85rem;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-DELIVERED { background: #198754; color: #fff; }
    .status-DECLINED { background: #dc3545; color: #fff; }
    .status-IN_TRANSIT { background: #0d6efd; color: #fff; }
    .status-OUT_FOR_DELIVERY { background: #0d6efd; color: #fff; }
    .status-READY_FOR_PICKUP { background: #17a2b8; color: #fff; }
    .status-PENDING_INSPECTION { background: #ffc107; color: #000; }
    .status-PENDING_DROP_OFF { background: #fd7e14; color: #fff; }
    .stats-row {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    .stat-chip {
        padding: 6px 16px;
        border-radius: 30px;
        font-size: 0.85rem;
        background: #f0f0f0;
    }
    .stat-chip.active {
        background: #0d6efd;
        color: white;
    }
    @media (max-width: 768px) {
        .parcels-header {
            flex-direction: column;
            gap: 1rem;
        }
        .search-form {
            flex-direction: column;
        }
        .search-input, .filter-select {
            width: 100%;
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
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">All Parcels</h1>
        <p class="text-muted mb-0">Search and filter parcels by tracking number or description.</p>
    </div>
</div>

<!-- Search Form -->
<div class="search-card">
    <form method="get" class="search-form">
        <input class="search-input" type="text" name="q" value="<?= h($q) ?>" placeholder="🔍 Search by tracking number or description...">
        <select class="filter-select" name="filter">
            <option value="all" <?= $filter === "all" ? "selected" : "" ?>>📋 All Parcels</option>
            <option value="active" <?= $filter === "active" ? "selected" : "" ?>>🟢 Active</option>
            <option value="completed" <?= $filter === "completed" ? "selected" : "" ?>>✅ Completed</option>
        </select>
        <button class="btn-primary" type="submit">Search</button>
        <a class="btn-outline" href="/DDXpress/staff/parcels.php">Reset</a>
    </form>
</div>

<!-- Quick Stats -->
<div class="stats-row">
    <?php
        $totalAll = count($rows);
        $totalActive = 0;
        $totalCompleted = 0;
        foreach ($rows as $r) {
            if (in_array($r["status"], $activeStatuses)) $totalActive++;
            if (in_array($r["status"], $completedStatuses)) $totalCompleted++;
        }
    ?>
    <span class="stat-chip <?= $filter === 'all' ? 'active' : '' ?>">📦 Total: <?= $totalAll ?></span>
    <span class="stat-chip <?= $filter === 'active' ? 'active' : '' ?>">🟢 Active: <?= $totalActive ?></span>
    <span class="stat-chip <?= $filter === 'completed' ? 'active' : '' ?>">✅ Completed: <?= $totalCompleted ?></span>
</div>

<!-- Parcels Table -->
<?php if (empty($rows)): ?>
    <div class="search-card" style="text-align: center;">
        <p class="text-muted">No parcels found.</p>
        <?php if ($q !== ""): ?>
            <p class="text-muted" style="margin-top: 0.5rem;">Try a different search term.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="search-card parcels-table">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Description</th>
                        <th>Weight</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Branch</th>
                        <th>Rider</th>
                        <th>Last Scan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><span class="tracking-code"><?= h($r["tracking_number"]) ?></span></td>
                            <td><?= h($r["description"]) ?></td>
                            <td><?= number_format((float)($r["weight_kg"] ?? 0), 2) ?> kg</td>
                            <td><?= h($r["customer_name"]) ?></td>
                            <td>
                                <span class="status-badge status-<?= h($r["status"]) ?>">
                                    <?= str_replace('_', ' ', h($r["status"])) ?>
                                </span>
                            </td>
                            <td><?= h($r["branch_name"] ?? "—") ?></td>
                            <td><?= h($r["rider_name"] ?? "—") ?></td>
                            <td><?= h($r["last_scan_at"] ?? "—") ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include("../layout/layout.php");
?>