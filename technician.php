<?php
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/geonet.php";
geonet_require_login("technician");
$display = $_SESSION["display_name"] ?? $_SESSION["username"];
$techId = $_SESSION["tech_id"] ?? "TECH-01";
$profile = geonet_own_profile($_SESSION["user_id"] ?? null);
$csrfToken = $_SESSION["profile_csrf_token"] ?? bin2hex(random_bytes(32));
$_SESSION["profile_csrf_token"] = $csrfToken;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoNet Connect · Technician</title>
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
            <div class="brand-icon"><i class="fa-solid fa-helmet-safety"></i></div>
            <div>
                <h1>Field portal</h1>
                <p>Technician workspace</p>
            </div>
        </div>
        <nav class="nav">
            <a class="nav-item" data-tech-view="profile" href="#profile"><i class="fa-solid fa-id-card"></i> Profile</a>
            <a class="nav-item active" data-tech-view="jobs" href="#jobs"><i class="fa-solid fa-clipboard-list"></i> My jobs</a>
            <a class="nav-item" data-tech-view="logs" href="#logs"><i class="fa-solid fa-clock-rotate-left"></i> Ticket logs</a>
        </nav>
        <div class="sidebar-foot">San Miguel, Iloilo · Field ops</div>
    </aside>
    <div class="main">
        <header class="topbar">
            <div>
                <h2>Assigned work</h2>
                <p class="topbar-sub">Jobs and tickets for your crew assignment</p>
            </div>
            <div class="topbar-right">
                <span class="user-chip"><?php echo htmlspecialchars($display); ?> · Technician</span>
                <a class="btn btn-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>
        <div class="content">
            <section id="view-jobs" class="view active">
            <div class="stats">
                <div class="card stat">
                    <div class="stat-label">Active assignments</div>
                    <div class="stat-value accent-blue" id="statJobs">0</div>
                </div>
                <div class="card stat">
                    <div class="stat-label">Open tickets</div>
                    <div class="stat-value accent-red" id="statTickets">0</div>
                </div>
                <div class="card stat">
                    <div class="stat-label">Availability</div>
                    <div class="stat-value" id="statAvail">—</div>
                    <form id="availabilityForm" class="availability-form">
                        <label class="sr-only" for="availabilitySelect">Set your availability</label>
                        <select id="availabilitySelect" name="availability" required>
                            <option value="available">Available</option>
                            <option value="on_duty">On duty</option>
                            <option value="en_route">En route</option>
                            <option value="off_duty">Off duty</option>
                        </select>
                        <button class="btn btn-outline" type="submit" id="saveAvailability">Update</button>
                    </form>
                    <p class="availability-message" id="availabilityMessage" role="status" aria-live="polite"></p>
                </div>
                <div class="card stat">
                    <div class="stat-label">Phone</div>
                    <div class="stat-value" id="statPhone" style="font-size:18px">—</div>
                </div>
            </div>
            <div class="dash-grid">
                <div class="card map-card">
                    <div class="map-head"><h3>Assigned work map</h3></div>
                    <div id="techMap" class="map"></div>
                </div>
                <div class="card panel">
                    <div class="panel-head"><h3>Tickets assigned to you</h3></div>
                    <div id="jobList" class="event-list"></div>
                    <p class="availability-message" id="requestActionMessage" role="status" aria-live="polite"></p>
                </div>
            </div>
            </section>
            <section id="view-logs" class="view">
                <div class="card panel">
                    <div class="panel-head"><h3>Ticket logs</h3></div>
                    <div id="ticketLogList" class="event-list"></div>
                </div>
            </section>
            <section id="view-profile" class="view">
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
                            <span class="side-meta">Use an address ending in @tech.rlink.ph.</span>
                        </label>
                        <label class="field">Phone
                            <input name="phone" type="tel" maxlength="20" required
                                   value="<?php echo htmlspecialchars($profile["phone"] ?? ""); ?>">
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
    const techId = <?php echo json_encode($techId); ?>;
    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    })[character]);
    const tech = G.technicians.find((t) => t.id === techId);
    const jobs = G.jobs.filter((j) => j.technicianId === techId);
    const tickets = G.tickets.filter((t) => t.technicianId === techId);
    const openTickets = tickets.filter((t) => t.status !== "closed");
    const jobList = document.getElementById("jobList");
    const ticketLogList = document.getElementById("ticketLogList");

    const activeJobs = jobs.filter((j) => j.status !== "resolved").length;
    document.getElementById("statJobs").textContent = activeJobs;
    document.getElementById("statTickets").textContent = openTickets.length;
    document.getElementById("statAvail").textContent = (tech?.availability || "—").replace("_", " ");
    document.getElementById("statPhone").textContent = tech?.phone || "—";
    const availabilitySelect = document.getElementById("availabilitySelect");
    const availabilityForm = document.getElementById("availabilityForm");
    const availabilitySave = document.getElementById("saveAvailability");
    const availabilityMessage = document.getElementById("availabilityMessage");
    if (tech) {
        availabilitySelect.value = tech.availability;
    } else {
        availabilitySelect.disabled = true;
        availabilitySave.disabled = true;
    }
    availabilityForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        availabilitySave.disabled = true;
        availabilityMessage.textContent = "";
        availabilityMessage.classList.remove("error");
        try {
            const response = await fetch("api/technician.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "update_availability",
                    availability: availabilitySelect.value
                })
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result.error || "Could not update availability.");
            }
            tech.availability = result.availability;
            document.getElementById("statAvail").textContent = result.availability.replace("_", " ");
            availabilityMessage.textContent = result.message;
        } catch (error) {
            availabilityMessage.textContent = error.message || "Could not update availability.";
            availabilityMessage.classList.add("error");
        } finally {
            availabilitySave.disabled = false;
        }
    });

    const requestActionMessage = document.getElementById("requestActionMessage");
    let dispatchedRequests = [];
    let requestMapLayer = null;
    function renderAssignedJobs() {
        const activeJobItems = jobs.filter((job) => job.status !== "resolved").map((job) => {
            const outage = G.outages.find((item) => item.id === job.outageId);
            return `<div class="event">
                <strong>${job.id} · ${job.status.replace("_", " ")}</strong>
                <p>${outage ? outage.barangay + " · " + outage.suspected : job.outageId}<br>
                    Assigned ${job.assignedAt}</p>
            </div>`;
        });
        const completedJobItems = jobs.filter((job) => job.status === "resolved").map((job) => {
            const outage = G.outages.find((item) => item.id === job.outageId);
            return `<div class="event">
                <strong>${job.id} · Completed outage job</strong>
                <p>${outage ? outage.barangay + " · " + outage.suspected : job.outageId}<br>
                    Assigned ${job.assignedAt}</p>
            </div>`;
        });
        const assignedRequestItems = dispatchedRequests.filter(
            (request) => ["assigned", "en_route"].includes(request.technicianWorkStatus)
        ).map((request) => `
            <div class="event">
                <strong>Subscription request #${request.id} · Assigned to you</strong>
                <p>${request.name} · ${request.plan}<br>
                    ${request.address}, ${request.barangay} · ${request.status}<br>
                    ${request.phone} · Dispatched ${request.dispatchedAt || "recently"}<br>
                    ${request.technicianWorkStatus === "en_route"
                        ? "You are on route to this assignment."
                        : request.technicianWorkStatus === "not_completed"
                        ? "Marked not complete; reason sent to admin."
                        : request.technicianWorkStatus === "completed"
                            ? "Assignment completed."
                        : request.technicianResponse === "accepted"
                        ? "You accepted this request."
                        : "Your response is pending."}
                    ${request.technicianWorkReason
                        ? `<br>Reason reported to admin: ${escapeHtml(request.technicianWorkReason)}`
                        : ""}
                </p>
                ${request.technicianResponse === "accepted" &&
                  request.technicianWorkStatus !== "not_completed" ? `
                    <div class="subscription-request-actions">
                        ${request.technicianWorkStatus === "assigned" ? `
                            <button class="btn btn-outline" type="button"
                                    data-work-status="en_route" data-request-id="${request.id}">
                                Mark on route
                            </button>
                        ` : request.technicianWorkStatus === "en_route" ? `
                            <button class="btn btn-on-route" type="button" disabled>
                                On route
                            </button>
                        ` : ""}
                        ${request.technicianWorkStatus === "en_route" ? `
                            <button class="btn btn-primary" type="button"
                                    data-work-status="completed" data-request-id="${request.id}">
                                Mark complete
                            </button>
                        ` : ""}
                        <button class="btn btn-outline" type="button"
                                data-show-incomplete-form="${request.id}">
                            Mark not complete
                        </button>
                    </div>
                    <form class="request-incomplete-form" data-incomplete-form="${request.id}" hidden>
                        <label for="incompleteReason${request.id}">Reason this assignment is not complete</label>
                        <textarea id="incompleteReason${request.id}" name="reason" maxlength="2000"
                                  rows="3" required></textarea>
                        <div class="subscription-request-actions">
                            <button class="btn btn-primary" type="submit">Send reason to admin</button>
                            <button class="btn btn-ghost" type="button"
                                    data-cancel-incomplete="${request.id}">Cancel</button>
                        </div>
                    </form>
                ` : request.technicianResponse !== "pending" ? "" : `
                    <div class="subscription-request-actions">
                        <button class="btn btn-primary" type="button"
                                data-request-response="accepted" data-request-id="${request.id}">
                            Accept request
                        </button>
                        <button class="btn btn-danger" type="button"
                                data-request-response="declined" data-request-id="${request.id}">
                            Decline request
                        </button>
                    </div>
                `}
            </div>
        `);
        const completedRequestItems = dispatchedRequests.filter(
            (request) => request.status === "approved" &&
                request.technicianWorkStatus === "completed"
        ).map((request) => `
            <div class="event" data-completed-request="${escapeHtml(request.id)}">
                <strong>Subscription request #${escapeHtml(request.id)} · Approved</strong>
                <p>${escapeHtml(request.name)} · ${escapeHtml(request.plan)}<br>
                    ${escapeHtml(request.address)}, ${escapeHtml(request.barangay)} · ${escapeHtml(request.status)}<br>
                    ${escapeHtml(request.phone)} · Dispatched ${escapeHtml(request.dispatchedAt || "recently")}<br>
                    Technician completion approved by admin.
                </p>
            </div>
        `);
        jobList.innerHTML = [...activeJobItems, ...assignedRequestItems].join("") ||
            "<p class='side-meta'>No jobs or subscription requests assigned.</p>";
        ticketLogList.innerHTML = [...completedJobItems, ...completedRequestItems].join("") ||
            "<p class='side-meta'>No completed work has been logged.</p>";

        [jobList, ticketLogList].forEach((container) => {
            container.querySelectorAll("[data-show-incomplete-form]").forEach((button) => {
                button.addEventListener("click", () => {
                    const form = container.querySelector(
                        `[data-incomplete-form="${button.dataset.showIncompleteForm}"]`
                    );
                    if (!form) return;
                    form.hidden = false;
                    button.hidden = true;
                    form.querySelector("textarea").focus();
                });
            });
            container.querySelectorAll("[data-cancel-incomplete]").forEach((button) => {
                button.addEventListener("click", () => {
                    const form = container.querySelector(
                        `[data-incomplete-form="${button.dataset.cancelIncomplete}"]`
                    );
                    if (!form) return;
                    form.reset();
                    form.hidden = true;
                    container.querySelector(
                        `[data-show-incomplete-form="${button.dataset.cancelIncomplete}"]`
                    ).hidden = false;
                });
            });
            container.querySelectorAll("[data-incomplete-form]").forEach((form) => {
                form.addEventListener("submit", async (event) => {
                    event.preventDefault();
                    await updateRequestWorkStatus({
                        requestId: form.dataset.incompleteForm,
                        workStatus: "not_completed",
                        reason: new FormData(form).get("reason"),
                        button: form.querySelector('[type="submit"]')
                    });
                });
            });
        });

        jobList.querySelectorAll("[data-work-status], [data-request-response]").forEach((button) => {
            button.addEventListener("click", async () => {
                const decision = button.dataset.requestResponse;
                const isDecline = decision === "declined";
                const workStatus = button.dataset.workStatus;
                if (isDecline &&
                    !window.confirm("Decline this subscription request and return it for reassignment?")) {
                    return;
                }
                if (workStatus === "completed" &&
                    !window.confirm("Mark this subscription request assignment as complete?")) {
                    return;
                }
                button.disabled = true;
                try {
                    const response = await fetch("api/technician.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        credentials: "same-origin",
                        body: JSON.stringify({
                            action: workStatus
                                ? "update_subscription_request_work_status"
                                : "respond_subscription_request",
                            requestId: button.dataset.requestId,
                            ...(workStatus ? { workStatus } : { decision })
                        })
                    });
                    const result = await response.json();
                    if (!response.ok || !result.ok) {
                        throw new Error(result.error || "Could not update the assigned work.");
                    }
                    dispatchedRequests = result.requests;
                    renderAssignedJobs();
                    renderAssignedRequestMap();
                    const activeRequests = dispatchedRequests.filter(
                        (request) => ["assigned", "en_route"].includes(request.technicianWorkStatus)
                    ).length;
                    document.getElementById("statJobs").textContent = activeJobs + activeRequests;
                    requestActionMessage.textContent = result.message;
                    requestActionMessage.classList.remove("error");
                } catch (error) {
                    button.disabled = false;
                    requestActionMessage.textContent = error.message || "Could not update the assigned work.";
                    requestActionMessage.classList.add("error");
                }
            });
        });
    }

    async function updateRequestWorkStatus({ requestId, workStatus, reason = "", button }) {
        button.disabled = true;
        try {
            const response = await fetch("api/technician.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "update_subscription_request_work_status",
                    requestId,
                    workStatus,
                    reason
                })
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result.error || "Could not update the assigned work.");
            }
            dispatchedRequests = result.requests;
            renderAssignedJobs();
            renderAssignedRequestMap();
            const activeRequests = dispatchedRequests.filter(
                (request) => ["assigned", "en_route"].includes(request.technicianWorkStatus)
            ).length;
            document.getElementById("statJobs").textContent = activeJobs + activeRequests;
            requestActionMessage.textContent = result.message;
            requestActionMessage.classList.remove("error");
        } catch (error) {
            button.disabled = false;
            requestActionMessage.textContent = error.message || "Could not update the assigned work.";
            requestActionMessage.classList.add("error");
        }
    }

    function renderAssignedRequestMap() {
        if (!requestMapLayer) return;
        requestMapLayer.clearLayers();
        dispatchedRequests.forEach((request) => {
            if (request.status === "approved") return;
            const color = "#d97706";
            L.marker([request.lat, request.lng], {
                icon: L.divIcon({
                    className: "",
                    html: `<div style="width:16px;height:16px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 0 0 2px ${color}55"></div>`,
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                })
            })
                .addTo(requestMapLayer)
                .bindPopup(
                `<strong>${request.technicianWorkStatus === "completed" ? "Completed subscription request" : "Subscription request"} #${escapeHtml(request.id)}</strong><br>` +
                `${escapeHtml(request.name)}<br>${escapeHtml(request.address)}, ${escapeHtml(request.barangay)}<br>${escapeHtml(request.plan)}<br>` +
                    `${request.technicianWorkStatus === "completed"
                        ? "Technician marked complete · awaiting admin approval"
                        : request.technicianWorkStatus === "not_completed"
                            ? "Marked not complete · reason sent to admin"
                            : request.technicianWorkStatus === "en_route"
                                ? "On route to assignment"
                            : request.technicianResponse === "accepted" ? "Accepted by you" : "Response pending"}`
                );
        });
    }

    try {
        const response = await fetch("api/technician.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ action: "list_subscription_requests" })
        });
        const result = await response.json();
        if (!response.ok || !result.ok) {
            throw new Error(result.error || "Could not load dispatched requests.");
        }
        dispatchedRequests = result.requests;
        const activeRequests = dispatchedRequests.filter(
            (request) => request.technicianWorkStatus !== "completed"
        ).length;
        document.getElementById("statJobs").textContent = activeJobs + activeRequests;
        renderAssignedJobs();
    } catch (error) {
        document.getElementById("statJobs").textContent = "—";
        document.getElementById("jobList").innerHTML =
            `<p class="side-meta">${error.message || "Could not load dispatched requests."}</p>`;
    }

    const map = L.map("techMap").setView(G.center, 13);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19 }).addTo(map);
    G.coverageAreas.forEach((area) => {
        L.polygon(area.coordinates, {
            color: "#7b4cc2",
            weight: 2,
            fillColor: "#8f63d2",
            fillOpacity: 0.2
        }).addTo(map).bindTooltip(area.name);
    });
    G.barangays.forEach((barangay) => {
        L.circleMarker([barangay.lat, barangay.lng], {
            radius: 5,
            color: "#1f6ea8",
            fillOpacity: 0.75
        }).addTo(map).bindTooltip(barangay.name);
    });
    G.subscribers.filter((subscriber) => subscriber.mapVisible).forEach((subscriber) => {
        const color = subscriber.status === "online"
            ? "#1f9d58"
            : subscriber.status === "offline" ? "#e24b42" : "#e0a21a";
        L.marker([subscriber.lat, subscriber.lng], {
            icon: L.divIcon({
                className: "",
                html: `<div style="width:14px;height:14px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 0 0 2px ${color}55"></div>`,
                iconSize: [14, 14],
                iconAnchor: [7, 7]
            })
        }).addTo(map).bindPopup(
            `<strong>${escapeHtml(subscriber.name)}</strong><br>${escapeHtml(subscriber.id)}<br>` +
            `${escapeHtml(subscriber.barangay)}<br>${escapeHtml(subscriber.status)}`
        );
    });
    jobs.forEach((j) => {
        const o = G.outages.find((x) => x.id === j.outageId);
        if (!o) return;
        L.circleMarker([o.lat, o.lng], { radius: 10, color: "#e24b42", fillOpacity: 0.8 })
            .addTo(map)
            .bindPopup(`<strong>${o.id}</strong><br>${o.barangay}<br>${o.suspected}`);
    });
    requestMapLayer = L.layerGroup().addTo(map);
    renderAssignedRequestMap();

    function showTechnicianView(viewName) {
        const view = ["jobs", "logs", "profile"].includes(viewName) ? viewName : "jobs";
        document.querySelectorAll(".content > .view").forEach((section) => {
            section.classList.toggle("active", section.id === `view-${view}`);
        });
        document.querySelectorAll("[data-tech-view]").forEach((link) => {
            link.classList.toggle("active", link.dataset.techView === view);
        });
    }
    document.querySelectorAll("[data-tech-view]").forEach((link) => {
        link.addEventListener("click", (event) => {
            event.preventDefault();
            history.replaceState(null, "", link.getAttribute("href"));
            showTechnicianView(link.dataset.techView);
        });
    });
    window.addEventListener("hashchange", () => {
        showTechnicianView(location.hash.slice(1));
    });
    showTechnicianView(location.hash.slice(1));

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
                    csrfToken: <?php echo json_encode($csrfToken); ?>,
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
                `${result.profile.name} · Technician`;
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
