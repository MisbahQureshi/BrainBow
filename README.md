# BrainBow – Student Planner WebApp  
A full-stack productivity and organization tool built for students, by a student.

## About the Project
**BrainBow** is a complete student planner designed to bring clarity to academic life.  
As students, our notes, deadlines, tasks, and ideas are often scattered across several apps, which makes planning overwhelming.

This project aims to reduce that chaos by keeping everything in **one organized space**.

BrainBow lets you manage:
- Course-based projects  
- Notes  
- To-dos & priorities  
- Mind Maps  
- Whiteboards  
- Events & Calendar  
- Dashboard overview  

Built over several months as part of the **CS5130 – Fall 2025** term project, BrainBow reflects real-world full-stack development practices while solving a genuine student problem.

## Features
### Authentication
- Register, Login, Logout  
- Session-based access control  
- Tracks login count and last login timestamp  

### Dashboard
- Overview of upcoming tasks  
- Recent notes  
- Upcoming events  
- Quick access to mind maps, whiteboards, etc.

### Projects
- Create, view, update, archive/delete projects  
- Each project contains all related content  

### To-dos
- Create, edit, delete  
- Priority, status, due dates  

### Notes
- Project-scoped notes with CRUD  

### Mind Maps
- Interactive drag-and-drop editor  
- JSON-based save system  
- Thumbnail generation  

### Whiteboards
- Free drawing canvas  
- Saves JSON + thumbnail  

### Events (Calendar)
- Event creation with date/time/location  
- Upcoming event overview  


## Tech Stack
- **Frontend:** HTML, CSS, JavaScript, jQuery  
- **Backend:** PHP (MVC)  
- **Database:** MySQL (InnoDB, utf8mb4)  
- **Other:** Sessions, Cookies, PDO prepared statements  

## Installation Guide
### 1️⃣ Clone the repository
```bash
git clone https://github.com/YOUR_USERNAME/BrainBow.git
cd BrainBow 
```
### 2️⃣ Import the database

Import mainSchemaDB.sql via phpMyAdmin or:
mysql -u root -p

```txt
CREATE DATABASE student_planner;
USE student_planner;
SOURCE mainSchemaDB.sql;
```
### 3️⃣ Configure database connection

Edit app/Lib/db.php:
```bash
$DB_HOST = 'localhost';
$DB_NAME = 'student_planner';
$DB_USER = 'root';
$DB_PASS = '';
```
### 4️⃣ Start the server
```txt
php -S localhost:8000 -t public
Visit:
http://localhost:8000/index.php?route=login
```
- Run seedAdmin.php file once and then login.
## Project Structure

```txt
app/
  Controllers/
  Models/
  Views/
  Lib/
  Middleware/
public/
  css/
  js/
  index.php
mainSchemaDB.sql
README.md
```
## Future Enhancements
```txt
Collaboration System (Planned)
Multi-user projects
Sharing notes, tasks, whiteboards
```

## Contributing
```txt
Fork
Branch
Commit
PR
```

## Author
**Misbah Qureshi**

---