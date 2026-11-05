<?php

$environmentValue = static function ($name, $fallback = null) {
    $value = getenv($name);
    return $value === false || $value === "" ? $fallback : $value;
};

$isVercel = getenv("VERCEL") === "1";
$config = [
    "host" => $environmentValue("DB_HOST", $environmentValue("MYSQLHOST", "127.0.0.1")),
    "port" => $environmentValue("DB_PORT", $environmentValue("MYSQLPORT", "3306")),
    "name" => $environmentValue("DB_NAME", $environmentValue("MYSQLDATABASE", "geonet_connect")),
    "user" => $environmentValue("DB_USER", $environmentValue("MYSQLUSER", "root")),
    "pass" => $environmentValue("DB_PASSWORD", $environmentValue("MYSQLPASSWORD", "")),
    "charset" => $environmentValue("DB_CHARSET", "utf8mb4"),
];

if ($isVercel) {
    foreach ([
        "host" => "DB_HOST",
        "name" => "DB_NAME",
        "user" => "DB_USER",
        "pass" => "DB_PASSWORD",
    ] as $key => $environmentName) {
        if ($config[$key] === "" || ($key === "host" && $config[$key] === "127.0.0.1") ||
            ($key === "name" && $config[$key] === "geonet_connect") ||
            ($key === "user" && $config[$key] === "root")) {
            throw new RuntimeException("Set the " . $environmentName . " environment variable for the hosted database.");
        }
    }

    if (!ctype_digit((string) $config["port"]) || (int) $config["port"] < 1 || (int) $config["port"] > 65535) {
        throw new RuntimeException("DB_PORT must be a valid TCP port.");
    }
}

return [
    "host" => $config["host"],
    "port" => (int) $config["port"],
    "name" => $config["name"],
    "user" => $config["user"],
    "pass" => $config["pass"],
    "charset" => $config["charset"],
];
