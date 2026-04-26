<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["admin"]);

$q = trim($_GET["q"] ?? "");

if ($q !== "") {
    $like = "%" . $q . "%";
    $stmt = $conn->prepare("
        SELECT b.id, b.status, b.created_at,
               c.full_name AS customer_name,
               st.name AS service_name,
               ob.name AS origin_branch,
               db.name AS destination_branch,
               COALESCE(p.status,'PENDING') AS payment_status,
               COALESCE(p.amount,0) AS payment_amount
        FROM booking b
        JOIN customer c ON c.id = b.customer_id
        JOIN service_type st ON st.id = b.service_type_id
        JOIN branch ob ON ob.id = b.origin_branch_id
        JOIN branch db ON db.id = b.destination_branch_id
        LEFT JOIN payment p ON p.booking_id = b.id
        WHERE c.full_name LIKE ? OR c.email LIKE ? OR CAST(b.id AS CHAR) LIKE ?
        ORDER BY b.id DESC
        LIMIT 100
    ");
    $stmt->bind_param("sss", $like, $like, $like);
} else {
    $stmt = $conn->prepare("
        SELECT b.id, b.status, b.created_at,
               c.full_name AS customer_name,
               st.name AS service_name,
               ob.name AS origin_branch,
               db.name AS destination_branch,
               COALESCE(p.status,'PENDING') AS payment_status,
               COALESCE(p.amount,0) AS payment_amount
        FROM booking b
        JOIN customer c ON c.id = b.customer_id
        JOIN service_type st ON st.id = b.service_type_id
        JOIN branch ob ON ob.id = b.origin_branch_id
        JOIN branch db ON db.id = b.destination_branch_id
        LEFT JOIN payment p ON p.booking_id = b.id
        ORDER BY b.id DESC
        LIMIT 100
    ");
}

$stmt->execute();
$res = $stmt->get_result();
$bookings = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($bookings === null) {
    $bookings = [];
}

// Pull latest parcels for these bookings
$bookingIds = array_map(fn($b) => (int)$b["id"], $bookings);
$parcelsByBooking = [];
if ($bookingIds) {
    $placeholders = implode(",", array_fill(0, count($bookingIds), "?"));
    $types = str_repeat("i", count($bookingIds));
    $sql = "
        SELECT pr.booking_id, pr.tracking_number, pr.status, pr.description, pr.weight_kg,
               br.name AS branch_name,
               r.full_name AS rider_name
        FROM parcel pr
        LEFT JOIN branch br ON br.id = pr.current_branch_id
        LEFT JOIN staff r ON r.id = pr.assigned_rider_id
        WHERE pr.booking_id IN ($placeholders)
        ORDER BY pr.id DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$bookingIds);
    $stmt->execute();
    $res = $stmt->get_result();
    $parcels = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($parcels as $p) {
        $bid = (int)$p["booking_id"];
        $parcelsByBooking[$bid][] = $p;
    }
}

$pageTitle = "Bookings & Parcels";
ob_start();
?>

<style>
    .bookings-header {
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
    .search-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
    }
    .booking-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
        transition: transform 0.2s ease;
    }
    .booking-card:hover {
        transform: translateY(-2px);
    }
    .booking-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 1rem;
    }
    .booking-title {
        flex: 1;
    }
    .booking-id {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .customer-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: #333;
        margin-top: 0.25rem;
    }
    .route-info {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    .booking-status {
        text-align: right;
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
    .parcels-title {
        font-size: 1rem;
        font-weight: 600;
        margin: 1rem 0 0.75rem;
        color: #333;
    }
    .parcel-table {
        width: 100%;
        border-collapse: collapse;
    }
    .parcel-table th,
    .parcel-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
    }
    .parcel-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
        font-size: 0.8rem;
    }
    .parcel-table td {
        font-size: 0.85rem;
    }
    .tracking-code {
        font-family: monospace;
        background: #f0f0f0;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 0.8rem;
    }
    .empty-parcels {
        color: #6c757d;
        font-size: 0.85rem;
        padding: 0.5rem 0;
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
    .search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
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
    .no-bookings {
        text-align: center;
        padding: 3rem;
        background: white;
        border-radius: 20px;
        color: #6c757d;
    }
    @media (max-width: 768px) {
        .bookings-header {
            flex-direction: column;
            gap: 1rem;
        }
        .booking-header {
            flex-direction: column;
            gap: 0.5rem;
        }
        .booking-status {
            text-align: left;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .parcel-table th,
        .parcel-table td {
            padding: 6px;
            font-size: 0.7rem;
        }
        .tracking-code {
            font-size: 0.7rem;
        }
    }
</style>

<!-- Header -->
<div class="bookings-header">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Bookings & Parcels</h1>
        <p class="text-muted mb-0">Admin view across all customer bookings.</p>
    </div>
    <a class="btn-back" href="/DDXpress/admin/dashboard.php">← Back to Dashboard</a>
</div>

<!-- Welcome Card -->
<div class="welcome-card">
    <h2>Booking Management</h2>
    <p>Monitor all customer bookings, track parcels, and view delivery status.</p>
</div>

<!-- Stats -->
<?php
    $totalBookings = count($bookings);
    $totalParcels = 0;
    foreach ($parcelsByBooking as $parcels) {
        $totalParcels += count($parcels);
    }
?>
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-number"><?= $totalBookings ?></div>
        <div class="stat-label">Total Bookings</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= $totalParcels ?></div>
        <div class="stat-label">Total Parcels</div>
    </div>
</div>

<!-- Search Form -->
<div class="search-card">
    <form method="get" class="search-form">
        <input class="search-input" type="text" name="q" value="<?= h($q) ?>" placeholder="Search by customer name, email, or booking ID...">
        <button class="btn-primary" type="submit">🔍 Search</button>
        <a class="btn-outline" href="/DDXpress/admin/bookings.php">Reset</a>
    </form>
</div>

<!-- Bookings List -->
<?php if (empty($bookings)): ?>
    <div class="no-bookings">
        <p>📭 No bookings found.</p>
        <?php if ($q !== ""): ?>
            <p class="text-muted" style="margin-top: 0.5rem;">Try a different search term.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php foreach ($bookings as $b): ?>
        <div class="booking-card">
            <div class="booking-header">
                <div class="booking-title">
                    <div class="booking-id">Booking #<?= (int)$b["id"] ?></div>
                    <div class="customer-name">👤 <?= h($b["customer_name"]) ?></div>
                    <div class="route-info">
                        📍 <?= h($b["origin_branch"]) ?> → 📍 <?= h($b["destination_branch"]) ?>
                        • <?= h($b["service_name"]) ?>
                    </div>
                </div>
                <div class="booking-status">
                    <div>
                        <span class="status-badge status-<?= h($b["status"]) ?>">
                            <?= str_replace('_', ' ', h($b["status"])) ?>
                        </span>
                    </div>
                    <div style="margin-top: 0.5rem;">
                        <span class="status-badge payment-<?= h($b["payment_status"]) ?>">
                            💰 <?= h($b["payment_status"]) ?>
                        </span>
                    </div>
                    <div class="text-muted" style="font-size: 0.75rem; margin-top: 0.5rem;">
                        ₱<?= number_format((float)$b["payment_amount"], 2) ?>
                    </div>
                    <div class="text-muted" style="font-size: 0.7rem; margin-top: 0.25rem;">
                        📅 <?= date('M d, Y h:i A', strtotime($b["created_at"])) ?>
                    </div>
                </div>
            </div>

            <div class="parcels-title">📦 Parcels in this booking</div>
            <?php $plist = $parcelsByBooking[(int)$b["id"]] ?? []; ?>
            <?php if (!$plist): ?>
                <div class="empty-parcels">No parcels found for this booking.</div>
            <?php else: ?>
                <table class="parcel-table">
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Description</th>
                            <th>Weight</th>
                            <th>Status</th>
                            <th>Branch</th>
                            <th>Rider</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plist as $p): ?>
                            <tr>
                                <td><span class="tracking-code"><?= h($p["tracking_number"]) ?></span></td>
                                <td><?= h($p["description"]) ?></td>
                                <td><?= number_format((float)($p["weight_kg"] ?? 0), 2) ?> kg</td>
                                <td>
                                    <span class="status-badge status-<?= h($p["status"]) ?>">
                                        <?= str_replace('_', ' ', h($p["status"])) ?>
                                    </span>
                                 </td>
                                <td><?= h($p["branch_name"] ?? "—") ?></td>
                                <td><?= h($p["rider_name"] ?? "—") ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include("../layout/layout.php");
?>