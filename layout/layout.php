<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$showSidebar = !empty($_SESSION["user_id"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?= htmlspecialchars($pageTitle ?? "DDXpress", ENT_QUOTES, "UTF-8") ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="/DDXpress/assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=ABeeZee&amp;display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=ADLaM+Display&amp;display=swap">
    <link rel="stylesheet" href="/DDXpress/assets/css/Navbar-Centered-Brand-Dark-icons.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'ADLaM Display', serif;
        }
        
        /* Layout Grid */
        .app {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }
        
        .app.no-sidebar {
            grid-template-columns: 1fr;
        }
        
        /* Sidebar Styling */
        .sidebar {
            background: linear-gradient(135deg,rgb(79, 7, 161) 0%, #0d6efd 100%);
            color: #e5e7eb;
            padding: 1.5rem 1rem;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-brand {
            font-family: 'ADLaM Display', serif;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar-brand a {
            color: white;
            text-decoration: none;
        }
        
        .sidebar-user-info {
            margin-bottom: 1.5rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            font-size: 0.85rem;
        }
        
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .sidebar-nav a {
            display: block;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            color: #e5e7eb;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .sidebar-nav hr {
            margin: 0.75rem 0;
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Main Content */
        .main {
            padding: 2rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .app {
                grid-template-columns: 1fr;
            }
            .sidebar {
                display: none;
            }
            .main {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="app<?= $showSidebar ? "" : " no-sidebar" ?>">
        <?php if ($showSidebar): ?>
            <aside class="sidebar">
                <?php include(__DIR__ . "/sidebar.php"); ?>
            </aside>
        <?php endif; ?>
        <main class="main">
            <?= $content ?? "" ?>
        </main>
    </div>
    
    <script src="/DDXpress/assets/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>