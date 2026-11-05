<?php
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/geonet.php";

if (isset($_SESSION["logged_in"])) {
    header("Location: " . geonet_home($_SESSION["role"] ?? "admin"));
    exit();
}

$error = "";
$signupMapData = ["center" => [10.7794, 122.4668], "barangays" => [], "coverageAreas" => []];
$requestSubmitted = false;
$availablePlans = geonet_available_subscriber_plans();
try {
    $signupMapData = geonet_signup_map_data();
} catch (Throwable $e) {
    $error = "Sign-up is temporarily unavailable. Please try again later.";
}

if (isset($_SESSION["subscription_request_submitted"])) {
    unset($_SESSION["subscription_request_submitted"]);
    $requestSubmitted = true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $username = trim((string) ($_POST["username"] ?? ""));
        if ($username === "" || strlen($username) > 64 || !preg_match('/^[A-Za-z0-9._%+-]+$/D', $username)) {
            throw new InvalidArgumentException("Enter a valid username using letters, numbers, dots, underscores, or hyphens.");
        }
        $email = strtolower($username) . "@sub.rlink.ph";
        $subscriberInput = $_POST;
        $subscriberInput["email"] = $email;
        geonet_create_subscription_request($subscriberInput);
        $_SESSION["subscription_request_submitted"] = true;
        header("Location: signup.php?submitted=1");
        exit();
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $error = "Sign-up could not be completed. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoNet Connect - Subscriber Sign-up</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
<div class="login-container">
    <div class="left-panel">
        <div class="grid-overlay"></div>
        <svg class="network-lines" viewBox="0 0 700 700" preserveAspectRatio="none">
            <polyline points="70,100 130,165 225,150 310,215 375,180 440,250 510,230" fill="none"/>
            <polyline points="35,250 115,270 195,235 275,305 355,275 425,335 520,315" fill="none"/>
            <polyline points="85,415 165,400 245,435 325,405 405,445 490,425" fill="none"/>
            <polyline points="165,40 175,115 205,190 250,270 275,400" fill="none"/>
            <polyline points="340,55 310,215 340,285 375,465" fill="none"/>
            <polyline points="455,70 440,150 470,230 425,395 440,480" fill="none"/>
        </svg>
        <div class="logo">
            <div class="logo-icon"><i class="fa-solid fa-location-dot"></i></div>
            <div><h3>GeoNet Connect</h3><span>R-Link Data and Solutions</span></div>
        </div>
        <div class="hero-content">
            <h1>Join the<br>R-Link network.</h1>
            <p>Create your subscriber account to view your service information and network notices.</p>
        </div>
    </div>

    <div class="right-panel">
        <div class="login-box login-box-wide">
            <h1>Request subscriber service</h1>
            <p class="subtitle">Request service in your area. An administrator will review your request.</p>
            <?php if ($requestSubmitted): ?>
                <div class="success-message" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    Your subscription request was submitted. We’ll review your service location and contact you.
                </div>
            <?php elseif ($error !== ""): ?>
                <div class="error-message">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if (!$requestSubmitted): ?>
            <form method="POST" action="signup.php" id="subscriptionRequestForm">
                <div class="form-group">
                    <label for="signupName">Full name</label>
                    <input type="text" name="name" id="signupName" maxlength="120" autocomplete="name"
                           value="<?php echo htmlspecialchars((string) ($_POST["name"] ?? "")); ?>" required>
                </div>
                <div class="form-group">
                    <label for="signupUsername">Email username</label>
                    <div class="email-composite">
                        <input type="text" name="username" id="signupUsername" maxlength="64"
                               pattern="[A-Za-z0-9._%+\-]+" autocomplete="username"
                               value="<?php echo htmlspecialchars((string) ($_POST["username"] ?? "")); ?>" required>
                        <span aria-hidden="true">@sub.rlink.ph</span>
                    </div>
                    <small class="form-hint">Choose the username you want to use to sign in.</small>
                </div>
                <div class="form-group">
                    <label for="signupPassword">Password</label>
                    <input type="password" name="password" id="signupPassword" minlength="8"
                           autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="signupBarangay">Barangay</label>
                    <select name="barangay" id="signupBarangay" required <?php echo $signupMapData["barangays"] ? "" : "disabled"; ?>>
                        <option value="">Choose your barangay</option>
                        <?php foreach ($signupMapData["barangays"] as $barangay): ?>
                            <option value="<?php echo htmlspecialchars($barangay["name"]); ?>"
                                <?php echo (($_POST["barangay"] ?? "") === $barangay["name"]) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($barangay["name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="signupMap">Pin your service location</label>
                    <p class="form-hint">Choose your barangay to zoom the map, then click your approximate service location.</p>
                    <div id="signupMap" class="signup-map"></div>
                    <div class="map-pick-status" id="mapPickStatus" role="status">Choose a barangay, then place your pin on the map.</div>
                    <input type="hidden" name="lat" id="signupLat"
                           value="<?php echo htmlspecialchars((string) ($_POST["lat"] ?? "")); ?>">
                    <input type="hidden" name="lng" id="signupLng"
                           value="<?php echo htmlspecialchars((string) ($_POST["lng"] ?? "")); ?>">
                </div>
                <div class="form-group">
                    <label for="signupAddress">Service address</label>
                    <input type="text" name="address" id="signupAddress" maxlength="255"
                           autocomplete="street-address"
                           value="<?php echo htmlspecialchars((string) ($_POST["address"] ?? "")); ?>" required>
                </div>
                <div class="form-group">
                    <label for="signupPhone">Phone</label>
                    <input type="tel" name="phone" id="signupPhone" maxlength="20" autocomplete="tel"
                           value="<?php echo htmlspecialchars((string) ($_POST["phone"] ?? "")); ?>" required>
                </div>
                <div class="form-group">
                    <label for="signupPlan">Requested plan</label>
                    <select name="plan" id="signupPlan" required>
                        <option value="">Choose an available plan</option>
                        <?php foreach ($availablePlans as $plan): ?>
                            <option value="<?php echo htmlspecialchars($plan); ?>"
                                <?php echo (($_POST["plan"] ?? "") === $plan) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($plan); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="login-button" <?php echo $signupMapData["barangays"] ? "" : "disabled"; ?>>
                    Submit subscription request
                </button>
            </form>
            <?php else: ?>
                <p class="request-access"><a href="signup.php">Submit another request</a></p>
            <?php endif; ?>
            <p class="request-access">Already have an account? <a href="index.php">Sign in</a></p>
        </div>
        <div class="footer">© 2026 R-Link Data and Solutions · San Miguel, Iloilo</div>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php if (!$requestSubmitted && $signupMapData["barangays"]): ?>
<script>
(function () {
    const data = <?php echo json_encode($signupMapData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const map = L.map("signupMap").setView(data.center, 13);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap"
    }).addTo(map);

    data.coverageAreas.forEach((area) => {
        const label = L.DomUtil.create("span");
        label.textContent = area.name;
        L.polygon(area.coordinates, {
            color: "#7b4cc2",
            weight: 2,
            fillColor: "#8f63d2",
            fillOpacity: 0.2
        }).addTo(map).bindTooltip(label);
    });
    const barangaySelect = document.getElementById("signupBarangay");
    const latitude = document.getElementById("signupLat");
    const longitude = document.getElementById("signupLng");
    const status = document.getElementById("mapPickStatus");
    let pin = null;
    const isInsideCoverage = (lat, lng) => data.coverageAreas.some((area) => {
        const points = area.coordinates || [];
        if (points.length < 3) return false;
        let inside = false;
        for (let i = 0, j = points.length - 1; i < points.length; j = i++) {
            const [latA, lngA] = points[i];
            const [latB, lngB] = points[j];
            const cross = (lng - lngA) * (latB - latA) - (lat - latA) * (lngB - lngA);
            if (
                Math.abs(cross) < 1e-10 &&
                lng >= Math.min(lngA, lngB) - 1e-10 &&
                lng <= Math.max(lngA, lngB) + 1e-10 &&
                lat >= Math.min(latA, latB) - 1e-10 &&
                lat <= Math.max(latA, latB) + 1e-10
            ) return true;
            if ((latA > lat) !== (latB > lat)) {
                const intersectionLng = (lngB - lngA) * (lat - latA) /
                    (latB - latA) + lngA;
                if (lng < intersectionLng) inside = !inside;
            }
        }
        return inside;
    });

    const selectedBarangay = data.barangays.find((item) => item.name === barangaySelect.value);
    if (selectedBarangay) map.setView([selectedBarangay.lat, selectedBarangay.lng], 15);

    if (latitude.value && longitude.value) {
        const lat = Number(latitude.value);
        const lng = Number(longitude.value);
        if (isInsideCoverage(lat, lng)) {
            pin = L.marker([lat, lng]).addTo(map);
            status.textContent = "Service location selected. Click inside network coverage to move your pin.";
        } else {
            latitude.value = "";
            longitude.value = "";
            status.textContent = "Choose a service location inside the network coverage area.";
        }
    }

    barangaySelect.addEventListener("change", () => {
        const barangay = data.barangays.find((item) => item.name === barangaySelect.value);
        if (!barangay) return;
        latitude.value = "";
        longitude.value = "";
        if (pin) {
            map.removeLayer(pin);
            pin = null;
        }
        status.textContent = "Barangay selected. Click inside network coverage to pin your service location.";
        map.flyTo([barangay.lat, barangay.lng], 15);
    });

    map.on("click", (event) => {
        const selected = data.barangays.find((item) => item.name === barangaySelect.value);
        if (!selected) {
            status.textContent = "Choose a barangay before placing your pin.";
            return;
        }
        if (!isInsideCoverage(event.latlng.lat, event.latlng.lng)) {
            status.textContent = "Choose a location inside the network coverage area.";
            return;
        }
        latitude.value = event.latlng.lat.toFixed(6);
        longitude.value = event.latlng.lng.toFixed(6);
        if (!pin) pin = L.marker(event.latlng).addTo(map);
        else pin.setLatLng(event.latlng);
        pin.bindPopup("Requested service location").openPopup();
        status.textContent = "Service location pinned inside network coverage. Click inside coverage to adjust it.";
    });

    document.getElementById("subscriptionRequestForm").addEventListener("submit", (event) => {
        if (
            !latitude.value ||
            !longitude.value ||
            !isInsideCoverage(Number(latitude.value), Number(longitude.value))
        ) {
            event.preventDefault();
            status.textContent = "Pin your service location inside the network coverage area before submitting.";
            document.getElementById("signupMap").scrollIntoView({ behavior: "smooth", block: "center" });
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
