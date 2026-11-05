<?php

require_once __DIR__ . "/db.php";

class GeoNetDatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private $lockName = null;

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        if ($this->lockName !== null) {
            $stmt = geonet_pdo()->prepare("SELECT RELEASE_LOCK(?)");
            $stmt->execute([$this->lockName]);
            $this->lockName = null;
        }
        return true;
    }

    public function read(string $id): string|false
    {
        $this->lockName = "geonet_" . substr(hash("sha256", $id), 0, 57);
        $lock = geonet_pdo()->prepare("SELECT GET_LOCK(?, 10)");
        $lock->execute([$this->lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            $this->lockName = null;
            throw new RuntimeException("Unable to lock the database session.");
        }

        $stmt = geonet_pdo()->prepare(
            "SELECT session_data FROM geonet_sessions WHERE session_id = ?"
        );
        $stmt->execute([$id]);
        $data = $stmt->fetchColumn();
        return $data === false ? "" : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        $stmt = geonet_pdo()->prepare(
            "INSERT INTO geonet_sessions (session_id, session_data, last_activity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                session_data = VALUES(session_data),
                last_activity = VALUES(last_activity)"
        );
        return $stmt->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        $stmt = geonet_pdo()->prepare("DELETE FROM geonet_sessions WHERE session_id = ?");
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = geonet_pdo()->prepare(
            "DELETE FROM geonet_sessions WHERE last_activity < ?"
        );
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }

    public function validateId(string $id): bool
    {
        $stmt = geonet_pdo()->prepare(
            "SELECT 1 FROM geonet_sessions WHERE session_id = ? AND last_activity >= ?"
        );
        $stmt->execute([$id, time() - (int) ini_get("session.gc_maxlifetime")]);
        return $stmt->fetchColumn() !== false;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    if (getenv("VERCEL") === "1") {
        session_set_save_handler(new GeoNetDatabaseSessionHandler(), true);
        session_set_cookie_params([
            "httponly" => true,
            "secure" => true,
            "samesite" => "Lax",
        ]);
        ini_set("session.use_strict_mode", "1");
    }
    session_start();
}

function geonet_home($role)
{
    if ($role === "technician") {
        return "technician.php";
    }
    if ($role === "subscriber") {
        return "subscriber.php";
    }
    return "dashboard.php";
}

function geonet_require_login($allowedRoles = null)
{
    if (!isset($_SESSION["logged_in"])) {
        header("Location: index.php");
        exit();
    }

    $role = $_SESSION["role"] ?? "admin";

    if ($allowedRoles !== null) {
        $allowed = (array) $allowedRoles;
        if (!in_array($role, $allowed, true)) {
            header("Location: " . geonet_home($role));
            exit();
        }
    }
}
