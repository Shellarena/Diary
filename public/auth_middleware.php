<?php
// auth_middleware.php - Middleware für Authentifizierung
require_once __DIR__ . '/php/auth.php';

// Authentifizierung für alle geschützten Seiten erforderlich
requireAuth();
?>