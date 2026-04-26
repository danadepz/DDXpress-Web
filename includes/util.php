<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8");
}

function redirect(string $path): void
{
    header("Location: " . $path);
    exit;
}

function set_flash(string $key, string $message): void
{
    $_SESSION["_flash"][$key] = $message;
}

function get_flash(string $key): ?string
{
    $msg = $_SESSION["_flash"][$key] ?? null;
    if ($msg !== null) {
        unset($_SESSION["_flash"][$key]);
    }
    return $msg;
}

