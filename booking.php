
<?php

session_start();
date_default_timezone_set("Asia/Manila");

require_once __DIR__ . "/config/database.php";

// Generate CSRF token.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$success = "";
$error = "";

// Preserve form values.
$name = "";
$email = "";
$phone = "";
$service_id = "";
$appointment_date = "";
$appointment_time = "";
$notes = "";

// Load services from database.
$stmt = $pdo->query("
    SELECT id, service_name, price, duration_minutes
    FROM services
    ORDER BY service_name ASC
");

$services = $stmt->fetchAll();

// Process booking.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $service_id = $_POST["service_id"] ?? "";
    $appointment_date = $_POST["appointment_date"] ?? "";
    $appointment_time = $_POST["appointment_time"] ?? "";
    $notes = trim($_POST["notes"] ?? "");

    $token = $_POST["csrf_token"] ?? "";

    $lockAcquired = false;

    try {

        // Check CSRF token.
        if (
            !is_string($token) ||
            !hash_equals($_SESSION["csrf_token"], $token)
        ) {
            throw new Exception(
                "Invalid form submission. Please refresh the page."
            );
        }

        // Validate customer information.
        if (
            $name === "" ||
            mb_strlen($name) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 100 ||
            $phone === "" ||
            strlen($phone) > 20
        ) {
            throw new Exception(
                "Please enter valid customer information."
            );
        }

        // Normalize email.
        $email = strtolower($email);

        // Validate service ID.
        $service_id = filter_var(
            $service_id,
            FILTER_VALIDATE_INT
        );

        if (!$service_id || $service_id < 1) {
            throw new Exception(
                "Please select a valid service."
            );
        }

        // Validate appointment date.
        $date = DateTime::createFromFormat(
            "!Y-m-d",
            $appointment_date
        );

        if (
            !$date ||
            $date->format("Y-m-d") !== $appointment_date ||
            $appointment_date < date("Y-m-d")
        ) {
            throw new Exception(
                "Please select a valid appointment date."
            );
        }

        // Validate appointment time.
        $time = DateTime::createFromFormat(
            "!H:i",
            $appointment_time
        );

        if (
            !$time ||
            $time->format("H:i") !== $appointment_time
        ) {
            throw new Exception(
                "Please select a valid appointment time."
            );
        }

        // Prevent bookings in the past.
        if (
            $appointment_date === date("Y-m-d") &&
            $appointment_time <= date("H:i")
        ) {
            throw new Exception(
                "Please select a future appointment time."
            );
        }

        // Validate notes.
        if (mb_strlen($notes) > 1000) {
            throw new Exception(
                "Your notes must not exceed 1000 characters."
            );
        }

        // Get selected service and its duration.
        $stmt = $pdo->prepare("
            SELECT
                id,
                service_name,
                price,
                duration_minutes
            FROM services
            WHERE id = ?
        ");

        $stmt->execute([$service_id]);

        $service = $stmt->fetch();

        if (!$service) {
            throw new Exception(
                "Selected service does not exist. Please choose another service."
            );
        }

        $duration = (int) $service["duration_minutes"];

        if ($duration < 1) {
            throw new Exception(
                "This service has an invalid appointment duration."
            );
        }

        /*
         * Acquire a named database lock.
         * This serializes booking checks so two requests
         * cannot both pass the conflict check simultaneously.
         */
        $lockStmt = $pdo->query("
            SELECT GET_LOCK('glowup_booking_slot', 10)
        ");

        $lockResult = $lockStmt->fetchColumn();

        if ((int) $lockResult !== 1) {
            throw new Exception(
                "The booking system is busy. Please try again."
            );
        }

        $lockAcquired = true;

        // Begin database transaction.
        $pdo->beginTransaction();

        /*
         * Check for overlapping appointments.
         *
         * Existing Pending and Confirmed appointments
         * block the requested time.
         *
         * Cancelled and Completed appointments do not
         * block new bookings.
         */
        $conflictStmt = $pdo->prepare("
            SELECT a.id
            FROM appointments a
            INNER JOIN services s
                ON s.id = a.service_id
            WHERE a.appointment_date = ?
              AND a.status IN ('Pending', 'Confirmed')

              AND TIMESTAMP(
                    a.appointment_date,
                    a.appointment_time
                  ) < DATE_ADD(
                    TIMESTAMP(?, ?),
                    INTERVAL ? MINUTE
                  )

              AND DATE_ADD(
                    TIMESTAMP(
                        a.appointment_date,
                        a.appointment_time
                    ),
                    INTERVAL s.duration_minutes MINUTE
                  ) > TIMESTAMP(?, ?)

            LIMIT 1
        ");

        $conflictStmt->execute([
            $appointment_date,
            $appointment_date,
            $appointment_time,
            $duration,
            $appointment_date,
            $appointment_time
        ]);

        $conflict = $conflictStmt->fetch();

        if ($conflict) {
            throw new Exception(
                "Sorry, this appointment time is already occupied. Please choose another time."
            );
        }

        // Find existing customer by email.
        $stmt = $pdo->prepare("
            SELECT id
            FROM customers
            WHERE email = ?
        ");

        $stmt->execute([$email]);

        $customer = $stmt->fetch();

        if ($customer) {

            $customer_id = $customer["id"];

            // Update existing customer information.
            $stmt = $pdo->prepare("
                UPDATE customers
                SET
                    full_name = ?,
                    phone = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $phone,
                $customer_id
            ]);

        } else {

            // Register new customer.
            $stmt = $pdo->prepare("
                INSERT INTO customers
                    (full_name, email, phone)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $email,
                $phone
            ]);

            $customer_id = $pdo->lastInsertId();
        }

        // Save appointment.
        $stmt = $pdo->prepare("
            INSERT INTO appointments (
                customer_id,
                service_id,
                appointment_date,
                appointment_time,
                notes,
                status
            )
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");

        $stmt->execute([
            $customer_id,
            $service_id,
            $appointment_date,
            $appointment_time,
            $notes
        ]);

        // Commit transaction.
        $pdo->commit();

        $success = "Your appointment request was submitted successfully!";

        // Clear form values.
        $name = "";
        $email = "";
        $phone = "";
        $service_id = "";
        $appointment_date = "";
        $appointment_time = "";
        $notes = "";

        // Generate fresh CSRF token.
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            "GlowUp booking database error: " . $e->getMessage()
        );

        $error = "Unable to process your booking. Please try again.";

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            "GlowUp booking error: " . $e->getMessage()
        );

        $error = $e->getMessage();

    } finally {

        // Always release the named database lock.
        if ($lockAcquired) {
            try {
                $pdo->query("
                    SELECT RELEASE_LOCK('glowup_booking_slot')
                ");
            } catch (Throwable $e) {
                error_log(
                    "GlowUp lock release error: " . $e->getMessage()
                );
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

    <title>Book Appointment | GlowUp Salon & Spa</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>

        .booking-page {
            min-height: 100vh;
            padding: 70px 20px;
            background: #11100f;
        }

        .booking-wrapper {
            max-width: 700px;
            margin: auto;
        }

        .booking-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .booking-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            margin-bottom: 15px;
        }

        .booking-header p {
            color: #aaa;
        }

        .booking-form-container {
            background: #1c1a18;
            padding: 40px;
            border: 1px solid #39332a;
            border-radius: 10px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            color: #c9a66b;
            margin-bottom: 9px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px;
            background: #11100f;
            color: #fff;
            border: 1px solid #444;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #c9a66b;
        }

        .form-group select option {
            background: #1c1a18;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 110px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .booking-submit {
            width: 100%;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }

        .booking-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .message {
            padding: 16px;
            border-radius: 5px;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .success-message {
            background: #173d29;
            color: #a8efbd;
            border: 1px solid #276440;
        }

        .error-message {
            background: #481e1e;
            color: #ffbaba;
            border: 1px solid #803b3b;
        }

        .back-home {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #c9a66b;
        }

        .back-home:hover {
            color: #fff;
        }

        @media(max-width: 600px) {

            .booking-form-container {
                padding: 25px 18px;
            }

            .booking-header h1 {
                font-size: 32px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

        }

    </style>

</head>

<body>

<section class="booking-page">

    <div class="booking-wrapper">

        <div class="booking-header">

            <p class="subtitle">YOUR BEAUTY JOURNEY STARTS HERE</p>

            <h1>
                Book Your <span>Appointment</span>
            </h1>

            <p>
                Complete the form below to request your appointment.
            </p>

        </div>

        <div class="booking-form-container">

            <?php if ($success): ?>

                <div class="message success-message">
                    <strong>Booking Successful!</strong><br>

                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>

                    <br>

                    Your booking is currently pending confirmation.
                </div>

            <?php endif; ?>


            <?php if ($error): ?>

                <div class="message error-message">
                    <strong>Booking Error</strong><br>

                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>

            <?php endif; ?>


            <form method="POST" action="booking.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8'); ?>"
                >

                <div class="form-group">

                    <label for="full_name">Full Name *</label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        maxlength="100"
                        value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Enter your full name"
                        autocomplete="name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="email">Email Address *</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="100"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="example@email.com"
                        autocomplete="email"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="phone">Phone Number *</label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        maxlength="20"
                        value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="09XXXXXXXXX"
                        autocomplete="tel"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="service_id">Choose Your Service *</label>

                    <select
                        id="service_id"
                        name="service_id"
                        required
                    >

                        <option value="">Select a salon service</option>

                        <?php foreach ($services as $service): ?>

                            <option
                                value="<?= (int) $service["id"]; ?>"
                                <?= ((string) $service_id === (string) $service["id"]) ? "selected" : ""; ?>
                            >

                                <?= htmlspecialchars($service["service_name"], ENT_QUOTES, 'UTF-8'); ?>

                                - ₱<?= number_format((float) $service["price"], 2); ?>

                                (<?= (int) $service["duration_minutes"]; ?> minutes)

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <?php if (empty($services)): ?>

                        <p style="color:#ffbaba; margin-top:10px;">
                            No services are currently available.
                        </p>

                    <?php endif; ?>

                </div>


                <div class="form-row">

                    <div class="form-group">

                        <label for="appointment_date">Appointment Date *</label>

                        <input
                            type="date"
                            id="appointment_date"
                            name="appointment_date"
                            min="<?= date("Y-m-d"); ?>"
                            value="<?= htmlspecialchars($appointment_date, ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="appointment_time">Appointment Time *</label>

                        <input
                            type="time"
                            id="appointment_time"
                            name="appointment_time"
                            value="<?= htmlspecialchars($appointment_time, ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >

                    </div>

                </div>


                <div class="form-group">

                    <label for="notes">Additional Notes</label>

                    <textarea
                        id="notes"
                        name="notes"
                        maxlength="1000"
                        placeholder="Any special requests?"
                    ><?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></textarea>

                </div>


                <button
                    type="submit"
                    class="primary-btn booking-submit"
                    <?= empty($services) ? "disabled" : ""; ?>
                >

                    Confirm Booking

                </button>

            </form>


            <a href="index.php" class="back-home">
                ← Back to Homepage
            </a>

        </div>

    </div>

</section>

</body>

</html>
