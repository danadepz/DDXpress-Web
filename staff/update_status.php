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

// Fetch parcels for dropdown (only parcels that need inspection/update)
$stmt = $conn->prepare("
    SELECT pr.id, pr.tracking_number, pr.description, pr.status, pr.current_branch_id, pr.weight_kg,
           cb.full_name AS customer_name,
           b.id AS booking_id,
           p.id AS payment_id,
           p.amount AS payment_amount,
           p.method AS payment_method,
           p.status AS payment_status,
           p.paid_at AS payment_date
    FROM parcel pr
    JOIN booking b ON b.id = pr.booking_id
    JOIN customer cb ON cb.id = b.customer_id
    LEFT JOIN payment p ON p.booking_id = b.id
    WHERE (pr.current_branch_id = ? OR pr.current_branch_id IS NULL)
      AND pr.status != 'DELIVERED'
      AND pr.status != 'DECLINED'
    ORDER BY pr.id DESC
    LIMIT 200
");
$stmt->bind_param("i", $branchId);
$stmt->execute();
$res = $stmt->get_result();
$parcels = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($parcels === null) {
    $parcels = [];
}

$statuses = [
    "PENDING_INSPECTION" => "Pending Inspection",
    "READY_FOR_PICKUP" => "Ready for Pickup",
    "IN_TRANSIT" => "In Transit",
    "OUT_FOR_DELIVERY" => "Out for Delivery",
    "DELIVERED" => "Delivered",
    "DECLINED" => "Declined",
];

$error = null;
$ok = null;
$selectedParcel = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $parcelId = (int)($_POST["parcel_id"] ?? 0);
    $newStatus = $_POST["status"] ?? "";
    $note = trim($_POST["note"] ?? "");
    
    // Payment fields that staff enters
    $paymentAmount = (float)($_POST["payment_amount"] ?? 0);
    $paymentMethod = $_POST["payment_method"] ?? "";
    $paymentReference = trim($_POST["payment_reference"] ?? "");
    $paymentStatus = $_POST["payment_status"] ?? "PAID";

    if ($parcelId === 0 || !in_array($newStatus, array_keys($statuses), true)) {
        $error = "Please select a parcel and valid status.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                SELECT id, tracking_number, description, status, current_branch_id, booking_id
                FROM parcel 
                WHERE id = ? 
                LIMIT 1 FOR UPDATE
            ");
            $stmt->bind_param("i", $parcelId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$row) {
                throw new RuntimeException("Parcel not found");
            }

            $selectedParcel = $row;
            $oldStatus = $row["status"];
            $bookingId = $row["booking_id"];
            
            // Update parcel status
            $stmt = $conn->prepare("UPDATE parcel SET status = ?, current_branch_id = ?, last_scan_at = NOW() WHERE id = ?");
            $stmt->bind_param("sii", $newStatus, $branchId, $parcelId);
            $stmt->execute();
            $stmt->close();

            $historyNote = $note ?: "Status updated by staff from " . $oldStatus . " to " . $newStatus;
            
            $stmt = $conn->prepare("
                INSERT INTO parcel_status_history (parcel_id, status, branch_id, note, updated_by_staff_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isisi", $parcelId, $newStatus, $branchId, $historyNote, $staffId);
            $stmt->execute();
            $stmt->close();

            // STAFF ENTERS/EDITS PAYMENT INFORMATION
            if ($paymentAmount > 0 && !empty($paymentMethod)) {
                // Map payment method to database values
                $methodMap = [
                    "CASH" => "CASH",
                    "COD" => "CASH",
                    "GCASH" => "GCASH",
                    "CREDIT_CARD" => "CARD",
                    "BANK_TRANSFER" => "CARD",
                ];
                $paymentMethodDb = $methodMap[$paymentMethod] ?? "CASH";
                
                // Generate reference if not provided
                $finalReference = $paymentReference;
                if (empty($finalReference) && ($paymentMethod === 'GCASH' || $paymentMethod === 'CREDIT_CARD' || $paymentMethod === 'BANK_TRANSFER')) {
                    $finalReference = "REF_" . strtoupper(uniqid());
                }

                // Check if payment already exists for this booking
                $stmt = $conn->prepare("SELECT id FROM payment WHERE booking_id = ?");
                $stmt->bind_param("i", $bookingId);
                $stmt->execute();
                $res = $stmt->get_result();
                $existingPayment = $res ? $res->fetch_assoc() : null;
                $stmt->close();

                if ($existingPayment) {
                    // Update existing payment
                    $stmt = $conn->prepare("
                        UPDATE payment 
                        SET amount = ?,
                            method = ?,
                            status = ?,
                            paid_at = NOW()
                        WHERE booking_id = ?
                    ");
                    $stmt->bind_param("dssi", $paymentAmount, $paymentMethodDb, $paymentStatus, $bookingId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $paymentAction = "updated";
                } else {
                    // Insert new payment
                    $stmt = $conn->prepare("
                        INSERT INTO payment (booking_id, amount, method, status, paid_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("idss", $bookingId, $paymentAmount, $paymentMethodDb, $paymentStatus);
                    $stmt->execute();
                    $stmt->close();
                    
                    $paymentAction = "recorded";
                }
                
                // Add payment history entry
                $paymentNote = "💰 Payment " . $paymentAction . ": " . number_format($paymentAmount, 2) . " via " . $paymentMethod;
                if ($finalReference) {
                    $paymentNote .= " (Ref: " . $finalReference . ")";
                }
                $paymentNote .= " - Status: " . $paymentStatus;
                
                $stmt = $conn->prepare("
                    INSERT INTO parcel_status_history (parcel_id, status, branch_id, note, updated_by_staff_id)
                    VALUES (?, 'PAYMENT_RECORDED', ?, ?, ?)
                ");
                $stmt->bind_param("iisi", $parcelId, $branchId, $paymentNote, $staffId);
                $stmt->execute();
                $stmt->close();
            } else if ($paymentAmount == 0 && $newStatus == 'DECLINED') {
                // No payment recorded for declined parcel - that's fine
            } else if ($paymentAmount == 0 && $newStatus != 'PENDING_INSPECTION') {
                $error = "Please enter payment amount before updating status.";
                throw new RuntimeException("Payment amount required");
            }

            // Keep booking status in sync so customer dashboard reflects latest parcel updates.
            $stmt = $conn->prepare("
                SELECT
                    SUM(CASE WHEN status = 'DELIVERED' THEN 1 ELSE 0 END) AS delivered_count,
                    SUM(CASE WHEN status IN ('IN_TRANSIT', 'OUT_FOR_DELIVERY') THEN 1 ELSE 0 END) AS moving_count,
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

            $bookingStatus = "CREATED";
            if ($totalCount > 0 && $deliveredCount === $totalCount) {
                $bookingStatus = "DELIVERED";
            } elseif ($movingCount > 0) {
                $bookingStatus = "IN_TRANSIT";
            } elseif ($paymentAmount > 0 && strtoupper((string)$paymentStatus) === "PAID") {
                $bookingStatus = "PAID";
            }

            $stmt = $conn->prepare("UPDATE booking SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $bookingStatus, $bookingId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $ok = "Parcel status updated successfully!";
            if ($paymentAmount > 0) {
                $ok .= " Payment has been recorded.";
            }
            
            // Refresh parcels list
            $stmt = $conn->prepare("
                SELECT pr.id, pr.tracking_number, pr.description, pr.status, pr.current_branch_id, pr.weight_kg,
                       cb.full_name AS customer_name,
                       b.id AS booking_id,
                       p.id AS payment_id,
                       p.amount AS payment_amount,
                       p.method AS payment_method,
                       p.status AS payment_status,
                       '' AS payment_reference,
                       p.paid_at AS payment_date
                FROM parcel pr
                JOIN booking b ON b.id = pr.booking_id
                JOIN customer cb ON cb.id = b.customer_id
                LEFT JOIN payment p ON p.booking_id = b.id
                WHERE (pr.current_branch_id = ? OR pr.current_branch_id IS NULL)
                  AND pr.status != 'DELIVERED'
                  AND pr.status != 'DECLINED'
                ORDER BY pr.id DESC
                LIMIT 200
            ");
            $stmt->bind_param("i", $branchId);
            $stmt->execute();
            $res = $stmt->get_result();
            $parcels = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        } catch (Throwable $e) {
            $conn->rollback();
            if ($error === "") {
                $error = "Update failed. Please try again.";
            }
        }
    }
}

$pageTitle = "Update Parcel Status";
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
        max-width: 700px;
        margin: 0 auto;
    }
    .update-card h3 {
        font-family: 'ADLaM Display', serif;
        font-size: 1.3rem;
        margin-bottom: 1rem;
        text-align: center;
    }
    .payment-card {
        background: #f8f9fa;
        border-radius: 16px;
        padding: 1.25rem;
        margin-bottom: 1.25rem;
        border: 1px solid #e5e7eb;
    }
    .payment-card h4 {
        font-family: 'ADLaM Display', serif;
        font-size: 1.1rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e0e0e0;
    }
    .form-group {
        margin-bottom: 1rem;
    }
    .form-label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: block;
        color: #333;
    }
    .form-control, .form-select {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 0.95rem;
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
    .radio-group {
        display: flex;
        gap: 1rem;
        align-items: center;
        margin-top: 0.5rem;
    }
    .radio-group .form-check {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .tracking-option {
        font-family: monospace;
    }
    .parcel-detail {
        font-size: 0.85rem;
        color: #6c757d;
        margin-left: 0.5rem;
    }
    .small-text {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    @media (max-width: 768px) {
        .status-header {
            flex-direction: column;
            gap: 1rem;
        }
        .update-card {
            margin: 0 0.5rem;
            padding: 1rem;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .radio-group {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<!-- Header -->
<div class="status-header">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Update Parcel Status</h1>
        <p class="text-muted mb-0">Select a parcel, update status, and record payment.</p>
    </div>
</div>

<!-- Welcome Card -->
<div class="welcome-card">
    <h2>Welcome, <?= h($staffName) ?>!</h2>
    <p>Branch ID: <?= (int)$branchId ?> | Record payment and update parcel status.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-number"><?= count($parcels) ?></div>
        <div class="stat-label">Active Parcels</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= (int)$branchId ?></div>
        <div class="stat-label">Your Branch ID</div>
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
    <h3>📝 Record Payment & Update Status</h3>
    
    <?php if (empty($parcels)): ?>
        <div class="alert alert-info" role="alert">
            ℹ️ No active parcels found in your branch.
        </div>
    <?php else: ?>
        <form method="post" id="updateForm">
            <!-- Select Parcel -->
            <div class="form-group">
                <label class="form-label">Select Parcel <span class="required-star">*</span></label>
                <select class="form-select" name="parcel_id" id="parcel_select" required>
                    <option value="">— Select a parcel —</option>
                    <?php foreach ($parcels as $p): ?>
                        <option value="<?= (int)$p["id"] ?>">
                            <span class="tracking-option"><?= h($p["tracking_number"]) ?></span>
                            <span class="parcel-detail">- <?= h($p["description"]) ?> (<?= str_replace('_', ' ', h($p["status"])) ?>)</span>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Parcel Details Display -->
            <div id="parcel_details" style="display: none; background: #f0f8ff; border-radius: 12px; padding: 0.75rem; margin-bottom: 1rem;">
                <div><strong>📦 Tracking:</strong> <span id="detail_tracking"></span></div>
                <div><strong>📝 Description:</strong> <span id="detail_description"></span></div>
                <div><strong>👤 Customer:</strong> <span id="detail_customer"></span></div>
            </div>

            <!-- Payment Section - Staff ENTERS payment -->
            <div class="payment-card">
                <h4>💰 Enter Payment Details</h4>
                
                <div class="form-group">
                    <label class="form-label">Payment Amount <span class="required-star">*</span></label>
                    <input class="form-control" type="number" step="0.01" min="0" name="payment_amount" id="payment_amount" placeholder="0.00" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Payment Method <span class="required-star">*</span></label>
                    <select class="form-select" name="payment_method" id="payment_method" required>
                        <option value="">— Select Payment Method —</option>
                        <option value="CASH">💵 Cash</option>
                        <option value="GCASH">📱 GCash</option>
                        <option value="CREDIT_CARD">💳 Credit Card</option>
                        <option value="BANK_TRANSFER">🏦 Bank Transfer</option>
                    </select>
                </div>
                
                <div class="form-group" id="referenceSection" style="display: none;">
                    <label class="form-label">Reference Number</label>
                    <input class="form-control" type="text" name="payment_reference" id="payment_reference" placeholder="Enter transaction/reference number">
                    <div class="small-text">Required for GCash, Credit Card, and Bank Transfer</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Payment Status</label>
                    <div class="radio-group">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_status" id="status_paid" value="PAID" checked>
                            <label class="form-check-label" for="status_paid">✅ Paid</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_status" id="status_pending" value="PENDING">
                            <label class="form-check-label" for="status_pending">⏳ Pending</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Status -->
            <div class="form-group">
                <label class="form-label">Update Parcel Status <span class="required-star">*</span></label>
                <select class="form-select" name="status" id="status_select" required>
                    <option value="">— Select new status —</option>
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?= h($key) ?>"><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Note -->
            <div class="form-group">
                <label class="form-label">Note (Optional)</label>
                <input class="form-control" type="text" name="note" placeholder="Add a note about this update...">
            </div>

            <button class="btn-pill" type="submit">💾 Save Changes</button>
        </form>
    <?php endif; ?>
</div>

<script>
    const parcelSelect = document.getElementById('parcel_select');
    const paymentMethodSelect = document.getElementById('payment_method');
    const referenceSection = document.getElementById('referenceSection');
    const parcelDetails = document.getElementById('parcel_details');
    
    // Store parcels data from PHP
    const parcelsData = <?php echo json_encode($parcels); ?>;
    
    // Update parcel details when selected
    if (parcelSelect) {
        parcelSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            if (!selectedValue) {
                parcelDetails.style.display = 'none';
                return;
            }
            
            // Find the selected parcel
            const selectedParcel = parcelsData.find(p => p.id == selectedValue);
            if (selectedParcel) {
                document.getElementById('detail_tracking').innerText = selectedParcel.tracking_number;
                document.getElementById('detail_description').innerText = selectedParcel.description;
                document.getElementById('detail_customer').innerText = selectedParcel.customer_name;
                parcelDetails.style.display = 'block';
            } else {
                parcelDetails.style.display = 'none';
            }
        });
    }
    
    // Show/hide reference field based on payment method
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', function() {
            const method = this.value;
            if (method === 'GCASH' || method === 'CREDIT_CARD' || method === 'BANK_TRANSFER') {
                referenceSection.style.display = 'block';
            } else {
                referenceSection.style.display = 'none';
            }
        });
    }
</script>

<?php
$content = ob_get_clean();
include("../layout/layout.php");
?>