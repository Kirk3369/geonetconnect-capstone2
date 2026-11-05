<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function geonet_home($role)
{
    if ($role === "technician") {
        return "technician.php";
    }
    if ($role === "subscriber") {
        return "subscriber.php";
    }
    return "dashboard.php";
}

function geonet_require_login($allowedRoles = null)
{
    if (!isset($_SESSION["logged_in"])) {
        header("Location: index.php");
        exit();
    }

    $role = $_SESSION["role"] ?? "admin";

    if ($allowedRoles !== null) {
        $allowed = (array) $allowedRoles;
        if (!in_array($role, $allowed, true)) {
            header("Location: " . geonet_home($role));
            exit();
        }
    }
}
