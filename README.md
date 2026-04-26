# DDXpress - Parcel Delivery Management System

A role-based parcel booking and tracking system built with PHP (MySQLi) and Bootstrap 5.

---

## Quick Start

1. Clone or download the project to `C:\xampp\htdocs\DDXpress`
2. Create the database and import the SQL file
3. Start XAMPP (Apache + MySQL)
4. Open `http://localhost/DDXpress/`
5. Login using the seeded credentials below

---

## Setup Instructions

### 1. Create the Database

Using phpMyAdmin or MySQL CLI, create a database:

CREATE DATABASE DBXpress_db;

Then import the SQL file:

USE DBXpress_db;
SOURCE C:/xampp/htdocs/DDXpress/sql/ddxpress_schema_seed.sql;

Or use phpMyAdmin to import `sql/ddxpress_schema_seed.sql`

### 2. Configure Database Connection

The database credentials are set in `config/db.php`:

$conn = new mysqli("localhost", "root", "", "DBXpress_db");

Note: If your MySQL has a password, update the third parameter accordingly.

### 3. Run the Application

Start Apache and MySQL in XAMPP, then open:

http://localhost/DDXpress/

---

## Default Login Credentials

Password for all seeded accounts: `password`

| Role | Email |
|------|-------|
| Admin | admin@ddxpress.test |
| Staff | staff@ddxpress.test |
| Rider | rider@ddxpress.test |
| Customer | customer@ddxpress.test |

You can also register as a new customer.

---

## Module Overview

| Role | Functions |
|------|-----------|
| Customer | Dashboard, create booking (multi-parcel), track parcel, booking history |
| Staff | Inspection queue (accept/decline), view parcels, update status, scan parcel |
| Rider | Accept/decline deliveries, view assigned parcels, update status, scan, delivery notes |
| Admin | Manage branches, service types, staff accounts, view bookings/parcels, generate reports |

---

## Tech Stack

- Backend: PHP (MySQLi)
- Frontend: Bootstrap Studio, Bootstrap 5
- Database: MySQL
- Server: XAMPP / Apache

---

## Project Structure
DDXpress/
├── admin/ # Admin management modules
├── auth/ # Login, registration, logout
├── customer/ # Customer booking and tracking
├── staff/ # Staff inspection and updates
├── rider/ # Rider delivery management
├── layout/ # Master layout and sidebar
├── config/ # Database configuration
├── includes/ # Helper functions and authentication
├── assets/ # CSS, JS, Bootstrap files
├── sql/ # Database schema and seed data
└── index.php # Entry point

text

---

## Page Directory

| Role | Pages |
|------|-------|
| Customer | Dashboard, Create Booking, Track Parcel, Booking History |
| Staff | Inspection Queue, All Parcels, Update Status, Scan Parcel |
| Rider | Available Parcels, My Parcels, Update Status, Scan |
| Admin | Manage Branches, Service Types, Staff, Bookings, Reports |

---

## Notes

- The system uses sessions for role-based access control
- Passwords are hashed using password_hash()
- Parcels have a complete status history trail
- Payment can be recorded via Cash, GCash, Credit Card, or Bank Transfer

---

## Author

Dana Depz

GitHub: danadepz

---

## License

This project is for educational/demonstration purposes.