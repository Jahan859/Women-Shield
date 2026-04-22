# Women Shield

A XAMPP-friendly PHP/MySQL website for women's safety workflows, including:

- Login system
- Emergency contacts
- Report CRUD
- Safety map
- AI categorization
- Danger score AI
- Safety tips AI
- AI chat assistant
- Monitoring agent
- Alert agent
- Night risk agent
- Fake report agent
- Admin dashboard
- AI insights
- Emergency mode

## Tech Stack

- PHP 8+
- MySQL / MariaDB through XAMPP
- Plain JavaScript
- Leaflet + OpenStreetMap tiles for the map

## XAMPP Setup

1. Copy the project into your XAMPP `htdocs` folder, for example `xampp/htdocs/Logno`.
   If top-level `htdocs` is not writable on macOS, placing it under a writable subfolder like `htdocs/demo/Logno` also works.
2. Start Apache and MySQL from XAMPP.
3. Either:
   - Open `http://localhost/Logno/install.php` and run the installer, or
   - Import [database/schema.sql](/Users/mohammadmujahid/Desktop/Logno/database/schema.sql) in phpMyAdmin.
4. Optional: copy `config/local.example.php` to `config/local.php` and adjust credentials or base URL.
5. Open the matching local URL, for example:
   - `http://localhost/Logno`
   - `http://localhost/demo/Logno`

## Default Admin Login

- Email: `admin@safety.local`
- Password: `Admin@123`

## Notes

- The AI features are implemented as local heuristic intelligence so the site works fully on a local XAMPP stack without needing external APIs.
- If you later want real LLM responses, you can replace the heuristic functions inside [lib/ai.php](/Users/mohammadmujahid/Desktop/Logno/lib/ai.php) with an API integration.

## PHPMailer Option

If you want Emergency Mode emails to use SMTP instead of plain PHP `mail()`, you can enable PHPMailer.

1. In the project root, run:
   - `composer install`
2. Copy `config/local.example.php` to `config/local.php`.
3. Set:
   - `'mail' => ['driver' => 'phpmailer', ...]`
4. Fill your SMTP settings, for example Gmail:
   - host: `smtp.gmail.com`
   - port: `587`
   - encryption: `tls`
   - username: your Gmail address
   - password: your Gmail app password
5. Activate Emergency Mode from the site.

If PHPMailer is not installed or the SMTP settings are incomplete, the app will show a failure message on Emergency Mode activation.
