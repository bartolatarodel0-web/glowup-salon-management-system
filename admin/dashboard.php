
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// Protect dashboard
if (empty($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

// Session inactivity timeout: 30 minutes
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

try {
    $totalAppointments = (int) $pdo
        ->query("SELECT COUNT(*) FROM appointments")
        ->fetchColumn();

    $pendingAppointments = (int) $pdo
        ->query("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'")
        ->fetchColumn();

    $totalCustomers = (int) $pdo
        ->query("SELECT COUNT(*) FROM customers")
        ->fetchColumn();

    $totalServices = (int) $pdo
        ->query("SELECT COUNT(*) FROM services")
        ->fetchColumn();

    $recentAppointments = $pdo->query(
        "SELECT
            a.id,
            c.full_name,
            s.service_name,
            a.appointment_date,
            a.appointment_time,
            a.status
         FROM appointments a
         INNER JOIN customers c ON a.customer_id = c.id
         INNER JOIN services s ON a.service_id = s.id
         ORDER BY a.created_at DESC
         LIMIT 5"
    )->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    die("Unable to load dashboard information. Please check your database.");
}

$adminUsername = $_SESSION["admin_username"] ?? "Administrator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | GlowUp</title>

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
            height: 100vh;
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
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 35px;
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

        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .card {
            padding: 25px;
            background: #171717;
            border: 1px solid #333;
            border-radius: 12px;
        }

        .card p {
            color: #999;
            margin-bottom: 15px;
        }

        .card h2 {
            color: #d4af37;
            font-size: 32px;
        }

        .section {
            padding: 25px;
            background: #171717;
            border: 1px solid #333;
            border-radius: 12px;
        }

        .section h2 {
            margin-bottom: 20px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        th {
            color: #d4af37;
            font-size: 13px;
        }

        td {
            color: #ccc;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #292316;
            color: #d4af37;
            font-size: 12px;
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

        .empty {
            color: #999;
            padding: 20px 0;
        }

        @media (max-width: 1000px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                width: 100%;
                height: auto;
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

            .cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">

    <div class="logo">GlowUp.</div>

    <h3>Menu</h3>

    <a href="dashboard.php" class="active">
        Dashboard
    </a>

    <a href="../index.php">
        View Website
    </a>

</aside>

<main class="main-content">

    <header class="topbar">

        <div>
            <h1>Dashboard Overview</h1>

            <p>
                Welcome back,
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

    <section class="cards">

        <div class="card">
            <p>Total Appointments</p>
            <h2><?= $totalAppointments ?></h2>
        </div>

        <div class="card">
            <p>Pending Appointments</p>
            <h2><?= $pendingAppointments ?></h2>
        </div>

        <div class="card">
            <p>Total Customers</p>
            <h2><?= $totalCustomers ?></h2>
        </div>

        <div class="card">
            <p>Available Services</p>
            <h2><?= $totalServices ?></h2>
        </div>

    </section>

    <section class="section">

        <h2>Recent Appointments</h2>

        <?php if (count($recentAppointments) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($recentAppointments as $appointment): ?>

                            <tr>
                                <td>
                                    <?= (int) $appointment["id"] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($appointment["full_name"], ENT_QUOTES, "UTF-8") ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($appointment["service_name"], ENT_QUOTES, "UTF-8") ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($appointment["appointment_date"], ENT_QUOTES, "UTF-8") ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($appointment["appointment_time"], ENT_QUOTES, "UTF-8") ?>
                                </td>

                                <td>
                                    <span class="status">
                                        <?= htmlspecialchars($appointment["status"], ENT_QUOTES, "UTF-8") ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">No appointments have been recorded yet.</p>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
