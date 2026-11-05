window.GEONET = {
  center: [10.7794, 122.4668],
  barangays: [
    { name: "Poblacion", lat: 10.7794, lng: 122.4668 },
    { name: "Consolacion", lat: 10.7902, lng: 122.4551 },
    { name: "Igtambo", lat: 10.7718, lng: 122.4794 },
    { name: "San Jose", lat: 10.7641, lng: 122.4568 },
    { name: "San Vicente", lat: 10.7936, lng: 122.4762 },
    { name: "Santa Cruz", lat: 10.7568, lng: 122.4721 },
    { name: "Santo Niño", lat: 10.7864, lng: 122.4479 },
    { name: "San Rafael", lat: 10.7689, lng: 122.4462 },
    { name: "Cagay", lat: 10.7971, lng: 122.4618 },
    { name: "Calampitao", lat: 10.7604, lng: 122.4865 }
  ],
  landmarks: [],
  subscribers: [],
  outages: [],
  technicians: [],
  jobs: [],
  tickets: [
    { id: "TKT-901", opened: "2026-08-30 08:16", status: "open", priority: "critical", component: "Fiber feeder", barangay: "Igtambo", outageId: null, technicianId: "TECH-01", action: "Inspect feeder drop SM-IGT-A, test OTDR from NAP-03, restore splice if cut confirmed.", linkedNote: "Historical incident ticket." },
    { id: "TKT-897", opened: "2026-08-30 06:44", status: "in_progress", priority: "high", component: "NAP cabinet", barangay: "Santa Cruz", outageId: null, technicianId: "TECH-02", action: "Check NAP power feed and south splice tray; isolate if cabinet UPS failed.", linkedNote: "Historical incident ticket." },
    { id: "TKT-890", opened: "2026-08-29 22:08", status: "in_progress", priority: "medium", component: "Last-mile drop", barangay: "Poblacion", outageId: null, technicianId: "TECH-03", action: "Replace ONT / reterminate drop near Public Market.", linkedNote: "Historical incident ticket." },
    { id: "TKT-874", opened: "2026-08-27 14:22", status: "closed", priority: "medium", component: "Splice / attenuation", barangay: "Consolacion", outageId: null, technicianId: "TECH-01", action: "Resplice tray; confirmed restored 18:55.", linkedNote: "Historical incident ticket." },
    { id: "TKT-861", opened: "2026-08-24 09:12", status: "closed", priority: "low", component: "CPE / connector", barangay: "San Rafael", outageId: null, technicianId: "TECH-04", action: "Reseat SC/APC at CPE; customer online.", linkedNote: "Historical incident ticket." },
    { id: "TKT-852", opened: "2026-08-21 10:05", status: "scheduled", priority: "low", component: "Preventive NAP", barangay: "Poblacion", outageId: null, technicianId: "TECH-05", action: "Quarterly NAP-01 cabinet clean and label audit — scheduled Sep 2, 08:00.", linkedNote: "No active outage; preventive from monthly incident mix." },
    { id: "TKT-841", opened: "2026-08-18 16:40", status: "closed", priority: "high", component: "Power / UPS", barangay: "San Jose", outageId: null, technicianId: "TECH-02", action: "Replaced NAP-04 rectifier; no recurrence.", linkedNote: "Derived from San Jose power-related ticket history." },
    { id: "TKT-830", opened: "2026-08-12 11:20", status: "scheduled", priority: "medium", component: "Fiber spine", barangay: "Consolacion", outageId: null, technicianId: "TECH-04", action: "Aerial span inspection SM-ILO-01 west after typhoon season.", linkedNote: "Scheduled from barangay incident frequency (Consolacion + Igtambo)." },
    { id: "TKT-818", opened: "2026-07-29 09:00", status: "closed", priority: "medium", component: "Last-mile drop", barangay: "Cagay", outageId: null, technicianId: "TECH-06", action: "Replaced weathered drop for SUB-1320.", linkedNote: "Closed; used in monthly volume trend." },
    { id: "TKT-802", opened: "2026-07-08 13:15", status: "closed", priority: "high", component: "Fiber feeder", barangay: "Calampitao", outageId: null, technicianId: "TECH-01", action: "Repaired sagging feeder after vehicle strike.", linkedNote: "Historical feeder incident feeding component analytics." }
  ],
  ticketTrend: {
    labels: ["Mar", "Apr", "May", "Jun", "Jul", "Aug"],
    opened: [18, 21, 16, 24, 22, 19],
    resolved: [16, 19, 18, 20, 23, 15]
  },
  componentMix: [
    { label: "Fiber feeder", count: 9 },
    { label: "NAP cabinet", count: 7 },
    { label: "Last-mile drop", count: 11 },
    { label: "Splice / attenuation", count: 6 },
    { label: "Power / UPS", count: 5 },
    { label: "CPE / connector", count: 8 }
  ],
  analyticsMonthly: {
    labels: ["Mar", "Apr", "May", "Jun", "Jul", "Aug"],
    uptime: [99.1, 98.7, 99.4, 98.2, 99.0, 97.8],
    tickets: [18, 21, 16, 24, 22, 19]
  }
};
