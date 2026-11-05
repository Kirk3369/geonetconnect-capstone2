<?php

require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/geonet.php";

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["logged_in"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "Unauthorized"]);
    exit();
}

try {
    echo json_encode(["ok" => true, "geonet" => geonet_payload()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Database is not ready. Open install.php first."]);
}
