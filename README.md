# OpsMan – Logistics Operations Management System

> Complete logistics management system for shipments, customs, warehouse, field operations, and transit tracking.

---

## Architecture

```
opsman/
├── frontend/          # HTML5 + Vanilla JS single-page-like UI
│   ├── css/           # Shared stylesheet
│   └── js/            # Page-specific JS modules + app.js (shared)
├── backend/
│   ├── api/           # PHP REST endpoints
│   ├── config/        # DB connection & app config
│   ├── middleware/     # Auth (Bearer token) middleware
│   ├── models/        # PDO-based data models
│   └── utils/         # Response & Validator helpers
├── database/
│   ├── schema.sql     # Full MySQL schema
│   └── seed.sql       # Demo data with all user roles
└── ai-service/        # Python Flask ML micro-service
    ├── app.py
    ├── models/        # delay_predictor, performance_analyzer
    └── utils/
```

---

## Roles

| Role               | Description                                      |
|--------------------|--------------------------------------------------|
| admin              | Full access to all modules                       |
| operations_manager | Full access except destructive deletes           |
| customs_officer    | Manages customs declarations                     |
| warehouse_officer  | Manages warehouse records and inventory          |
| field_employee     | Submits task reports, GPS check-in/out           |
| field_agent        | Cross-border transit supervision                 |
| accountant         | Financial/accounting read access                 |

---

## Prerequisites

- PHP 8.1+
- MySQL 5.7+ (or MariaDB 10.5+)
- Apache 2.4+ with `mod_rewrite`
- Python 3.9+ (for AI service)
- Composer (optional)

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/your-org/opsman.git
cd opsman
```

### 2. Database setup

```bash
mysql -u root -p << 'SQL'
CREATE DATABASE IF NOT EXISTS opsman CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL

mysql -u root -p opsman < database/schema.sql
mysql -u root -p opsman < database/seed.sql
```

### 3. Backend configuration

Edit `backend/config/config.php` and `backend/config/database.php` with your DB credentials.

### 4. Upload directory

```bash
mkdir -p backend/uploads
chmod 775 backend/uploads
```

### 5. Apache Virtual Host

```apache
<VirtualHost *:80>
    ServerName opsman.local
    DocumentRoot /var/www/opsman/frontend

    Alias /api /var/www/opsman/backend/api

    <Directory /var/www/opsman/backend/api>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    <Directory /var/www/opsman/frontend>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable and restart:

```bash
a2enmod rewrite
a2ensite opsman
systemctl restart apache2
```

### 6. AI Service (optional)

```bash
pip3 install -r ai-service/requirements.txt
python3 ai-service/app.py &
```

Or use a systemd service file.

---

## Modules

| Module       | Frontend Page      | API Endpoint            |
|--------------|--------------------|-------------------------|
| Auth         | index.html         | /api/auth.php           |
| Dashboard    | dashboard.html     | /api/dashboard.php      |
| Shipments    | shipments.html     | /api/shipments.php      |
| Customs      | customs.html       | /api/customs.php        |
| Warehouses   | warehouses.html    | /api/warehouses.php     |
| Transit      | transit.html       | /api/transit.php        |
| Tasks        | tasks.html         | /api/tasks.php          |
| Employees    | employees.html     | /api/employees.php      |
| Reports      | reports.html       | /api/reports.php        |
| Analytics    | analytics.html     | /api/analytics.php      |
| Alerts       | alerts.html        | /api/alerts.php         |
| GPS          | employee-portal    | /api/gps.php            |
| Uploads      | —                  | /api/uploads.php        |

---

## API Endpoints

### Auth (`/api/auth.php`)
| Method | Params         | Description       |
|--------|----------------|-------------------|
| POST   | action=login   | Login             |
| POST   | action=logout  | Logout            |
| GET    | action=me      | Get current user  |

### Shipments (`/api/shipments.php`)
| Method | Params                   | Description             |
|--------|--------------------------|-------------------------|
| GET    | page, per_page, status, search | List shipments    |
| GET    | id=X                     | Get single shipment     |
| POST   |                          | Create (manager/admin)  |
| PUT    | id=X                     | Update (manager/admin)  |
| DELETE | id=X                     | Delete (admin only)     |

### Customs (`/api/customs.php`)
| Method | Params                   | Description              |
|--------|--------------------------|--------------------------|
| GET    | page, per_page, status, search | List declarations  |
| GET    | id=X                     | Get single declaration   |
| POST   |                          | Create (customs+)        |
| PUT    | id=X                     | Update                   |
| PUT    | id=X&action=update-status | Status update only      |
| DELETE | id=X                     | Delete (admin only)      |

### Warehouses (`/api/warehouses.php`)
| Method | Params                           | Description              |
|--------|----------------------------------|--------------------------|
| GET    | page, per_page, status, search   | List warehouses          |
| GET    | id=X                             | Get single warehouse     |
| GET    | action=records&warehouse_id=X    | Get warehouse records    |
| POST   |                                  | Create warehouse (mgr+)  |
| POST   | action=record                    | Add warehouse record     |
| PUT    | id=X                             | Update warehouse         |
| DELETE | id=X                             | Delete (admin only)      |

### Transit (`/api/transit.php`)
| Method | Params                    | Description             |
|--------|---------------------------|-------------------------|
| GET    | page, per_page, status, search | List transit records |
| GET    | id=X                      | Get single record       |
| POST   |                           | Create (mgr/agent/admin)|
| PUT    | id=X                      | Update                  |
| PUT    | id=X&action=update-status | Status + location update|
| DELETE | id=X                      | Delete (admin only)     |

### Tasks (`/api/tasks.php`)
| Method | Params     | Description             |
|--------|------------|-------------------------|
| GET    | filters    | List tasks              |
| GET    | id=X       | Get single task         |
| POST   |            | Create (manager/admin)  |
| PUT    | id=X       | Update                  |
| DELETE | id=X       | Delete (admin only)     |

### Dashboard (`/api/dashboard.php`)
| Method | Params                   | Description              |
|--------|--------------------------|--------------------------|
| GET    |                          | Dashboard summary        |
| GET    | action=stats             | Detailed statistics      |
| GET    | action=employee-locations | GPS map data            |

---

## Default Test Credentials

| Username      | Password      | Role               |
|---------------|---------------|--------------------|
| admin         | Admin@123     | admin              |
| manager1      | Manager@123   | operations_manager |
| employee1     | Employee@123  | field_employee     |
| customs1      | Admin@123     | customs_officer    |
| warehouse1    | Admin@123     | warehouse_officer  |
| accountant1   | Admin@123     | accountant         |
| agent1        | Admin@123     | field_agent        |

---

## VPS / Public Deployment

1. **Document Root**: Point Apache `DocumentRoot` to `frontend/` OR place the `opsman/` directory under `public_html/`.
2. **Backend path**: Backend runs at `public_html/opsman/backend/api/`.
3. **API Base URL**: Update `API_BASE_URL` in `frontend/js/app.js` to your actual domain, e.g.:
   ```js
   const API_BASE_URL = 'https://yourdomain.com/opsman/backend/api';
   ```
4. **Uploads directory**: Ensure `backend/uploads/` has write permissions:
   ```bash
   chmod 775 backend/uploads
   chown www-data:www-data backend/uploads
   ```
5. **mod_rewrite**: Enable and restart:
   ```bash
   a2enmod rewrite && systemctl restart apache2
   ```
6. **AI service**: Run as a background process or systemd service:
   ```bash
   python3 ai-service/app.py
   # or
   nohup python3 ai-service/app.py > /var/log/opsman-ai.log 2>&1 &
   ```
