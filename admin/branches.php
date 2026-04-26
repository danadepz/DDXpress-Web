<?php
require_once("../config/db.php");
require_once("../includes/auth.php");
require_once("../includes/util.php");

require_role(["admin"]);

$error = null;
$selectedRegion = "";
$selectedArea = "";
$inputAddress = "";

$regionAreas = [
    "Luzon" => ["NCR", "North Luzon", "South Luzon", "Bicol"],
    "Visayas" => ["Western Visayas", "Central Visayas", "Eastern Visayas"],
    "Mindanao" => ["Northern Mindanao", "Davao Region", "SOCCSKSARGEN", "Zamboanga Peninsula"]
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    if ($action === "add") {
        $name = trim($_POST["name"] ?? "");
        $selectedRegion = trim($_POST["region"] ?? "");
        $selectedArea = trim($_POST["area"] ?? "");
        $inputAddress = trim($_POST["address"] ?? "");
        if ($name === "") {
            $error = "Branch name is required.";
        } elseif (!isset($regionAreas[$selectedRegion])) {
            $error = "Please select a valid region.";
        } elseif (!in_array($selectedArea, $regionAreas[$selectedRegion], true)) {
            $error = "Please select a valid area for the chosen region.";
        } else {
            $addressParts = [$selectedArea, $selectedRegion];
            if ($inputAddress !== "") {
                array_unshift($addressParts, $inputAddress);
            }
            $address = implode(", ", $addressParts);
            $stmt = $conn->prepare("INSERT INTO branch (name, address) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $address);
            $stmt->execute();
            $stmt->close();
            set_flash("ok", "Branch added successfully.");
            redirect("/DDXpress/admin/branches.php");
        }
    } elseif ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM branch WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            set_flash("ok", "Branch deleted.");
            redirect("/DDXpress/admin/branches.php");
        }
    }
}

$stmt = $conn->prepare("SELECT id, name, address, created_at FROM branch ORDER BY id ASC");
$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($rows === null) {
    $rows = [];
}

$pageTitle = "Manage Branches";
ob_start();
?>

<style>
    .branches-header {
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
    .form-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
    }
    .form-card h3 {
        font-family: 'ADLaM Display', serif;
        font-size: 1.3rem;
        margin-bottom: 1rem;
    }
    .branches-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    .branches-card h3 {
        font-family: 'ADLaM Display', serif;
        font-size: 1.3rem;
        margin-bottom: 1rem;
    }
    .form-group {
        margin-bottom: 1rem;
    }
    .form-label {
        font-weight: 500;
        margin-bottom: 0.5rem;
        display: block;
        color: #333;
    }
    .form-control, .form-select {
        width: 100%;
        padding: 10px 15px;
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
    .btn-danger {
        padding: 6px 16px;
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
    .btn-danger:hover {
        background: transparent;
        border-color: #dc3545;
        color: #dc3545;
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
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    th {
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
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
    .row-flex {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
    }
    .flex-grow {
        flex: 1;
        min-width: 200px;
    }
    @media (max-width: 768px) {
        .branches-header {
            flex-direction: column;
            gap: 1rem;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .row-flex {
            flex-direction: column;
        }
        .btn-pill, .btn-back {
            width: 100%;
            text-align: center;
        }
        th, td {
            padding: 8px;
            font-size: 0.8rem;
        }
    }
</style>

<!-- Header -->
<div class="branches-header">
    <div>
        <h1 style="font-family: 'ADLaM Display', serif; font-size: 1.8rem; margin: 0;">Manage Branches</h1>
        <p class="text-muted mb-0">Add and remove delivery branches.</p>
    </div>
    <a class="btn-back" href="/DDXpress/admin/dashboard.php">← Back to Dashboard</a>
</div>

<!-- Welcome Card -->
<div class="welcome-card">
    <h2>Branch Management</h2>
    <p>Manage all delivery branches across Luzon, Visayas, and Mindanao.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-number"><?= count($rows) ?></div>
        <div class="stat-label">Total Branches</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= count($regionAreas) ?></div>
        <div class="stat-label">Regions</div>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($ok = get_flash("ok")): ?>
    <div class="alert alert-success" role="alert">✅ <?= h($ok) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">❌ <?= h($error) ?></div>
<?php endif; ?>

<!-- Add Branch Form -->
<div class="form-card">
    <h3>➕ Add New Branch</h3>
    <form method="post">
        <input type="hidden" name="action" value="add">
        
        <div class="row-flex">
            <div class="flex-grow">
                <label class="form-label">Branch Name <span class="required-star">*</span></label>
                <input class="form-control" type="text" name="name" placeholder="e.g., DDXpress Cubao" required>
            </div>
            
            <div class="flex-grow">
                <label class="form-label">Region <span class="required-star">*</span></label>
                <select class="form-select" name="region" id="region-select" required>
                    <option value="">Select region</option>
                    <?php foreach (array_keys($regionAreas) as $region): ?>
                        <option value="<?= h($region) ?>" <?= $selectedRegion === $region ? "selected" : "" ?>><?= h($region) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex-grow">
                <label class="form-label">Area <span class="required-star">*</span></label>
                <select class="form-select" name="area" id="area-select" required>
                    <option value="">Select area</option>
                </select>
            </div>
            
            <div class="flex-grow">
                <label class="form-label">Street / Address</label>
                <input class="form-control" type="text" name="address" placeholder="Optional" value="<?= h($inputAddress) ?>">
            </div>
            
            <div>
                <button class="btn-pill" type="submit" style="margin-top: 28px;">Add Branch</button>
            </div>
        </div>
    </form>
</div>

<script>
    (function () {
        const areaMap = <?= json_encode($regionAreas, JSON_UNESCAPED_SLASHES) ?>;
        const regionSelect = document.getElementById("region-select");
        const areaSelect = document.getElementById("area-select");
        const initialArea = <?= json_encode($selectedArea, JSON_UNESCAPED_SLASHES) ?>;

        function setAreaOptions(region, selectedArea) {
            areaSelect.innerHTML = '<option value="">Select area</option>';
            if (!areaMap[region]) return;
            areaMap[region].forEach(function (area) {
                const option = document.createElement("option");
                option.value = area;
                option.textContent = area;
                if (selectedArea === area) {
                    option.selected = true;
                }
                areaSelect.appendChild(option);
            });
        }

        setAreaOptions(regionSelect.value, initialArea);
        regionSelect.addEventListener("change", function () {
            setAreaOptions(regionSelect.value, "");
        });
    })();
</script>

<!-- Branches List -->
<div class="branches-card">
    <h3>📋 All Branches</h3>
    
    <?php if (empty($rows)): ?>
        <div class="text-muted text-center py-4">
            <p>No branches found. Add your first branch using the form above.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="branches-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Branch Name</th>
                        <th>Address</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><strong><?= (int)$r["id"] ?></strong></td>
                            <td><?= h($r["name"]) ?></td>
                            <td><?= h($r["address"]) ?></td>
                            <td><?= date('M d, Y', strtotime($r["created_at"])) ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this branch? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$r["id"] ?>">
                                    <button class="btn-danger" type="submit">Delete</button>
                                </form>
                            </td>
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