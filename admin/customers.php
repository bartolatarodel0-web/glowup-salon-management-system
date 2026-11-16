
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Automatically log out after 30 minutes of inactivity.
$timeout = 1800;

if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}

$_SESSION['last_activity'] = time();

require_once __DIR__ . '/../config/database.php';

// Generate CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Search customers.
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        c.id,
        c.full_name,
        c.email,
        c.phone,
        c.created_at,
        COUNT(a.id) AS total_appointments
    FROM customers c
    LEFT JOIN appointments a ON a.customer_id = c.id
";

$params = [];

if ($search !== '') {
    $sql .= "
        WHERE c.full_name LIKE :name
        OR c.email LIKE :email
        OR c.phone LIKE :phone
    ";

    $keyword = '%' . $search . '%';

    $params = [
        ':name' => $keyword,
        ':email' => $keyword,
        ':phone' => $keyword
    ];
}

$sql .= "
    GROUP BY
        c.id,
        c.full_name,
        c.email,
        c.phone,
        c.created_at
    ORDER BY c.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$totalCustomers = count($customers);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management | GlowUp Admin</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #101010;
            color: #f5f5f5;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: #181818;
            padding: 30px 20px;
            border-right: 1px solid #292929;
        }

        .logo {
            color: #d4af37;
            font-size: 27px;
            font-weight: bold;
            margin-bottom: 40px;
        }

        .sidebar a {
            display: block;
            color: #ccc;
            text-decoration: none;
            padding: 14px;
            margin-bottom: 8px;
            border-radius: 7px;
            transition: 0.3s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #292315;
            color: #d4af37;
        }

        .main {
            flex: 1;
            padding: 35px;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #999;
        }

        .logout button {
            background: transparent;
            border: 1px solid #d4af37;
            color: #d4af37;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
        }

        .logout button:hover {
            background: #d4af37;
            color: #111;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #1b1b1b;
            padding: 25px;
            border: 1px solid #303030;
            border-radius: 10px;
        }

        .stat-card h3 {
            color: #999;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .stat-card p {
            color: #d4af37;
            font-size: 30px;
            font-weight: bold;
        }

        .content-card {
            background: #1b1b1b;
            border: 1px solid #303030;
            border-radius: 10px;
            padding: 25px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .section-heading h2 {
            font-size: 21px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-form input {
            background: #111;
            border: 1px solid #444;
            color: white;
            padding: 11px 13px;
            border-radius: 6px;
            min-width: 230px;
        }

        .search-form button,
        .clear-btn {
            padding: 11px 16px;
            border: none;
            border-radius: 6px;
            background: #d4af37;
            color: #111;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }

        .clear-btn {
            background: #333;
            color: white;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        th {
            color: #d4af37;
            font-size: 13px;
            text-transform: uppercase;
        }

        td {
            color: #ddd;
            font-size: 14px;
        }

        .appointment-count {
            display: inline-block;
            background: #302817;
            color: #d4af37;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            color: #999;
            padding: 35px;
        }

        @media (max-width: 800px) {
            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #292929;
            }

            .sidebar a {
                display: inline-block;
                margin-right: 5px;
            }

            .main {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<div class="layout">

    <aside class="sidebar">
        <div class="logo">GlowUp.</div>

        <a href="dashboard.php">Dashboard</a>
        <a href="appointments.php">Appointments</a>
        <a href="customers.php" class="active">Customers</a>
        <a href="../index.php" target="_blank">View Website</a>
    </aside>

    <main class="main">

        <div class="topbar">
            <div>
                <h1>Customer Management</h1>
                <p class="subtitle">View and search your registered customers.</p>
            </div>

            <form class="logout" action="logout.php" method="POST">
                <button type="submit">Logout</button>
            </form>
        </div>

        <section class="stats">
            <div class="stat-card">
                <h3>Customers Found</h3>
                <p><?= number_format($totalCustomers) ?></p>
            </div>
        </section>

        <section class="content-card">

            <div class="section-heading">
                <h2>Customer List</h2>

                <form class="search-form" method="GET" action="customers.php">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search name, email, or phone..."
                        value="<?= escape($search) ?>"
                    >

                    <button type="submit">Search</button>

                    <?php if ($search !== ''): ?>
                        <a href="customers.php" class="clear-btn">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Appointments</th>
                            <th>Registration Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($customers) > 0): ?>

                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?= escape($customer['id']) ?></td>

                                    <td>
                                        <?= escape($customer['full_name']) ?>
                                    </td>

                                    <td>
                                        <?= escape($customer['email']) ?>
                                    </td>

                                    <td>
                                        <?= escape($customer['phone']) ?>
                                    </td>

                                    <td>
                                        <span class="appointment-count">
                                            <?= escape($customer['total_appointments']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= escape(date(
                                            'M d, Y',
                                            strtotime($customer['created_at'])
                                        )) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="empty">
                                    No customers found.
                                </td>
                            </tr>

                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>

    </main>

</div>

</body>
</html>
