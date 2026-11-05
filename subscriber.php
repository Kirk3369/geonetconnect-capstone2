<?php
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/geonet.php";
geonet_require_login("subscriber");
$display = $_SESSION["display_name"] ?? $_SESSION["username"];
$subId = $_SESSION["sub_id"] ?? "SUB-1042";
$profile = geonet_own_profile($_SESSION["user_id"] ?? null);
$csrfToken = $_SESSION["profile_csrf_token"] ?? bin2hex(random_bytes(32));
$_SESSION["profile_csrf_token"] = $csrfToken;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoNet Connect · Subscriber</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon"><i class="fa-solid fa-wifi"></i></div>
            <div>
                <h1>My connection</h1>
                <p>Subscriber portal</p>
            </div>
        </div>
        <nav class="nav">
            <a class="nav-item" data-subscriber-view="profile" href="#profile"><i class="fa-solid fa-id-card"></i> Profile</a>
            <a class="nav-item" data-subscriber-view="account" href="#account"><i class="fa-solid fa-house"></i> My account</a>
        </nav>
        <div class="sidebar-foot">San Miguel, Iloilo · R-Link</div>
    </aside>
    <div class="main">
        <header class="topbar">
            <div>
                <h2>Account overview</h2>
                <p class="topbar-sub">Your plan, status, and nearby network notices</p>
            </div>
            <div class="topbar-right">
                <a class="user-chip" href="#profile" data-subscriber-profile-link>
                    <?php echo htmlspecialchars($display); ?> · Subscriber
                </a>
                <a class="btn btn-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>
        <div class="content">
            <section id="subscriber-view-account" class="view active">
            <div class="stats">
                <div class="card stat">
                    <div class="stat-label">Status</div>
                    <div class="stat-value" id="statStatus">—</div>
                </div>
                <div class="card stat">
                    <div class="stat-label">Plan</div>
                    <div class="stat-value" id="statPlan" style="font-size:18px">—</div>
                </div>
                <div class="card stat">
                    <div class="stat-label">Account ID</div>
                    <div class="stat-value" id="statId" style="font-size:18px"><?php echo htmlspecialchars($subId); ?></div>
                </div>
                <div class="card stat">
                    <div class="stat-label">Active notices</div>
                    <div class="stat-value accent-red" id="statNotices">0</div>
                </div>
            </div>
            <div class="dash-grid">
                <div class="card map-card">
                    <div class="map-head"><h3>Your service location</h3></div>
                    <div id="subMap" class="map"></div>
                </div>
                <div class="card panel">
                    <div class="panel-head"><h3>Account details</h3></div>
                    <div id="accountDetail" class="event-list"></div>
                </div>
            </div>
            <div class="card panel" style="margin-top:16px">
                <div class="panel-head"><h3>Outage notices</h3></div>
                <div id="noticeList" class="event-list"></div>
            </div>
            </section>
            <section id="subscriber-view-profile" class="view">
                <section class="card panel profile-panel">
                    <div class="panel-head"><h3>Edit your details</h3></div>
                    <form id="profileForm" class="profile-form">
                        <input id="profileCsrfToken" type="hidden"
                               value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <label class="field">Full name
                            <input name="name" maxlength="120" required
                                   value="<?php echo htmlspecialchars($profile["name"]); ?>">
                        </label>
                        <label class="field">Email address
                            <input name="email" type="email" maxlength="190" required
                                   value="<?php echo htmlspecialchars($profile["email"]); ?>">
                            <span class="side-meta">Use an address ending in @sub.rlink.ph.</span>
                        </label>
                        <label class="field">Phone
                            <input name="phone" type="tel" maxlength="20" required
                                   value="<?php echo htmlspecialchars($profile["phone"] ?? ""); ?>">
                        </label>
                        <label class="field">Service address
                            <input name="address" maxlength="255" required
                                   value="<?php echo htmlspecialchars($profile["address"] ?? ""); ?>">
                        </label>
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
            </section>
        </div>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/data.js"></script>
<script src="assets/data-api.js"></script>
<script>
(async function () {
    if (window.GEONET_LOAD) {
        await window.GEONET_LOAD;
    }
    const G = window.GEONET;
    const subId = <?php echo json_encode($subId); ?>;
    const sub = G.subscribers.find((s) => s.id === subId);
    const notices = G.outages.filter((o) => o.subscribers.includes(subId));
    const active = notices.filter((o) => o.status === "active");
    let subMap = null;

    document.getElementById("statStatus").textContent = sub?.status || "—";
    document.getElementById("statPlan").textContent = sub?.plan || "—";
    document.getElementById("statNotices").textContent = active.length;

    document.getElementById("accountDetail").innerHTML = sub ? `
        <div class="event"><strong>${sub.name}</strong><p>${sub.address}</p></div>
        <div class="event"><strong>Barangay</strong><p>${sub.barangay}</p></div>
        <div class="event"><strong>Phone</strong><p>${sub.phone}</p></div>
        <div class="event"><strong>Connected since</strong><p>${sub.connected}</p></div>
    ` : "<p class='side-meta'>Account not found.</p>";

    document.getElementById("noticeList").innerHTML = notices.length
        ? notices.map((o) => `
            <div class="event">
                <strong>${o.id} · ${o.status} · ${o.severity}</strong>
                <p>${o.barangay}<br>${o.suspected}<br>Started ${o.started}${o.resolved ? " · Restored " + o.resolved : ""}</p>
            </div>
        `).join("")
        : "<p class='side-meta' style='padding:12px'>No outage notices for your account right now.</p>";

    if (sub) {
        subMap = L.map("subMap").setView([sub.lat, sub.lng], 15);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19 }).addTo(subMap);
        G.coverageAreas.forEach((area) => {
            L.polygon(area.coordinates, {
                color: "#7b4cc2",
                weight: 2,
                fillColor: "#8f63d2",
                fillOpacity: 0.2
            }).addTo(subMap).bindTooltip(area.name);
        });
        const color = sub.status === "online" ? "#1f9d58" : sub.status === "offline" ? "#e24b42" : "#e0a21a";
        L.circleMarker([sub.lat, sub.lng], { radius: 12, color, fillOpacity: 0.9 })
            .addTo(subMap)
            .bindPopup(`<strong>${sub.name}</strong><br>${sub.plan}<br>${sub.status}`);
    }

    function showSubscriberView(viewName) {
        const view = viewName === "profile" ? "profile" : "account";
        document.querySelectorAll(".content > .view").forEach((section) => {
            section.classList.toggle("active", section.id === `subscriber-view-${view}`);
        });
        document.querySelectorAll("[data-subscriber-view]").forEach((link) => {
            link.classList.toggle("active", link.dataset.subscriberView === view);
        });
        document.querySelector(".topbar h2").textContent =
            view === "profile" ? "Profile" : "Account overview";
        document.querySelector(".topbar-sub").textContent = view === "profile"
            ? "Manage your account details"
            : "Your plan, status, and nearby network notices";
        if (view === "account" && subMap) {
            setTimeout(() => subMap.invalidateSize(), 100);
        }
    }
    document.querySelectorAll("[data-subscriber-view]").forEach((link) => {
        link.addEventListener("click", (event) => {
            event.preventDefault();
            history.replaceState(null, "", link.getAttribute("href"));
            showSubscriberView(link.dataset.subscriberView);
        });
    });
    document.querySelector("[data-subscriber-profile-link]").addEventListener("click", (event) => {
        event.preventDefault();
        history.replaceState(null, "", "#profile");
        showSubscriberView("profile");
    });
    window.addEventListener("hashchange", () => {
        showSubscriberView(location.hash.slice(1));
    });
    showSubscriberView(location.hash.slice(1));

    const profileForm = document.getElementById("profileForm");
    const profileMessage = document.getElementById("profileMessage");
    const profileSave = document.getElementById("saveProfile");
    const profilePassword = profileForm.elements.password;
    const profileConfirmPassword = profileForm.elements.confirmPassword;
    function validateProfilePassword() {
        profileConfirmPassword.setCustomValidity(
            profileConfirmPassword.value && profileConfirmPassword.value !== profilePassword.value
                ? "The passwords do not match."
                : ""
        );
    }
    profilePassword.addEventListener("input", validateProfilePassword);
    profileConfirmPassword.addEventListener("input", validateProfilePassword);
    profileForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        validateProfilePassword();
        if (!profileForm.reportValidity()) return;
        profileSave.disabled = true;
        profileMessage.textContent = "";
        profileMessage.classList.remove("error");
        try {
            const response = await fetch("api/profile.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "update_profile",
                    csrfToken: document.getElementById("profileCsrfToken").value,
                    ...Object.fromEntries(new FormData(profileForm).entries())
                })
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result.error || "Could not update your profile.");
            }
            profilePassword.value = "";
            profileConfirmPassword.value = "";
            profileMessage.textContent = result.message;
            document.querySelector(".user-chip").textContent =
                `${result.profile.name} · Subscriber`;
        } catch (error) {
            profileMessage.textContent = error.message || "Could not update your profile.";
            profileMessage.classList.add("error");
        } finally {
            profileSave.disabled = false;
        }
    });
})();
</script>
</body>
</html>
