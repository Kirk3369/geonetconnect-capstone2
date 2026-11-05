<?php

require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/geonet.php";

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["logged_in"]) || ($_SESSION["role"] ?? "") !== "technician") {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Technician access required."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

try {
    if (($input["action"] ?? "") === "list_subscription_requests") {
        echo json_encode([
            "ok" => true,
            "requests" => geonet_technician_subscription_requests($_SESSION["user_id"] ?? null),
        ]);
        exit();
    }

    if (($input["action"] ?? "") === "respond_subscription_request") {
        geonet_respond_to_subscription_request(
            $input["requestId"] ?? null,
            $_SESSION["user_id"] ?? null,
            $input["decision"] ?? ""
        );
        echo json_encode([
            "ok" => true,
            "message" => ($input["decision"] ?? "") === "accepted"
                ? "Subscription request accepted."
                : "Subscription request declined and returned for reassignment.",
            "requests" => geonet_technician_subscription_requests($_SESSION["user_id"] ?? null),
        ]);
        exit();
    }

    if (($input["action"] ?? "") === "update_subscription_request_work_status") {
        $workStatus = $input["workStatus"] ?? "";
        geonet_update_subscription_request_work_status(
            $input["requestId"] ?? null,
            $_SESSION["user_id"] ?? null,
            $workStatus,
            $input["reason"] ?? ""
        );
        echo json_encode([
            "ok" => true,
            "message" => $workStatus === "completed"
                ? "Subscription request assignment marked complete."
                : ($workStatus === "en_route"
                    ? "You are now marked on route to this assignment."
                    : "Reason sent to admin; the assignment is marked not complete."),
            "requests" => geonet_technician_subscription_requests($_SESSION["user_id"] ?? null),
        ]);
        exit();
    }

    if (($input["action"] ?? "") !== "update_availability") {
        http_response_code(400);
        echo json_encode(["ok" => false, "error" => "Unknown action."]);
        exit();
    }

    $availability = geonet_update_own_technician_availability(
        $_SESSION["user_id"] ?? 0,
        $input["availability"] ?? ""
    );
    echo json_encode([
        "ok" => true,
        "availability" => $availability,
        "message" => "Availability updated.",
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Could not process technician request."]);
}
