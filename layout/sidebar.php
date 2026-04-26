<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$isLoggedIn = !empty($_SESSION["user_id"]);
$role = $_SESSION["role"] ?? null;
$email = $_SESSION["email"] ?? "User";
?>

<div class="sidebar-brand">
    <a href="/DDXpress/index.php">🚚 DDXpress</a>
</div>

<?php if (!$isLoggedIn): ?>
    <div class="sidebar-nav">
        <a href="/DDXpress/auth/login.php">🔐 Login</a>
        <a href="/DDXpress/auth/register.php">📝 Register (Customer)</a>
        <hr>
        <a href="/DDXpress/customer/track.php">📦 Track Parcel</a>
    </div>
<?php else: ?>
    <div class="sidebar-user-info">
        <div>Signed in as</div>
        <strong><?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?></strong>
        <div style="margin-top: 5px;">
            <span class="badge" style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 20px;">
                <?= htmlspecialchars($role ?? "unknown", ENT_QUOTES, "UTF-8") ?>
            </span>
        </div>
    </div>

    <div class="sidebar-nav">
        <?php if ($role === "customer"): ?>
            <a href="/DDXpress/customer/dashboard.php">📊 Dashboard</a>
            <a href="/DDXpress/customer/create_booking.php">✏️ Create Booking</a>
            <a href="/DDXpress/customer/history.php">📜 Booking History</a>
            <a href="/DDXpress/customer/track.php">📦 Track Parcel</a>
            
        <?php elseif ($role === "staff"): ?>
            <a href="/DDXpress/staff/dashboard.php">📊 Dashboard</a>
            <a href="/DDXpress/staff/parcels.php">📦 View All Parcels</a>
            <a href="/DDXpress/staff/update_status.php">🔄 Update Parcel Status</a>
            
        <?php elseif ($role === "rider"): ?>
            <a href="/DDXpress/rider/dashboard.php">📊 Dashboard</a>
            <a href="/DDXpress/rider/parcels.php">📦 My Accepted Parcels</a>
            <a href="/DDXpress/rider/update_status.php">🔄 Update Parcel Status</a>
            
        <?php elseif ($role === "admin"): ?>
            <a href="/DDXpress/admin/branches.php">🏢 Manage Branches</a>
            <a href="/DDXpress/admin/service_types.php">⚙️ Manage Service Types</a>
            <a href="/DDXpress/admin/staff.php">👥 Manage Staff</a>
            <a href="/DDXpress/admin/bookings.php">📦 Bookings & Parcels</a>
            <a href="/DDXpress/admin/reports.php">📊 Reports/Analytics</a>
        <?php endif; ?>
        
        <hr>
        
        <a href="/DDXpress/auth/logout.php" onclick="return confirm('Are you sure you want to log out?')" style="color: #f87171;">
            🚪 Logout
        </a>
    </div>
<?php endif; ?>