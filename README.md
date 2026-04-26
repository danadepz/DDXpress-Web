# DDXpress (PHP + MySQLi)

Role-based parcel booking + tracking demo app for XAMPP/Apache on Windows.

## 1) Create the database

In phpMyAdmin (or MySQL CLI), create:

- **Database**: `Ddxpress_db`

Then import:

- `sql/ddxpress_schema_seed.sql`

## 2) Configure DB connection

The app uses:

- `config/db.php`

It is already set to:

```php
$conn = new mysqli("localhost","root","","Ddxpress_db");
```

## 3) Run the app

Start **Apache** + **MySQL** in XAMPP, then open:

- `http://localhost/DDXpress/`

## Seeded logins

Password for seeded users is: `password`

- **Admin**: `admin@ddxpress.test`
- **Staff**: `staff@ddxpress.test`
- **Rider**: `rider@ddxpress.test`
- **Customer**: `customer@ddxpress.test` (or register a new customer)

## Pages

- **Customer**: dashboard, create booking (multi-parcel), track parcel, booking history
- **Staff**: inspection queue (accept/decline), view parcels, update status, scan
- **Rider**: accept/decline, my parcels, update status, scan, delivered note
- **Admin**: manage branches, service types, staff, view bookings/parcels, reports

# DDXpress-Web
