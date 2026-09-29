# ccc_eskuelog
# 

engineered and maintained by the **MISD Team**.

---
## Technical Specifications & Stack

* **Core Engine:** PHP 7.4 – 8.x
* **Database Target:** MySQL / MariaDB
* **Architecture:** Modular MVC / Front-Controller layout with custom `.env` runtime parser
* **Default Timezone:** `Asia/Manila` (`UTC+8`)
* **Max Payload Limit:** `20MB`
* **Session Strategy:** AES-256-CBC encrypted dynamic sessions
* **Federated Auth Partner:** `e-GURO++`
---

## System Architecture & Directory Map

```text
├── .env                  # Environment configurations (Keep out of source control)
├── .htaccess             # Root URL rewrite & routing rules
├── config/
│   └── config.php        # Core runtime bootstrap, constants, & route matrices
├── public/
│   ├── .htaccess         # Public directory rewrite rules
│   ├── admin/            # Admin portal workspace & access control
│   │   └── .htaccess     # Admin directory security rules
│   ├── assets/           # Frontend assets (CSS, JS, vendor libraries)
│   ├── upload/           # Public file storage
│   │   ├── files/        # Uploaded document assets
    │   ├── guide/        # System import guides and instructional files
│   │   ├── images/       # Logos, banners, default fallbacks, slider images
│   │   └── sections/     # Page layout section image assets
│   └── index.php         # Primary web entry point
├── src/
│   ├── Api/              # API helpers and system dispatchers
│   ├── Common/           # Global helpers, layout managers, & session handlers
│   ├── Controllers/      # Core logic, auth checkers, and routing handlers
│   ├── Database/         # Database connection routines (`connect.php`)
│   ├── Handlers/         # Request execution and process handlers
│   ├── Mail/             # SMTP mailing routines and email templates
│   ├── Route/            # URL dispatching and system routing logic
│   └── Views/            # Master layouts, navigation, and error pages
└── storage/
    ├── csv/              # Batch import CSV templates & temporary storage
    ├── logs/             # Secure runtime log directory (`php-error.log`)
    └── txt/              # System text logs and summary reports


