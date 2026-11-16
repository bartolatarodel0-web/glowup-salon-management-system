
# GlowUp - Salon & Spa Management System

A web-based Salon and Spa Management System built using PHP, MySQL, HTML, CSS, and JavaScript.

GlowUp helps salon businesses manage customer appointments, services, and customer information through a simple administrative dashboard.

## Features

### Customer Features
- View available salon services.
- Submit appointment bookings.
- Enter customer contact information.
- Select appointment dates and times.
- Receive booking submission feedback.
- Prevent overlapping appointments.

### Admin Features
- Secure administrator login.
- Admin dashboard with booking statistics.
- View and manage appointments.
- Update appointment statuses.
- Manage customer records.
- Add, edit, and delete salon services.
- View appointment information.

## Technologies Used

- HTML5
- CSS3
- JavaScript
- PHP
- MySQL
- PDO
- XAMPP
- Git and GitHub

## Project Structure

```text
glowup/
├── admin/
│   ├── login.php
│   ├── dashboard.php
│   ├── logout.php
│   ├── appointments.php
│   ├── customers.php
│   └── services.php
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── script.js
│   └── images/
├── config/
│   └── database.php
├── includes/
├── database/
├── index.php
├── booking.php
└── README.md
```

## Installation

1. Install XAMPP.
2. Start Apache and MySQL.
3. Copy the project folder into:

   `C:\xampp\htdocs\`

4. Create a MySQL database named:

   `glowup_db`

5. Create the required database tables:
   - customers
   - services
   - appointments
   - admins

6. Configure your database connection in:

   `config/database.php`

7. Create an administrator account using a secure password-hashing process.

8. Open the project in your browser:

   `http://localhost/glowup/`

## Admin Access

Open:

`http://localhost/glowup/admin/login.php`

Use the administrator account created during setup.

Do not publish your administrator password or database credentials.

## Project Testing

The following features have been tested:

- Customer booking submission.
- Database record creation.
- Admin login and logout.
- Dashboard booking display.
- Appointment status updates.
- Customer management.
- Service creation, editing, and deletion.
- Dynamic service integration with the booking form.
- Overlapping appointment prevention.

## Developer

**Rodel Bartolata**

Web Developer Portfolio Project

## Project Status

Core functionality completed. Final documentation, visual presentation, and deployment preparation are ongoing.
