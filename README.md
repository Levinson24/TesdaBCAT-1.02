# TESDA-BCAT Grade Management System (V1.0.3)
## Design and Implementation of a Local Role-Based Grade Management System

### 📋 System Overview
A comprehensive web-based grade management system for TESDA-BCAT (Balicuatro College of Arts and Trades) that manages student grades using role-based access control.

### 👥 User Roles & Permissions

#### 1. Administrator
- Add and manage system users
- View all grades across the system
- Control system settings
- Access audit logs
- Manage instructors and students

#### 2. Registrar & Registrar Staff
- Add, view, update, and delete users
- Manage academic hierarchy (Colleges, Programs)
- Manage subjects, class sections, and enrollments
- Validate submitted grades and generate transcripts

#### 3. Department Head
- Manage instructor assignments and loads
- Monitor student schedules within the department
- View department-specific academic reports
- Audit student profiles and progress

#### 3. Instructor
- View assigned classes
- Submit student grades (midterm and final)
- View grade submission history
- Export class grade sheets

#### 4. Student
- View own grades only
- View GWA and academic progress
- Download official transcript
- View enrollment history

---

## 🚀 Installation Instructions

### Prerequisites
- **Web Server**: Apache 2.4+ (XAMPP, WAMP, or LAMP)
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7 or higher
- **MySQL Workbench**: Latest version (optional, for database management)

### Step 1: Extract Files
1. Extract the `tesda_gms` folder
2. Place it in your web server's root directory:
   - **XAMPP**: `C:\xampp\htdocs\`
   - **WAMP**: `C:\wamp64\www\`
   - **LAMP**: `/var/www/html/`

### Step 2: Configure Database Connection
1. Open `config/database.php`
2. Verify the database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', 'Hiddenidentity10');
   define('DB_NAME', 'tesda_db');
   ```
3. Modify if your MySQL credentials are different

### Step 3: Create Database

#### Option A: Using MySQL Workbench
1. Open MySQL Workbench
2. Connect to your MySQL server
3. Go to File → Open SQL Script
4. Navigate to `tesda_gms/database_schema.sql`
5. Click Execute (Lightning bolt icon)
6. Verify that `tesda_db` database is created

#### Option B: Using phpMyAdmin
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Click "Import" tab
3. Click "Choose File" and select `database_schema.sql`
4. Click "Go" to execute

#### Option C: Using Command Line
```bash
mysql -u root -p < database_schema.sql
```

### Step 4: Set Folder Permissions
Ensure the following folders are writable:
```bash
chmod 755 tesda_gms/exports
chmod 755 tesda_gms/uploads
```

### Step 5: Access the System
1. Open your web browser
2. Navigate to: `http://localhost/tesda_gms/`
3. You should see the login page

---

## 🔐 Default Login Credentials

### Administrator Account
- **Username**: `admin`
- **Password**: `admin123`

⚠️ **Important**: Change the default password immediately after first login!

---

## 📁 Project Structure

```
tesda_gms/
├── admin/                  # Admin module
│   ├── dashboard.php
│   ├── users.php
│   ├── students.php
│   ├── instructors.php
│   ├── courses.php
│   ├── grades.php
│   ├── settings.php
│   └── reports.php
├── dept_head/              # Department Head module
│   ├── dashboard.php
│   ├── students.php
│   ├── instructors.php
│   ├── instructor_load.php
│   ├── courses.php
│   └── reports.php
├── registrar/             # Registrar module
│   ├── dashboard.php
│   ├── students.php
│   ├── instructors.php
│   ├── courses.php
│   ├── sections.php
│   ├── enrollments.php
│   ├── grades.php
│   └── transcripts.php
├── instructor/            # Instructor module
│   ├── dashboard.php
│   ├── my_classes.php
│   ├── submit_grades.php
│   ├── grade_history.php
│   └── profile.php
├── student/               # Student module
│   ├── dashboard.php
│   ├── my_grades.php
│   ├── transcript.php
│   └── profile.php
├── config/                # Configuration files
│   └── database.php
├── includes/              # Shared includes
│   ├── auth.php           # Authentication functions
│   ├── functions.php      # Common utility functions
│   ├── excel_export.php   # Excel/CSV export functions
│   ├── header.php         # Common header template
│   └── footer.php         # Common footer template
├── assets/                # Static assets
│   ├── css/
│   ├── js/
│   └── images/
├── exports/               # Generated export files
├── uploads/               # User uploaded files
├── database_schema.sql    # Database structure
├── index.php             # Login page
├── logout.php            # Logout handler
└── README.md             # This file
```

---

## 🗄️ Database Schema

### Core Tables
1. **colleges** - Top-level organizational units
2. **departments** - Academic diploma programs
3. **programs** - Specific academic programs/courses
4. **users** - User authentication and roles (inc. Dept Head)
5. **students** - Student profile information
6. **instructors** - Instructor profile information
7. **courses** - Available subjects
8. **class_sections** - Class sections with instructor assignments
9. **enrollments** - Student enrollments in sections
10. **grades** - Student grades (midterm, final, computed)
11. **system_settings** - Application settings
12. **audit_logs** - System activity logging

### Key Relationships
- Colleges → Departments (1:N)
- Departments → Programs (1:N)
- Programs → Students/Courses (1:N)
- Users → Students/Instructors (1:1)
- Instructors → Class Sections (1:N)
- Students → Enrollments (1:N)
- Enrollments → Grades (1:1)

---

## 🔧 Features

### User Management
- Create, read, update, delete users
- Role-based access control
- Password hashing for security
- Session management
- Audit logging

### Student Management
- Student profile management (including document upload)
- Enrollment tracking and GWA calculation
- Online/Offline status tracking
- Academic hierarchy mapping

### Grade Management
- Midterm and final grade entry
- Automatic grade calculation
- Multi-level approval workflow:
  1. Instructor submits grades
  2. Registrar approves/rejects
  3. Student views approved grades
- Grade history tracking

### Reporting & Export
- CSV export for transcripts
- Class grade sheet export
- Printable transcripts
- Academic reports

### Security Features
- Password hashing (bcrypt)
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- Session timeout
- Role-based authorization
- Audit trail logging

---

## 🎨 UI Features

- **Responsive Design**: Works on desktop, tablet, and mobile
- **Bootstrap 5**: Modern, clean interface
- **Font Awesome Icons**: Visual clarity
- **DataTables**: Advanced table features (sorting, searching, pagination)
- **SweetAlert2**: Beautiful alert messages
- **Color-coded badges**: Visual status indicators

---

## 📊 Grading System

### Grade Scale
- **1.00 (96-100%)** - Excellent
- **1.25 (93-95%)** - Very Good
- **1.50 (90-92%)** - Very Good
- **1.75 (87-89%)** - Good
- **2.00 (84-86%)** - Good
- **2.25 (81-83%)** - Good
- **2.50 (78-80%)** - Fair
- **2.75 (75-77%)** - Passed
- **Below 75%** - Failed
- **INC** - Incomplete

### Grade Calculation
Final Grade = (Midterm + Final) / 2

---

## 🔄 Workflow

### Grade Submission Workflow
1. **Instructor** submits grades (status: submitted)
2. **Registrar** reviews and approves/rejects (status: approved/rejected)
3. **Student** can view approved grades
4. **System** calculates GWA automatically

### Enrollment Workflow
1. **Registrar** creates class sections
2. **Registrar** enrolls students in sections
3. **Instructor** views enrolled students
4. **Instructor** submits grades for enrolled students

---

## 🛠️ Customization

### Changing School Information
Edit values in `system_settings` table:
```sql
UPDATE system_settings SET setting_value = 'Your School Name' 
WHERE setting_key = 'school_name';
```

### Adding New Users
1. Login as Admin
2. Go to "Manage Users"
3. Click "Add New User"
4. Fill in user details
5. Assign appropriate role

### Modifying Grading Scale
Edit the `getGradeRemark()` function in `includes/functions.php`

---

## 🐛 Troubleshooting

### Database Connection Error
- Verify MySQL is running
- Check database credentials in `config/database.php`
- Ensure `tesda_db` database exists

### Page Not Found (404)
- Check that files are in correct web server directory
- Verify Apache is running
- Check file permissions

### Session Errors
- Ensure `session.save_path` is writable
- Check PHP session configuration

### Export Not Working
- Verify `exports/` folder exists and is writable
- Check PHP file permissions

---

## 📝 Usage Guide

For detailed, step-by-step instructions on handling grading workflows, enrollments, and system configurations, please refer to the comprehensive [USER_MANUAL.md](USER_MANUAL.md).

### Quick Reference by Role:

#### For Administrators
1. Login with admin credentials
2. Navigate to "Manage Users" to create accounts
3. Set up courses, instructors, and students
4. Monitor system via dashboard
5. Configure settings as needed

#### For Registrars
1. Create/manage student accounts
2. Set up class sections
3. Enroll students in sections
4. Approve submitted grades
5. Generate official transcripts

#### For Instructors
1. View assigned classes
2. Check enrolled students
3. Submit midterm and final grades
4. View grade submission history

#### For Students
1. View personal grades
2. Check GWA and academic progress
3. Download official transcript
4. Print transcript for records

---

## 🔒 Security Best Practices

1. **Change Default Password** immediately after installation
2. **Use Strong Passwords** (minimum 8 characters, mixed case, numbers, symbols)
3. **Regular Backups** of database
4. **Update PHP** to latest stable version
5. **Disable Error Display** in production:
   ```php
   ini_set('display_errors', 0);
   error_reporting(0);
   ```
6. **SSL Certificate** for production deployment
7. **Regular Security Audits** via audit_logs table

---

## 📞 Support & Maintenance

### Common Tasks

#### Backup Database
```bash
mysqldump -u root -p tesda_db > backup_$(date +%Y%m%d).sql
```

#### Restore Database
```bash
mysql -u root -p tesda_db < backup_20250207.sql
```

#### Clear Audit Logs (older than 90 days)
```sql
DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

## 📋 System Requirements

### Minimum Requirements
- **Processor**: 1 GHz
- **RAM**: 2 GB
- **Storage**: 500 MB
- **Browser**: Chrome 90+, Firefox 88+, Safari 14+

### Recommended Requirements
- **Processor**: 2 GHz dual-core
- **RAM**: 4 GB
- **Storage**: 2 GB
- **Browser**: Latest version of Chrome, Firefox, or Edge

---

## 📄 License
This system was developed for TESDA-BCAT educational purposes.

## 👨‍💻 Development Info
- **Language**: Native PHP (no frameworks)
- **Database**: MySQL
- **Frontend**: Bootstrap 5, jQuery, Font Awesome
- **Version**: 1.0.3
- **Last Updated**: March 2026

---

## ✅ Testing Checklist

- [ ] Database created successfully
- [ ] Admin login works
- [ ] Can create new users
- [ ] Can create students
- [ ] Can create instructors
- [ ] Can create courses
- [ ] Can create class sections
- [ ] Can enroll students
- [ ] Instructor can submit grades
- [ ] Registrar can approve grades
- [ ] Student can view grades
- [ ] Transcript export works
- [ ] CSV export works

---

For additional support or questions, contact your system administrator.

**Thank you for using TESDA-BCAT Grade Management System!**
