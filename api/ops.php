<?php

require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/geonet.php";

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["logged_in"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "Unauthorized"]);
    exit();
}

if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Admin access required."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input["action"] ?? "";

try {
    if ($action === "add_barangay") {
        geonet_add_barangay(
            $input["name"] ?? "",
            $input["lat"] ?? null,
            $input["lng"] ?? null
        );
        echo json_encode([
            "ok" => true,
            "message" => "Barangay added.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "update_barangay") {
        geonet_update_barangay(
            $input["barangayId"] ?? null,
            $input["name"] ?? "",
            $input["lat"] ?? null,
            $input["lng"] ?? null
        );
        echo json_encode([
            "ok" => true,
            "message" => "Barangay updated.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "delete_barangay") {
        geonet_delete_barangay($input["barangayId"] ?? null);
        echo json_encode([
            "ok" => true,
            "message" => "Barangay removed.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "delete_all_barangays") {
        $deleted = geonet_delete_all_barangays();
        echo json_encode([
            "ok" => true,
            "message" => "All barangays and their linked subscriber accounts, requests, outages, and tickets were removed.",
            "deleted" => $deleted,
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "list_subscription_requests") {
        echo json_encode([
            "ok" => true,
            "requests" => geonet_subscription_requests(),
        ]);
        exit();
    }

    if ($action === "dispatch_subscription_request") {
        geonet_dispatch_subscription_request(
            $input["requestId"] ?? null,
            $input["technicianId"] ?? ""
        );
        echo json_encode([
            "ok" => true,
            "message" => "Technician dispatched to the subscription request.",
            "requests" => geonet_subscription_requests(),
        ]);
        exit();
    }

    if ($action === "review_subscription_request") {
        $decision = (string) ($input["decision"] ?? "");
        $subscriberId = geonet_review_subscription_request(
            $input["requestId"] ?? null,
            $decision,
            $_SESSION["user_id"] ?? null
        );
        echo json_encode([
            "ok" => true,
            "subscriberId" => $subscriberId,
            "message" => $decision === "approve"
                ? "Subscription request approved and subscriber account created."
                : "Subscription request rejected.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "assign_job") {
        $jobId = geonet_assign_job($input["technicianId"] ?? "", $input["outageId"] ?? "");
        echo json_encode([
            "ok" => true,
            "jobId" => $jobId,
            "message" => "Technician assigned.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "advance_job") {
        $status = geonet_advance_job($input["jobId"] ?? "");
        echo json_encode([
            "ok" => true,
            "status" => $status,
            "message" => "Job is now " . str_replace("_", " ", $status) . ".",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "update_ticket") {
        geonet_update_ticket($input["ticketId"] ?? "", $input["status"] ?? "");
        echo json_encode([
            "ok" => true,
            "message" => "Ticket updated.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "move_subscriber_pin") {
        geonet_move_subscriber_pin(
            $input["subscriberId"] ?? "",
            $input["lat"] ?? null,
            $input["lng"] ?? null
        );
        echo json_encode([
            "ok" => true,
            "message" => "Subscriber pin updated.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "update_subscriber") {
        geonet_update_subscriber($input["subscriberId"] ?? "", $input);
        echo json_encode([
            "ok" => true,
            "message" => "Subscriber details updated.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "delete_subscriber") {
        geonet_delete_subscriber($input["subscriberId"] ?? "");
        echo json_encode([
            "ok" => true,
            "message" => "Subscriber and linked login removed.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "add_subscriber") {
        $subscriberId = geonet_add_subscriber($input);
        echo json_encode([
            "ok" => true,
            "subscriberId" => $subscriberId,
            "message" => "Subscriber added. Their pin is hidden until you place it on the map.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "add_technician") {
        $technicianId = geonet_add_technician($input);
        echo json_encode([
            "ok" => true,
            "technicianId" => $technicianId,
            "message" => "Technician and login account added.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "update_technician") {
        geonet_update_technician($input["technicianId"] ?? "", $input);
        echo json_encode([
            "ok" => true,
            "message" => "Technician details updated.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "delete_technician") {
        geonet_delete_technician($input["technicianId"] ?? "");
        echo json_encode([
            "ok" => true,
            "message" => "Technician and linked login removed.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "add_nap_pin") {
        geonet_add_nap_pin(
            $input["name"] ?? "",
            $input["lat"] ?? null,
            $input["lng"] ?? null
        );
        echo json_encode([
            "ok" => true,
            "message" => "NAP box added.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "add_coverage_area") {
        geonet_add_coverage_area($input["name"] ?? "", $input["coordinates"] ?? null);
        echo json_encode([
            "ok" => true,
            "message" => "Coverage area saved.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    if ($action === "update_coverage_area") {
        geonet_update_coverage_area(
            $input["areaId"] ?? null,
            $input["name"] ?? "",
            $input["coordinates"] ?? null
        );
        echo json_encode([
            "ok" => true,
            "message" => "Coverage area updated.",
            "geonet" => geonet_payload(),
        ]);
        exit();
    }

    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Unknown action."]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
