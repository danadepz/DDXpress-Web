<?php
require_once(__DIR__ . "/../config/db.php");
require_once(__DIR__ . "/../includes/util.php");

if (!empty($_SESSION["user_id"])) {
    redirect("/DDXpress/index.php");
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");

    if ($fullName === "" || $email === "" || $password === "") {
        $error = "Please fill out required fields.";
    } else {
        $stmt = $conn->prepare("
            SELECT 1 AS exists_flag FROM customer WHERE email = ?
            UNION ALL
            SELECT 1 AS exists_flag FROM staff WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $existing = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($existing) {
            $error = "Email is already registered.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO customer (full_name, email, password_hash, phone, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $fullName, $email, $hash, $phone, $address);
                $stmt->execute();
                $stmt->close();

                set_flash("ok", "Account created. Please log in.");
                redirect("/DDXpress/auth/login.php");
            } catch (Throwable $e) {
                $error = "Registration failed. Try again.";
            }
        }
    }
}

$pageTitle = "Register";
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Register - DDXpress</title>
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
        .btn-create {
            padding: 12px 35px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: 2px solid #0d6efd;
            display: inline-block;
            background: #0d6efd;
            color: white;
            font-size: 1rem;
            cursor: pointer;
        }
        .btn-create:hover {
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            color: #6c757d;
        }
        .btn-back:hover {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        .register-card {
            background: white;
            border-radius: 25px;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
        }
        .register-card h1 {
            font-family: "ADLaM Display", serif;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-align: center;
        }
        .register-card p {
            color: #6c757d;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .form-control {
            border-radius: 50px;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            width: 100%;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #0d6efd;
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #333;
        }
        .required-star {
            color: #dc3545;
        }
        .button-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .back-button-wrapper {
            margin-bottom: 15px;
        }
        @media (max-width: 768px) {
            .register-card {
                padding: 1.5rem;
            }
            .btn-create {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-md py-3 ddx-navbar" data-bs-theme="dark">
        <div class="container">
            <svg class="bi bi-truck d-inline-flex align-content-center flex-wrap" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16" style="font-size: 54px; color: var(--bs-primary);">
                <path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5zm1.294 7.456A2 2 0 0 1 4.732 11h5.536a2 2 0 0 1 .732-.732V3.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .294.456M12 10a2 2 0 0 1 1.732 1h.768a.5.5 0 0 0 .5-.5V8.35a.5.5 0 0 0-.11-.312l-1.48-1.85A.5.5 0 0 0 13.02 6H12zm-9 1a1 1 0 1 0 0 2 1 1 0 0 0 0-2m9 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2"></path>
            </svg>
            <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navcol-6">
                <span class="visually-hidden">Toggle navigation</span>
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse flex-grow-0 order-md-first" id="navcol-6">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/DDXpress/index.php" style="font-family: 'ADLaM Display', serif; font-size: 47px;">DDXpress</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                
                <!-- Back Button - OUTSIDE the card (top left) -->
                <div class="back-button-wrapper">
                    <a class="btn-back" href="/DDXpress/auth/login.php">
                        ← Back to Login
                    </a>
                </div>

                <!-- Register Card -->
                <div class="register-card">
                    <h1>Customer Registration</h1>
                    <p>Staff/rider/admin accounts are created by admin (seeded in SQL).</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
                    <?php endif; ?>

                    <form method="post" autocomplete="on">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="required-star">*</span></label>
                                <input class="form-control" type="text" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="required-star">*</span></label>
                                <input class="form-control" type="email" name="email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="required-star">*</span></label>
                                <input class="form-control" type="password" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input class="form-control" type="text" name="phone">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Address</label>
                            <input class="form-control" type="text" name="address">
                        </div>

                        <!-- Create Account Button - Centered inside card -->
                        <div class="button-container">
                            <button class="btn-create" type="submit">Create Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="/DDXpress/assets/bootstrap/js/bootstrap.min.js"></script>
</body>

</html>