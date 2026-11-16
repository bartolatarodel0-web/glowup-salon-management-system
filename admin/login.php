
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// Redirect logged-in administrators
if (!empty($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if (empty($_SESSION["login_csrf_token"])) {
    $_SESSION["login_csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["login_csrf_token"], $csrfToken)
    ) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($username === "" || $password === "") {
            $error = "Please enter your username and password.";
        } else {

            try {
                $stmt = $pdo->prepare(
                    "SELECT id, username, password
                     FROM admins
                     WHERE username = :username
                     LIMIT 1"
                );

                $stmt->execute([
                    ":username" => $username
                ]);

                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin["password"])) {

                    session_regenerate_id(true);

                    $_SESSION["admin_id"] = $admin["id"];
                    $_SESSION["admin_username"] = $admin["username"];
                    $_SESSION["admin_last_activity"] = time();

                    unset($_SESSION["login_csrf_token"]);

                    header("Location: dashboard.php");
                    exit;

                } else {
                    $error = "Incorrect username or password.";
                }

            } catch (PDOException $e) {
                error_log("Admin login error: " . $e->getMessage());
                $error = "Unable to process your login. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | GlowUp</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: #090909;
            color: #fff;
            font-family: Arial, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 40px;
            background: #151515;
            border: 1px solid #333;
            border-radius: 15px;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .4);
        }

        .logo {
            color: #d4af37;
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }

        h2 {
            text-align: center;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #aaa;
            text-align: center;
            margin-bottom: 30px;
        }

        label {
            display: block;
            margin: 18px 0 8px;
            color: #ddd;
        }

        input {
            width: 100%;
            padding: 14px;
            background: #222;
            border: 1px solid #444;
            border-radius: 7px;
            color: white;
            outline: none;
        }

        input:focus {
            border-color: #d4af37;
        }

        button {
            width: 100%;
            padding: 14px;
            margin-top: 25px;
            background: #d4af37;
            color: #111;
            border: none;
            border-radius: 7px;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
        }

        button:hover {
            background: #f0cc59;
        }

        .error {
            padding: 12px;
            margin-bottom: 18px;
            background: #451d1d;
            color: #ffaaaa;
            border-radius: 7px;
            line-height: 1.5;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #d4af37;
            text-decoration: none;
        }

        .back-link:hover {
            color: #fff;
        }
    </style>
</head>

<body>

<div class="login-container">

    <div class="logo">GlowUp</div>

    <h2>Admin Login</h2>

    <p class="subtitle">Welcome back! Please sign in.</p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION["login_csrf_token"], ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="username">Username</label>

        <input
            type="text"
            id="username"
            name="username"
            autocomplete="username"
            required
        >

        <label for="password">Password</label>

        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">Login to Dashboard</button>

    </form>

    <a href="../index.php" class="back-link">
        ← Back to GlowUp Website
    </a>

</div>

</body>
</html>
