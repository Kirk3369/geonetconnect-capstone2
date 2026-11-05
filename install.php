<?php

require_once __DIR__ . "/includes/db.php";

$cfg = geonet_db_config();
$messages = [];
$error = "";
$done = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $server = geonet_pdo(false);
        $dbName = preg_replace("/[^a-zA-Z0-9_]/", "", $cfg["name"]);
        $server->exec(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $messages[] = "Database `{$dbName}` is ready.";

        $pdo = geonet_pdo(true);
        geonet_run_sql_file($pdo, __DIR__ . "/sql/schema.sql");
        $messages[] = "Tables created.";

        geonet_run_sql_file($pdo, __DIR__ . "/sql/seed.sql");
        $messages[] = "San Miguel demo data loaded.";
        $done = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} else {
    if (geonet_db_ready()) {
        $messages[] = "The database is already installed. Re-run only if you want to wipe and restore demo data.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install GeoNet Connect database</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app" style="display:block;padding:40px;">
    <div class="card panel" style="max-width:640px;margin:0 auto;">
        <div class="panel-head"><h3>GeoNet Connect database</h3></div>
        <p class="side-meta">
            Creates MySQL database <strong><?php echo htmlspecialchars($cfg["name"]); ?></strong>
            on <?php echo htmlspecialchars($cfg["host"]); ?> and loads barangays, tickets,
            and the admin demo login. Add subscriber and technician accounts
            from their respective admin pages.
        </p>
        <?php foreach ($messages as $msg): ?>
            <p><?php echo htmlspecialchars($msg); ?></p>
        <?php endforeach; ?>
        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <button class="btn btn-primary" type="submit">
                <?php echo geonet_db_ready() ? "Rebuild database" : "Install database"; ?>
            </button>
            <?php if ($done || geonet_db_ready()): ?>
                <a class="btn btn-outline" href="index.php">Go to login</a>
            <?php endif; ?>
        </form>
        <p class="side-meta" style="margin-top:16px">
            Demo logins after install:<br>
            admin@admin.rlink.ph / admin123<br>
        </p>
    </div>
</div>
</body>
</html>
