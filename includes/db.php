<?php

function geonet_db_config()
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . "/config/database.php";
    }
    return $config;
}

function geonet_pdo($withDatabase = true)
{
    static $pdo = null;
    static $pdoServer = null;

    $cfg = geonet_db_config();
    $dsnBase = sprintf(
        "mysql:host=%s;port=%s;charset=%s",
        $cfg["host"],
        $cfg["port"],
        $cfg["charset"]
    );

    if (!$withDatabase) {
        if ($pdoServer instanceof PDO) {
            return $pdoServer;
        }
        $pdoServer = new PDO($dsnBase, $cfg["user"], $cfg["pass"], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdoServer;
    }

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = $dsnBase . ";dbname=" . $cfg["name"];
    $pdo = new PDO($dsn, $cfg["user"], $cfg["pass"], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function geonet_db_ready()
{
    try {
        $pdo = geonet_pdo(true);
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function geonet_run_sql_file(PDO $pdo, $path)
{
    if (!is_readable($path)) {
        throw new RuntimeException("SQL file not found: " . $path);
    }

    $sql = file_get_contents($path);
    $sql = preg_replace("/--.*$/m", "", $sql);
    $chunks = array_filter(array_map("trim", explode(";", $sql)));

    foreach ($chunks as $statement) {
        if ($statement !== "") {
            $pdo->exec($statement);
        }
    }
}
