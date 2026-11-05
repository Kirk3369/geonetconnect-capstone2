(async function () {
    if (window.GEONET_LOAD) {
        await window.GEONET_LOAD;
    }
    const G = window.GEONET;
    const titles = {
        dashboard: "Dashboard",
        subscribers: "Subscribers",
        technicians: "Technicians",
        maintenance: "Maintenance Support",
        analytics: "Analytics",
        settings: "Settings",
        profile: "Profile"
    };

    let dashMap, subMiniMap, trendChart, componentChart, uptimeChart, ticketVolChart;
    let dashMapLayers, mapMode = null, coveragePoints = [], coveragePreview = null;
    let coverageEditId = null, coverageEditName = "", coverageHandles = [];
    let pendingSubscriptionRequests = [];
    let activeView = "dashboard";
    let appInitialized = false;
    let requestRefreshErrorShown = false;
    let selectedSubId = null;
    let editingSubId = null;
    let selectedBarangayId = null;
    let selectedTicketId = G.tickets[0]?.id;
    let mapsReady = { dash: false };

    function toast(msg) {
        const el = document.getElementById("toast");
        el.textContent = msg;
        el.classList.add("show");
        setTimeout(() => el.classList.remove("show"), 3800);
    }

    function applyPayload(data) {
        if (!data || !data.geonet) {
            return;
        }
        Object.keys(data.geonet).forEach((key) => {
            G[key] = data.geonet[key];
        });
        const subscriberSelect = document.getElementById("mapSubscriber");
        if (subscriberSelect) {
            const selectedId = subscriberSelect.value;
            subscriberSelect.innerHTML =
                '<option value="">Select subscriber…</option>' +
                G.subscribers.map((s) =>
                    `<option value="${escapeHtml(s.id)}">${escapeHtml(s.id)} · ${escapeHtml(s.name)}</option>`
                ).join("");
            subscriberSelect.value = selectedId;
        }
        const coverageSelect = document.getElementById("coverageAreaSelect");
        if (coverageSelect) {
            const selectedId = coverageSelect.value;
            coverageSelect.innerHTML =
                '<option value="">Select coverage area…</option>' +
                (G.coverageAreas || []).map((area) =>
                    `<option value="${escapeHtml(area.id)}">${escapeHtml(area.name)}</option>`
                ).join("");
            coverageSelect.value = selectedId;
            document.getElementById("editCoverageArea").disabled = !coverageSelect.value;
        }
    }

    async function postOps(body) {
        const res = await fetch("api/ops.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (!data.ok) {
            toast(data.error || "Request failed.");
            return null;
        }
        applyPayload(data);
        return data;
    }

    function badge(status) {
        return `<span class="badge ${status}">${status.replace("_", " ")}</span>`;
    }

    function findSub(id) {
        return G.subscribers.find((s) => s.id === id);
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (character) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        }[character]));
    }

    function findTech(id) {
        return G.technicians.find((t) => t.id === id);
    }

    function findOutage(id) {
        return G.outages.find((o) => o.id === id);
    }

    function jobsFor(techId) {
        return G.jobs.filter((j) => j.technicianId === techId);
    }

    function pinColor(status) {
        if (status === "online") return "#1f9d58";
        if (status === "offline") return "#e24b42";
        return "#e0a21a";
    }

    function circleIcon(color, size) {
        return L.divIcon({
            className: "",
            html: `<div style="width:${size}px;height:${size}px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 0 0 2px ${color}55"></div>`,
            iconSize: [size, size],
            iconAnchor: [size / 2, size / 2]
        });
    }

    function addBaseLayers(map) {
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap"
        }).addTo(map);

    }

    function addInfrastructure(map) {
        G.landmarks.filter((lm) => lm.type !== "nap" || lm.mapVisible).forEach((lm) => {
            const color = lm.type === "nap" ? "#1f6ea8" : lm.type === "fiber" ? "#1a8a86" : "#6b7c8f";
            L.marker([lm.lat, lm.lng], { icon: circleIcon(color, 10) })
                .addTo(map)
                .bindPopup(`<strong>${escapeHtml(lm.name)}</strong><br>${escapeHtml(lm.type)}`);
        });
    }

    function renderDashMapLayers() {
        if (!dashMapLayers) return;
        dashMapLayers.clearLayers();
        const outageFilter = document.getElementById("outageFilter")?.value || "all";
        G.outages.filter((outage) => outageFilter === "all" || outage.status === outageFilter).forEach((outage) => {
            const color = outage.status === "active" ? "#e24b42" : "#1f9d58";
            L.circle([outage.lat, outage.lng], {
                radius: 220 + outage.affected * 40,
                color,
                fillColor: color,
                fillOpacity: 0.22,
                weight: 2
            }).addTo(dashMapLayers).bindPopup(
                `<strong>${escapeHtml(outage.id)} · ${escapeHtml(outage.barangay)}</strong><br>` +
                `${outage.affected} subscribers<br>${escapeHtml(outage.source)}<br>${escapeHtml(outage.suspected)}`
            );
        });
        const barangayManagerOpen = !document.getElementById("barangayManager").hidden;
        if (!barangayManagerOpen || !G.barangays.some((barangay) => String(barangay.id) === selectedBarangayId)) {
            selectedBarangayId = null;
        }
        if (barangayManagerOpen) {
            G.barangays.forEach((barangay) => {
                const isSelected = String(barangay.id) === selectedBarangayId;
                const marker = L.marker([barangay.lat, barangay.lng], {
                    draggable: isSelected,
                    icon: circleIcon(isSelected ? "#d97706" : "#1f6ea8", isSelected ? 18 : 12),
                    zIndexOffset: isSelected ? 1000 : 0
                }).addTo(dashMapLayers).bindTooltip(
                    `${escapeHtml(barangay.name)}${isSelected ? " · selected, drag to move" : " · click to select"}`
                );
                marker.on("click", (event) => {
                    L.DomEvent.stopPropagation(event);
                    selectedBarangayId = String(barangay.id);
                    renderDashMapLayers();
                    document.getElementById("mapActionStatus").textContent =
                        `${barangay.name} selected and highlighted. Drag its pin to update the location.`;
                });
                marker.on("dragend", async (event) => {
                    const newLocation = event.target.getLatLng();
                    try {
                        const result = await postOps({
                            action: "update_barangay",
                            barangayId: barangay.id,
                            name: barangay.name,
                            lat: newLocation.lat,
                            lng: newLocation.lng
                        });
                        if (!result) {
                            marker.setLatLng([barangay.lat, barangay.lng]);
                            return;
                        }
                        refreshBarangayViews();
                        toast(`${barangay.name} location updated across all maps.`);
                    } catch (error) {
                        marker.setLatLng([barangay.lat, barangay.lng]);
                        toast(error.message || `Could not update ${barangay.name}'s location.`);
                    }
                });
            });
        }
        addInfrastructure(dashMapLayers);
        G.subscribers.filter((s) => s.mapVisible).forEach((s) => {
            L.marker([s.lat, s.lng], { icon: circleIcon(pinColor(s.status), 14) })
                .addTo(dashMapLayers)
                .bindPopup(`<strong>${escapeHtml(s.name)}</strong><br>${escapeHtml(s.id)}<br>${escapeHtml(s.barangay)}<br>${escapeHtml(s.status)}`);
        });
        pendingSubscriptionRequests.filter((request) => request.status === "pending").forEach((request) => {
            const marker = L.marker([request.lat, request.lng], { icon: circleIcon("#d97706", 16) })
                .addTo(dashMapLayers)
                .bindPopup(`
                    <div class="request-map-popup">
                        <strong>Subscription request · ${escapeHtml(request.name)}</strong>
                        <span>${escapeHtml(request.barangay)} · ${escapeHtml(request.plan)}</span>
                        <span>${request.technicianResponse === "accepted"
                            ? request.technicianWorkStatus === "completed"
                                ? "Technician completed · awaiting admin approval"
                                : request.technicianWorkStatus === "en_route"
                                    ? "Technician is on route"
                                : "Technician accepted"
                            : request.technicianResponse === "declined"
                                ? "Technician declined · ready for reassignment"
                                : request.technicianId
                                    ? "Awaiting technician response"
                                    : "No technician assigned"}</span>
                        ${request.technicianWorkReason
                            ? `<span>Technician update: ${escapeHtml(request.technicianWorkReason)}</span>`
                            : ""}
                        ${request.technicianResponse === "accepted" &&
                          request.technicianWorkStatus === "completed" ? `
                            <button class="btn btn-primary subscription-request-approve"
                                    type="button" data-map-approve-request="${request.id}">
                                Approve subscription request
                            </button>
                        ` : ""}
                        <label for="mapRequestTechnician${request.id}">Dispatch technician</label>
                        <select id="mapRequestTechnician${request.id}" data-map-request-technician="${request.id}">
                            <option value="">Choose technician…</option>
                            ${G.technicians.map((technician) => `
                                <option value="${escapeHtml(technician.id)}"
                                    ${technician.id === request.technicianId ? "selected" : ""}>
                                    ${escapeHtml(technician.name)} · ${escapeHtml(technician.id)}
                                </option>
                            `).join("")}
                        </select>
                        <button class="btn btn-outline" type="button"
                                data-map-dispatch-request="${request.id}"
                                ${G.technicians.length === 0 ? "disabled" : ""}>
                            ${request.technicianId ? "Re-dispatch technician" : "Dispatch technician"}
                        </button>
                        <button class="btn btn-danger" type="button"
                                data-map-reject-request="${request.id}">
                            Reject subscription request
                        </button>
                    </div>
                `);
            marker.on("popupopen", (event) => {
                const popupElement = event.popup.getElement();
                L.DomEvent.disableClickPropagation(popupElement);
                const dispatchButton = popupElement.querySelector("[data-map-dispatch-request]");
                dispatchButton.addEventListener("click", () => {
                    const select = popupElement.querySelector("[data-map-request-technician]");
                    dispatchSubscriptionRequest(request.id, select.value, dispatchButton);
                });
                const rejectButton = popupElement.querySelector("[data-map-reject-request]");
                rejectButton.addEventListener("click", () => {
                    reviewSubscriptionRequest(request.id, "reject", rejectButton);
                });
                const approveButton = popupElement.querySelector("[data-map-approve-request]");
                if (approveButton) {
                    approveButton.addEventListener("click", () => {
                        reviewSubscriptionRequest(request.id, "approve", approveButton);
                    });
                }
            });
        });
        (G.coverageAreas || []).forEach((area) => {
            L.polygon(area.coordinates, {
                color: "#7b4cc2",
                weight: 2,
                fillColor: "#8f63d2",
                fillOpacity: 0.2,
                interactive: false
            }).addTo(dashMapLayers).bindTooltip(escapeHtml(area.name));
        });
    }

    function initDashMap() {
        if (mapsReady.dash) {
            dashMap.invalidateSize();
            return;
        }
        dashMap = L.map("dashMap").setView(G.center, 14);
        addBaseLayers(dashMap);
        dashMapLayers = L.layerGroup().addTo(dashMap);
        renderDashMapLayers();
        dashMap.on("click", handleDashMapClick);
        mapsReady.dash = true;
        setTimeout(() => dashMap.invalidateSize(), 200);
    }

    function setMapMode(mode, message) {
        if (mode !== "coverage" && mode !== "coverage-edit" && coveragePreview && dashMap) {
            dashMap.removeLayer(coveragePreview);
            coveragePreview = null;
            coveragePoints = [];
            coverageEditId = null;
            coverageEditName = "";
            clearCoverageHandles();
        }
        mapMode = mode;
        document.getElementById("placeSubscriberPin").classList.toggle("btn-primary", mode === "subscriber");
        document.getElementById("placeSubscriberPin").classList.toggle("btn-outline", mode !== "subscriber");
        document.getElementById("addNapPin").classList.toggle("btn-primary", mode === "nap");
        document.getElementById("addNapPin").classList.toggle("btn-outline", mode !== "nap");
        document.getElementById("drawCoverage").classList.toggle("btn-primary", mode === "coverage");
        document.getElementById("drawCoverage").classList.toggle("btn-outline", mode !== "coverage");
        document.getElementById("undoCoveragePoint").disabled =
            (mode !== "coverage" && mode !== "coverage-edit") || coveragePoints.length === 0;
        document.getElementById("finishCoverage").disabled =
            (mode !== "coverage" && mode !== "coverage-edit") || coveragePoints.length < 3;
        document.getElementById("finishCoverage").textContent =
            mode === "coverage-edit" ? "Update area" : "Save area";
        document.getElementById("cancelMapAction").disabled = mode === null;
        document.getElementById("mapActionStatus").textContent = message;
        document.getElementById("coverageActionStatus").textContent =
            mode === "coverage" || mode === "coverage-edit"
                ? message
                : "Select an area to edit or draw a new coverage area.";
    }

    function renderCoveragePreview() {
        if (coveragePreview) {
            dashMap.removeLayer(coveragePreview);
            coveragePreview = null;
        }
        if (coveragePoints.length >= 3) {
            coveragePreview = L.polygon(coveragePoints, {
                color: coverageEditId ? "#d97706" : "#7b4cc2",
                weight: 3,
                dashArray: coverageEditId ? null : "6 4",
                fillColor: coverageEditId ? "#f59e0b" : "#8f63d2",
                fillOpacity: coverageEditId ? 0.18 : 0.12
            }).addTo(dashMap);
        } else if (coveragePoints.length > 0) {
            coveragePreview = L.polyline(coveragePoints, {
                color: "#7b4cc2",
                dashArray: "6 4"
            }).addTo(dashMap);
        }
        document.getElementById("finishCoverage").disabled = coveragePoints.length < 3;
        document.getElementById("undoCoveragePoint").disabled = coveragePoints.length === 0;
        const message =
            `${coverageEditId ? "Editing" : "Drawing"} ${coverageEditName || "coverage area"}: ` +
            `${coveragePoints.length} boundary point${coveragePoints.length === 1 ? "" : "s"}. ` +
            `${coveragePoints.length < 3 ? "Add at least 3 points." : "Drag points to reshape; click edge dots to add points."}`;
        document.getElementById("mapActionStatus").textContent = message;
        document.getElementById("coverageActionStatus").textContent = message;
        if (coverageEditId !== null) {
            renderCoverageHandles();
        }
    }

    function clearCoverageHandles() {
        coverageHandles.forEach((handle) => dashMap.removeLayer(handle));
        coverageHandles = [];
    }

    function renderCoverageHandles() {
        clearCoverageHandles();
        coveragePoints.forEach((point, index) => {
            const handle = L.marker(point, {
                draggable: true,
                keyboard: false,
                icon: circleIcon("#d97706", 14),
                zIndexOffset: 1000
            }).addTo(dashMap);
            handle.bindTooltip("Drag to reshape · Right-click to remove point");
            handle.on("drag", (event) => {
                coveragePoints[index] = [event.latlng.lat, event.latlng.lng];
                if (coveragePreview) coveragePreview.setLatLngs(coveragePoints);
            });
            handle.on("contextmenu", (event) => {
                event.originalEvent.preventDefault();
                event.originalEvent.stopPropagation();
                coveragePoints.splice(index, 1);
                renderCoveragePreview();
            });
            handle.on("click", (event) => L.DomEvent.stopPropagation(event));
            coverageHandles.push(handle);

            const nextPoint = coveragePoints[(index + 1) % coveragePoints.length];
            const midpoint = [(point[0] + nextPoint[0]) / 2, (point[1] + nextPoint[1]) / 2];
            const addPointHandle = L.marker(midpoint, {
                keyboard: false,
                icon: circleIcon("#7b4cc2", 11),
                zIndexOffset: 999
            }).addTo(dashMap);
            addPointHandle.bindTooltip("Click to add a boundary point");
            addPointHandle.on("click", (event) => {
                L.DomEvent.stopPropagation(event);
                coveragePoints.splice(index + 1, 0, [event.latlng.lat, event.latlng.lng]);
                renderCoveragePreview();
            });
            coverageHandles.push(addPointHandle);
        });
    }

    function startCoverageEdit(area) {
        if (coveragePreview) dashMap.removeLayer(coveragePreview);
        clearCoverageHandles();
        coverageEditId = area.id;
        coverageEditName = area.name;
        coveragePoints = area.coordinates.map((point) => [...point]);
        renderCoveragePreview();
        setMapMode("coverage-edit", `Editing ${area.name}. Drag boundary points to expand or reduce the area.`);
        dashMap.fitBounds(L.latLngBounds(coveragePoints), { padding: [24, 24], maxZoom: 16 });
    }

    function undoCoveragePoint() {
        if (coveragePoints.length === 0) return;
        coveragePoints.pop();
        renderCoveragePreview();
    }

    async function handleDashMapClick(event) {
        if (!mapMode) return;
        const { lat, lng } = event.latlng;
        if (mapMode === "add-barangay") {
            const form = document.getElementById("addBarangayForm");
            const name = form.elements.name.value.trim();
            if (!name) {
                toast("Enter a barangay name before choosing its map location.");
                return;
            }
            const result = await postOps({ action: "add_barangay", name, lat, lng });
            if (!result) return;
            form.reset();
            refreshBarangayViews();
            setMapMode(null, `${name} added at the selected map location.`);
            toast(result.message);
            return;
        }
        if (mapMode === "subscriber") {
            const subscriberId = document.getElementById("mapSubscriber").value;
            if (!subscriberId) {
                toast("Select a subscriber before placing a pin.");
                return;
            }
            const subscriber = findSub(subscriberId);
            const result = await postOps({
                action: "move_subscriber_pin",
                subscriberId,
                lat,
                lng
            });
            if (!result) return;
            renderDashMapLayers();
            setMapMode(null, `${subscriber ? subscriber.name : subscriberId} pin updated.`);
            toast(result.message);
            return;
        }
        if (mapMode === "nap") {
            const name = window.prompt("Enter a name for this NAP box:");
            if (name === null) return;
            const result = await postOps({ action: "add_nap_pin", name, lat, lng });
            if (!result) return;
            renderDashMapLayers();
            setMapMode(null, "NAP box added. Choose another map action when ready.");
            toast(result.message);
            return;
        }
        if (mapMode === "coverage") {
            coveragePoints.push([lat, lng]);
            renderCoveragePreview();
        }
    }

    async function saveCoverageArea() {
        if (coveragePoints.length < 3) return;
        const isEditing = coverageEditId !== null;
        const name = isEditing ? coverageEditName : window.prompt("Enter a name for this coverage area:");
        if (name === null) return;
        const result = await postOps({
            action: isEditing ? "update_coverage_area" : "add_coverage_area",
            ...(isEditing ? { areaId: coverageEditId } : {}),
            name,
            coordinates: coveragePoints.map(([lat, lng]) => ({ lat, lng }))
        });
        if (!result) return;
        if (coveragePreview) {
            dashMap.removeLayer(coveragePreview);
            coveragePreview = null;
        }
        clearCoverageHandles();
        coveragePoints = [];
        coverageEditId = null;
        coverageEditName = "";
        renderDashMapLayers();
        setMapMode(null, isEditing ? "Coverage area updated." : "Coverage area saved.");
        toast(result.message);
    }

    function renderDashboard() {
        document.getElementById("statSubscribers").textContent = G.subscribers.length;
        document.getElementById("statOutages").textContent = G.outages.filter((o) => o.status === "active").length;
        document.getElementById("statTechs").textContent = G.technicians.filter((t) => t.availability !== "off_duty").length;
        document.getElementById("statOnline").textContent = G.subscribers.filter((s) => s.status === "online").length;

    }

    async function loadSubscriptionRequests() {
        const response = await fetch("api/ops.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ action: "list_subscription_requests" })
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.error || "Could not load subscription requests.");
        }
        pendingSubscriptionRequests = data.requests;
        requestRefreshErrorShown = false;
        renderSubscriptionRequests();
        renderDashMapLayers();
        renderTechnicians();
    }

    async function dispatchSubscriptionRequest(requestId, technicianId, button) {
        if (!technicianId) {
            toast("Choose a technician before dispatching.");
            return;
        }
        button.disabled = true;
        try {
            const result = await postOps({
                action: "dispatch_subscription_request",
                requestId,
                technicianId
            });
            if (!result) {
                button.disabled = false;
                return;
            }
            pendingSubscriptionRequests = result.requests;
            renderSubscriptionRequests();
            renderDashMapLayers();
            renderTechnicians();
            toast(result.message);
        } catch (error) {
            button.disabled = false;
            toast(error.message || "Could not dispatch technician.");
        }
    }

    function renderSubscriptionRequests() {
        const container = document.getElementById("subscriptionRequests");
        if (!container) return;
        container.innerHTML = pendingSubscriptionRequests.length
            ? pendingSubscriptionRequests.map((request) => `
                <article class="subscription-request">
                    <div class="subscription-request-details">
                        <strong>${escapeHtml(request.name)}${request.status === "approved" ? " · Approved" : ""}</strong>
                        <span>${escapeHtml(request.email)} · ${escapeHtml(request.requestedAt)}</span>
                        <span>${escapeHtml(request.plan)} · ${escapeHtml(request.barangay)}</span>
                        <span>${escapeHtml(request.address)} · ${escapeHtml(request.phone)}</span>
                        <span>Map pin: ${Number(request.lat).toFixed(5)}, ${Number(request.lng).toFixed(5)}</span>
                        <span>${request.technicianResponse === "accepted"
                            ? request.technicianWorkStatus === "not_completed"
                                ? `Marked not complete by ${escapeHtml(request.technicianName || "technician")}`
                                : request.technicianWorkStatus === "completed"
                                    ? `Completed by ${escapeHtml(request.technicianName || "technician")} · awaiting admin approval`
                                    : request.technicianWorkStatus === "en_route"
                                        ? `${escapeHtml(request.technicianName || "Technician")} is on route`
                                    : `Accepted by ${escapeHtml(request.technicianName || "technician")}`
                            : request.technicianResponse === "declined"
                                ? "Technician declined · ready for reassignment"
                                : request.technicianName
                                    ? `Awaiting response from ${escapeHtml(request.technicianName)}`
                                    : "No technician dispatched"}</span>
                        ${request.technicianWorkReason
                            ? `<span>Reason not complete: ${escapeHtml(request.technicianWorkReason)}</span>`
                            : ""}
                        ${request.status === "pending" &&
                          (request.technicianResponse !== "accepted" ||
                           request.technicianWorkStatus !== "completed")
                            ? '<span class="side-meta">Approval is available after an assigned technician accepts and completes the assignment.</span>'
                            : ""}
                    </div>
                    <div class="subscription-request-actions">
                        ${request.status === "pending" ? `<div class="subscription-dispatch-control">
                            <select aria-label="Technician for ${escapeHtml(request.name)}"
                                    data-request-technician="${request.id}">
                                <option value="">Choose technician…</option>
                                ${G.technicians.map((technician) => `
                                    <option value="${escapeHtml(technician.id)}"
                                        ${technician.id === request.technicianId ? "selected" : ""}>
                                        ${escapeHtml(technician.name)} · ${escapeHtml(technician.id)}
                                    </option>
                                `).join("")}
                            </select>
                            <button class="btn btn-outline" type="button"
                                    data-dispatch-request="${request.id}"
                                    ${G.technicians.length === 0 ? "disabled" : ""}>
                                ${request.technicianId ? "Re-dispatch" : "Dispatch"}
                            </button>
                        </div>
                        <button class="btn btn-primary subscription-request-approve" type="button"
                                data-approve-request="${request.id}"
                                ${request.technicianResponse !== "accepted" ||
                                  request.technicianWorkStatus !== "completed"
                                    ? "disabled title=\"Wait for the assigned technician to accept and complete the assignment.\""
                                    : ""}>
                            Approve
                        </button>
                        <button class="btn btn-danger" type="button" data-reject-request="${request.id}">Reject</button>` : ""}
                    </div>
                </article>
            `).join("")
            : '<p class="side-meta">No pending subscription requests.</p>';

        container.querySelectorAll("[data-dispatch-request]").forEach((button) => {
            button.addEventListener("click", async () => {
                const requestId = button.dataset.dispatchRequest;
                const technicianId = container.querySelector(
                    `[data-request-technician="${requestId}"]`
                ).value;
                await dispatchSubscriptionRequest(requestId, technicianId, button);
            });
        });

        container.querySelectorAll("[data-approve-request], [data-reject-request]").forEach((button) => {
            button.addEventListener("click", async () => {
                const decision = button.hasAttribute("data-approve-request") ? "approve" : "reject";
                const requestId = button.dataset.approveRequest || button.dataset.rejectRequest;
                await reviewSubscriptionRequest(requestId, decision, button);
            });
        });
    }

    async function reviewSubscriptionRequest(requestId, decision, button) {
        if (decision === "reject" && !window.confirm("Reject this subscription request?")) return;
        button.disabled = true;
        const result = await postOps({
            action: "review_subscription_request",
            requestId,
            decision
        });
        if (!result) {
            button.disabled = false;
            return;
        }
        try {
            await loadSubscriptionRequests();
        } catch (error) {
            toast(error.message);
        }
        renderDashMapLayers();
        renderSubscribers();
        renderDashboard();
        toast(result.message);
    }

    function barangayOptions() {
        const sel = document.getElementById("subBarangay");
        const selected = sel.value;
        const names = ["All barangays", ...G.barangays.map((barangay) => barangay.name)];
        sel.innerHTML = names.map((n, i) => {
            const value = i === 0 ? "" : n;
            return `<option value="${escapeHtml(value)}">${escapeHtml(n)}</option>`;
        }).join("");
        sel.value = names.includes(selected) ? selected : "";
        document.getElementById("addSubBarangay").innerHTML = G.barangays.map((barangay) =>
            `<option value="${escapeHtml(barangay.name)}">${escapeHtml(barangay.name)}</option>`
        ).join("");
    }

    function renderBarangays() {
        const tbody = document.getElementById("barangayTable");
        tbody.innerHTML = G.barangays.map((barangay) => `
            <tr data-barangay-id="${escapeHtml(barangay.id || "")}">
                <td>${escapeHtml(barangay.name)}</td>
                <td>Click pin to select, then drag</td>
                <td>
                    <button class="btn btn-outline" type="button" data-edit-barangay="${escapeHtml(barangay.id || "")}">Edit</button>
                    <button class="btn btn-danger" type="button" data-delete-barangay="${escapeHtml(barangay.id || "")}">Remove</button>
                </td>
            </tr>
        `).join("");

        tbody.querySelectorAll("[data-edit-barangay]").forEach((button) => {
            button.addEventListener("click", () => {
                const barangay = G.barangays.find((item) => String(item.id) === button.dataset.editBarangay);
                if (!barangay) return;
                const row = button.closest("tr");
                const formId = `editBarangay-${barangay.id}`;
                row.innerHTML = `
                    <td><input form="${formId}" name="name" maxlength="80" required value="${escapeHtml(barangay.name)}" aria-label="Barangay name"></td>
                    <td>
                        <button class="btn btn-primary" type="submit" form="${formId}">Save</button>
                        <button class="btn btn-outline" type="button" data-cancel-barangay>Cancel</button>
                    </td>
                `;
                const form = document.createElement("form");
                form.id = formId;
                form.addEventListener("submit", async (event) => {
                    event.preventDefault();
                    const values = Object.fromEntries(new FormData(form).entries());
                    const result = await postOps({
                        action: "update_barangay",
                        barangayId: barangay.id,
                        ...values,
                        lat: barangay.lat,
                        lng: barangay.lng
                    });
                    if (!result) return;
                    refreshBarangayViews();
                    toast(result.message);
                });
                row.cells[1].appendChild(form);
                row.querySelector("[data-cancel-barangay]").addEventListener("click", renderBarangays);
            });
        });

        tbody.querySelectorAll("[data-delete-barangay]").forEach((button) => {
            button.addEventListener("click", async () => {
                const barangay = G.barangays.find((item) => String(item.id) === button.dataset.deleteBarangay);
                if (!barangay || !window.confirm(`Remove ${barangay.name}? Barangays used by accounts or records cannot be removed.`)) {
                    return;
                }
                const result = await postOps({
                    action: "delete_barangay",
                    barangayId: barangay.id
                });
                if (!result) return;
                refreshBarangayViews();
                toast(result.message);
            });
        });
    }

    function refreshBarangayViews() {
        barangayOptions();
        renderBarangays();
        renderSubscribers();
        renderDashMapLayers();
        if (activeView === "dashboard") renderDashboard();
    }

    function renderSubscribers() {
        const q = document.getElementById("subSearch").value.toLowerCase();
        const st = document.getElementById("subStatus").value;
        const brgy = document.getElementById("subBarangay").value;
        const rows = G.subscribers.filter((s) => {
            const hay = `${s.name} ${s.id} ${s.barangay} ${s.plan}`.toLowerCase();
            return hay.includes(q) && (!st || s.status === st) && (!brgy || s.barangay === brgy);
        });

        document.getElementById("subTable").innerHTML = rows.map((s) => `
            <tr data-id="${escapeHtml(s.id)}" class="${s.id === selectedSubId ? "selected" : ""}">
                <td>${escapeHtml(s.id)}</td>
                <td>${escapeHtml(s.name)}</td>
                <td>${escapeHtml(s.barangay)}</td>
                <td>${escapeHtml(s.plan)}</td>
                <td>${badge(s.status)}</td>
            </tr>
        `).join("");

        document.querySelectorAll("#subTable tr").forEach((tr) => {
            tr.addEventListener("click", () => {
                selectedSubId = tr.dataset.id;
                editingSubId = null;
                renderSubscribers();
            });
        });
        renderSubDetail();
    }

    function openRecordModal(modalId, formId) {
        const modal = document.getElementById(modalId);
        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
        document.querySelector(`#${formId} [name="name"]`).focus();
    }

    function closeRecordModal(modalId, formId) {
        const modal = document.getElementById(modalId);
        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
        document.getElementById(formId).reset();
    }

    function showAddSubscriberForm(show) {
        if (show) {
            openRecordModal("addSubscriberModal", "addSubscriberForm");
        } else {
            closeRecordModal("addSubscriberModal", "addSubscriberForm");
        }
    }

    function renderSubDetail() {
        const s = findSub(selectedSubId);
        const el = document.getElementById("subDetail");
        if (!s) {
            el.innerHTML = '<p class="side-meta">Select a subscriber to view their details.</p>';
            if (subMiniMap) {
                subMiniMap.remove();
                subMiniMap = null;
            }
            return;
        }
        const mapPin = `
            <p class="side-meta">Map pin: ${s.lat.toFixed(5)}, ${s.lng.toFixed(5)}</p>
            <div id="subMiniMap" class="mini-map"></div>
        `;
        if (editingSubId !== s.id) {
            el.innerHTML = `
                <h3>${escapeHtml(s.name)}</h3>
                <p class="side-meta">${escapeHtml(s.id)} · Connected ${escapeHtml(s.connected)}</p>
                <div class="kv">
                    <span>Barangay</span><strong>${escapeHtml(s.barangay)}</strong>
                    <span>Address</span><strong>${escapeHtml(s.address)}</strong>
                    <span>Phone</span><strong>${escapeHtml(s.phone)}</strong>
                    <span>Plan</span><strong>${escapeHtml(s.plan)}</strong>
                    <span>Status</span><strong>${escapeHtml(s.status)}</strong>
                </div>
                <button class="btn btn-primary" type="button" id="editSubscriber">Edit</button>
                ${mapPin}
            `;
            document.getElementById("editSubscriber").addEventListener("click", () => {
                editingSubId = s.id;
                renderSubDetail();
            });
            renderSubMiniMap(s);
            return;
        }
        const options = G.barangays.map((barangay) =>
            `<option value="${escapeHtml(barangay.name)}" ${barangay.name === s.barangay ? "selected" : ""}>${escapeHtml(barangay.name)}</option>`
        ).join("");
        el.innerHTML = `
            <h3>${escapeHtml(s.name)}</h3>
            <p class="side-meta">${escapeHtml(s.id)} · Connected ${escapeHtml(s.connected)}</p>
            <form id="subEditForm" class="subscriber-edit-form">
                <label class="field">Full name
                    <input name="name" maxlength="120" required value="${escapeHtml(s.name)}">
                </label>
                <label class="field">Barangay
                    <select name="barangay" required>${options}</select>
                </label>
                <label class="field">Address
                    <input name="address" maxlength="255" required value="${escapeHtml(s.address)}">
                </label>
                <label class="field">Phone
                    <input name="phone" maxlength="20" required value="${escapeHtml(s.phone)}">
                </label>
                <label class="field">Plan
                    <input name="plan" maxlength="80" required value="${escapeHtml(s.plan)}">
                </label>
                <label class="field">Status
                    <select name="status" required>
                        <option value="online" ${s.status === "online" ? "selected" : ""}>Online</option>
                        <option value="offline" ${s.status === "offline" ? "selected" : ""}>Offline</option>
                        <option value="intermittent" ${s.status === "intermittent" ? "selected" : ""}>Intermittent</option>
                    </select>
                </label>
                <div class="profile-actions">
                    <button class="btn btn-primary" type="submit">Save subscriber</button>
                    <button class="btn btn-outline" type="button" id="cancelEditSubscriber">Cancel</button>
                </div>
            </form>
            <button class="btn btn-danger" type="button" id="deleteSubscriber">Remove subscriber</button>
            ${mapPin}
        `;
        document.getElementById("cancelEditSubscriber").addEventListener("click", () => {
            editingSubId = null;
            renderSubDetail();
        });
        document.getElementById("subEditForm").addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const values = Object.fromEntries(new FormData(form).entries());
            const result = await postOps({
                action: "update_subscriber",
                subscriberId: s.id,
                ...values
            });
            if (!result) return;
            editingSubId = null;
            renderDashMapLayers();
            renderSubscribers();
            toast(result.message);
        });
        document.getElementById("deleteSubscriber").addEventListener("click", async () => {
            if (!window.confirm(
                `Remove ${s.name} (${s.id}) and their linked subscriber login? Their outage subscriber links will also be removed.`
            )) {
                return;
            }
            const result = await postOps({
                action: "delete_subscriber",
                subscriberId: s.id
            });
            if (!result) return;
            selectedSubId = null;
            editingSubId = null;
            document.getElementById("subSearch").value = "";
            document.getElementById("subStatus").value = "";
            document.getElementById("subBarangay").value = "";
            renderDashMapLayers();
            renderSubscribers();
            toast(result.message);
        });
        renderSubMiniMap(s);
    }

    function renderSubMiniMap(s) {
        if (subMiniMap) {
            subMiniMap.remove();
            subMiniMap = null;
        }
        const mapElement = document.getElementById("subMiniMap");
        if (!mapElement) return;
        subMiniMap = L.map("subMiniMap").setView([s.lat, s.lng], 16);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19 }).addTo(subMiniMap);
        if (s.mapVisible) {
            L.marker([s.lat, s.lng], { icon: circleIcon(pinColor(s.status), 14) }).addTo(subMiniMap);
        }
        setTimeout(() => subMiniMap.invalidateSize(), 150);
    }

    function renderOutages() {
        const filter = document.getElementById("outageFilter").value;
        const list = G.outages.filter((o) => filter === "all" || o.status === filter);
        document.getElementById("smsOutageBtn").disabled =
            !G.outages.some((outage) => outage.status === "active");
        document.getElementById("outageList").innerHTML = list.map((o) => `
            <div class="event">
                <strong>${o.id}</strong> ${badge(o.status)} ${badge(o.severity)}
                <p><strong>${o.barangay}</strong> · ${o.affected} subscribers</p>
                <p>Source: ${o.source}</p>
                <p>${o.suspected}</p>
                <p>Started ${o.started}${o.resolved ? " · resolved " + o.resolved : ""}</p>
            </div>
        `).join("");
        initDashMap();
        renderDashMapLayers();
    }

    function renderTechnicians() {
        document.getElementById("techGrid").innerHTML = G.technicians.map((t) => {
            const job = jobsFor(t.id).find((j) => j.status !== "resolved") || jobsFor(t.id)[0];
            const outage = job ? findOutage(job.outageId) : null;
            const assignedRequests = pendingSubscriptionRequests.filter(
                (request) => request.technicianId === t.id
            );
            const activeOutages = G.outages.filter((o) => o.status === "active");
            return `
                <div class="card tech-card">
                    <div class="tech-top">
                        <div class="avatar">${escapeHtml(t.photo)}</div>
                        <div>
                            <strong>${escapeHtml(t.name)}</strong>
                            <div class="side-meta">${escapeHtml(t.role)} · ${escapeHtml(t.phone)}</div>
                            ${badge(t.availability)}
                        </div>
                    </div>
                    <p class="side-meta" style="margin-top:10px">
                        ${outage ? `Job ${job.id} · ${outage.barangay} (${outage.id})` : ""}
                        ${assignedRequests.map((request) => `
                            <span class="technician-subscription-assignment">
                                Subscription request #${request.id} · ${escapeHtml(request.name)} ·
                                ${request.technicianWorkStatus === "not_completed"
                                    ? "Not complete · reason sent"
                                    : request.technicianWorkStatus === "completed"
                                        ? "Accepted · Completed"
                                    : request.technicianWorkStatus === "en_route"
                                        ? "Accepted · On route"
                                    : request.technicianResponse === "accepted"
                                        ? "Accepted"
                                        : "Awaiting response"}
                                ${request.technicianWorkStatus === "not_completed" &&
                                  request.technicianWorkReason
                                    ? `<span class="technician-subscription-reason">Reason: ${escapeHtml(request.technicianWorkReason)}</span>`
                                    : ""}
                            </span>
                        `).join("")}
                        ${!outage && assignedRequests.length === 0 ? "No active assignment" : ""}
                    </p>
                    <button class="btn btn-outline technician-edit-toggle" type="button" data-toggle-tech="${escapeHtml(t.id)}">Edit technician</button>
                    <form class="technician-edit-form" data-edit-tech="${escapeHtml(t.id)}" hidden>
                        <label class="field">Full name
                            <input name="name" maxlength="120" required value="${escapeHtml(t.name)}">
                        </label>
                        <label class="field">Phone
                            <input name="phone" maxlength="20" required value="${escapeHtml(t.phone)}">
                        </label>
                        <label class="field">Job title
                            <input name="jobTitle" maxlength="80" required value="${escapeHtml(t.role)}">
                        </label>
                        <label class="field">Availability
                            <select name="availability" required>
                                <option value="available" ${t.configuredAvailability === "available" ? "selected" : ""}>Available</option>
                                <option value="on_duty" ${t.configuredAvailability === "on_duty" ? "selected" : ""}>On duty</option>
                                <option value="en_route" ${t.configuredAvailability === "en_route" ? "selected" : ""}>En route</option>
                                <option value="off_duty" ${t.configuredAvailability === "off_duty" ? "selected" : ""}>Off duty</option>
                            </select>
                        </label>
                        <div class="technician-edit-actions">
                            <button class="btn btn-primary" type="submit">Save technician</button>
                            <button class="btn btn-ghost" type="button" data-cancel-edit-tech="${escapeHtml(t.id)}">Cancel</button>
                            <button class="btn btn-danger" type="button" data-delete-tech="${escapeHtml(t.id)}">Remove technician</button>
                        </div>
                    </form>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
                        <select data-assign="${t.id}">
                            <option value="">Assign task…</option>
                            <optgroup label="Active outages">
                                ${activeOutages.map((o) => `
                                    <option value="${escapeHtml(o.id)}" data-task-type="outage">
                                        ${escapeHtml(o.id)} · ${escapeHtml(o.barangay)}
                                    </option>
                                `).join("")}
                            </optgroup>
                            <optgroup label="Available subscription requests">
                                ${pendingSubscriptionRequests.filter((request) =>
                                    request.status === "pending" &&
                                    request.technicianId !== t.id &&
                                    (!request.technicianId ||
                                     request.technicianResponse === "declined" ||
                                     request.technicianWorkStatus === "not_completed")
                                ).map((request) => `
                                    <option value="${escapeHtml(request.id)}" data-task-type="subscription_request">
                                        Request #${escapeHtml(request.id)} · ${escapeHtml(request.name)} · ${escapeHtml(request.barangay)}
                                    </option>
                                `).join("")}
                            </optgroup>
                        </select>
                        ${job && job.status !== "resolved" ? `
                            <button class="btn btn-ghost" data-advance="${job.id}">Advance status</button>
                        ` : ""}
                    </div>
                </div>
            `;
        }).join("") || '<div class="card panel"><p class="side-meta">No technicians yet. Use “Add technician” to create the roster.</p></div>';

        document.querySelectorAll("[data-toggle-tech]").forEach((button) => {
            button.addEventListener("click", () => {
                const form = document.querySelector(`[data-edit-tech="${button.dataset.toggleTech}"]`);
                if (!form) return;
                form.hidden = false;
                button.hidden = true;
                form.querySelector('[name="name"]').focus();
            });
        });

        document.querySelectorAll("[data-cancel-edit-tech]").forEach((button) => {
            button.addEventListener("click", () => {
                const form = document.querySelector(`[data-edit-tech="${button.dataset.cancelEditTech}"]`);
                if (!form) return;
                form.reset();
                form.hidden = true;
                document.querySelector(`[data-toggle-tech="${button.dataset.cancelEditTech}"]`).hidden = false;
            });
        });

        document.querySelectorAll("[data-edit-tech]").forEach((form) => {
            form.addEventListener("submit", async (event) => {
                event.preventDefault();
                const values = Object.fromEntries(new FormData(form).entries());
                const result = await postOps({
                    action: "update_technician",
                    technicianId: form.dataset.editTech,
                    ...values
                });
                if (!result) return;
                renderTechnicians();
                renderDashboard();
                toast(result.message);
            });
        });

        document.querySelectorAll("[data-delete-tech]").forEach((button) => {
            button.addEventListener("click", async () => {
                const technicianId = button.dataset.deleteTech;
                const technician = findTech(technicianId);
                if (!window.confirm(
                    `Remove ${technician ? technician.name : technicianId} and their linked login? Their assigned jobs will also be removed and ticket assignments cleared.`
                )) {
                    return;
                }
                const result = await postOps({
                    action: "delete_technician",
                    technicianId
                });
                if (!result) return;
                renderTechnicians();
                renderDashboard();
                toast(result.message);
            });
        });

        document.querySelectorAll("[data-assign]").forEach((sel) => {
            sel.addEventListener("change", async () => {
                const option = sel.selectedOptions[0];
                if (!sel.value || !option) return;
                const techId = sel.dataset.assign;
                if (option.dataset.taskType === "subscription_request") {
                    await dispatchSubscriptionRequest(sel.value, techId, sel);
                    return;
                }
                const outageId = sel.value;
                const data = await postOps({ action: "assign_job", technicianId: techId, outageId });
                if (!data) return;
                const tech = findTech(techId);
                toast(`${tech ? tech.name : techId} assigned to ${outageId}.`);
                renderTechnicians();
            });
        });

        document.querySelectorAll("[data-advance]").forEach((btn) => {
            btn.addEventListener("click", async () => {
                const jobId = btn.dataset.advance;
                const data = await postOps({ action: "advance_job", jobId });
                if (!data) return;
                toast(data.message || `Job ${jobId} updated.`);
                renderTechnicians();
                renderDashboard();
            });
        });
    }

    function ticketCounts() {
        const c = { open: 0, in_progress: 0, scheduled: 0, closed: 0 };
        G.tickets.forEach((t) => { c[t.status] += 1; });
        document.getElementById("tOpen").textContent = c.open;
        document.getElementById("tProgress").textContent = c.in_progress;
        document.getElementById("tScheduled").textContent = c.scheduled;
        document.getElementById("tClosed").textContent = c.closed;
    }

    function renderTickets() {
        const p = document.getElementById("ticketPriority").value;
        const st = document.getElementById("ticketStatus").value;
        const rows = G.tickets.filter((t) => (!p || t.priority === p) && (!st || t.status === st));
        document.getElementById("ticketTable").innerHTML = rows.map((t) => `
            <tr data-id="${t.id}" class="${t.id === selectedTicketId ? "selected" : ""}">
                <td>${t.id}</td>
                <td>${t.opened}</td>
                <td>${t.barangay}</td>
                <td>${t.component}</td>
                <td>${badge(t.priority)}</td>
                <td>${badge(t.status)}</td>
            </tr>
        `).join("");
        document.querySelectorAll("#ticketTable tr").forEach((tr) => {
            tr.addEventListener("click", () => {
                selectedTicketId = tr.dataset.id;
                renderTickets();
            });
        });
        renderTicketDetail();
    }

    function renderTicketDetail() {
        const t = G.tickets.find((x) => x.id === selectedTicketId);
        const el = document.getElementById("ticketDetail");
        if (!t) return;
        const o = t.outageId ? findOutage(t.outageId) : null;
        const tech = t.technicianId ? findTech(t.technicianId) : null;
        el.innerHTML = `
            <h3>${t.id}</h3>
            <p class="side-meta">${t.component} · ${t.barangay}, San Miguel, Iloilo</p>
            <div class="kv">
                <span>Priority</span><div>${badge(t.priority)}</div>
                <span>Status</span><div>${badge(t.status)}</div>
                <span>Linked outage</span><div>${o ? `${o.id} (${o.status}) · ${o.source}` : "None — preventive / historical analytics"}</div>
                <span>Technician</span><div>${tech ? tech.name + " · " + tech.phone : "Unassigned"}</div>
                <span>Analytics note</span><div>${t.linkedNote}</div>
            </div>
            <p><strong>Recommended action</strong></p>
            <p class="side-meta">${t.action}</p>
            ${t.status !== "closed" ? `
                <div style="display:flex;gap:8px;margin-top:12px">
                    <button class="btn btn-ghost" id="updTicket">Update to in progress</button>
                    <button class="btn btn-danger" id="closeTicket">Close ticket</button>
                </div>
            ` : `<p class="side-meta">Ticket closed.</p>`}
        `;
        const upd = document.getElementById("updTicket");
        const cls = document.getElementById("closeTicket");
        if (upd) upd.onclick = async () => {
            const data = await postOps({ action: "update_ticket", ticketId: t.id, status: "in_progress" });
            if (!data) return;
            ticketCounts();
            renderTickets();
            toast(t.id + " updated to in progress.");
        };
        if (cls) cls.onclick = async () => {
            const data = await postOps({ action: "update_ticket", ticketId: t.id, status: "closed" });
            if (!data) return;
            ticketCounts();
            renderTickets();
            toast(t.id + " closed.");
        };
    }

    function initCharts() {
        if (trendChart) return;
        const tctx = document.getElementById("trendChart");
        trendChart = new Chart(tctx, {
            type: "line",
            data: {
                labels: G.ticketTrend.labels,
                datasets: [
                    { label: "Opened", data: G.ticketTrend.opened, borderColor: "#e24b42", backgroundColor: "#e24b4233", tension: 0.3, fill: true },
                    { label: "Resolved", data: G.ticketTrend.resolved, borderColor: "#1f9d58", backgroundColor: "#1f9d5833", tension: 0.3, fill: true }
                ]
            },
            options: { responsive: true, plugins: { legend: { position: "bottom" } }, scales: { y: { beginAtZero: true } } }
        });

        componentChart = new Chart(document.getElementById("componentChart"), {
            type: "bar",
            data: {
                labels: G.componentMix.map((c) => c.label),
                datasets: [{ label: "Incidents", data: G.componentMix.map((c) => c.count), backgroundColor: "#1f6ea8", borderRadius: 6 }]
            },
            options: { indexAxis: "y", responsive: true, plugins: { legend: { display: false } } }
        });
    }

    function initAnalytics() {
        if (uptimeChart) return;
        uptimeChart = new Chart(document.getElementById("uptimeChart"), {
            type: "line",
            data: {
                labels: G.analyticsMonthly.labels,
                datasets: [{ label: "Uptime %", data: G.analyticsMonthly.uptime, borderColor: "#1a8a86", tension: 0.3, fill: false }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { min: 96, max: 100 } } }
        });
        ticketVolChart = new Chart(document.getElementById("analyticsTicketChart"), {
            type: "bar",
            data: {
                labels: G.analyticsMonthly.labels,
                datasets: [{ label: "Tickets", data: G.analyticsMonthly.tickets, backgroundColor: "#2d72aa", borderRadius: 6 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });

        const counts = {};
        G.outages.forEach((o) => { counts[o.barangay] = (counts[o.barangay] || 0) + o.affected; });
        document.getElementById("barangayMix").innerHTML = Object.entries(counts).map(([b, n]) =>
            `<div class="event"><strong>${b}</strong><p>${n} subscriber-incidents in current outage history</p></div>`
        ).join("");
    }

    function downloadReport() {
        const header = ["id", "opened", "status", "priority", "component", "barangay", "outageId", "technicianId", "action"];
        const lines = [header.join(",")].concat(G.tickets.map((t) => header.map((k) => `"${String(t[k] ?? "").replace(/"/g, '""')}"`).join(",")));
        const blob = new Blob([lines.join("\n")], { type: "text/csv" });
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = "geonet-maintenance-report.csv";
        a.click();
        toast("Maintenance report downloaded.");
    }

    function showView(name) {
        if (!titles[name]) name = "dashboard";
        if (name === "subscribers") {
            selectedSubId = null;
            editingSubId = null;
        }
        activeView = name;
        document.querySelectorAll(".view").forEach((v) => v.classList.remove("active"));
        document.querySelectorAll(".nav-item").forEach((n) => n.classList.toggle("active", n.dataset.view === name));
        document.getElementById("view-" + name).classList.add("active");
        document.getElementById("pageTitle").textContent = titles[name];

        if (name === "dashboard") {
            renderDashboard();
            renderOutages();
        }
        if (name === "subscribers") renderSubscribers();
        if (name === "technicians") renderTechnicians();
        if (name === "technicians" || name === "subscribers") {
            if (appInitialized) {
                loadSubscriptionRequests().catch((error) => {
                    if (!requestRefreshErrorShown) {
                        toast(error.message);
                        requestRefreshErrorShown = true;
                    }
                });
            }
        }
        if (name === "maintenance") {
            ticketCounts();
            initCharts();
            renderTickets();
        }
        if (name === "analytics") initAnalytics();
        if (name === "settings") loadSmsSettings();
    }

    function recipientsFor(outage, audience) {
        const people = [];
        if (audience === "technicians" || audience === "both") {
            const assigned = G.jobs.filter((j) => j.outageId === outage.id).map((j) => findTech(j.technicianId));
            const pool = assigned.length ? assigned : G.technicians.filter((t) => t.availability !== "off_duty");
            pool.forEach((t) => t && people.push({ name: t.name, phone: t.phone, role: "technician" }));
        }
        if (audience === "subscribers" || audience === "both") {
            outage.subscribers.forEach((id) => {
                const s = findSub(id);
                if (s) people.push({ name: s.name, phone: s.phone, role: "subscriber" });
            });
        }
        return people;
    }

    function openSmsModal() {
        const active = G.outages.filter((o) => o.status === "active");
        if (active.length === 0) {
            toast("There are no active outages to report.");
            return;
        }
        document.getElementById("smsOutageSelect").innerHTML = active.map((o) =>
            `<option value="${o.id}">${o.id} · ${o.barangay} (${o.affected} affected)</option>`
        ).join("");
        fillSmsMessage();
        document.getElementById("smsModal").classList.add("open");
    }

    function fillSmsMessage() {
        const o = findOutage(document.getElementById("smsOutageSelect").value);
        if (!o) return;
        document.getElementById("smsMessage").value =
            `R-Link GeoNet: Outage ${o.id} in Brgy. ${o.barangay}, San Miguel, Iloilo. ` +
            `${o.affected} subscribers affected. Suspected source: ${o.source}. ` +
            `Crew is being dispatched. For updates contact R-Link.`;
    }

    async function sendSms() {
        const o = findOutage(document.getElementById("smsOutageSelect").value);
        const audience = document.getElementById("smsAudience").value;
        const message = document.getElementById("smsMessage").value.trim();
        const recipients = recipientsFor(o, audience);
        const res = await fetch("api/sms.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: "send",
                context: "outage",
                outageId: o.id,
                message,
                recipients
            })
        });
        const data = await res.json();
        document.getElementById("smsModal").classList.remove("open");
        toast(data.message || data.error || "SMS request finished.");
        loadSmsSettings();
    }

    async function loadSmsSettings() {
        const res = await fetch("api/sms.php");
        const data = await res.json();
        if (!data.ok) return;
        document.getElementById("smsEnabled").checked = !!data.config.enabled;
        document.getElementById("smsMock").checked = !!data.config.mock_mode;
        document.getElementById("smsSender").value = data.config.sender_name || "RLINK";
        document.getElementById("smsKey").placeholder = data.config.has_api_key ? "Key saved (leave blank to keep)" : "Paste API key to enable live send";
        document.getElementById("smsLog").innerHTML = (data.recent_logs || []).map((l) => `
            <div class="log-item">
                <strong>${l.name}</strong> (${l.role}) · ${l.phone}<br>
                ${badge(l.status)} ${l.at}<br>
                ${l.message}
            </div>
        `).join("") || "<p class='side-meta'>No SMS sent yet.</p>";
    }

    async function saveSmsSettings(e) {
        e.preventDefault();
        const res = await fetch("api/sms.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: "save_config",
                enabled: document.getElementById("smsEnabled").checked,
                mock_mode: document.getElementById("smsMock").checked,
                sender_name: document.getElementById("smsSender").value,
                api_key: document.getElementById("smsKey").value
            })
        });
        const data = await res.json();
        toast(data.message || data.error);
        document.getElementById("smsKey").value = "";
        loadSmsSettings();
    }

    document.querySelectorAll(".nav-item[data-view]").forEach((a) => {
        a.addEventListener("click", (e) => {
            e.preventDefault();
            history.replaceState(null, "", a.getAttribute("href"));
            showView(a.dataset.view);
        });
    });
    document.querySelector("[data-admin-profile-link]").addEventListener("click", (event) => {
        event.preventDefault();
        history.replaceState(null, "", "#profile");
        showView("profile");
    });
    window.addEventListener("hashchange", () => {
        const view = (location.hash || "#dashboard").slice(1);
        showView(titles[view] ? view : "dashboard");
    });

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
            document.querySelector(".user-chip").textContent = result.profile.name;
        } catch (error) {
            profileMessage.textContent = error.message || "Could not update your profile.";
            profileMessage.classList.add("error");
        } finally {
            profileSave.disabled = false;
        }
    });

    ["subSearch", "subStatus", "subBarangay"].forEach((id) => {
        document.getElementById(id).addEventListener("input", renderSubscribers);
        document.getElementById(id).addEventListener("change", renderSubscribers);
    });
    document.getElementById("outageFilter").addEventListener("change", renderOutages);
    document.getElementById("coverageAreaSelect").addEventListener("change", (event) => {
        document.getElementById("editCoverageArea").disabled = !event.currentTarget.value;
    });
    document.getElementById("editCoverageArea").addEventListener("click", () => {
        const areaId = document.getElementById("coverageAreaSelect").value;
        const area = G.coverageAreas.find((item) => String(item.id) === areaId);
        if (!area) {
            toast("Select a coverage area before editing.");
            return;
        }
        startCoverageEdit(area);
    });
    document.getElementById("toggleBarangayManager").addEventListener("click", (event) => {
        const manager = document.getElementById("barangayManager");
        manager.hidden = !manager.hidden;
        event.currentTarget.textContent = manager.hidden ? "Manage barangays" : "Hide barangays";
        if (!manager.hidden) renderBarangays();
        renderDashMapLayers();
    });
    document.getElementById("toggleCoverageManager").addEventListener("click", (event) => {
        const manager = document.getElementById("coverageManager");
        manager.hidden = !manager.hidden;
        event.currentTarget.textContent = manager.hidden ? "Edit coverage" : "Hide coverage";
    });
    document.getElementById("ticketPriority").addEventListener("change", renderTickets);
    document.getElementById("ticketStatus").addEventListener("change", renderTickets);
    document.getElementById("refreshSubscriptionRequests").addEventListener("click", () => {
        loadSubscriptionRequests().catch((error) => toast(error.message));
    });
    document.getElementById("downloadReport").addEventListener("click", downloadReport);
    document.getElementById("smsOutageBtn").addEventListener("click", openSmsModal);
    document.getElementById("smsCancel").addEventListener("click", () => document.getElementById("smsModal").classList.remove("open"));
    document.getElementById("smsSend").addEventListener("click", sendSms);
    document.getElementById("smsOutageSelect").addEventListener("change", fillSmsMessage);
    document.getElementById("smsSettingsForm").addEventListener("submit", saveSmsSettings);
    document.getElementById("addBarangayForm").addEventListener("submit", async (event) => {
        event.preventDefault();
        const name = event.currentTarget.elements.name.value.trim();
        if (!name) return;
        setMapMode("add-barangay", `Click the map to place ${name}.`);
        document.getElementById("barangayManager").hidden = true;
        document.getElementById("toggleBarangayManager").textContent = "Manage barangays";
        document.getElementById("dashMap").scrollIntoView({ behavior: "smooth", block: "center" });
    });
    document.getElementById("deleteAllBarangays").addEventListener("click", async () => {
        const confirmed = window.confirm(
            "Permanently delete every barangay and all linked subscriber accounts, subscription requests, outages, and maintenance tickets? This cannot be undone."
        );
        if (!confirmed) return;
        const confirmation = window.prompt('Type DELETE to confirm removing all barangays and linked records:');
        if (confirmation === null) return;
        if (confirmation !== "DELETE") {
            toast("Removal cancelled. Confirmation text did not match.");
            return;
        }
        const result = await postOps({ action: "delete_all_barangays" });
        if (!result) return;
        selectedSubId = null;
        editingSubId = null;
        setMapMode(null, "All barangays removed. Add new barangays from the map.");
        refreshBarangayViews();
        renderOutages();
        try {
            await loadSubscriptionRequests();
        } catch (error) {
            toast(error.message);
        }
        toast(result.message);
    });

    document.getElementById("showAddTechnician").addEventListener("click", () => {
        openRecordModal("addTechnicianModal", "addTechnicianForm");
    });
    ["cancelAddTechnician", "cancelAddTechnicianFooter"].forEach((id) => {
        document.getElementById(id).addEventListener("click", () => {
            closeRecordModal("addTechnicianModal", "addTechnicianForm");
        });
    });
    document.getElementById("addTechnicianForm").addEventListener("submit", async (event) => {
        event.preventDefault();
        const values = Object.fromEntries(new FormData(event.currentTarget).entries());
        const result = await postOps({ action: "add_technician", ...values });
        if (!result) return;
        closeRecordModal("addTechnicianModal", "addTechnicianForm");
        renderTechnicians();
        renderDashboard();
        toast(`${result.message} ID: ${result.technicianId}`);
    });

    document.getElementById("addSubBarangay").innerHTML = G.barangays.map((barangay) =>
        `<option value="${escapeHtml(barangay.name)}">${escapeHtml(barangay.name)}</option>`
    ).join("");
    document.getElementById("showAddSubscriber").addEventListener("click", () => {
        showAddSubscriberForm(true);
    });
    ["cancelAddSubscriber", "cancelAddSubscriberFooter"].forEach((id) => {
        document.getElementById(id).addEventListener("click", () => {
            showAddSubscriberForm(false);
        });
    });
    document.querySelectorAll(".record-modal-back").forEach((modal) => {
        modal.addEventListener("click", (event) => {
            if (event.target === modal) {
                if (modal.id === "addSubscriberModal") {
                    showAddSubscriberForm(false);
                } else if (modal.id === "addTechnicianModal") {
                    closeRecordModal("addTechnicianModal", "addTechnicianForm");
                }
            }
        });
    });
    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") return;
        if (document.getElementById("addSubscriberModal").classList.contains("open")) {
            showAddSubscriberForm(false);
        } else if (document.getElementById("addTechnicianModal").classList.contains("open")) {
            closeRecordModal("addTechnicianModal", "addTechnicianForm");
        }
    });
    document.getElementById("addSubscriberForm").addEventListener("submit", async (event) => {
        event.preventDefault();
        const values = Object.fromEntries(new FormData(event.currentTarget).entries());
        const result = await postOps({ action: "add_subscriber", ...values });
        if (!result) return;
        selectedSubId = null;
        editingSubId = null;
        document.getElementById("subSearch").value = "";
        document.getElementById("subStatus").value = "";
        document.getElementById("subBarangay").value = "";
        showAddSubscriberForm(false);
        renderDashMapLayers();
        renderSubscribers();
        toast(`${result.message} ID: ${result.subscriberId}`);
    });

    document.getElementById("mapSubscriber").innerHTML =
        '<option value="">Select subscriber…</option>' +
        G.subscribers.map((s) => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.id)} · ${escapeHtml(s.name)}</option>`).join("");
    document.getElementById("placeSubscriberPin").addEventListener("click", () => {
        if (!document.getElementById("mapSubscriber").value) {
            toast("Select a subscriber before placing a pin.");
            return;
        }
        setMapMode("subscriber", "Click the map to place the selected subscriber pin.");
    });
    document.getElementById("addNapPin").addEventListener("click", () => {
        setMapMode("nap", "Click the map where the new NAP box is located.");
    });
    document.getElementById("drawCoverage").addEventListener("click", () => {
        if (coveragePreview) dashMap.removeLayer(coveragePreview);
        clearCoverageHandles();
        coveragePreview = null;
        coverageEditId = null;
        coverageEditName = "";
        coveragePoints = [];
        setMapMode("coverage", "Click around the coverage boundary to add points. Undo mistakes, then save.");
        renderCoveragePreview();
    });
    document.getElementById("finishCoverage").addEventListener("click", saveCoverageArea);
    document.getElementById("undoCoveragePoint").addEventListener("click", undoCoveragePoint);
    document.getElementById("cancelMapAction").addEventListener("click", () => {
        if (coveragePreview && dashMap) {
            dashMap.removeLayer(coveragePreview);
            coveragePreview = null;
        }
        clearCoverageHandles();
        coveragePoints = [];
        coverageEditId = null;
        coverageEditName = "";
        setMapMode(null, "Choose an action, then click the map.");
    });

    barangayOptions();
    const coverageAreaSelect = document.getElementById("coverageAreaSelect");
    coverageAreaSelect.innerHTML =
        '<option value="">Select coverage area…</option>' +
        (G.coverageAreas || []).map((area) =>
            `<option value="${escapeHtml(area.id)}">${escapeHtml(area.name)}</option>`
        ).join("");
    const hash = (location.hash || "#dashboard").replace("#", "");
    showView(titles[hash] ? hash : "dashboard");
    loadSubscriptionRequests().catch((error) => toast(error.message));
    appInitialized = true;
    window.setInterval(() => {
        if (
            (activeView === "dashboard" || activeView === "technicians" || activeView === "subscribers") &&
            !document.hidden
        ) {
            loadSubscriptionRequests().catch((error) => {
                if (!requestRefreshErrorShown) {
                    toast(error.message);
                    requestRefreshErrorShown = true;
                }
            });
        }
    }, 15000);
})();
