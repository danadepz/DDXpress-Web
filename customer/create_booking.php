<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["customer"]);
$customerId = (int)($_SESSION["user_id"] ?? 0);

$res = $conn->query("SELECT id, name, base_fee, per_kg_fee, eta_days FROM service_type ORDER BY id ASC");
$serviceTypes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$res = $conn->query("SELECT id, name FROM branch ORDER BY id ASC");
$branches = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$error = null;

function gen_tracking(): string
{
    return "DDX-" . strtoupper(bin2hex(random_bytes(4)));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $serviceTypeId = (int)($_POST["service_type_id"] ?? 0);
    $originBranchId = (int)($_POST["origin_branch_id"] ?? 0);
    $destinationBranchId = (int)($_POST["destination_branch_id"] ?? 0);

    $senderName = trim($_POST["sender_name"] ?? "");
    $senderPhone = trim($_POST["sender_phone"] ?? "");
    $senderAddress = trim($_POST["sender_address"] ?? "");

    $receiverName = trim($_POST["receiver_name"] ?? "");
    $receiverPhone = trim($_POST["receiver_phone"] ?? "");
    $receiverAddress = trim($_POST["receiver_address"] ?? "");

    $notes = trim($_POST["notes"] ?? "");

    $descriptions = $_POST["parcel_description"] ?? [];
    $weights = $_POST["parcel_weight"] ?? [];
    $values = $_POST["parcel_value"] ?? [];

    $parcels = [];
    $count = min(count($descriptions), count($weights), count($values));
    for ($i = 0; $i < $count; $i++) {
        $desc = trim((string)$descriptions[$i]);
        $w = (float)$weights[$i];
        $v = (float)$values[$i];
        if ($desc === "") {
            continue;
        }
        if ($w < 0) $w = 0;
        if ($v < 0) $v = 0;
        $parcels[] = ["description" => $desc, "weight" => $w, "value" => $v];
    }

    if (!$serviceTypeId || !$originBranchId || !$destinationBranchId) {
        $error = "Please select service type and branches.";
    } elseif ($senderName === "" || $receiverName === "") {
        $error = "Sender and receiver names are required.";
    } elseif (count($parcels) === 0) {
        $error = "Add at least one parcel.";
    } else {
        $stmt = $conn->prepare("SELECT base_fee, per_kg_fee FROM service_type WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $serviceTypeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $svc = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$svc) {
            $error = "Invalid service type.";
        } else {
            $baseFee = (float)$svc["base_fee"];
            $perKg = (float)$svc["per_kg_fee"];
            $totalWeight = 0.0;
            foreach ($parcels as $p) $totalWeight += (float)$p["weight"];
            $amount = $baseFee + ($totalWeight * $perKg);

            $conn->begin_transaction();
            try {
                $status = "CREATED";
                $stmt = $conn->prepare("
                    INSERT INTO booking (
                      customer_id, service_type_id, origin_branch_id, destination_branch_id,
                      sender_name, sender_phone, sender_address,
                      receiver_name, receiver_phone, receiver_address,
                      notes, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iiiissssssss",
                    $customerId, $serviceTypeId, $originBranchId, $destinationBranchId,
                    $senderName, $senderPhone, $senderAddress,
                    $receiverName, $receiverPhone, $receiverAddress,
                    $notes, $status
                );
                $stmt->execute();
                $bookingId = (int)$stmt->insert_id;
                $stmt->close();

                $payStatus = "PENDING";
                $method = "CASH";
                $stmt = $conn->prepare("INSERT INTO payment (booking_id, amount, method, status) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("idss", $bookingId, $amount, $method, $payStatus);
                $stmt->execute();
                $stmt->close();

                foreach ($parcels as $p) {
                    $tracking = gen_tracking();
                    $parcelStatus = "PENDING_DROP_OFF";
                    $desc = $p["description"];
                    $val = (float)$p["value"];
                    $w = (float)$p["weight"];
                    $stmt = $conn->prepare("
                        INSERT INTO parcel (booking_id, tracking_number, description, declared_value, weight_kg, status, current_branch_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("issddsi", $bookingId, $tracking, $desc, $val, $w, $parcelStatus, $originBranchId);
                    $stmt->execute();
                    $parcelId = (int)$stmt->insert_id;
                    $stmt->close();

                    $note = "Booking created by customer";
                    $stmt = $conn->prepare("
                        INSERT INTO parcel_status_history (parcel_id, status, branch_id, note)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->bind_param("isis", $parcelId, $parcelStatus, $originBranchId, $note);
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();
                set_flash("ok", "Booking created successfully! Drop off parcels at the origin branch.");
                redirect("/DDXpress/customer/create_booking.php");
            } catch (Throwable $e) {
                $conn->rollback();
                $error = "Failed to create booking. Please try again.";
            }
        }
    }
}

$pageTitle = "Create Booking";
ob_start();
?>

<!-- Header -->
<div class="header-row">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Create New Booking</h1>
        <p class="text-muted mb-0">Add multiple parcels under one booking.</p>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($ok = get_flash("ok")): ?>
    <div class="alert alert-success mt-3" role="alert"><?= h($ok) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger mt-3" role="alert"><?= h($error) ?></div>
<?php endif; ?>

<form method="post">
    <!-- Service & Branches -->
    <div class="booking-card">
        <h3>📍 Service & Branches</h3>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Service Type <span class="required-star">*</span></label>
                <select class="form-select" name="service_type_id" required>
                    <option value="">Select...</option>
                    <?php foreach ($serviceTypes as $s): ?>
                        <option value="<?= (int)$s["id"] ?>">
                            <?= h($s["name"]) ?> (Base $<?= number_format((float)$s["base_fee"],2) ?>, +$<?= number_format((float)$s["per_kg_fee"],2) ?>/kg, ETA <?= (int)$s["eta_days"] ?>d)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Origin Branch <span class="required-star">*</span></label>
                <select class="form-select" name="origin_branch_id" required>
                    <option value="">Select...</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b["id"] ?>"><?= h($b["name"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Destination Branch <span class="required-star">*</span></label>
                <select class="form-select" name="destination_branch_id" required>
                    <option value="">Select...</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b["id"] ?>"><?= h($b["name"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Sender Information -->
    <div class="booking-card">
        <h3>👤 Sender Information</h3>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name <span class="required-star">*</span></label>
                <input class="form-control" type="text" name="sender_name" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input class="form-control" type="text" name="sender_phone">
            </div>
            <div class="col-12">
                <label class="form-label">Address</label>
                <input class="form-control" type="text" name="sender_address">
            </div>
        </div>
    </div>

    <!-- Receiver Information -->
    <div class="booking-card">
        <h3>📦 Receiver Information</h3>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name <span class="required-star">*</span></label>
                <input class="form-control" type="text" name="receiver_name" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input class="form-control" type="text" name="receiver_phone">
            </div>
            <div class="col-md-8">
                <label class="form-label">Address</label>
                <input class="form-control" type="text" name="receiver_address">
            </div>
            <div class="col-md-4">
                <label class="form-label">Notes</label>
                <input class="form-control" type="text" name="notes" placeholder="Special instructions...">
            </div>
        </div>
    </div>

    <!-- Parcels Section -->
    <div class="booking-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 style="margin: 0;">📋 Parcels</h3>
            <button class="btn-pill-add" type="button" id="addParcel">+ Add Parcel</button>
        </div>

        <div id="parcelList">
            <div class="parcel-row" data-parcel-row>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Description <span class="required-star">*</span></label>
                        <input class="form-control" type="text" name="parcel_description[]" placeholder="What's inside?" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Weight (kg)</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="parcel_weight[]" value="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Declared Value ($)</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="parcel_value[]" value="0">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn-pill-danger w-100 removeParcel" type="button">Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="d-flex gap-3 mt-3">
        <button class="btn-pill" type="submit">✅ Create Booking</button>
        <a class="btn-pill-outline" href="/DDXpress/customer/dashboard.php">Cancel</a>
    </div>
</form>

<style>
    .booking-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
    }
    .booking-card h3 {
        font-family: "ADLaM Display", serif;
        font-size: 1.3rem;
        margin-bottom: 1rem;
        color: #333;
    }
    .form-control, .form-select {
        border-radius: 12px;
        padding: 10px 15px;
        border: 2px solid #e0e0e0;
        width: 100%;
        transition: all 0.3s;
    }
    .form-control:focus, .form-select:focus {
        border-color: #0d6efd;
        outline: none;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
    }
    .form-label {
        font-weight: 500;
        margin-bottom: 5px;
        color: #333;
        font-size: 0.9rem;
    }
    .required-star {
        color: #dc3545;
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
    .btn-pill-danger {
        padding: 8px 16px;
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
    .btn-pill-danger:hover {
        background: transparent;
        border-color: #dc3545;
        color: #dc3545;
    }
    .btn-pill-add {
        padding: 8px 20px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        border: 2px solid #198754;
        display: inline-block;
        background: #198754;
        color: white;
        cursor: pointer;
    }
    .btn-pill-add:hover {
        background: transparent;
        border-color: #198754;
        color: #198754;
    }
    .parcel-row {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    .header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    @media (max-width: 768px) {
        .booking-card {
            padding: 1rem;
        }
        .header-row {
            flex-direction: column;
            gap: 1rem;
        }
        .btn-pill, .btn-pill-outline, .btn-pill-add {
            width: 100%;
            text-align: center;
        }
    }
</style>

<script>
    (function() {
        const list = document.getElementById('parcelList');
        const addBtn = document.getElementById('addParcel');

        function wireRow(row) {
            const btn = row.querySelector('.removeParcel');
            if (btn) {
                btn.addEventListener('click', function() {
                    const rows = list.querySelectorAll('[data-parcel-row]');
                    if (rows.length <= 1) return;
                    row.remove();
                });
            }
        }

        list.querySelectorAll('[data-parcel-row]').forEach(wireRow);

        addBtn.addEventListener('click', function() {
            const tpl = list.querySelector('[data-parcel-row]');
            const clone = tpl.cloneNode(true);
            clone.querySelectorAll('input').forEach(i => {
                if (i.type === 'number') {
                    i.value = '0';
                } else {
                    i.value = '';
                }
            });
            wireRow(clone);
            list.appendChild(clone);
        });
    })();
</script>

<?php
$content = ob_get_clean();
include("../layout/layout.php");
?>