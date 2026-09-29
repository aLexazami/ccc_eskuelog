# ** Environment Setup Guide**

This guide provides technical instructions for configuring the local development environment (`DEV`) and preparing the configuration for deployment (`PROD`) for ****.

---

## **Architecture & System Requirements**

* **Core Engine:** PHP 7.4 – 8.x
* **Database Target:** MySQL 8.0+ / MariaDB 10.4+ (`e_inventory`)
* **Architecture:** Modular MVC / Front-Controller layout with a custom `.env` runtime parser
* **Default Timezone:** `Asia/Manila` (`UTC+8`)
* **Max Payload Limit:** `20MB`
* **Session Strategy:** AES-256-CBC encrypted dynamic sessions (`dev_e_app_session` / `e_app_session`)
* **Web Server:** Apache 2.4+ or Nginx with URL rewrite (`mod_rewrite`) enabled

---

## **Environment Configuration (`/.env`)**

Create a file named `.env` in the root directory of your project (`/.env`) and copy the baseline configuration below:

```ini
# ======================================================================
# 
# Location: /.env
# ======================================================================

# SYSTEM ACCESS NAME
SYSTEM_ACCESS_NAME = 

# ----------------------------------------------------------------------
# SYSTEM_FLAG toggles global diagnostic layers. Options: DEV | PROD
# DEV  - Enables local subfolder setups, verbose error printing, and diagnostic logs
# PROD - Disables onscreen error outputs, enables secure system silence
SYSTEM_FLAG = 

# ----------------------------------------------------------------------
# FILE VERSION
FILE_VERSION = 

# ----------------------------------------------------------------------
# PERSISTENCE STORAGE ENGINE CREDENTIALS
DB_HOST = 
DB_USER = 
DB_PASS = 
DB_NAME = 

# ----------------------------------------------------------------------
# SYSTEM RUNTIME & SECURITY
APP_TIMEZONE = Asia/Manila
MAX_PAYLOAD_LIMIT = 20M

# ----------------------------------------------------------------------
# NETWORK REWRITE & LOCAL SUBFOLDER OFFSETS

# DEVELOPMENT DOMAIN ANCHORS
LOCAL_SUBFOLDER = 
API_LOCAL_SUBFOLDER = ''

# PRODUCTION DOMAIN ANCHORS
PRODUCTION_DOMAIN = 
API_PRODUCTION_DOMAIN = 

# Session Cookie Identity Name Configuration
SESSION_NAME = 

# ----------------------------------------------------------------------
# SMTP SECURE MAIL
SMTP_USER = 
SMTP_PASS = 

```

## **Environment Variable Reference**
    **Variable Name**	    **Category**	    **Description & Functional Purpose**
    * SYSTEM_ACCESS_NAME      System Core         System branding and global identification label.  
    * SYSTEM_FLAG             System Core         Global toggle. DEV enables diagnostic logs; PROD secures system silence.  
    * FILE_VERSION            System Core         Revision identifier for configuration schema compatibility.  
    * DB_HOST                 Database            Hostname or IP address of the MySQL/MariaDB server.  
    * DB_USER                 Database            Database user account identifier for persistent storage.  
    * DB_PASS                 Database            Authentication password for the storage engine user.  
    * DB_NAME                 Database            Target database schema name (e_inventory) for system operation.  
    * APP_TIMEZONE            Runtime             Default system time zone (Asia/Manila).  
    * MAX_PAYLOAD_LIMIT       Runtime             Maximum allowed upload and request payload size (20M).  
    * LOCAL_SUBFOLDER         Routing             Local server subfolder offset relative to localhost root (dev_e_app).
    * API_LOCAL_SUBFOLDER     Routing             Relative subfolder path for local API endpoints.
    * PRODUCTION_DOMAIN       Routing             Canonical web URL for production deployment.
    * API_PRODUCTION_DOMAIN   Routing             Base URL for production API routes.
    * SESSION_NAME            Security            Dynamic cookie identifier for encrypted session tracking.  
    * SMTP_USER               Mailer              Username for transactional SMTP email transmission.
    * SMTP_PASS               Mailer              Password or access token for SMTP service.

## **Local Initialization & Installation**
* **Directory Setup**: Place the repository under your local server root matching LOCAL_SUBFOLDER:
    * XAMPP: C:/xampp/htdocs/dev_e_app
    * WAMP: C:/wamp64/www/dev_e_app
    * LAMP: /var/www/html/dev_e_app
    * MAMP: /Applications/MAMP/htdocs/dev_e_app

* **Database Setup**: 
  1. Open your database management tool (phpMyAdmin, DBeaver, MySQL Workbench)[cite: 3].
  2. Create the target schema:
    ```sql
    CREATE DATABASE e_eskuelog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ```
  3. Import your database SQL seed directly into `e_inventory`[cite: 3].