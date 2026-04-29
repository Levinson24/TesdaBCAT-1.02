# TESDA-BCAT Grade Management System Documentation

## 1. Implementation Plan

### System Overview
The TESDA-BCAT Grade Management System is a role-based web application for managing academic records. It is built as a monolithic PHP application using a MySQL database and a Bootstrap-based frontend.

### Technology Stack
- PHP Engine: Version 7.4 or higher
- Database: MySQL 5.7 or higher / MariaDB
- Web Server: Apache 2.4+
- Frontend: HTML5, CSS3, JavaScript (jQuery), Bootstrap 5

### Implementation Details
- Authentication: Bcrypt password hashing.
- Authorization: Role-Based Access Control (Admin, Registrar, Dept Head, Instructor, Student).
- Database Logic: Utilizes SQL Views for reports and Stored Procedures for grade calculations.
- Data Integrity: Prepared statements (PDO/MySQLi) used throughout to prevent SQL injection.
- Security: Session-based login with role-level folder protection.
- UI/UX Design System: High-fidelity **Glassmorphism** architecture across administrative and student portals, utilizing glass-panel design tokens, sleek dark modes, and dynamic micro-animations.
- Student Profile Architecture: Implements a "Virtual Identity Hub" with structural grid layouts, premium banner/avatar badge systems, and comprehensive demographic data integration.

---

## 2. Deployment Diagram

+-----------------------------------------------------------+
|                                                           |
|       [ Client Side: Browsers (Desktop/Mobile) ]          |
|                               |                           |
|                  (HTTP / HTTPS Requests)                  |
|                               v                           |
|    +-------------------------------------------------+    |
|    |                                                 |    |
|    |      [ Server Environment (Apache / PHP) ]      |    |
|    |                                                 |    |
|    |  +-----------------+       +-----------------+  |    |
|    |  |  PHP Execution  | <---> | SQL Database    |  |    |
|    |  |  Engine         |       | (tesda_db)      |  |    |
|    |  +--------|--------+       +-----------------+  |    |
|    |           |                                     |    |
|    |  +--------v-----------------------------------+ |    |
|    |  | FileSystem (uploads/, exports/, logs/)    | |    |
|    |  +--------------------------------------------+ |    |
|    +-------------------------------------------------+    |
|                                                           |
+-----------------------------------------------------------+

### Summary of Diagram Components:
1. Client Side: End-users access the system via a web browser.
2. Server Environment: The web server (Apache) processes requests using the PHP Engine.
3. Database: The SQL Database (MySQL) stores and retrieves project data.
4. FileSystem: Local storage for user uploads, PDF exports, and system logs.

---

## 3. Deployment Steps
1. Server Setup: Install XAMPP (Windows) or LAMP (Linux) with recommended PHP/MySQL versions.
2. File Placement: Copy project files to the web root directory (e.g., htdocs/).
3. Database Migration: Import 'database_schema.sql' using MySQL client or phpMyAdmin.
4. Configuration: Update 'config/database.php' with local database credentials (defaults to localhost:3306).
5. Permissions: Ensure 'exports/' and 'uploads/' directories have write permissions.
6. Verification: Access the system via http://localhost:8080/ and test core workflows.

---

## 4. Troubleshooting & XAMPP Repair

If MySQL or Apache fails to start in XAMPP (common due to PID lock files or improper shutdowns), use the provided repair utility:

### 🛠️ XAMPP Repair Utility
1. Located in the project root as `fix_xampp.bat`.
2. **How to use**: Right-click `fix_xampp.bat` and select **Run as Administrator**.
3. **What it does**:
   - Stops conflicting `mysqld` and `httpd` processes.
   - Clears stale `.pid` and `.err` lock files from XAMPP directories.
   - Checks for port conflicts (**8080, 4433, 3306**).
   - Attempts to restart services in standalone mode.

> [!TIP]
> Always try running the XAMPP Control Panel as Administrator first if services fail to start.

---

## 5. Manual Repair Guide (Alternative Methods)

If the repair script does not solve the issue, follow these manual procedures:

### A. Port Conflict Resolution (When ports 80 or 3306 are blocked)
1.  **For Apache (Port 8080/4433)**:
    - **STATUS**: Set to Port **8080** and SSL Port **4433**.
    - Access: `http://localhost:8080/`.
2.  **For MySQL (Port 3306)**:
    - **STATUS**: Reverted to Port **3306** (Standard XAMPP).
    - Note: `config/database.php` uses `localhost`.

### B. MySQL Database Recovery (Fixing "Error: MySQL shutdown unexpectedly")
1.  Go to `C:\xampp\mysql`.
2.  Rename the `data` folder to `data_old`.
3.  Create a **NEW** folder named `data`.
4.  Copy everything from `C:\xampp\mysql\backup` into the new `data` folder.
5.  Copy your specific database folders (e.g., `tesda_db`) from `data_old` into the new `data` folder.
6.  Copy the `ibdata1` file from `data_old` into the new `data` folder (Replace the existing one).
7.  Start MySQL again.

### C. World Wide Web Publishing Service (Windows Only)
If Apache won't start on port 80, a common culprit is a Windows service.
1.  Press `Win + R`, type `services.msc`, and press Enter.
2.  Find **World Wide Web Publishing Service**.
3.  Right-click it and select **Stop**, then set its **Startup Type** to **Manual** or **Disabled**.

### D. System Health Check (Diagnostic Tool)
If the system still won't run, use the diagnostic tool:
1.  Open your browser to `http://localhost/TesdaBCAT-1.02/health_check.php`.
2.  Review the checklist for:
    - **PHP Environment**: Checks if required extensions like `mysqli` are enabled.
    - **Database Connectivity**: Verifies if the `tesda_db` database and all required tables exist.
    - **Folder Permissions**: Ensures `uploads`, `exports`, and `logs` are writable.
3.  Follow the suggestions provided in the red "❌" sections.

### E. How to Set a Static IP (Permanent Remote Access)
To prevent your computer's IP address from changing, set a Static IP:
1.  Open **Settings** > **Network & Internet**.
2.  Click on your connection (e.g., **Wi-Fi** or **Ethernet**).
3.  Click **Edit** next to **IP Assignment** (it usually says "DHCP").
4.  Change the dropdown from **Automatic (DHCP)** to **Manual**.
5.  Turn on **IPv4**.
6.  **Fill in the fields** (use the info from `ipconfig`):
    - **IP Address**: Your current IP (e.g., `192.168.1.10`).
    - **Subnet Mask**: `255.255.255.0`.
    - **Gateway**: `192.168.1.1` (your router).
    - **DNS**: `8.8.8.8` and `8.8.4.4`.
7.  Click **Save**.

### F. Database Backup Utility
To ensure your data is safe, use the automated backup tool:
1.  Located in the project root as `backup_db.bat`.
2.  **How to use**: Right-click `backup_db.bat` and select **Run as Administrator**.
3.  **Result**: A new `.sql` file will be created in the `backups/` folder with the current date and time (e.g., `tesda_db_backup_2026-03-21_1419.sql`).
4.  **Note**: This tool is configured to use Port **3306** so it works with our restored setup.

### G. Web-Based Database Backup (Remote)
Administrators can now backup the database directly from the system:
1.  Log in as **Administrator**.
2.  Navigate to **System Settings**.
3.  Scroll down to the **Database Management** section.
4.  Click **Download Database Backup**.
5.  **Result**: Your browser will immediately download a `.sql` backup file to your computer.

---

## 6. External Tools & Networking

### A. MySQL Workbench Connection
To manage the database visually, use the following connection settings:
- **Connection Name**: XAMPP-Project
- **Hostname**: `127.0.0.1`
- **Port**: `3306`
- **Username**: `root`
- **Password**: (Leave Blank)

### B. Local Network Access (LAN)
Access the system from other devices on the same Wi-Fi:
1. **LAN URL**: `http://192.168.1.30:8080/`
2. **Firewall Requirement**: Run the following as Administrator in PowerShell:
   ```powershell
   netsh advfirewall firewall add rule name="XAMPP Apache (8080)" dir=in action=allow protocol=TCP localport=8080
   ```

### C. Online Deployment (Tunneling)
To make the system accessible online instantly:
1. Run the following command in the project terminal:
   ```bash
   npx localtunnel --port 8080 --subdomain tesdabcat
   ```
2. **Tunnel Password**: When prompted, enter your Public IP. You can find it by visiting `https://loca.lt/mytunnelpassword`.
3. Share the provided `.loca.lt` URL with external users.

> [!CAUTION]
> **SECURITY**: Before using Online Access, ensure you have set a MySQL root password via phpMyAdmin to protect your data.

---

## 7. Real-time User Tracking (NEW)

The system now features a live **Active Now** monitor for Administrators:

1. **Activity Detection**: The system automatically tracks the "last activity" of all logged-in users.
2. **Dashboard Widget**: A real-time card on the Admin Dashboard displays:
   - Username and Role of online users.
   - Current session duration.
   - Live "Online" pulse indicator.
3. **Automatic Refresh**: The widget updates itself every 15 seconds without needing a page reload.

---

## 8. Automation & Persistence (Always Online)

To ensure the system stays online even after a computer restart:

### A. Autostart Tunnel (Silent Mode)
1. Use the provided `start_tunnel.bat` file in the project root.
2. The script is configured to run silently using VBScript to prevent unnecessary terminal windows from appearing.
3. Task Scheduler Setup:
   - Create a Task triggered "At Log On".
   - Set the Action to start `start_tunnel.bat`.
   - **Important**: Check "Run with highest privileges" in Task properties.

### B. XAMPP Background Service
1. Open XAMPP Control Panel as **Administrator**.
2. Click **Config** and check **Apache** and **MySQL** under "Autostart of modules".
3. To run as a true Windows service:
   - Click the red **X** button next to Apache and MySQL modules.
   - Confirm the installation as a service (icon will turn into a green checkmark).
4. Save and close.

---

## 9. Institutional Branding Standards

The system maintains a professional and unified institutional identity across all portals.

1. **Browser Tab Title Format**: Standardized across the entire platform as `[Page Name] - TESDA-BCAT GMS`.
2. **Official Favicon**: The institutional BCAT logo is consistently applied to all administrative portals, public verification pages, and official document print templates.
3. **Typography & Aesthetics**: Modern fonts (Inter/Roboto/Outfit) paired with curated, harmonious color palettes to ensure a premium, state-of-the-art user experience.

---

## 10. Academic & Grading Standards

The system is fully compliant with modern educational standards for grading and document generation.

1. **CHED-Compliant Grading System**: Standardized grading scales that align with Commission on Higher Education (CHED) requirements for higher education institutions.
2. **Standardized Grades**: Transitioned from legacy values to a standardized grade architecture (e.g., numeric 1.00 - 5.00 or percentage-based systems).
3. **Professional Templates**: Upgraded, high-density print layouts for official documents:
   - **Transcript of Records (TOR)**: Professional, multi-column layouts with institutional branding.
   - **Certificate of Registration (COR)**: Clean, high-performance templates for student registration.

---

## 11. Student Management Updates

New fields and features have been integrated into the student management lifecycle:

1. **Admission Date**: Each student record now tracks the official date of admission.
2. **Admission Editing**: This field is integrated into both the "Add Student" and "Update Student" workflows for comprehensive record management.
3. **Data Density**: Registry tables have been optimized to display admission dates and other key demographic information in a clean, professional layout.

---

## 12. Default System Credentials

For initial setup and administrative access, the following default credentials have been configured:

| Role | Username | Password | Access Level |
| :--- | :--- | :--- | :--- |
| **Super Administrator** | `super_admin` | `Super_Admin-123` | Full System Access |
| **Default Admin** | `admin` | `admin123` | General Administration |

> [!WARNING]
> **SECURITY FIRST**: It is highly recommended to change these passwords immediately after the first login via the **User Profile > Account Settings** section to maintain system integrity.

