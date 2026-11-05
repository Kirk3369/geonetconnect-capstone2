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
    $config = geonet_sms_config();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Database is not ready. Open install.php first."]);
    exit();
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
    $safe = $config;
    $safe["api_key"] = $config["api_key"] ? "********" : "";
    $safe["has_api_key"] = !empty($config["api_key"]);
    echo json_encode([
        "ok" => true,
        "config" => $safe,
        "recent_logs" => geonet_sms_logs(20),
    ]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input["action"] ?? "send";

if ($action === "save_config") {
    if (($_SESSION["role"] ?? "") !== "admin") {
        http_response_code(403);
        echo json_encode(["ok" => false, "error" => "Admin access required."]);
        exit();
    }
    geonet_save_sms_config($input);
    echo json_encode(["ok" => true, "message" => "SMS settings saved."]);
    exit();
}

if (empty($config["enabled"])) {
    echo json_encode(["ok" => false, "error" => "SMS notifications are disabled in Settings."]);
    exit();
}

$recipients = $input["recipients"] ?? [];
if (!is_array($recipients)) {
    $recipients = [];
}

$message = trim($input["message"] ?? "");
$context = $input["context"] ?? "outage";
$outageId = $input["outageId"] ?? null;

if ($message === "" || count($recipients) === 0) {
    echo json_encode(["ok" => false, "error" => "Message and at least one recipient are required."]);
    exit();
}

$useMock = !empty($config["mock_mode"]) || empty($config["api_key"]);
$results = [];

foreach ($recipients as $person) {
    $number = preg_replace("/\s+/", "", $person["phone"] ?? "");
    $name = $person["name"] ?? "Recipient";
    $role = $person["role"] ?? "subscriber";
    if (!in_array($role, ["technician", "subscriber", "admin"], true)) {
        $role = "subscriber";
    }

    $entry = [
        "id" => uniqid("sms_"),
        "at" => date("c"),
        "name" => $name,
        "role" => $role,
        "phone" => $number,
        "context" => $context,
        "outageId" => $outageId,
        "message" => $message,
        "status" => "queued",
        "provider" => $useMock ? "mock" : $config["provider"],
        "note" => null,
    ];

    if ($useMock) {
        $entry["status"] = "logged";
        $entry["note"] = "Mock mode — message stored locally (no carrier send).";
    } else {
        $ch = curl_init($config["api_url"]);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                "apikey" => $config["api_key"],
                "number" => $number,
                "message" => $message,
                "sendername" => $config["sender_name"],
            ]),
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $entry["status"] = "failed";
            $entry["note"] = $err;
        } elseif ($code >= 200 && $code < 300) {
            $entry["status"] = "sent";
            $entry["note"] = $response;
        } else {
            $entry["status"] = "failed";
            $entry["note"] = $response ?: ("HTTP " . $code);
        }
    }

    geonet_log_sms($entry);
    $results[] = $entry;
}

echo json_encode([
    "ok" => true,
    "mock" => $useMock,
    "sent" => count($results),
    "results" => $results,
    "message" => $useMock
        ? "SMS queued in mock mode. Enable live send in Settings with a Semaphore API key."
        : "SMS dispatched via " . $config["provider"] . ".",
]);
