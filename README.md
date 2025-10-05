# Digital Diary

A simple web application for keeping a digital diary.

## Local Development without Docker

1. Make sure PHP and MySQL are installed on your system (e.g. via XAMPP).
2. Run `php php/setup_db.php` to create the database and table.
3. Open `public/index.html` in your browser (via a local server, e.g. Apache).

## Local Development with Docker

1. Make sure Docker and Docker Compose are installed
2. Run `docker-compose up --build`
3. Open http://localhost:8080 in your browser
4. The database will be created automatically and is persistent

## Deployment

To deploy this application on your web server:

### Prerequisites

- Web server with PHP support (Apache, Nginx, etc.)
- MySQL database server
- PHP 7.4 or higher

### Installation Steps

1. **Upload files**: Upload all files to your web server's document root or a subdirectory
2. **Database setup**: 
   - Create a MySQL database for your diary application
   - Update the database configuration in `php/db_config.php` with your server details:
     ```php
     $host = 'your-db-host';
     $dbname = 'your-database-name';
     $username = 'your-db-username';
     $password = 'your-db-password';
     ```
3. **Initialize database**: Run the setup script by accessing `your-domain.com/php/setup_db.php` in your browser
4. **Set permissions**: Ensure the web server has read/write permissions for the application files
5. **Security**: 
   - Remove or restrict access to `php/setup_db.php` after initial setup
   - Consider setting up HTTPS for secure data transmission
   - Configure your web server to deny direct access to PHP files in the `php/` directory

## Features

- Rich text editor for entries
- Calendar for navigating to different days
- Data stored in MySQL database
- User authentication and registration
- Responsive design with multiple themes

## Technologies

- HTML
- Tailwind CSS
- JavaScript (Quill.js for editor)
- PHP
- MySQL
- Docker (optional)