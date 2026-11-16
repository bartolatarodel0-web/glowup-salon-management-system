# GlowUp – Salon & Spa Management System

A web-based Salon and Spa Management System developed using PHP, MySQL, HTML5, CSS3, and JavaScript.

GlowUp helps salon businesses manage customer appointments, services, and customer information through a user-friendly administrative dashboard.

**Developer:** Rodel Bartolata  
**Project Type:** Full-Stack Web Development  
**Project Status:** Core functionality completed; portfolio preparation in progress.

---

## Features

### Customer Features
- View available salon services.
- Submit appointment bookings.
- Enter customer contact information.
- Select appointment dates and times.
- Receive booking submission feedback.
- Prevent overlapping appointments.
- View dynamically updated service prices and durations.

### Admin Features
- Secure administrator login and logout.
- Dashboard with appointment statistics.
- View and manage customer appointments.
- Update appointment statuses.
- Search and view customer records.
- Add, edit, and delete salon services.
- View appointment and customer information.

### Booking Management
- Customer records are saved in MySQL.
- Existing customers can be identified by email.
- Appointment availability is checked before booking.
- Cancelled and completed appointments do not block future bookings.

---

## Technologies Used

| Technology | Purpose |
|---|---|
| HTML5 | Website structure |
| CSS3 | Styling and responsive layout |
| JavaScript | Frontend interactions |
| PHP | Backend functionality |
| MySQL | Database management |
| PDO | Secure database operations |
| XAMPP | Local development environment |
| Git | Version control |
| GitHub | Source code hosting |

---

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
├── index.html
├── booking.php
├── .gitignore
└── README.md
```

**Note:** The local `config/database.php` file is excluded from GitHub to protect database credentials.

---

## Installation and Setup

### 1. Install XAMPP

Download and install XAMPP.

Start the following services:
- Apache
- MySQL

### 2. Download the Project

Clone the repository:

```bash
git clone https://github.com/bartolatarodel0-web/glowup-salon-management-system.git
```

Alternatively, download the repository as a ZIP file.

Place the project folder inside:

```text
C:\xampp\htdocs\
```

### 3. Create the Database

1. Open phpMyAdmin at `http://localhost/phpmyadmin/`.
2. Create a database named `glowup_db`.
3. Create the required tables:
   - customers
   - services
   - appointments
   - admins

The database must contain the columns and relationships expected by the application.

### 4. Configure the Database Connection

Create the local file:

```text
config/database.php
```

Configure the connection using your own local database credentials.

Do not upload this file or publish your database password.

### 5. Create an Administrator Account

Create an administrator account with a securely hashed password.

The application uses password verification for administrator login. Never store a plain-text administrator password in the database.

### 6. Run the Application

Open the homepage:

```text
http://localhost/glowup/
```

Open the administrator login:

```text
http://localhost/glowup/admin/login.php
```

---

## Project Testing

The following functionality has been tested successfully:

- Customer booking submission.
- Database record creation.
- Administrator login and logout.
- Dashboard booking display.
- Appointment status updates.
- Customer management.
- Service creation, editing, and deletion.
- Dynamic service integration with the booking form.
- Appointment conflict prevention.
- Cancelled appointment time availability.

---

## Security Considerations

- Database operations use PDO prepared statements.
- Administrator passwords are verified using password hashing.
- Login sessions are protected.
- CSRF protection is implemented in relevant forms.
- Database credentials are excluded from the Git repository.
- Temporary administrator setup scripts should not be published.

---

## Future Improvements

Potential improvements for future versions include:

- Email booking notifications.
- Staff and room scheduling.
- Online payment integration.
- Customer booking history.
- Live hosting and production deployment.
- Additional reporting features.

---

## Developer

**Rodel Bartolata**

Aspiring Web Developer | PHP & MySQL

GitHub: [bartolatarodel0-web](https://github.com/bartolatarodel0-web)

Project Repository: [GlowUp – Salon & Spa Management System](https://github.com/bartolatarodel0-web/glowup-salon-management-system)

---

## Project Status

Core functionality completed.

Final documentation, screenshots, portfolio presentation, and live deployment preparation are ongoing.