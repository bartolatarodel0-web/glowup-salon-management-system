
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// Protect admin page
if (empty($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

// Session timeout: 30 minutes
if (
    isset($_SESSION["admin_last_activity"]) &&
    time() - $_SESSION["admin_last_activity"] > 1800
) {
    session_unset();
    session_destroy();

    header("Location: login.php?timeout=1");
    exit;
}

$_SESSION["admin_last_activity"] = time();

// Create CSRF token
if (empty($_SESSION["appointment_csrf_token"])) {
    $_SESSION["appointment_csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

// Allowed appointment statuses
$allowedStatuses = [
    "Pending",
    "Confirmed",
    "Cancelled",
    "Completed"
];

// Handle appointment status update
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["appointment_csrf_token"], $token)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } else {

        $appointmentId = filter_var(
            $_POST["appointment_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        $newStatus = $_POST["status"] ?? "";

        if (!$appointmentId || !in_array($newStatus, $allowedStatuses, true)) {
            $message = "Invalid appointment information.";
            $messageType = "error";

        } else {

            try {
                $stmt = $pdo->prepare(
                    "UPDATE appointments
                     SET status = :status
                     WHERE id = :id"
                );

                $stmt->execute([
                    ":status" => $newStatus,
                    ":id" => $appointmentId
                ]);

                if ($stmt->rowCount() > 0) {
                    $message = "Appointment status updated successfully.";
                    $messageType = "success";
                } else {
                    // Check whether the appointment exists and already has this status
                    $check = $pdo->prepare(
                        "SELECT id FROM appointments WHERE id = :id"
                    );
                    $check->execute([":id" => $appointmentId]);

                    if ($check->fetch()) {
                        $message = "Appointment already has that status.";
                        $messageType = "success";
                    } else {
                        $message = "Appointment not found.";
                        $messageType = "error";
                    }
                }

            } catch (PDOException $e) {
                error_log("Appointment update error: " . $e->getMessage());
                $message = "Unable to update appointment. Please try again.";
                $messageType = "error";
            }
        }
    }
}

// Get all appointments
try {
    $stmt = $pdo->query(
        "SELECT
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.notes,
            a.status,
            a.created_at,
            c.full_name,
            c.email,
            c.phone,
            s.service_name,
            s.price,
            s.duration_minutes
         FROM appointments a
         INNER JOIN customers c ON a.customer_id = c.id
         INNER JOIN services s ON a.service_id = s.id
         ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );

    $appointments = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Appointment listing error: " . $e->getMessage());
    die("Unable to load appointments. Please check your database.");
}

$adminUsername = $_SESSION["admin_username"] ?? "Administrator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Appointments | GlowUp Admin</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0b0b0b;
            color: #fff;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 30px 20px;
            background: #151515;
            border-right: 1px solid #333;
        }

        .logo {
            color: #d4af37;
            font-size: 29px;
            font-weight: bold;
            margin-bottom: 45px;
        }

        .sidebar h3 {
            color: #777;
            font-size: 12px;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .sidebar a {
            display: block;
            padding: 14px;
            margin-bottom: 8px;
            border-radius: 7px;
            color: #ccc;
            text-decoration: none;
        }

        .sidebar a.active,
        .sidebar a:hover {
            background: #292316;
            color: #d4af37;
        }

        .main-content {
            margin-left: 250px;
            padding: 35px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .topbar h1 {
            font-size: 27px;
        }

        .topbar p {
            color: #999;
            margin-top: 8px;
        }

        .admin-name {
            color: #d4af37;
        }

        .logout-button {
            padding: 11px 18px;
            background: #d4af37;
            color: #111;
            border: none;
            border-radius: 7px;
            font-weight: bold;
            cursor: pointer;
        }

        .logout-button:hover {
            background: #f0cc59;
        }

        .content-box {
            padding: 25px;
            background: #171717;
            border: 1px solid #333;
            border-radius: 12px;
        }

        .content-box h2 {
            margin-bottom: 20px;
        }

        .message {
            padding: 13px;
            margin-bottom: 20px;
            border-radius: 7px;
        }

        .success {
            background: #153c26;
            color: #9af0b5;
        }

        .error {
            background: #451d1d;
            color: #ffaaaa;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #333;
            vertical-align: middle;
        }

        th {
            color: #d4af37;
            font-size: 13px;
        }

        td {
            color: #ccc;
            font-size: 13px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            background: #292316;
            color: #d4af37;
        }

        .status-form {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        select {
            padding: 9px;
            background: #222;
            border: 1px solid #444;
            border-radius: 6px;
            color: #fff;
        }

        .update-button {
            padding: 9px 12px;
            background: #d4af37;
            color: #111;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }

        .update-button:hover {
            background: #f0cc59;
        }

        .empty {
            color: #999;
            padding: 20px 0;
        }

        .notes {
            max-width: 180px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        @media (max-width: 700px) {
            .sidebar {
                width: 100%;
                min-height: auto;
                position: relative;
                padding: 18px;
            }

            .logo {
                margin-bottom: 15px;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">

    <div class="logo">GlowUp.</div>

    <h3>Menu</h3>

    <a href="dashboard.php">Dashboard</a>

    <a href="appointments.php" class="active">Appointments</a>

    <a href="../index.php">View Website</a>

</aside>

<main class="main-content">

    <header class="topbar">

        <div>
            <h1>Appointment Management</h1>

            <p>
                Welcome,
                <span class="admin-name">
                    <?= htmlspecialchars($adminUsername, ENT_QUOTES, "UTF-8") ?>
                </span>!
            </p>
        </div>

        <form action="logout.php" method="POST">
            <button type="submit" class="logout-button">
                Logout
            </button>
        </form>

    </header>

    <section class="content-box">

        <h2>All Customer Appointments</h2>

        <?php if ($message !== ""): ?>
            <div class="message <?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <?php if (count($appointments) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($appointments as $appointment): ?>

                        <tr>

                            <td>
                                <?= (int) $appointment["id"] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($appointment["full_name"], ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($appointment["email"], ENT_QUOTES, "UTF-8") ?>
                                <br>
                                <?= htmlspecialchars($appointment["phone"], ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($appointment["service_name"], ENT_QUOTES, "UTF-8") ?>
                                <br>
                                ₱<?= number_format((float) $appointment["price"], 2) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($appointment["appointment_date"], ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($appointment["appointment_time"], ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td class="notes">
                                <?= htmlspecialchars($appointment["notes"] ?? "", ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <span class="status">
                                    <?= htmlspecialchars($appointment["status"], ENT_QUOTES, "UTF-8") ?>
                                </span>
                            </td>

                            <td>

                                <form method="POST" class="status-form">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION["appointment_csrf_token"], ENT_QUOTES, "UTF-8") ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="appointment_id"
                                        value="<?= (int) $appointment["id"] ?>"
                                    >

                                    <select name="status" required>

                                        <?php foreach ($allowedStatuses as $status): ?>

                                            <option
                                                value="<?= htmlspecialchars($status) ?>"
                                                <?= $appointment["status"] === $status ? "selected" : "" ?>
                                            >
                                                <?= htmlspecialchars($status) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                    <button type="submit" class="update-button">
                                        Save
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">
                No appointments found. Customer bookings will appear here.
            </p>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
