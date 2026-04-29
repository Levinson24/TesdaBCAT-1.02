# TESDA-BCAT Grade Management System
## 📋 Comprehensive User Manual

Welcome to the official user manual for the TESDA-BCAT Grade Management System. This guide provides step-by-step instructions for each user role to ensure a smooth academic workflow.

---

## 🗂️ Table of Contents
1. [Administrator Guide](#-1-administrator-guide)
2. [Registrar Guide](#-2-registrar-guide)
3. [Department Head Guide](#-3-department-head-guide)
4. [Instructor Guide](#-4-instructor-guide)
5. [Student Guide](#-5-student-guide)

---

## 🛡️ 1. Administrator Guide
*Focus: System security, user governance, and global configuration.*

### 1.1 Managing Users
- **Add User**: `User Management` > `+ Add New User`. Define roles (Admin, Registrar, Instructor, Student).
- **Edit/Reset**: Use the **Pencil icon** to update credentials or the **Trash icon** to revoke access.
- **Audit**: Visit `Audit Logs` to monitor all system-wide actions for security compliance.

### 1.2 Global Settings
- Navigate to `System Settings` to update the **School Name**, **Current Academic Year**, and **Grading Scale**.
- Changes here reflect instantly on all student transcripts.

---

## 📝 2. Registrar Guide
*Focus: Academic setup, enrollment, and grade verification.*

### 2.1 Academic Infrastructure
- **Colleges & Programs**: Define the institutional hierarchy under `Colleges` and `Diploma Programs`.
- **Courses**: Define subjects and link them to specific **Programs** under `Courses` > `+ Add Course`.
- **Sections**: Create class groups under `Class Sections`. **Assign an Instructor** and verify the semester/year.

### 2.2 Student Lifecycle
- **Enrollment**: Link students to sections via `Enrollments` > `+ New Enrollment`.
- **Grade Approval**: Review submitted grades in `Grade Approvals`. Only **Approved** grades appear on official transcripts.
- **Transcripts**: Search for a student in `Transcripts` and click `Generate PDF` for the official Record of Grades.

---

## 🏛️ 3. Department Head Guide
*Focus: Departmental oversight, instructor loads, and student tracking.*

### 3.1 Faculty Management
- **Instructor Load**: Monitor teaching assignments and unit counts per instructor in `Instructor Load`.
- **Schedules**: Review student schedules and section distribution to ensure balance.

### 3.2 Academic Monitoring
- **Student Profiles**: Access detailed student profiles, including enrollment history and uploaded documents in `Student Management`.
- **Reports**: Generate department-specific reports on student progress and faculty performance.

---

## 👨‍🏫 4. Instructor Guide
*Focus: Student evaluation and grade submission.*

### 3.1 Grading Workflow
1. **View Classes**: Check `My Classes` for your assigned sections.
2. **Enter Grades**: Click `View Students` > `Enter Grades`. Input numeric values for Midterm and Finals.
3. **Submit**: Click `Submit Grades`. 
   > [!NOTE]
   > Grades remain "Pending" until verified by the Registrar. Once approved, they are locked for editing.

### 3.2 Offline Records
- Use the **Export to Excel/CSV** button in `Grade History` to maintain personal backups of your class records.

---

## 🎓 5. Student Guide
*Focus: Progress tracking and self-service records.*

### 4.1 Academic Dashboard
- **GWA**: Your General Weighted Average is calculated automatically and shown on your main dashboard.
- **My Grades**: View a detailed breakdown of your **Approved** scores.

### 4.2 Official Records
- Navigate to `My Transcript` to download a print-ready PDF of your academic history.

---

## 🛠️ 6. System Maintenance & Security
*Focus: Long-term system stability and data protection.*

### 6.1 Database Security (Crucial)
- **Restricted Access**: The system uses a dedicated user (`tesda_app_user`) to connect to the database. Do **not** revert to the MySQL `root` account for the live application.
- **Hidden Dashboard**: For security, the standard `phpmyadmin` URL has been obfuscated.
  - **Private URL**: `http://localhost:8080/portal_db_admin_2026` 
  - To change this again, modify the `Alias` line in `C:\xampp\apache\conf\extra\httpd-xampp.conf`.

### 6.2 Filesystem Protection
- **.htaccess Files**: These files in the root and sensitive directories (like `config/`) are responsible for blocking unauthorized browser access to your logic and passwords.
- **Never delete or move these files**, especially when deploying to a new server or using a tunnel.

### 6.3 Backup Procedures
- Regularly export your database from the private dashboard.
- Back up the `uploads/` directory, as it contains all important student documents.

---

## ❓ Support
- **Login Issues?** Contact your System Administrator to reset your password.
- **Grade Discrepancies?** Contact your Registrar to verify if the grade has been formally approved.

---
*Thank you for helping us maintain academic excellence at TESDA-BCAT!*
