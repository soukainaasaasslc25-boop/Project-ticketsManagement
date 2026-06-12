# Student Requests & Complaints Ticket Tracking System

## Overview

Student Requests & Complaints Ticket Tracking System is a web application developed to simplify communication between students and administration.

The platform allows students to submit requests and complaints, track ticket progress, upload attachments, and communicate with administrators.

Administrators can manage tickets, categories, students, and monitor the entire ticket lifecycle.

---

## Technologies Used

### Backend

* PHP 8+
* MySQL 8+
* PDO

### Frontend

* HTML5
* Tailwind CSS
* Vanilla JavaScript

### Environment

* XAMPP
* Apache
* phpMyAdmin

---

## User Roles

### Student

Students can:

* Login and logout
* Create requests
* Create complaints
* Save drafts
* Edit drafts and new tickets
* Upload attachments
* View ticket details
* Reply to tickets
* Track ticket status
* Change password

### Administrator

Administrators can:

* Manage students
* Manage tickets
* Manage categories
* Manage subcategories
* Assign tickets
* Update ticket status
* Respond to tickets
* View dashboards and statistics
* Import students from Excel

---

## Ticket Workflow

A ticket can have the following statuses:

1. Draft
2. New
3. Opened
4. In Progress
5. Completed
6. Rejected

---

## Main Features

### Authentication

* Username/password login
* Role-based access
* Session management

### Ticket Management

* Requests management
* Complaints management
* Ticket tracking

### Categories

* Request categories
* Complaint categories
* Subcategories

### Attachments

Supported file types:

* PDF
* DOCX
* XLSX
* JPG
* PNG

---

## Project Structure

```text
pfe/
│
├── admin/
├── student/
├── auth/
├── config/
├── uploads/
├── assets/
│
├── index.php
└── README.md
```

---

## Database

Main tables:

* users
* categories
* subcategories
* tickets
* ticket_responses
* ticket_attachments
* remember_tokens

---

## Installation

### 1. Clone the project

```bash
git clone <repository-url>
```

### 2. Move project to XAMPP

```text
C:\xampp\htdocs\pfe
```

### 3. Create database

Create a MySQL database:

```sql
CREATE DATABASE pfe;
```

### 4. Import schema

Import the SQL file using phpMyAdmin.

### 5. Configure database connection

Update:

```text
config/database.php
```

with your database credentials.

### 6. Start XAMPP

Start:

* Apache
* MySQL

### 7. Open project

```text
http://localhost/pfe
```

---

## Security Features

* Prepared Statements (PDO)
* Password Hashing
* Session Regeneration
* Role Checking
* Input Validation
* Secure File Uploads
* SQL Injection Protection

---

## Future Improvements

* Email notifications
* PDF exports
* Advanced reporting
* Ticket assignment automation
* Dashboard analytics
* Real-time notifications

---

## Author

Soukaina Asaas

Bootcamp Web Development Student – SoliCode
