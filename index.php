<?php
require_once(__DIR__ . "/config/db.php");
require_once(__DIR__ . "/includes/util.php");

$role = $_SESSION["role"] ?? null;

$isLoggedIn = !empty($_SESSION["user_id"]);
$dashboardHref = "/DDXpress/index.php";
if ($role === "customer") {
    $dashboardHref = "/DDXpress/customer/dashboard.php";
} elseif ($role === "staff") {
    $dashboardHref = "/DDXpress/staff/dashboard.php";
} elseif ($role === "rider") {
    $dashboardHref = "/DDXpress/rider/dashboard.php";
} elseif ($role === "admin") {
    $dashboardHref = "/DDXpress/admin/reports.php";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDXpress - Track Your Parcel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=ADLaM+Display&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(135deg, rgb(255, 255, 255) 0%, rgb(250, 250, 250) 100%); min-height: 100vh; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: "ADLaM Display", serif; }
        .ddx-navbar {
            background: linear-gradient(98deg, rgb(119, 0, 255), #0d6efd 20%, #ffffff) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            font-family: "ADLaM Display", serif;
        }
        .brand-link {
            font-family: "ADLaM Display", serif;
            font-size: 47px;
            color: white !important;
            text-decoration: none;
        }
        .brand-link:hover { color: #ffdd00 !important; }
        .truck-icon { font-size: 54px; color: var(--bs-primary); }
        .nav-buttons { display: flex; gap: 12px; margin-left: 1rem; }
        .btn-pill {
            padding: 8px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: 2px solid #0d6efd;
        }
        .btn-outline-white { background: transparent; border-color: white; color: white; }
        .btn-outline-white:hover { background: white; color: #0d6efd; }
        .btn-solid-blue { background: #0d6efd; color: white; }
        .btn-solid-blue:hover { background: transparent; border-color: white; color: white; }
        .btn-danger-pill { background: #dc2626; border-color: #dc2626; color: white; }
        .btn-danger-pill:hover { background: transparent; border-color: white; color: white; }
        .main-content { min-height: calc(100vh - 85px); display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .track-card {
            background: white;
            border-radius: 25px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 500px;
            width: 100%;
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
        .track-card p { color: #6c757d; margin-bottom: 1.5rem; }
        .track-input {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            font-size: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        .track-input:focus { border-color: #0d6efd; outline: none; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25); }
        .track-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 50px;
            color: white;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
        }
        .track-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md py-3 ddx-navbar" data-bs-theme="dark">
        <div class="container">
            <svg class="truck-icon bi bi-truck d-inline-flex align-content-center flex-wrap" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16">
                <path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5zm1.294 7.456A2 2 0 0 1 4.732 11h5.536a2 2 0 0 1 .732-.732V3.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .294.456M12 10a2 2 0 0 1 1.732 1h.768a.5.5 0 0 0 .5-.5V8.35a.5.5 0 0 0-.11-.312l-1.48-1.85A.5.5 0 0 0 13.02 6H12zm-9 1a1 1 0 1 0 0 2 1 1 0 0 0 0-2m9 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2"></path>
            </svg>
            <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#indexNav">
                <span class="visually-hidden">Toggle navigation</span>
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="indexNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active brand-link" href="/DDXpress/index.php">DDXpress</a>
                    </li>
                </ul>
                <div class="nav-buttons">
                    <?php if (!$isLoggedIn): ?>
                        <a class="btn-pill btn-outline-white" style="font-family: 'ADLaM Display', serif; font-size: 20px;" href="/DDXpress/auth/login.php">Log In</a>
                        <a class="btn-pill btn-solid-blue" style="font-family: 'ADLaM Display', serif; font-size: 20px;" href="/DDXpress/auth/register.php">Register</a>
                    <?php else: ?>
                        <a class="btn-pill btn-outline-white" style="font-family: 'ADLaM Display', serif; font-size: 20px;" href="<?= h($dashboardHref) ?>">Dashboard</a>
                        <a class="btn-pill btn-danger-pill" style="font-family: 'ADLaM Display', serif; font-size: 20px;" href="/DDXpress/auth/logout.php">Logout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="track-card">
            <h1 style="font-family: 'ADLaM Display', serif; font-size: 2rem;">Track Your Parcel</h1>
            <p style="font-family: 'ADLaM Display', serif; font-size: 1.2rem;">Enter your tracking number to get real-time updates</p>
            <form method="get" action="/DDXpress/customer/track.php" autocomplete="on">
                <input type="text" class="track-input" name="tracking" placeholder="Tracking number (e.g. DDX-000001)" required>
                <button type="submit" class="track-btn">Track Parcel</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

