SET NAMES utf8mb4;

INSERT INTO barangays (id, name, lat, lng) VALUES
(1, 'Poblacion', 10.779400, 122.466800),
(2, 'Consolacion', 10.790200, 122.455100),
(3, 'Igtambo', 10.771800, 122.479400),
(4, 'San Jose', 10.764100, 122.456800),
(5, 'San Vicente', 10.793600, 122.476200),
(6, 'Santa Cruz', 10.756800, 122.472100),
(7, 'Santo Niño', 10.786400, 122.447900),
(8, 'San Rafael', 10.768900, 122.446200),
(9, 'Cagay', 10.797100, 122.461800),
(10, 'Calampitao', 10.760400, 122.486500);

-- Demo password: admin123
INSERT INTO users (id, email, password_hash, display_name, role, is_active) VALUES
(1, 'admin@admin.rlink.ph', '$2y$10$HVCB.c8bShazkfHuzF2LmuTGXW/qhbvk2On3/vqO06UjXEOmVp5oG', 'Network Admin', 'admin', 1);


INSERT INTO tickets (id, public_id, opened_at, status, priority, component, barangay_id, outage_id, technician_id, action_text, linked_note) VALUES
(1, 'TKT-901', '2026-08-30 08:16:00', 'open', 'critical', 'Fiber feeder', 3, NULL, NULL, 'Inspect feeder drop SM-IGT-A, test OTDR, restore splice if cut confirmed.', 'Historical incident ticket.'),
(2, 'TKT-897', '2026-08-30 06:44:00', 'in_progress', 'high', 'NAP cabinet', 6, NULL, NULL, 'Check NAP power feed and south splice tray; isolate if cabinet UPS failed.', 'Historical incident ticket.'),
(3, 'TKT-890', '2026-08-29 22:08:00', 'in_progress', 'medium', 'Last-mile drop', 1, NULL, NULL, 'Replace ONT / reterminate drop near Public Market.', 'Historical incident ticket.'),
(4, 'TKT-874', '2026-08-27 14:22:00', 'closed', 'medium', 'Splice / attenuation', 2, NULL, NULL, 'Resplice tray; confirmed restored 18:55.', 'Historical incident ticket.'),
(5, 'TKT-861', '2026-08-24 09:12:00', 'closed', 'low', 'CPE / connector', 8, NULL, NULL, 'Reseat SC/APC at CPE; customer online.', 'Historical incident ticket.'),
(6, 'TKT-852', '2026-08-21 10:05:00', 'scheduled', 'low', 'Preventive NAP', 1, NULL, NULL, 'Quarterly NAP-01 cabinet clean and label audit — scheduled Sep 2, 08:00.', 'No active outage; preventive from monthly incident mix.'),
(7, 'TKT-841', '2026-08-18 16:40:00', 'closed', 'high', 'Power / UPS', 4, NULL, NULL, 'Replaced NAP-04 rectifier; no recurrence.', 'Derived from San Jose power-related ticket history.'),
(8, 'TKT-830', '2026-08-12 11:20:00', 'scheduled', 'medium', 'Fiber spine', 2, NULL, NULL, 'Aerial span inspection SM-ILO-01 west after typhoon season.', 'Scheduled from barangay incident frequency (Consolacion + Igtambo).'),
(9, 'TKT-818', '2026-07-29 09:00:00', 'closed', 'medium', 'Last-mile drop', 9, NULL, NULL, 'Replaced weathered drop for SUB-1320.', 'Closed; used in monthly volume trend.'),
(10, 'TKT-802', '2026-07-08 13:15:00', 'closed', 'high', 'Fiber feeder', 10, NULL, NULL, 'Repaired sagging feeder after vehicle strike.', 'Historical feeder incident feeding component analytics.');

INSERT INTO monthly_metrics (period_date, tickets_opened, tickets_resolved, uptime_pct) VALUES
('2026-03-01', 18, 16, 99.10),
('2026-04-01', 21, 19, 98.70),
('2026-05-01', 16, 18, 99.40),
('2026-06-01', 24, 20, 98.20),
('2026-07-01', 22, 23, 99.00),
('2026-08-01', 19, 15, 97.80);

INSERT INTO component_incidents (component, incident_count) VALUES
('Fiber feeder', 9),
('NAP cabinet', 7),
('Last-mile drop', 11),
('Splice / attenuation', 6),
('Power / UPS', 5),
('CPE / connector', 8);

INSERT INTO app_settings (setting_key, setting_value) VALUES
('map_center_lat', '10.7794'),
('map_center_lng', '122.4668'),
('sms_provider', 'semaphore'),
('sms_enabled', '1'),
('sms_mock_mode', '1'),
('sms_api_key', ''),
('sms_sender_name', 'RLINK'),
('sms_api_url', 'https://api.semaphore.co/api/v4/messages');

INSERT INTO sms_logs (public_id, sent_at, recipient_name, recipient_role, phone, context, outage_public_id, message, status, provider, note) VALUES
('sms_6a94007bd6fdf', '2026-08-30 12:05:47', 'Carlo Mendoza', 'technician', '09175550101', 'outage', NULL, 'Test outage in Igtambo', 'logged', 'mock', 'Mock mode — message stored locally (no carrier send).');
