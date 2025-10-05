<?php
// Authentifizierungslogik direkt hier implementieren
require_once 'php/auth.php';

// Prüfen ob ein User existiert
if (!userExists()) {
    // Kein User vorhanden -> zur Registrierung
    header('Location: /register.php');
    exit;
}

// User existiert, prüfen ob eingeloggt
if (!isLoggedIn()) {
    // Nicht eingeloggt -> zum Login
    header('Location: /login.php');
    exit;
}

// Username für die Anzeige holen
$username = $_SESSION['username'] ?? 'Benutzer';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <!-- Verhindert Suchmaschinen-Indexierung -->
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="slurp" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="referrer" content="no-referrer">
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagebucheintrag</title>
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="img/favicons/favicon-light.ico">
    <link rel="icon" type="image/x-icon" href="img/favicons/favicon-dark.ico" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/svg+xml" href="img/favicons/favicon-light.svg">
    <link rel="icon" type="image/svg+xml" href="img/favicons/favicon-dark.svg" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicons/favicon-light-16x16.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicons/favicon-dark-16x16.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicons/favicon-light-32x32.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicons/favicon-dark32x32.png" media="(prefers-color-scheme: light)">
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicons/favicon-light-180x180.png">
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicons/favicon-dark-180x180.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="512x512" href="img/favicons/favicon-light-512x512.png">
    <link rel="icon" type="image/png" sizes="512x512" href="img/favicons/favicon-dark-512x512.png" media="(prefers-color-scheme: light)">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/pastel.css">
</head>
<body class="page-body">
    <div class="app-container">
        <!-- Lasche zum Öffnen der Sidebar -->
        <button id="sidebarTab" class="sidebar-tab"></button>
        <!-- Seitliches Panel -->
        <div id="sidebar" class="sidebar">
            <div class="sidebar-content">
                <div class="sidebar-header">
                    <h2 class="sidebar-title">Kalender</h2>
                    <button id="closeSidebar" class="close-btn"><span class="fa fa-times"></span></button>
                </div>
                <div class="calendar-nav">
                    <div class="calendar-nav-content">
                        <div id="calendarHeader" class="calendar-header">
                            <!-- Monat/Jahr werden per JS eingefügt -->
                        </div>
                        <button id="prevMonthBtn" class="nav-btn prev-btn" title="Vorheriger Monat"><i class="fa fa-chevron-left"></i></button>
                        <button id="nextMonthBtn" class="nav-btn next-btn" title="Nächster Monat"><i class="fa fa-chevron-right"></i></button>
                    </div>
                </div>
                <div id="calendar" class="calendar"></div>
                <!-- Einstellungen Button am unteren Ende der Sidebar -->
                <div class="sidebar-footer">
                    <button id="settingsBtn" class="settings-btn">
                        <i class="fa fa-cog mr-2"></i> <span>Einstellungen</span>
                    </button>
                    <!-- Einstellungen Panel, initial ausgeblendet -->
                    <div id="settingsPanel" class="settings-panel" style="display:none;">
                        <!-- Einstellungen: Musik & KI-Therapeut -->
                        <div class="settings-content">
                            <!-- Style-Auswahl -->
                            <div class="setting-group">
                                <div class="setting-item">
                                    <label for="settingsStyle" class="setting-label">Style</label>
                                    <select id="settingsStyle" class="setting-select">
                                        <option value="standard">Standard</option>
                                        <option value="hell">Hell</option>
                                        <option value="dunkel">Dunkel</option>
                                    </select>
                                    <p class="setting-help">Wähle das Farbschema der Anwendung.</p>
                                </div>
                            </div>

                            <!-- Musik-API Auswahl -->
                            <div class="setting-group">
                                <div class="setting-item">
                                    <div class="setting-label-row">
                                        <label for="settingsMusicApi" class="setting-label">Musik</label>
                                        <div class="info-tooltip">
                                            <button type="button" id="musicInfoBtn" class="info-btn">
                                                <i class="fa-solid fa-info-circle"></i>
                                            </button>
                                            <div id="musicInfoBubble" class="info-bubble">
                                                <div class="info-content">
                                                    <div>
                                                        <p style="margin-top: 0">Wähle eine Musik-API für die Musiksuche:</p>
                                                        <ul>
                                                            <li><strong>Lokal:</strong> Es wird aus einer lokal gespeicherten Musikbibliothek gesucht. Es werden keine Daten über das Internet übertragen, jedoch können nicht alle lieder gefunden werden.</li>
                                                            <li><strong>YouTube Music:</strong> Benötigt einen YouTube API Key. Deine Daten werden dadurch an YouTube übermittelt.</li>
                                                            <li><strong>Spotify:</strong> Benötigt Spotify API Credentials. Deine Daten werden an Spotify übermittelt und in deinem Spotify account gespeichert.</li>
                                                            <li><strong>iTunes/Apple Music:</strong> Funktioniert ohne API Key. Deine Daten werden jedoch an Apple übermittelt.</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <select id="settingsMusicApi" class="setting-select">
                                        <option value="">Aus</option>
                                        <option value="local">Lokal</option>
                                        <option value="youtube">YouTube Music</option>
                                        <option value="spotify">Spotify</option>
                                        <option value="itunes">iTunes/Apple Music</option>
                                    </select>
                                </div>

                                <!-- YouTube API Key -->
                                <div id="youtubeApiGroup" class="api-group hidden">
                                    <label for="settingsYouTubeApiKey" class="api-label">YouTube API Key</label>
                                    <input id="settingsYouTubeApiKey" type="password" placeholder="AIza..." class="api-input" />
                                    <p class="api-help">
                                        Kostenlosen API Key bei <a href="https://console.developers.google.com" target="_blank" class="help-link">Google Cloud Console</a> erstellen.
                                        <br>Aktiviere "YouTube Data API v3" und erstelle Credentials.
                                    </p>
                                </div>

                                <!-- Spotify API Keys -->
                                <div id="spotifyApiGroup" class="api-group hidden">
                                    <label class="api-label">Spotify API Credentials</label>
                                    <input id="settingsSpotifyClientId" type="password" placeholder="Client ID" class="api-input" />
                                    <input id="settingsSpotifyClientSecret" type="password" placeholder="Client Secret" class="api-input" />
                                    <p class="api-help">
                                        API Credentials bei <a href="https://developer.spotify.com/dashboard" target="_blank" class="help-link">Spotify Developer Dashboard</a> erstellen.
                                    </p>
                                </div>

                                <!-- iTunes (kein API Key erforderlich) -->
                                <div id="itunesApiGroup" class="api-group hidden">
                                    <p class="api-info">
                                        <i class="fa-solid fa-info-circle info-icon"></i>
                                        iTunes Search API ist kostenlos und erfordert keine Registrierung.
                                    </p>
                                </div>

                                <!-- Lokale Musik -->
                                <div id="localApiGroup" class="api-group hidden">
                                    <p class="api-info">
                                        <i class="fa-solid fa-info-circle info-icon"></i>
                                        Die Titel werden aus einer lokalen Datei geladen.
                                        <br>Titel können durchsucht, aber nicht abgespielt werden. Manchmal sind nicht alle Songs vorhanden.
                                    </p>
                                </div>
                            </div>

                            <!-- KI-Therapeut Auswahl -->
                            <!-- <div class="setting-item">
                                <label for="settingsAiMode" class="setting-label">KI-Therapeut</label>
                                <select id="settingsAiMode" class="setting-select">
                                    <option value="off">Aus</option>
                                    <option value="local">Lokal</option>
                                    <option value="server">Eigener Server</option>
                                    <option value="api">API Key</option>
                                </select>
                                <p class="setting-help">Wähle die Art der KI-Unterstützung für Reflexion / Coaching.</p>
                                <div id="aiServerGroup" class="api-group hidden">
                                    <label for="settingsAiServerUrl" class="api-label">Server URL</label>
                                    <input id="settingsAiServerUrl" type="text" placeholder="https://dein-server.tld/api" class="api-input" />
                                </div>
                                <div id="aiApiKeyGroup" class="api-group hidden">
                                    <label for="settingsAiApiKey" class="api-label">API Key</label>
                                    <input id="settingsAiApiKey" type="password" placeholder="sk-..." class="api-input" />
                                    <small class="api-note">Der Key wird nur lokal im Browser (localStorage) gespeichert.</small>
                                </div>
                            </div> -->
                        </div>
                        <!-- Logout Button in Einstellungen -->
                        <div class="logout-section">
                            <a href="/logout.php" class="logout-btn" title="Abmelden">
                                <i class="fa-solid fa-sign-out-alt mr-2"></i>
                                Abmelden
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hauptbereich -->
        <div class="main-content">
            <header class="main-header">
                <div class="header-controls">
                    <h1 id="entryTitle" class="entry-title">Tagebucheintrag</h1>
                    <div id="moodDropdown" class="mood-dropdown">
                        <span class="mood-label" title="Wie würdest du den heutigen Tag beschreiben?">Mood</span>
                        <button id="moodBtn" class="mood-btn">
                            <span id="moodIcon" class="fa-solid fa-question mood-icon"></span>
                            <span id="moodLabel" class="mood-text">Bitte auswählen</span>
                        </button>
                        <div id="moodMenu" class="mood-menu hidden">
                            <ul>
                                <li><button class="mood-option neutral" data-mood="1"><span class="fa-solid fa-question"></span><span>Bitte auswählen</span></button></li>
                                <li><button class="mood-option very-good" data-mood="2"><span class="fa-solid fa-face-grin-stars"></span><span>Sehr gut</span></button></li>
                                <li><button class="mood-option good" data-mood="3"><span class="fa-solid fa-face-smile"></span><span>Gut</span></button></li>
                                <li><button class="mood-option neutral-mood" data-mood="4"><span class="fa-solid fa-face-meh"></span><span>Neutral</span></button></li>
                                <li><button class="mood-option bad" data-mood="5"><span class="fa-solid fa-face-frown"></span><span>Schlecht</span></button></li>
                                <li><button class="mood-option very-bad" data-mood="6"><span class="fa-solid fa-face-angry"></span><span>Sehr schlecht</span></button></li>
                            </ul>
                        </div>
                    </div>
                    <!-- Musik-Suchfeld (initial versteckt) -->
                    <div id="musicSearchContainer" class="music-container hidden">
                        <span class="music-label" title="Welche musik hörst du gerade am liebsten?">Musik</span>
                        <div class="music-search">
                            <i class="fa-solid fa-music music-icon"></i>
                            <input id="musicSearchInput" type="text" placeholder="Musiktitel suchen..." class="music-input" />
                            <button id="musicPlayBtn" class="music-btn play-btn hidden" title="Abspielen">
                                <i class="fa-solid fa-play"></i>
                            </button>
                            <button id="musicPauseBtn" class="music-btn pause-btn hidden" title="Pausieren">
                                <i class="fa-solid fa-pause"></i>
                            </button>
                        </div>
                        <div id="musicSearchResults" class="music-results hidden">
                            <!-- Suchergebnisse werden hier eingefügt -->
                        </div>
                        <!-- Versteckter YouTube Player -->
                        <div id="youtubePlayerContainer" class="hidden">
                            <div id="youtubePlayer"></div>
                        </div>
                    </div>
                </div>
                <div class="header-actions">
                    <button id="saveBtn" class="save-btn">Speichern</button>
                </div>
            </header>
            <div class="editor-container">
                <div class="editor-wrapper">
                    <div id="toolbar">
                        <span class="ql-formats">
                            <select class="ql-header">
                                <option value="1"></option>
                                <option value="2"></option>
                                <option value="3"></option>
                                <option value="4"></option>
                                <option selected></option>
                            </select>
                            <select class="ql-size">
                                <option value="small"></option>
                                <option selected></option>
                                <option value="large"></option>
                                <option value="huge"></option>
                            </select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-bold"></button>
                            <button class="ql-italic"></button>
                            <button class="ql-underline"></button>
                            <button class="ql-strike"></button>
                        </span>
                        <span class="ql-formats">
                            <select class="ql-align"></select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-blockquote"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-list" value="ordered"></button>
                            <button class="ql-list" value="bullet"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-link"></button>
                            <button class="ql-image"></button>
                        </span>
                        <span class="ql-formats">
                            <select class="ql-color"></select>
                            <select class="ql-background"></select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-clean"><i class="fa fa-brush"></i></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-speech"><i class="fa fa-microphone"></i></button>
                        </span>
                    </div>
                    <div id="editor" class="editor"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/editor.js"></script>
    <script src="js/app.js"></script>
</body>
</html>