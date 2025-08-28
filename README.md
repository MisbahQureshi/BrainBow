# BrainBow Student Planner

A PHP/MySQL student productivity app that combines **projects, to-dos, notes, mind maps, whiteboards, and a calendar** into a single dashboard. Built as a lightweight MVC with XAMPP stack.

---

## Features

- User authentication (login, register, password reset)
- Dashboard with quick stats and top items
- To-do management (priority, due dates, project scoping)
- Notes with search and pinning
- Mind maps for brainstorming (JSON based)
- Whiteboards for sketching ideas
- Calendar & events with start/end times and locations
- Project organization (color codes, optional members)
- Search & tagging (planned)

---

## Tech Stack

- **Frontend**: HTML, CSS, vanilla JS  
- **Backend**: PHP 8+, custom MVC  
- **Database**: MySQL (InnoDB, utf8mb4)  
- **Environment**: XAMPP / phpMyAdmin / MySQL Workbench  

---


## Database Schema

Main entities:

- **users** – authentication, roles (`admin`, `student`)
- **projects** – owner, title, description, color
- **todos** – linked to projects, priority, status
- **notes** – text notes, searchable
- **events** – calendar items
- **mindmaps** – JSON mind map data
- **whiteboards** – JSON whiteboard data
- **tags & item_tags** – optional classification

---

## Milestones

- **M1**: Repo setup, DB schema, auth, dashboard shell  
- **M2**: Project CRUD, sidebar, empty modules  
- **M3**: Full To-do workflows + dashboard queries  
- **M4**: Notes + calendar basics  
- **M5**: Mind maps & whiteboards editors  
- **M6**: Search, tags, polish, security pass  

---

## Security Checklist

- Passwords stored with `password_hash()` / `password_verify()`  
- Sessions regenerated on login  
- Middleware restricts access to authenticated routes  
- CSRF tokens planned for forms 
---

## License

MIT — free to use, learn, and improve.

---

## Acknowledgments

Built as a learning project to integrate **planning tools** (to-dos, notes, whiteboards, mind maps, calendar) into a single student productivity dashboard.
