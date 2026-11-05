<?php
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/geonet.php";
geonet_require_login();

$profile = geonet_own_profile($_SESSION["user_id"] ?? null);
$roleLabel = ucfirst($profile["role"]);
$csrfToken = $_SESSION["profile_csrf_token"] ?? bin2hex(random_bytes(32));
$_SESSION["profile_csrf_token"] = $csrfToken;
$emailDomains = [
    "admin" => "admin.rlink.ph",
    "technician" => "tech.rlink.ph",
    "subscriber" => "sub.rlink.ph",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoNet Connect · Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon"><i class="fa-solid <?php
                echo $profile["role"] === "technician"
                    ? "fa-helmet-safety"
                    : ($profile["role"] === "subscriber" ? "fa-wifi" : "fa-location-dot");
            ?>"></i></div>
            <div>
                <h1><?php
                    echo $profile["role"] === "technician"
                        ? "Field portal"
                        : ($profile["role"] === "subscriber" ? "My connection" : "GeoNet Connect");
                ?></h1>
                <p><?php
                    echo $profile["role"] === "technician"
                        ? "Technician workspace"
                        : ($profile["role"] === "subscriber"
                            ? "Subscriber portal"
                            : "R-Link Data and Solutions");
                ?></p>
            </div>
        </div>
        <nav class="nav">
            <a class="nav-item active" href="<?php
                echo $profile["role"] === "admin"
                    ? "dashboard.php#profile"
                    : ($profile["role"] === "technician"
                        ? "technician.php#profile"
                        : "subscriber.php#profile");
            ?>">
                <i class="fa-solid fa-id-card"></i> Profile
            </a>
            <?php if ($profile["role"] === "admin"): ?>
                <a class="nav-item" href="dashboard.php#dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
                <a class="nav-item" href="dashboard.php#subscribers"><i class="fa-solid fa-users"></i> Subscribers</a>
                <a class="nav-item" href="dashboard.php#outages"><i class="fa-solid fa-bolt"></i> Outages</a>
                <a class="nav-item" href="dashboard.php#technicians"><i class="fa-solid fa-helmet-safety"></i> Technicians</a>
                <a class="nav-item" href="dashboard.php#maintenance"><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance Support</a>
                <a class="nav-item" href="dashboard.php#analytics"><i class="fa-solid fa-chart-line"></i> Analytics</a>
                <a class="nav-item" href="dashboard.php#settings"><i class="fa-solid fa-gear"></i> Settings</a>
            <?php elseif ($profile["role"] === "technician"): ?>
                <a class="nav-item" href="technician.php#jobs"><i class="fa-solid fa-clipboard-list"></i> My jobs</a>
                <a class="nav-item" href="technician.php#logs"><i class="fa-solid fa-clock-rotate-left"></i> Ticket logs</a>
            <?php else: ?>
                <a class="nav-item" href="subscriber.php#account"><i class="fa-solid fa-house"></i> My account</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-foot"><?php
            echo $profile["role"] === "technician"
                ? "San Miguel, Iloilo · Field ops"
                : ($profile["role"] === "subscriber"
                    ? "San Miguel, Iloilo · R-Link"
                    : "San Miguel, Iloilo · GIS ops");
        ?></div>
    </aside>
    <div class="main">
        <header class="topbar">
            <div>
                <h2>Profile</h2>
                <p class="topbar-sub">Manage your account details</p>
            </div>
            <div class="topbar-right">
                <span class="user-chip"><?php echo htmlspecialchars($profile["name"]); ?> · <?php echo htmlspecialchars($roleLabel); ?></span>
                <a class="btn btn-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>
        <main class="content">
            <section class="card panel profile-panel">
                <div class="panel-head"><h3>Edit your details</h3></div>
                <form id="profileForm" class="profile-form">
                    <label class="field">Full name
                        <input name="name" maxlength="120" required
                               value="<?php echo htmlspecialchars($profile["name"]); ?>">
                    </label>
                    <label class="field">Email address
                        <input name="email" type="email" maxlength="190" required
                               value="<?php echo htmlspecialchars($profile["email"]); ?>">
                        <span class="side-meta">Use an address ending in @<?php echo htmlspecialchars($emailDomains[$profile["role"]]); ?>.</span>
                    </label>
                    <?php if ($profile["role"] === "technician" || $profile["role"] === "subscriber"): ?>
                        <label class="field">Phone
                            <input name="phone" type="tel" maxlength="20" required
                                   value="<?php echo htmlspecialchars($profile["phone"] ?? ""); ?>">
                        </label>
                    <?php endif; ?>
                    <?php if ($profile["role"] === "subscriber"): ?>
                        <label class="field">Service address
                            <input name="address" maxlength="255" required
                                   value="<?php echo htmlspecialchars($profile["address"] ?? ""); ?>">
                        </label>
                    <?php endif; ?>
                    <label class="field">New password
                        <input name="password" type="password" minlength="8" autocomplete="new-password">
                        <span class="side-meta">Leave blank to keep your current password. Minimum 8 characters when changing it.</span>
                    </label>
                    <label class="field">Confirm new password
                        <input name="confirmPassword" type="password" minlength="8" autocomplete="new-password">
                        <span class="side-meta">Enter the same new password again.</span>
                    </label>
                    <div class="profile-actions">
                        <button class="btn btn-primary" type="submit" id="saveProfile">Save changes</button>
                        <p id="profileMessage" class="availability-message" role="status" aria-live="polite"></p>
                    </div>
                </form>
            </section>
        </main>
    </div>
</div>
<script>
(() => {
    const form = document.getElementById("profileForm");
    const button = document.getElementById("saveProfile");
    const message = document.getElementById("profileMessage");
    const password = form.elements.password;
    const confirmPassword = form.elements.confirmPassword;
    function validatePasswordConfirmation() {
        confirmPassword.setCustomValidity(
            confirmPassword.value && confirmPassword.value !== password.value
                ? "The passwords do not match."
                : ""
        );
    }
    password.addEventListener("input", validatePasswordConfirmation);
    confirmPassword.addEventListener("input", validatePasswordConfirmation);
    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        validatePasswordConfirmation();
        if (!form.reportValidity()) return;
        button.disabled = true;
        message.textContent = "";
        message.classList.remove("error");
        try {
            const response = await fetch("api/profile.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "update_profile",
                    csrfToken: <?php echo json_encode($csrfToken); ?>,
                    ...Object.fromEntries(new FormData(form).entries())
                })
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result.error || "Could not update your profile.");
            }
            password.value = "";
            confirmPassword.value = "";
            message.textContent = result.message;
            document.querySelector(".user-chip").textContent =
                `${result.profile.name} · ${result.profile.role.charAt(0).toUpperCase()}${result.profile.role.slice(1)}`;
        } catch (error) {
            message.textContent = error.message || "Could not update your profile.";
            message.classList.add("error");
        } finally {
            button.disabled = false;
        }
    });
})();
</script>
</body>
</html>
