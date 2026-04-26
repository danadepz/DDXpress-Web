<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["rider"]);
$riderId = (int)($_SESSION["user_id"] ?? 0);

$error = null;
$ok = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tracking = trim($_POST["tracking"] ?? "");
    $note = trim($_POST["note"] ?? "Scanned by rider");

    if ($tracking === "") {
        $error = "Tracking number is required.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id, status FROM parcel WHERE tracking_number = ? AND assigned_rider_id = ? LIMIT 1 FOR UPDATE");
            $stmt->bind_param("si", $tracking, $riderId);
            $stmt->execute();
            $res = $stmt->get_result();
            $p = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$p) throw new RuntimeException("Parcel not found/assigned");

            $parcelId = (int)$p["id"];
            $status = (string)$p["status"];

            $stmt = $conn->prepare("UPDATE parcel SET last_scan_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $parcelId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO parcel_status_history (parcel_id, status, branch_id, note, updated_by_rider_id)
                VALUES (?, ?, NULL, ?, ?)
            ");
            $stmt->bind_param("issi", $parcelId, $status, $note, $riderId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $ok = "Scan recorded.";
        } catch (Throwable $e) {
            $conn->rollback();
            $error = "Scan failed.";
        }
    }
}

$pageTitle = "Scan Parcel";
ob_start();
?>
<div class="row" style="justify-content: space-between;">
  <div>
    <h2 style="margin:0;">Scan Parcel (Rider)</h2>
    <div class="muted">Adds a history entry for assigned parcels.</div>
  </div>
  <div class="row">
    <a class="btn" href="/DDXpress/rider/dashboard.php">Back</a>
  </div>
</div>

<?php if ($ok): ?>
  <div class="ok" style="margin-top: 12px;"><?= h($ok) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="error" style="margin-top: 12px;"><?= h($error) ?></div>
<?php endif; ?>

<div class="card" style="margin-top: 14px; max-width: 720px;">
  <form method="post">
    <div style="margin-bottom: 10px;">
      <label class="muted">Tracking number *</label><br/>
      <input class="field" name="tracking" placeholder="e.g. DDX-000001" required />
    </div>
    <div style="margin-bottom: 14px;">
      <label class="muted">Note</label><br/>
      <input class="field" name="note" value="Scanned by rider" />
    </div>
    <button class="btn btn-primary" type="submit">Record scan</button>
  </form>
</div>
<?php
$content = ob_get_clean();
include("../layout/layout.php");

