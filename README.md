# Digitales Tagebuch

Eine einfache Web-App für das Führen eines digitalen Tagebuchs.

## Lokale Entwicklung ohne Docker

1. Stelle sicher, dass PHP und MySQL auf deinem System installiert sind (z.B. via XAMPP).
2. Führe `php php/setup_db.php` aus, um die Datenbank und Tabelle zu erstellen.
3. Öffne `public/index.html` in deinem Browser (über einen lokalen Server, z.B. Apache).

## Lokale Entwicklung mit Docker

1. Stelle sicher, dass Docker und Docker Compose installiert sind
2. Führe `docker-compose up --build` aus
3. Öffne http://localhost:8080 in deinem Browser
4. Die Datenbank wird automatisch erstellt und ist persistent

## Funktionen

- Rich-Text-Editor für Einträge
- Kalender zum Navigieren zu verschiedenen Tagen
- Daten werden in MySQL gespeichert

## Technologien

- HTML
- Tailwind CSS
- JavaScript (Quill.js für Editor)
- PHP
- MySQL
- Docker (optional)