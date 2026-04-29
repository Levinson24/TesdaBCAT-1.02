# QUICK START GUIDE
## TESDA-BCAT Grade Management System

### ⚡ 5-Minute Setup

#### Step 1: Requirements Check
- ✅ XAMPP/WAMP installed
- ✅ MySQL running
- ✅ PHP 7.4+

#### Step 2: Installation
1. **Extract Files**
   - Place `TesdaBCAT-1.02` folder in `C:\xampp\htdocs\`

2. **Create Database**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Click "Import"
   - Select `database_schema.sql`
   - Click "Go"

3. **Configure**
   - Open `config/database.php`
   - Verify password: (usually empty `''` or `Hiddenidentity10`)
   - Change if different in `config/database.php`

4. **Access System**
   - Open browser: http://localhost/TesdaBCAT-1.02
   - Login with:
     - Username: `admin`
     - Password: `password`

#### Step 3: First Steps
1. Change admin password
2. Create registrar and department head accounts
3. Create instructor account
4. Add colleges and diploma programs
5. Add courses/subjects
6. Create class sections
7. Enroll students
8. Submit grades

### 🎯 Key URLs
- **Login**: http://localhost/TesdaBCAT-1.02/
- **Admin**: http://localhost/TesdaBCAT-1.02/admin/dashboard.php
- **Registrar**: http://localhost/TesdaBCAT-1.02/registrar/dashboard.php
- **Dept Head**: http://localhost/TesdaBCAT-1.02/dept_head/dashboard.php
- **Instructor**: http://localhost/TesdaBCAT-1.02/instructor/dashboard.php
- **Student**: http://localhost/TesdaBCAT-1.02/student/dashboard.php

### 🆘 Common Issues

**Can't login?**
- Check database is created
- Verify credentials in database.php

**Database error?**
- Start MySQL in XAMPP Control Panel
- Check database name is `tesda_db`

**Page not found?**
- Verify folder is in htdocs
- Start Apache in XAMPP Control Panel

### 📱 Test Accounts (After Setup)

Create these for testing:

**Admin**
- Username: admin
- Password: admin123
- Role: Administrator

**Registrar** (Create via admin)
- Username: registrar
- Password: (set your own)
- Role: Registrar

**Instructor** (Create via admin)
- Username: instructor1
- Password: (set your own)
- Role: Instructor

**Dept Head** (Create via admin)
- Username: dept_head1
- Password: (set your own)
- Role: Department Head

**Student** (Create via registrar/admin)
- Username: student1
- Password: (set your own)
- Role: Student

### 🎨 Features Overview

**Admin Can:**
- Manage all users
- View system statistics
- Configure settings
- Access audit logs

**Registrar Can:**
- Manage students
- Manage enrollments
- Approve grades
- Generate transcripts

**Instructor Can:**
- View assigned classes
- Submit grades
- Export class lists

**Student Can:**
- View own grades
- Check GPA
- Download transcript

### 📊 Sample Workflow

1. **Admin** creates instructor and registrar accounts
2. **Registrar** creates student accounts
3. **Registrar** creates course (e.g., "Math 101")
4. **Registrar** creates class section and assigns instructor
5. **Registrar** enrolls students in section
6. **Instructor** submits midterm grades
7. **Instructor** submits final grades
8. **Registrar** approves grades
9. **Student** views approved grades
10. **Student** downloads transcript

### 🔐 Security Notes

- Change default password immediately
- Use strong passwords (8+ chars, mixed case, numbers)
- Regular database backups
- Keep audit logs for accountability

### 💾 Backup Command
```bash
mysqldump -u root -pHiddenidentity10 tesda_db > backup.sql
```

### 📞 Need Help?

Check:
1. README.md (detailed documentation)
2. Database schema comments
3. Code comments in PHP files

---

**You're ready to go! 🚀**

Login at: http://localhost/TesdaBCAT-1.02/
