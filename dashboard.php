<?php
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/geonet.php";
geonet_require_login("admin");
$username = $_SESSION["display_name"] ?? $_SESSION["username"];
$profile = geonet_own_profile($_SESSION["user_id"] ?? null);
$csrfToken = $_SESSION["profile_csrf_token"] ?? bin2hex(random_bytes(32));
$_SESSION["profile_csrf_token"] = $csrfToken;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoNet Connect · Admin</title>
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
            <div class="brand-icon"><i class="fa-solid fa-location-dot"></i></div>
            <div>
                <h1>GeoNet Connect</h1>
                <p>R-Link Data and Solutions</p>
            </div>
        </div>
        <nav class="nav">
            <a class="nav-item" data-view="profile" href="#profile"><i class="fa-solid fa-id-card"></i> Profile</a>
            <a class="nav-item active" data-view="dashboard" href="#dashboard"><i class="fa-solid fa-map-location-dot"></i> Network Map</a>
            <a class="nav-item" data-view="subscribers" href="#subscribers"><i class="fa-solid fa-users"></i> Subscribers</a>
            <a class="nav-item" data-view="technicians" href="#technicians"><i class="fa-solid fa-helmet-safety"></i> Technicians</a>
            <a class="nav-item" data-view="maintenance" href="#maintenance"><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance Support</a>
            <a class="nav-item" data-view="analytics" href="#analytics"><i class="fa-solid fa-chart-line"></i> Analytics</a>
            <a class="nav-item" data-view="settings" href="#settings"><i class="fa-solid fa-gear"></i> Settings</a>
        </nav>
        <div class="sidebar-foot">San Miguel, Iloilo · GIS ops</div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div>
                <h2 id="pageTitle">Dashboard</h2>
                <p class="topbar-sub">Network visibility across San Miguel, Iloilo</p>
            </div>
            <div class="topbar-right">
                <a class="user-chip" href="#profile" data-admin-profile-link aria-label="Edit admin profile">
                    <?php echo htmlspecialchars($username); ?>
                </a>
                <a class="btn btn-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>

        <div class="content">
            <section id="view-dashboard" class="view active">
                <div class="stats">
                    <div class="card stat">
                        <div class="stat-label">Total subscribers</div>
                        <div class="stat-value" id="statSubscribers">0</div>
                        <div class="stat-hint">San Miguel, Iloilo coverage</div>
                    </div>
                    <div class="card stat">
                        <div class="stat-label">Active outages</div>
                        <div class="stat-value accent-red" id="statOutages">0</div>
                        <div class="stat-hint">Barangay clusters in the field</div>
                    </div>
                    <div class="card stat">
                        <div class="stat-label">Technicians on duty</div>
                        <div class="stat-value accent-blue" id="statTechs">0</div>
                        <div class="stat-hint">Available, on duty, or en route</div>
                    </div>
                    <div class="card stat">
                        <div class="stat-label">Online now</div>
                        <div class="stat-value accent-green" id="statOnline">0</div>
                        <div class="stat-hint">Green pins on the live map</div>
                    </div>
                </div>
                <div class="toolbar">
                    <button class="btn btn-primary" id="smsOutageBtn"><i class="fa-solid fa-comment-sms"></i> SMS outage report</button>
                    <select id="outageFilter">
                        <option value="all">All outages</option>
                        <option value="active">Active</option>
                        <option value="resolved">Resolved</option>
                    </select>
                    <button class="btn btn-outline" type="button" id="toggleBarangayManager">Manage barangays</button>
                    <button class="btn btn-outline" type="button" id="toggleCoverageManager">Edit coverage</button>
                </div>
                <div class="card panel" id="barangayManager" hidden style="margin-bottom:16px">
                    <div class="panel-head">
                        <div>
                            <h3>Barangays</h3>
                            <p class="side-meta">Manage barangays and their map locations.</p>
                        </div>
                    </div>
                    <form id="addBarangayForm" class="toolbar">
                        <input name="name" maxlength="80" placeholder="Barangay name" aria-label="Barangay name" required>
                        <button class="btn btn-primary" type="submit">Add on map</button>
                        <span class="side-meta">Enter a name, then click the map to place the barangay.</span>
                    </form>
                    <div class="toolbar">
                        <button class="btn btn-danger" type="button" id="deleteAllBarangays">
                            Remove all barangays and linked records
                        </button>
                        <span class="side-meta">Permanently removes subscriber accounts, subscription requests, outages, and tickets linked to the current barangays.</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Name</th><th>Map location</th><th>Actions</th></tr>
                            </thead>
                            <tbody id="barangayTable"></tbody>
                        </table>
                    </div>
                </div>
                <div class="card panel" id="coverageManager" hidden style="margin-bottom:16px">
                    <div class="panel-head"><h3>Edit coverage area</h3></div>
                    <div class="toolbar">
                        <select id="coverageAreaSelect" aria-label="Coverage area to edit">
                            <option value="">Select coverage area…</option>
                        </select>
                        <button class="btn btn-primary" type="button" id="editCoverageArea" disabled>Edit selected area</button>
                        <button class="btn btn-outline" type="button" id="drawCoverage">Draw coverage area</button>
                        <button class="btn btn-ghost" type="button" id="undoCoveragePoint" disabled>Undo point</button>
                        <button class="btn btn-primary" type="button" id="finishCoverage" disabled>Save area</button>
                        <button class="btn btn-ghost" type="button" id="cancelMapAction" disabled>Cancel</button>
                    </div>
                    <p class="map-action-status" id="coverageActionStatus" role="status">Select an area to edit or draw a new coverage area.</p>
                </div>
                <div class="dash-grid">
                    <div class="card map-card">
                        <div class="map-head">
                            <h3>San Miguel network map</h3>
                            <div class="legend-row">
                                <span><i class="dot online"></i>Online</span>
                                <span><i class="dot offline"></i>Offline</span>
                                <span><i class="dot intermittent"></i>Intermittent</span>
                                <span><i class="dot" style="background:#e24b42"></i>Outages</span>
                            </div>
                        </div>
                        <div class="map-tools">
                            <select id="mapSubscriber" aria-label="Subscriber to place">
                                <option value="">Select subscriber…</option>
                            </select>
                            <button class="btn btn-outline" type="button" id="placeSubscriberPin">Place subscriber pin</button>
                            <button class="btn btn-outline" type="button" id="addNapPin">Add NAP box</button>
                            <span class="map-action-status" id="mapActionStatus"></span>
                        </div>
                        <div id="dashMap" class="map"></div>
                    </div>
                    <div class="card panel">
                        <div class="panel-head"><h3>Outage list</h3></div>
                        <div id="outageList" class="event-list"></div>
                    </div>
                </div>
            </section>

            <section id="view-subscribers" class="view">
                <div class="card panel subscription-request-panel">
                    <div class="panel-head">
                        <div>
                            <h3>Subscription requests</h3>
                            <p class="side-meta">Review requested service locations before creating subscriber accounts.</p>
                        </div>
                        <button class="btn btn-outline" type="button" id="refreshSubscriptionRequests">Refresh</button>
                    </div>
                    <div id="subscriptionRequests" class="subscription-request-list">
                        <p class="side-meta">Loading subscription requests…</p>
                    </div>
                </div>
                <div class="toolbar">
                    <button class="btn btn-primary" type="button" id="showAddSubscriber"><i class="fa-solid fa-user-plus"></i> Add subscriber</button>
                    <input id="subSearch" type="search" placeholder="Search name, ID, barangay…">
                    <select id="subStatus">
                        <option value="">All statuses</option>
                        <option value="online">Online</option>
                        <option value="offline">Offline</option>
                        <option value="intermittent">Intermittent</option>
                    </select>
                    <select id="subBarangay"></select>
                </div>
                <div class="split">
                    <div class="card table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Barangay</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="subTable"></tbody>
                        </table>
                    </div>
                    <div class="card side-panel" id="subDetail">
                        <p class="side-meta">Select a subscriber to view their details.</p>
                    </div>
                </div>
            </section>

            <section id="view-technicians" class="view">
                <div class="toolbar">
                    <button class="btn btn-primary" type="button" id="showAddTechnician"><i class="fa-solid fa-user-plus"></i> Add technician</button>
                </div>
                <div class="tech-grid" id="techGrid"></div>
            </section>

            <section id="view-maintenance" class="view">
                <div class="stats">
                    <div class="card stat"><div class="stat-label">Open</div><div class="stat-value accent-red" id="tOpen">0</div></div>
                    <div class="card stat"><div class="stat-label">In progress</div><div class="stat-value accent-blue" id="tProgress">0</div></div>
                    <div class="card stat"><div class="stat-label">Scheduled</div><div class="stat-value accent-yellow" id="tScheduled">0</div></div>
                    <div class="card stat"><div class="stat-label">Closed</div><div class="stat-value accent-green" id="tClosed">0</div></div>
                </div>
                <div class="charts">
                    <div class="card chart-box">
                        <div class="panel-head"><h3>Ticket volume trend</h3></div>
                        <canvas id="trendChart"></canvas>
                    </div>
                    <div class="card chart-box">
                        <div class="panel-head"><h3>Incidents by component</h3></div>
                        <canvas id="componentChart"></canvas>
                    </div>
                </div>
                <div class="toolbar">
                    <select id="ticketPriority">
                        <option value="">All priorities</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                    <select id="ticketStatus">
                        <option value="">All statuses</option>
                        <option value="open">Open</option>
                        <option value="in_progress">In progress</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="closed">Closed</option>
                    </select>
                    <button class="btn btn-outline" id="downloadReport"><i class="fa-solid fa-download"></i> Download report</button>
                </div>
                <div class="split">
                    <div class="card table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Opened</th>
                                    <th>Barangay</th>
                                    <th>Component</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="ticketTable"></tbody>
                        </table>
                    </div>
                    <div class="card side-panel" id="ticketDetail">
                        <p class="side-meta">Select a ticket sourced from outage and barangay analytics.</p>
                    </div>
                </div>
            </section>

            <section id="view-analytics" class="view">
                <div class="charts">
                    <div class="card chart-box">
                        <div class="panel-head"><h3>Network uptime (%)</h3></div>
                        <canvas id="uptimeChart"></canvas>
                    </div>
                    <div class="card chart-box">
                        <div class="panel-head"><h3>Tickets opened</h3></div>
                        <canvas id="analyticsTicketChart"></canvas>
                    </div>
                </div>
                <div class="card panel">
                    <div class="panel-head"><h3>Barangay incident mix (Aug 2026)</h3></div>
                    <div id="barangayMix"></div>
                </div>
            </section>

            <section id="view-settings" class="view">
                <div class="settings-grid">
                    <div class="card panel">
                        <div class="panel-head"><h3>SMS API (Semaphore)</h3></div>
                        <p class="side-meta">Used to report outages to technicians and affected subscribers in San Miguel.</p>
                        <form id="smsSettingsForm">
                            <div class="check">
                                <input type="checkbox" id="smsEnabled" checked>
                                <label for="smsEnabled">Enable SMS notifications</label>
                            </div>
                            <div class="check">
                                <input type="checkbox" id="smsMock" checked>
                                <label for="smsMock">Mock mode (log locally, do not send to carrier)</label>
                            </div>
                            <div class="field">
                                <label>Sender name</label>
                                <input id="smsSender" maxlength="11" value="RLINK">
                            </div>
                            <div class="field">
                                <label>Semaphore API key</label>
                                <input id="smsKey" type="password" placeholder="Paste API key to enable live send">
                            </div>
                            <button class="btn btn-primary" type="submit">Save SMS settings</button>
                        </form>
                    </div>
                    <div class="card panel">
                        <div class="panel-head"><h3>Recent SMS log</h3></div>
                        <div id="smsLog"></div>
                    </div>
                </div>
            </section>
            <section id="view-profile" class="view">
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
                            <span class="side-meta">Use an address ending in @admin.rlink.ph.</span>
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

<div class="modal-back" id="smsModal">
    <div class="modal">
        <h3>SMS outage report</h3>
        <p class="side-meta">Notify technicians and/or subscribers about an active cluster.</p>
        <div class="field">
            <label>Outage</label>
            <select id="smsOutageSelect"></select>
        </div>
        <div class="field">
            <label>Send to</label>
            <select id="smsAudience">
                <option value="both">Technicians and affected subscribers</option>
                <option value="technicians">Technicians only</option>
                <option value="subscribers">Affected subscribers only</option>
            </select>
        </div>
        <div class="field">
            <label>Message</label>
            <textarea id="smsMessage"></textarea>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button class="btn btn-ghost" type="button" id="smsCancel">Cancel</button>
            <button class="btn btn-primary" type="button" id="smsSend"><i class="fa-solid fa-paper-plane"></i> Send SMS</button>
        </div>
    </div>
</div>

<div class="modal-back record-modal-back" id="addSubscriberModal" aria-hidden="true">
    <section class="modal record-modal" role="dialog" aria-modal="true" aria-labelledby="addSubscriberTitle">
        <header class="record-modal-head">
            <div>
                <span class="record-modal-kicker">CUSTOMER ACCOUNT</span>
                <h3 id="addSubscriberTitle">Add subscriber</h3>
                <p>Enter account details. You can place their map pin later.</p>
            </div>
            <button class="icon-button" type="button" id="cancelAddSubscriber" aria-label="Close form">&times;</button>
        </header>
        <form id="addSubscriberForm">
            <div class="record-form-grid">
                <label class="record-field">Full name
                    <input name="name" maxlength="120" autocomplete="name" required>
                </label>
                <label class="record-field">Barangay
                    <select name="barangay" id="addSubBarangay" required></select>
                </label>
                <label class="record-field">Email / login
                    <input name="email" type="email" maxlength="190" autocomplete="email" placeholder="name@sub.rlink.ph" required>
                    <small>Subscriber accounts must use @sub.rlink.ph.</small>
                </label>
                <label class="record-field">Phone
                    <input name="phone" maxlength="20" autocomplete="tel" required>
                </label>
                <label class="record-field record-field-wide">Service address
                    <input name="address" maxlength="255" autocomplete="street-address" required>
                </label>
                <label class="record-field">Plan
                    <input name="plan" maxlength="80" required>
                </label>
                <label class="record-field">Status
                    <select name="status" required>
                        <option value="online">Online</option>
                        <option value="offline">Offline</option>
                        <option value="intermittent">Intermittent</option>
                    </select>
                </label>
                <label class="record-field record-field-wide">Initial password
                    <input name="password" type="password" minlength="8" autocomplete="new-password" required>
                    <small>Use at least 8 characters.</small>
                </label>
            </div>
            <footer class="record-modal-actions">
                <button class="btn btn-ghost" type="button" id="cancelAddSubscriberFooter">Cancel</button>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-plus"></i> Create subscriber</button>
            </footer>
        </form>
    </section>
</div>

<div class="modal-back record-modal-back" id="addTechnicianModal" aria-hidden="true">
    <section class="modal record-modal" role="dialog" aria-modal="true" aria-labelledby="addTechnicianTitle">
        <header class="record-modal-head">
            <div>
                <span class="record-modal-kicker">FIELD TEAM ACCOUNT</span>
                <h3 id="addTechnicianTitle">Add technician</h3>
                <p>Create a login and add a technician to the team roster.</p>
            </div>
            <button class="icon-button" type="button" id="cancelAddTechnician" aria-label="Close form">&times;</button>
        </header>
        <form id="addTechnicianForm">
            <div class="record-form-grid">
                <label class="record-field">Full name
                    <input name="name" maxlength="120" autocomplete="name" required>
                </label>
                <label class="record-field">Phone
                    <input name="phone" maxlength="20" autocomplete="tel" required>
                </label>
                <label class="record-field record-field-wide">Email / login
                    <div class="email-composite">
                        <input name="username" maxlength="64" pattern="[A-Za-z0-9._%+\-]+"
                               autocomplete="username" required>
                        <span aria-hidden="true">@tech.rlink.ph</span>
                    </div>
                    <small>Choose the username for the technician login.</small>
                </label>
                <label class="record-field">Job title
                    <input name="jobTitle" maxlength="80" required>
                </label>
                <label class="record-field record-field-wide">Initial password
                    <input name="password" type="password" minlength="8" autocomplete="new-password" required>
                    <small>Use at least 8 characters.</small>
                </label>
            </div>
            <footer class="record-modal-actions">
                <button class="btn btn-ghost" type="button" id="cancelAddTechnicianFooter">Cancel</button>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-plus"></i> Create technician</button>
            </footer>
        </form>
    </section>
</div>

<div class="toast" id="toast"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/data.js"></script>
<script src="assets/data-api.js"></script>
<script src="assets/app.js?v=<?php echo (int) filemtime(__DIR__ . "/assets/app.js"); ?>"></script>
</body>
</html>
