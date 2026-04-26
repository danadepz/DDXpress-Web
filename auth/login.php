<?php
require_once(__DIR__ . "/../config/db.php");
require_once(__DIR__ . "/../includes/util.php");

if (!empty($_SESSION["user_id"])) {
    redirect("/DDXpress/index.php");
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $user = null;
    $userType = null;

    // Try staff first (admin/staff/rider), then customer.
    $stmt = $conn->prepare("SELECT id, email, password_hash, staff_role AS role FROM staff WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        $user = $row;
        $userType = "staff";
    } else {
        $stmt = $conn->prepare("SELECT id, email, password_hash, 'customer' AS role FROM customer WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $user = $row;
            $userType = "customer";
        }
    }

    if (!$user || !password_verify($password, $user["password_hash"])) {
        $error = "Invalid email or password.";
    } else {
        $_SESSION["user_id"] = (int)$user["id"];
        $_SESSION["email"] = $user["email"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["user_type"] = $userType;
        if ($user["role"] === "customer") {
            redirect("/DDXpress/customer/dashboard.php");
        }
        if ($user["role"] === "staff") {
            redirect("/DDXpress/staff/dashboard.php");
        }
        if ($user["role"] === "rider") {
            redirect("/DDXpress/rider/dashboard.php");
        }
        if ($user["role"] === "admin") {
            redirect("/DDXpress/admin/reports.php");
        }
        redirect("/DDXpress/index.php");
    }
}

$pageTitle = "Login";
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Login - DDXpress</title>
    <link rel="stylesheet" href="/DDXpress/assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=ABeeZee&amp;display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=ADLaM+Display&amp;display=swap">
    <link rel="stylesheet" href="/DDXpress/assets/css/Navbar-Centered-Brand-Dark-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, rgb(255, 255, 255) 0%, rgb(250, 250, 250) 100%);
            min-height: 100vh;
        }
        .ddx-navbar {
            background: linear-gradient(98deg, rgb(119, 0, 255), #0d6efd 20%, #ffffff) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            font-family: "ADLaM Display", serif;
        }
        .btn-pill {
            padding: 10px 24px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: 2px solid #0d6efd;
            display: inline-block;
        }
        .btn-solid-blue { 
            background: #0d6efd; 
            color: white; 
        }
        .btn-solid-blue:hover { 
            background: transparent; 
            border-color: #0d6efd; 
            color: #0d6efd; 
        }
        .btn-track-gradient {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 10px 30px;
        }
        .btn-track-gradient:hover { 
            color: white; 
            opacity: 0.92; 
            transform: translateY(-2px);
        }
        .form-control {
            border-radius: 50px;
            padding: 12px 20px;
            margin-bottom: 15px;
            border: 2px solid #e0e0e0;
        }
        .form-control:focus {
            border-color: #0d6efd;
            outline: none;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.25);
        }
        .card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .card-body {
            padding: 2rem;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-md py-3 ddx-navbar" data-bs-theme="dark">
        <div class="container">
            <svg class="bi bi-truck d-inline-flex align-content-center flex-wrap" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16" style="font-size: 54px;color: var(--bs-primary);">
                <path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5zm1.294 7.456A2 2 0 0 1 4.732 11h5.536a2 2 0 0 1 .732-.732V3.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .294.456M12 10a2 2 0 0 1 1.732 1h.768a.5.5 0 0 0 .5-.5V8.35a.5.5 0 0 0-.11-.312l-1.48-1.85A.5.5 0 0 0 13.02 6H12zm-9 1a1 1 0 1 0 0 2 1 1 0 0 0 0-2m9 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2"></path>
            </svg>
            <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navcol-6">
                <span class="visually-hidden">Toggle navigation</span>
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse flex-grow-0 order-md-first" id="navcol-6">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/DDXpress/index.php" style="font-family: 'ADLaM Display', serif;font-size: 47px;">DDXpress</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h1 style="font-family: 'ADLaM Display', serif; font-size: 2rem;" class="text-center mb-3">Log In</h1>
                        <p class="text-center text-muted mb-4">Use a seeded account or register as a customer.</p>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
                        <?php endif; ?>

                        <?php if ($ok = get_flash("ok")): ?>
                            <div class="alert alert-success" role="alert"><?= h($ok) ?></div>
                        <?php endif; ?>

                        <form method="post" autocomplete="on">
                            <input class="form-control" type="email" name="email" placeholder="Email" required>
                            <input class="form-control" type="password" name="password" placeholder="Password" required>
                            
                            <div class="d-flex gap-3 justify-content-center mt-3 mb-3">
                                <button class="btn-pill btn-solid-blue" style="font-family: 'ADLaM Display', serif; font-size: 14px;" type="submit">Login</button>
                                <a class="btn-pill btn-solid-blue" style="font-family: 'ADLaM Display', serif; font-size: 14px;" href="/DDXpress/auth/register.php">Register</a>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/DDXpress/assets/bootstrap/js/bootstrap.min.js"></script>
</body>

</html>