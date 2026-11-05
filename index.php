<?php
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/geonet.php";

if (isset($_SESSION["logged_in"])) {
    header("Location: " . geonet_home($_SESSION["role"] ?? "admin"));
    exit();
}

$error = "";
$databaseError = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usernameInput = $_POST["username"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $username = is_string($usernameInput) ? strtolower(trim($usernameInput)) : "";
    $password = is_string($passwordInput) ? $passwordInput : "";

    try {
            $user = $username !== "" ? geonet_find_user_by_email($username) : null;
            $ok = $user && password_verify($password, $user["password_hash"]);
            geonet_log_login_attempt($username, $ok);

            if ($ok) {
                geonet_mark_login($user["id"]);
                $profile = geonet_profile_for_user($user);
                session_regenerate_id(true);
                $_SESSION["logged_in"] = true;
                $_SESSION["user_id"] = (int) $user["id"];
                $_SESSION["username"] = $user["email"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["display_name"] = $user["display_name"];
                if (!empty($profile["tech_id"])) {
                    $_SESSION["tech_id"] = $profile["tech_id"];
                }
                if (!empty($profile["sub_id"])) {
                    $_SESSION["sub_id"] = $profile["sub_id"];
                }
                header("Location: " . $profile["home"]);
                exit();
            }

            $error = "Invalid email address or password.";
    } catch (Throwable $e) {
            $error = "Database is not ready.";
            $databaseError = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoNet Connect - Login</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="login-container">
    <div class="left-panel">
        <div class="grid-overlay"></div>
        <svg class="network-lines" viewBox="0 0 700 700" preserveAspectRatio="none">
            <polyline points="70,100 130,165 225,150 310,215 375,180 440,250 510,230" fill="none"/>
            <polyline points="35,250 115,270 195,235 275,305 355,275 425,335 520,315" fill="none"/>
            <polyline points="85,415 165,400 245,435 325,405 405,445 490,425" fill="none"/>
            <polyline points="165,40 175,115 205,190 250,270 275,400" fill="none"/>
            <polyline points="340,55 310,215 340,285 375,465" fill="none"/>
            <polyline points="455,70 440,150 470,230 425,395 440,480" fill="none"/>
        </svg>

        <div class="logo">
            <div class="logo-icon"><i class="fa-solid fa-location-dot"></i></div>
            <div>
                <h3>GeoNet Connect</h3>
                <span>R-Link Data and Solutions</span>
            </div>
        </div>

        <span class="dot green d1"></span>
        <span class="dot green d2"></span>
        <span class="dot green d3"></span>
        <span class="dot green d4"></span>
        <span class="dot red d5"></span>
        <span class="dot red d6"></span>
        <span class="dot red d7"></span>
        <span class="dot yellow d8"></span>
        <span class="dot yellow d9"></span>
        <span class="dot yellow d10"></span>

        <div class="hero-content">
            <h1>One network.<br>One sign-in.</h1>
            <p>
                Sign in with your R-Link email to access the right GeoNet Connect workspace.
            </p>
            <div class="legend">
                <span><i class="legend-dot online"></i>Admin ops</span>
                <span><i class="legend-dot intermittent"></i>Field work</span>
                <span><i class="legend-dot offline"></i>My connection</span>
            </div>
        </div>
    </div>

    <div class="right-panel">
        <div class="login-box login-box-wide">
            <h1>Sign in</h1>
            <p class="subtitle">Your email address determines your access.</p>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php if ($databaseError): ?>
                        <?php echo htmlspecialchars($error); ?>
                        <a href="install.php">Open the installer to create it.</a>
                    <?php else: ?>
                        <?php echo htmlspecialchars($error); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="mode" value="login">
                <div class="form-group">
                    <label for="username">Email address</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-user input-icon"></i>
                        <input
                            type="email"
                            name="username"
                            id="username"
                            placeholder="Enter your R-Link email"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Enter your password"
                            required
                        >
                        <button type="button" class="toggle-password" id="togglePassword">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-button">
                    Sign in
                </button>
            </form>

            <p class="request-access">New subscriber? <a href="signup.php">Sign up</a></p>
            <div class="security-message">
                <i class="fa-solid fa-circle-info"></i>
                <span>
                    Access is restricted to authorized R-Link personnel and registered subscribers.
                    All login attempts are logged and monitored.
                </span>
            </div>
        </div>

        <div class="footer">
            © 2026 R-Link Data and Solutions · San Miguel, Iloilo
        </div>
        <button class="help-button">?</button>
    </div>
</div>
<script src="script.js"></script>
</body>
</html>
