# OpsMan – Field Operations Management System

A full-stack Field Operations Management System for managing customs, logistics, and border operations teams.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         OpsMan System                           │
├─────────────────┬──────────────────────────┬────────────────────┤
│   Frontend      │    PHP Backend (API)      │  Python AI Service │
│   (HTML/CSS/JS) │    Apache + PDO + MySQL   │  Flask + scikit-   │
│                 │                           │  learn (port 5001) │
│  - Dashboard    │  /api/auth.php            │                    │
│  - Tasks        │  /api/employees.php       │  /api/predict-delay│
│  - Reports      │  /api/tasks.php           │  /api/bottlenecks  │
│  - Analytics    │  /api/reports.php         │  /api/performance- │
│  - Employees    │  /api/gps.php             │    insights        │
│  - Alerts       │  /api/dashboard.php       │  /api/employee-    │
│  - Employee     │  /api/analytics.php       │    score           │
│    Portal       │  /api/alerts.php          │                    │
│                 │  /api/uploads.php         │                    │
└─────────────────┴──────────────────────────┴────────────────────┘
                            │
                     MySQL Database
                       (opsman)
```

## Prerequisites

- PHP 8.1+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- Apache 2.4+ with mod_rewrite enabled
- Python 3.9+ (for AI service)
- A modern web browser

## Installation

### 1. Clone / place the repository

```bash
git clone <repo-url> /var/www/html/opsman
```

### 2. Database Setup

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

### 3. Configure Backend

Edit `backend/config/database.php` to match your MySQL credentials:

```php
private $host     = 'localhost';
private $db_name  = 'opsman';
private $username = 'your_mysql_user';
private $password = 'your_mysql_password';
```

Edit `backend/config/config.php` for application settings:

```php
define('AI_SERVICE_URL', 'http://localhost:5001');
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
```

### 4. Create Upload Directory

```bash
mkdir -p /var/www/html/opsman/uploads
chmod 755 /var/www/html/opsman/uploads
chown www-data:www-data /var/www/html/opsman/uploads
```

### 5. Apache Virtual Host

```apache
<VirtualHost *:80>
    ServerName opsman.local
    DocumentRoot /var/www/html/opsman/frontend
    
    Alias /api /var/www/html/opsman/backend/api
    Alias /uploads /var/www/html/opsman/uploads
    
    <Directory /var/www/html/opsman/backend>
        AllowOverride All
        Require all granted
    </Directory>
    
    <Directory /var/www/html/opsman/frontend>
        AllowOverride None
        Require all granted
    </Directory>
</VirtualHost>
```

### 6. Python AI Service

```bash
cd ai-service
python -m venv venv
source venv/bin/activate        # Windows: venv\Scripts\activate
pip install -r requirements.txt

# Optional: create .env file
echo "DB_HOST=localhost" > .env
echo "DB_NAME=opsman" >> .env
echo "DB_USER=root" >> .env
echo "DB_PASSWORD=" >> .env

python app.py
```

The AI service starts on **port 5001**.

### 7. Frontend API Base URL

In `frontend/js/app.js`, update `API_BASE_URL` if needed:

```js
const API_BASE_URL = '/api';   // adjust if hosted differently
```

---

## Default Test Credentials

| Role               | Username    | Password       |
|--------------------|-------------|----------------|
| Admin              | `admin`     | `Admin@123`    |
| Operations Manager | `manager1`  | `Manager@123`  |
| Field Employee     | `employee1` | `Employee@123` |

---

## API Reference

### Authentication

| Method | Endpoint                           | Description           |
|--------|------------------------------------|-----------------------|
| POST   | `/api/auth.php?action=login`       | Login, returns token  |
| POST   | `/api/auth.php?action=logout`      | Invalidate token      |
| GET    | `/api/auth.php?action=me`          | Get current user      |
| PUT    | `/api/auth.php?action=change-password` | Change password   |

All other endpoints require `Authorization: Bearer <token>`.

### Employees

| Method | Endpoint                    | Description              |
|--------|-----------------------------|--------------------------|
| GET    | `/api/employees.php`        | List employees           |
| GET    | `/api/employees.php?id=X`   | Get employee             |
| POST   | `/api/employees.php`        | Create employee + user   |
| PUT    | `/api/employees.php?id=X`   | Update employee          |
| DELETE | `/api/employees.php?id=X`   | Delete employee (admin)  |

### Tasks

| Method | Endpoint                                      | Description       |
|--------|-----------------------------------------------|-------------------|
| GET    | `/api/tasks.php`                              | List tasks        |
| GET    | `/api/tasks.php?id=X`                         | Get task          |
| POST   | `/api/tasks.php`                              | Create task       |
| PUT    | `/api/tasks.php?id=X`                         | Update task       |
| PUT    | `/api/tasks.php?id=X&action=update-status`    | Update status     |
| DELETE | `/api/tasks.php?id=X`                         | Delete task       |

### Reports

| Method | Endpoint                             | Description    |
|--------|--------------------------------------|----------------|
| GET    | `/api/reports.php`                   | List reports   |
| GET    | `/api/reports.php?id=X`              | Get report     |
| POST   | `/api/reports.php`                   | Create report  |
| PUT    | `/api/reports.php?id=X`              | Update report  |
| POST   | `/api/reports.php?action=checkin`    | Check in       |
| POST   | `/api/reports.php?action=checkout`   | Check out      |

### GPS

| Method | Endpoint                              | Description                |
|--------|---------------------------------------|----------------------------|
| POST   | `/api/gps.php`                        | Log coordinates            |
| GET    | `/api/gps.php?employee_id=X`          | Employee location history  |
| GET    | `/api/gps.php?task_id=X`              | GPS trail for task         |
| GET    | `/api/gps.php?action=current`         | All active employee locs   |

### Dashboard

| Method | Endpoint                                       | Description          |
|--------|------------------------------------------------|----------------------|
| GET    | `/api/dashboard.php`                           | Dashboard summary    |
| GET    | `/api/dashboard.php?action=stats`              | Detailed stats       |
| GET    | `/api/dashboard.php?action=employee-locations` | Map data             |

### Analytics

| Method | Endpoint                                         | Description           |
|--------|--------------------------------------------------|-----------------------|
| GET    | `/api/analytics.php?action=performance`          | Employee stats        |
| GET    | `/api/analytics.php?action=delays`               | Delay analysis        |
| GET    | `/api/analytics.php?action=bottlenecks`          | AI bottleneck report  |
| GET    | `/api/analytics.php?action=predict-delay`        | AI delay prediction   |
| GET    | `/api/analytics.php?action=employee-score`       | AI employee scores    |

### Alerts

| Method | Endpoint                              | Description         |
|--------|---------------------------------------|---------------------|
| GET    | `/api/alerts.php`                     | List alerts         |
| GET    | `/api/alerts.php?id=X`                | Get alert           |
| PUT    | `/api/alerts.php?id=X&action=read`    | Mark as read        |
| PUT    | `/api/alerts.php?action=read-all`     | Mark all as read    |
| DELETE | `/api/alerts.php?id=X`                | Delete alert        |

### Uploads

| Method | Endpoint                          | Description             |
|--------|-----------------------------------|-------------------------|
| POST   | `/api/uploads.php`                | Upload file             |
| GET    | `/api/uploads.php?report_id=X`    | List files for report   |

---

## Security Features

- **PDO prepared statements** for all SQL queries
- **bcrypt** password hashing (`password_hash`/`password_verify`)
- **Bearer token** authentication with expiry
- **Input validation** on all endpoints (server-side + client-side)
- **File upload validation** (type, MIME, size)
- **CORS headers** on all API endpoints
- **Activity logging** for audit trails

---

## Screenshots

_Add screenshots here after setup._

| Dashboard | Tasks | Analytics |
|-----------|-------|-----------|
| ![Dashboard](assets/screenshots/dashboard.png) | ![Tasks](assets/screenshots/tasks.png) | ![Analytics](assets/screenshots/analytics.png) |
