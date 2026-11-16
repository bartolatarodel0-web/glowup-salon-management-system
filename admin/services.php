
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$timeout = 1800;

if (
    isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > $timeout
) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}

$_SESSION['last_activity'] = time();

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = 'success';

$editingService = null;

// Handle form submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'add' || $action === 'update') {
                $serviceName = trim($_POST['service_name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
                $duration = filter_var(
                    $_POST['duration_minutes'] ?? '',
                    FILTER_VALIDATE_INT
                );

                if (
                    $serviceName === '' ||
                    mb_strlen($serviceName) > 100 ||
                    $price === false ||
                    $price < 0 ||
                    $duration === false ||
                    $duration < 1
                ) {
                    throw new InvalidArgumentException(
                        'Please enter a valid service name, price, and duration.'
                    );
                }

                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO services
                            (service_name, description, price, duration_minutes)
                        VALUES
                            (:name, :description, :price, :duration)
                    ");

                    $stmt->execute([
                        ':name' => $serviceName,
                        ':description' => $description,
                        ':price' => $price,
                        ':duration' => $duration
                    ]);

                    $message = 'New service added successfully!';
                } else {
                    $serviceId = filter_var(
                        $_POST['service_id'] ?? '',
                        FILTER_VALIDATE_INT
                    );

                    if (!$serviceId || $serviceId < 1) {
                        throw new InvalidArgumentException(
                            'Invalid service selected.'
                        );
                    }

                    $stmt = $pdo->prepare("
                        UPDATE services
                        SET
                            service_name = :name,
                            description = :description,
                            price = :price,
                            duration_minutes = :duration
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':name' => $serviceName,
                        ':description' => $description,
                        ':price' => $price,
                        ':duration' => $duration,
                        ':id' => $serviceId
                    ]);

                    $message = 'Service updated successfully!';
                }
            } elseif ($action === 'delete') {
                $serviceId = filter_var(
                    $_POST['service_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

                if (!$serviceId || $serviceId < 1) {
                    throw new InvalidArgumentException(
                        'Invalid service selected.'
                    );
                }

                $stmt = $pdo->prepare("
                    DELETE FROM services
                    WHERE id = :id
                ");

                $stmt->execute([':id' => $serviceId]);

                $message = $stmt->rowCount() > 0
                    ? 'Service deleted successfully!'
                    : 'The service could not be found.';
            } else {
                throw new InvalidArgumentException('Invalid action.');
            }
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        } catch (PDOException $e) {
            error_log('GlowUp services error: ' . $e->getMessage());

            if ($action === 'delete') {
                $message = 'This service could not be deleted. It may already be used by an appointment.';
            } else {
                $message = 'A database error occurred. Please check your information and try again.';
            }

            $messageType = 'error';
        }
    }
}

// Load service details for editing.
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($editId && $editId > 0) {
        $stmt = $pdo->prepare("
            SELECT id, service_name, description, price, duration_minutes
            FROM services
            WHERE id = :id
        ");

        $stmt->execute([':id' => $editId]);
        $editingService = $stmt->fetch() ?: null;
    }
}

// Get all services.
$stmt = $pdo->query("
    SELECT id, service_name, description, price, duration_minutes, created_at
    FROM services
    ORDER BY id DESC
");

$services = $stmt->fetchAll();

$totalServices = count($services);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management | GlowUp Admin</title>

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
            flex-shrink: 0;
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
            min-width: 0;
            padding: 35px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 30px;
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

        .stat-card,
        .content-card {
            background: #1b1b1b;
            border: 1px solid #303030;
            border-radius: 10px;
            padding: 25px;
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

        .content-grid {
            display: grid;
            grid-template-columns: minmax(260px, 350px) minmax(0, 1fr);
            gap: 22px;
            align-items: start;
        }

        .content-card h2 {
            font-size: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            color: #ccc;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input,
        textarea {
            width: 100%;
            background: #111;
            border: 1px solid #444;
            color: white;
            padding: 12px;
            border-radius: 6px;
            font: inherit;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #d4af37;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 6px;
            padding: 11px 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: #d4af37;
            color: #111;
        }

        .btn-secondary {
            background: #333;
            color: white;
        }

        .btn-edit {
            background: #302817;
            color: #e5c65b;
        }

        .btn-delete {
            background: #3b1e1e;
            color: #ff9999;
        }

        .message {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 7px;
            background: #173421;
            color: #a7e5b8;
            border: 1px solid #285b39;
        }

        .message.error {
            background: #3b1e1e;
            color: #ffb0b0;
            border-color: #713333;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th,
        td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #333;
            vertical-align: middle;
        }

        th {
            color: #d4af37;
            font-size: 12px;
            text-transform: uppercase;
        }

        td {
            color: #ddd;
            font-size: 14px;
        }

        .service-description {
            color: #999;
            max-width: 220px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        .actions .btn {
            padding: 8px 10px;
            font-size: 12px;
        }

        .empty {
            text-align: center;
            color: #999;
            padding: 30px;
        }

        @media (max-width: 1050px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
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
        <a href="customers.php">Customers</a>
        <a href="services.php" class="active">Services</a>
        <a href="../index.php" target="_blank">View Website</a>
    </aside>

    <main class="main">

        <div class="topbar">
            <div>
                <h1>Services Management</h1>
                <p class="subtitle">Add, update, and manage salon services.</p>
            </div>

            <form class="logout" action="logout.php" method="POST">
                <button type="submit">Logout</button>
            </form>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message <?= $messageType === 'error' ? 'error' : '' ?>">
                <?= escape($message) ?>
            </div>
        <?php endif; ?>

        <section class="stats">
            <div class="stat-card">
                <h3>Total Services</h3>
                <p><?= number_format($totalServices) ?></p>
            </div>
        </section>

        <div class="content-grid">

            <section class="content-card">
                <h2>
                    <?= $editingService ? 'Edit Service' : 'Add New Service' ?>
                </h2>

                <form method="POST" action="services.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="<?= $editingService ? 'update' : 'add' ?>"
                    >

                    <?php if ($editingService): ?>
                        <input
                            type="hidden"
                            name="service_id"
                            value="<?= escape($editingService['id']) ?>"
                        >
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="service_name">Service Name</label>
                        <input
                            type="text"
                            id="service_name"
                            name="service_name"
                            maxlength="100"
                            required
                            value="<?= escape($editingService['service_name'] ?? '') ?>"
                            placeholder="Example: Hair Styling"
                        >
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            placeholder="Describe the service..."
                        ><?= escape($editingService['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Price (₱)</label>
                        <input
                            type="number"
                            id="price"
                            name="price"
                            min="0"
                            step="0.01"
                            required
                            value="<?= escape($editingService['price'] ?? '') ?>"
                            placeholder="500"
                        >
                    </div>

                    <div class="form-group">
                        <label for="duration_minutes">Duration (minutes)</label>
                        <input
                            type="number"
                            id="duration_minutes"
                            name="duration_minutes"
                            min="1"
                            step="1"
                            required
                            value="<?= escape($editingService['duration_minutes'] ?? '') ?>"
                            placeholder="60"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= $editingService ? 'Update Service' : 'Add Service' ?>
                    </button>

                    <?php if ($editingService): ?>
                        <a href="services.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="content-card">
                <h2>All Salon Services</h2>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Service</th>
                                <th>Price</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($services) > 0): ?>

                                <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td><?= escape($service['id']) ?></td>

                                        <td>
                                            <strong>
                                                <?= escape($service['service_name']) ?>
                                            </strong>
                                            <div class="service-description">
                                                <?= escape($service['description']) ?>
                                            </div>
                                        </td>

                                        <td>
                                            ₱<?= number_format((float) $service['price'], 2) ?>
                                        </td>

                                        <td>
                                            <?= escape($service['duration_minutes']) ?> min
                                        </td>

                                        <td>
                                            <div class="actions">
                                                <a
                                                    class="btn btn-edit"
                                                    href="services.php?edit=<?= escape($service['id']) ?>"
                                                >
                                                    Edit
                                                </a>

                                                <form
                                                    method="POST"
                                                    action="services.php"
                                                    onsubmit="return confirm('Are you sure you want to delete this service?');"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= escape($_SESSION['csrf_token']) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="delete"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="service_id"
                                                        value="<?= escape($service['id']) ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-delete"
                                                    >
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="5" class="empty">
                                        No services have been added yet.
                                    </td>
                                </tr>

                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>

    </main>

</div>

</body>
</html>
