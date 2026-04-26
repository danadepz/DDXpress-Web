<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["customer"]);
$customerId = (int)($_SESSION["user_id"] ?? 0);

// Get KPI data
$stmt = $conn->prepare("
    SELECT
      COUNT(*) AS total_created,
      SUM(CASE WHEN pr.status = 'DELIVERED' THEN 1 ELSE 0 END) AS total_delivered
    FROM parcel pr
    JOIN booking b ON b.id = pr.booking_id
    WHERE b.customer_id = ?
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$res = $stmt->get_result();
$kpi = $res ? $res->fetch_assoc() : null;
$stmt->close();

// Initialize variables with default values
$totalCreated = isset($kpi["total_created"]) ? (int)$kpi["total_created"] : 0;
$totalDelivered = isset($kpi["total_delivered"]) ? (int)$kpi["total_delivered"] : 0;

// Get bookings data
$stmt = $conn->prepare("
    SELECT b.id, b.status, b.created_at,
           st.name AS service_name,
           ob.name AS origin_branch,
           db.name AS destination_branch,
           COALESCE(p.status, 'PENDING') AS payment_status,
           COALESCE(p.amount, 0) AS payment_amount,
           (SELECT COUNT(*) FROM parcel pr WHERE pr.booking_id = b.id) AS parcel_count,
           COALESCE((SELECT GROUP_CONCAT(pr.tracking_number ORDER BY pr.id SEPARATOR ', ')
                     FROM parcel pr
                     WHERE pr.booking_id = b.id), '') AS tracking_numbers
    FROM booking b
    JOIN service_type st ON st.id = b.service_type_id
    JOIN branch ob ON ob.id = b.origin_branch_id
    JOIN branch db ON db.id = b.destination_branch_id
    LEFT JOIN payment p ON p.booking_id = b.id
    WHERE b.customer_id = ?
    ORDER BY b.id DESC
    LIMIT 50
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$res = $stmt->get_result();
$bookings = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Make sure $bookings is always an array
if ($bookings === null) {
    $bookings = [];
}

$pageTitle = "Customer Dashboard";
ob_start();
?>

<!-- Header -->
<div class="header-row">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">My Dashboard</h1>
        <p class="text-muted mb-0">Manage your bookings, track parcels, and view history.</p>
    </div>
    <div class="d-flex gap-3">
        <a class="btn-pill" href="/DDXpress/customer/create_booking.php">+ Create New Booking</a>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($ok = get_flash("ok")): ?>
    <div class="alert alert-success mt-3" role="alert"><?= h($ok) ?></div>
<?php endif; ?>
<?php if ($err = get_flash("error")): ?>
    <div class="alert alert-danger mt-3" role="alert"><?= h($err) ?></div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-label">Total Parcels Created</div>
            <div class="stat-number"><?= $totalCreated ?></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-label">Total Parcels Delivered</div>
            <div class="stat-number"><?= $totalDelivered ?></div>
        </div>
    </div>
</div>

<!-- Bookings Table -->
<div class="bookings-card">
    <h3 style="font-family: 'ADLaM Display', serif; font-size: 1.3rem; margin-bottom: 1rem;">Recent Bookings</h3>
    
    <?php if (empty($bookings)): ?>
        <div class="text-muted text-center py-4">
            <p>No bookings yet.</p>
            <a class="btn-pill" href="/DDXpress/customer/create_booking.php" style="margin-top: 10px;">Create Your First Booking</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking #</th>
                        <th>Route</th>
                        <th>Service</th>
                        <th>Parcels</th>
                        <th>Tracking Numbers</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><strong><?= (int)$b["id"] ?></strong></td>
                            <td><?= h($b["origin_branch"]) ?> → <?= h($b["destination_branch"]) ?></td>
                            <td><?= h($b["service_name"]) ?></td>
                            <td><?= (int)$b["parcel_count"] ?></td>
                            <td>
                                <?php if (($b["tracking_numbers"] ?? "") !== ""): ?>
                                    <?php foreach (explode(", ", (string)$b["tracking_numbers"]) as $trackingNumber): ?>
                                        <div class="tracking-row">
                                            <code class="tracking-code"><?= h($trackingNumber) ?></code>
                                            <button
                                                class="copy-btn"
                                                type="button"
                                                data-copy="<?= h($trackingNumber) ?>"
                                                title="Copy tracking number"
                                                aria-label="Copy tracking number"
                                            >📋</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= h($b["status"]) ?>">
                                    <?= str_replace('_', ' ', h($b["status"])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge payment-<?= h($b["payment_status"]) ?>">
                                    <?= h($b["payment_status"]) ?>
                                </span>
                                <small class="text-muted d-block">$<?= number_format((float)$b["payment_amount"], 2) ?></small>
                            </td>
                            <td><?= date('M d, Y', strtotime($b["created_at"])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        text-align: center;
        transition: transform 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }
    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin-top: 0.5rem;
    }
    .stat-label {
        color: #6c757d;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .bookings-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        margin-top: 1.5rem;
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
    .status-CREATED { background: #ffc107; color: #000; }
    .status-CONFIRMED { background: #17a2b8; color: #fff; }
    .status-IN_TRANSIT { background: #0d6efd; color: #fff; }
    .status-DELIVERED { background: #198754; color: #fff; }
    .status-CANCELLED { background: #dc3545; color: #fff; }
    .payment-PENDING { background: #ffc107; color: #000; }
    .payment-PAID { background: #198754; color: #fff; }
    .payment-FAILED { background: #dc3545; color: #fff; }
    .tracking-row {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 4px;
    }
    .tracking-code {
        background: #f0f0f0;
        color: #0d6efd;
        padding: 4px 10px;
        border-radius: 20px;
        font-family: monospace;
        font-size: 1rem;
    }
    .copy-btn {
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 1.2rem;
        line-height: 1;
        padding: 2px;
    }
    .copy-btn:hover {
        transform: scale(1.1);
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
    .header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    @media (max-width: 768px) {
        .header-row {
            flex-direction: column;
            gap: 1rem;
        }
        .btn-pill, .btn-pill-outline {
            width: 100%;
            text-align: center;
        }
        th, td {
            padding: 8px;
            font-size: 0.85rem;
        }
    }
</style>

<script>
    (function () {
        const copyButtons = document.querySelectorAll(".copy-btn[data-copy]");
        copyButtons.forEach((btn) => {
            btn.addEventListener("click", async function () {
                const value = btn.getAttribute("data-copy");
                if (!value) return;
                try {
                    await navigator.clipboard.writeText(value);
                    const previous = btn.textContent;
                    btn.textContent = "✅";
                    setTimeout(() => {
                        btn.textContent = previous;
                    }, 900);
                } catch (e) {
                    // Ignore clipboard errors silently for older browsers.
                }
            });
        });
    })();
</script>

<?php
$content = ob_get_clean();
include("../layout/layout.php");
?>