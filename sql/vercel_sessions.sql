CREATE TABLE IF NOT EXISTS geonet_sessions (
    session_id VARCHAR(128) NOT NULL PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    KEY idx_geonet_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
