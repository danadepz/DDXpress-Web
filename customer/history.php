<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["customer"]);
$customerId = (int)($_SESSION["user_id"] ?? 0);

$stmt = $conn->prepare("
    SELECT b.id, b.status, b.created_at,
           st.name AS service_name,
           ob.name AS origin_branch,
           db.name AS destination_branch,
           COALESCE(p.status, 'PENDING') AS payment_status,
           COALESCE(p.amount, 0) AS payment_amount,
           COALESCE(GROUP_CONCAT(pr.tracking_number ORDER BY pr.id SEPARATOR ', '), '') AS tracking_numbers
    FROM booking b
    JOIN service_type st ON st.id = b.service_type_id
    JOIN branch ob ON ob.id = b.origin_branch_id
    JOIN branch db ON db.id = b.destination_branch_id
    LEFT JOIN payment p ON p.booking_id = b.id
    LEFT JOIN parcel pr ON pr.booking_id = b.id
    WHERE b.customer_id = ?
    GROUP BY b.id, b.status, b.created_at, st.name, ob.name, db.name, p.status, p.amount
    ORDER BY b.id DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Initialize rows as empty array if null
if ($rows === null) {
    $rows = [];
}

$pageTitle = "Booking History";
ob_start();
?>

<!-- Header -->
<div class="header-row">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Booking History</h1>
        <p class="text-muted mb-0">View all your past and current bookings.</p>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($ok = get_flash("ok")): ?>
    <div class="alert alert-success mt-3" role="alert"><?= h($ok) ?></div>
<?php endif; ?>
<?php if ($err = get_flash("error")): ?>
    <div class="alert alert-danger mt-3" role="alert"><?= h($err) ?></div>
<?php endif; ?>

<!-- Bookings Table -->
<div class="history-card">
    <h3 style="font-family: 'ADLaM Display', serif; font-size: 1.3rem; margin-bottom: 1rem;">📋 All Bookings</h3>
    
    <?php if (empty($rows)): ?>
        <div class="text-muted text-center py-4">
            <p>No bookings found.</p>
            <a class="btn-pill" href="/DDXpress/customer/create_booking.php" style="margin-top: 10px;">Create Your First Booking</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="booking-table">
                <thead>
                    <tr>
                        <th>Booking #</th>
                        <th>Route</th>
                        <th>Service</th>
                        <th>Tracking Numbers</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><strong>#<?= (int)$r["id"] ?></strong></td>
                            <td>
                                <span class="route-arrow">
                                    <?= h($r["origin_branch"]) ?> → <?= h($r["destination_branch"]) ?>
                                </span>
                            </td>
                            <td><?= h($r["service_name"]) ?></td>
                            <td>
                                <?php if (($r["tracking_numbers"] ?? "") !== ""): ?>
                                    <?php 
                                        $trackingNumbers = explode(', ', $r["tracking_numbers"]);
                                        foreach ($trackingNumbers as $tn): 
                                    ?>
                                        <code class="tracking-code"><?= h($tn) ?></code><br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= h($r["status"]) ?>">
                                    <?= str_replace('_', ' ', h($r["status"])) ?>
                                </span>
                            </td>
                            <td>
                                <div>
                                    <span class="payment-badge payment-<?= h($r["payment_status"]) ?>">
                                        <?= h($r["payment_status"]) ?>
                                    </span>
                                </div>
                                <small class="text-muted">$<?= number_format((float)$r["payment_amount"], 2) ?></small>
                            </td>
                            <td><?= date('M d, Y h:i A', strtotime($r["created_at"])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
    .history-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        margin-top: 0;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    
    .booking-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .booking-table th,
    .booking-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
    }
    
    .booking-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .booking-table tr:hover {
        background: #f8f9fa;
    }
    
    .route-arrow {
        font-weight: 500;
    }
    
    .tracking-code {
        display: inline-block;
        background: #f0f0f0;
        color: #0d6efd;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-family: monospace;
        margin: 2px 0;
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
    .status-PENDING_DROP_OFF { background: #fd7e14; color: #fff; }
    
    .payment-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .payment-PENDING { background: #ffc107; color: #000; }
    .payment-PAID { background: #198754; color: #fff; }
    .payment-FAILED { background: #dc3545; color: #fff; }
    
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
        .history-card {
            padding: 1rem;
        }
        
        .header-row {
            flex-direction: column;
            gap: 1rem;
        }
        
        .btn-pill, .btn-pill-outline {
            width: 100%;
            text-align: center;
        }
        
        .booking-table th,
        .booking-table td {
            padding: 8px;
            font-size: 0.8rem;
        }
        
        .tracking-code {
            font-size: 0.65rem;
        }
    }
</style>

<?php
$content = ob_get_clean();
include("../layout/layout.php");
?>