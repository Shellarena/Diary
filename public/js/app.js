// app.js - Main logic for the diary

// Dynamically load YouTube API
function loadYouTubeAPI() {
    return new Promise((resolve, reject) => {
        // Check if API is already loaded
        if (youtubeApiLoaded && typeof YT !== 'undefined' && YT.Player) {
            resolve();
            return;
        }
        
        // Check if API is already being loaded
        if (youtubeApiLoading) {
            // Wait until API is loaded
            const checkLoaded = () => {
                if (youtubeApiLoaded && typeof YT !== 'undefined' && YT.Player) {
                    resolve();
                } else {
                    setTimeout(checkLoaded, 100);
                }
            };
            checkLoaded();
            return;
        }
        
        youtubeApiLoading = true;
        
        // Define YouTube API Ready Callback
        window.onYouTubeIframeAPIReady = function() {
            youtubeApiLoaded = true;
            youtubeApiLoading = false;
            window.youtubeApiReady = true;
            resolve();
        };
        
        // Load YouTube API Script
        const script = document.createElement('script');
        script.src = 'https://www.youtube.com/iframe_api';
        script.onload = function() {
            // Script loaded, wait for onYouTubeIframeAPIReady
        };
        script.onerror = function() {
            youtubeApiLoading = false;
            reject(new Error('Fehler beim Laden der YouTube API'));
        };
        
        document.head.appendChild(script);
    });
}

let currentMood = 1; // Default: Please select
let lastSavedContent = '';
let lastSavedMood = 1;
let currentMusicTitle = ''; // Currently selected music title
let lastSavedMusicTitle = '';
// Complete music data for playback
// Create a factory function for music data objects
const createMusicData = (title = '', api = null, id = null, artist = '', url = null) => ({
    title, api, id, artist, url
});

let currentMusicData = createMusicData();
let lastSavedMusicData = createMusicData();
let bgAudio = null; // For background music
function getLocalISODate() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 10); // YYYY-MM-DD
}
let currentDate = getLocalISODate(); // YYYY-MM-DD in local format
    let calendarYear, calendarMonth;

function applyStyle(style) {
    // Find all existing theme CSS files (not layout.css)
    const existingStyleLinks = document.querySelectorAll('link[href*="css/style"], link[href*="css/light"], link[href*="css/dark"], link[href*="css/pastel"]');
    
    // Determine the new CSS file
    let newCssFile;
    if (style === 'hell') {
        newCssFile = 'css/light.css';
    } else if (style === 'dunkel') {
        newCssFile = 'css/dark.css';
    } else {
        newCssFile = 'css/pastel.css'; // Default theme
    }
    
    // Check if the desired CSS file is already loaded
    const alreadyLoaded = Array.from(existingStyleLinks).some(link => 
        link.href.includes(newCssFile.split('/')[1])
    );
    
    if (!alreadyLoaded) {
        // Remove all old theme CSS links (keep layout.css)
        existingStyleLinks.forEach(link => link.remove());
        
        // Create new theme style link
        const newStyleLink = document.createElement('link');
        newStyleLink.rel = 'stylesheet';
        newStyleLink.href = newCssFile;
        
        // Insert after layout.css link (if available)
        const layoutLink = document.querySelector('link[href*="layout.css"]');
        if (layoutLink) {
            // Insert directly after layout.css
            layoutLink.parentNode.insertBefore(newStyleLink, layoutLink.nextSibling);
        } else {
            // Insert before first script tag
            const firstScript = document.querySelector('script');
            if (firstScript) {
                document.head.insertBefore(newStyleLink, firstScript);
            } else {
                document.head.appendChild(newStyleLink);
            }
        }
    }
    
    // Remove old theme classes (if present)
    document.body.classList.remove('theme-standard', 'theme-hell', 'theme-dunkel');
}

function updateEntryTitle(dateStr) {
    const [year, month, day] = dateStr.split('-');
    const formatted = `${day}.${month}.${year}`;
    const title = document.getElementById('entryTitle');
    if (title) title.textContent = `Tagebucheintrag, ${formatted}`;
    document.title = `Tagebucheintrag, ${formatted}`;
}

document.addEventListener('DOMContentLoaded', function() {
    // Apply default theme (will be overridden later by saved settings)
    applyStyle('standard');
    
    // Initialize title
    updateEntryTitle(currentDate);
    
    // Initialize editor
    quill = initEditor();
    
    // Mood dropdown interaction
    const moodBtn = document.getElementById('moodBtn');
    const moodMenu = document.getElementById('moodMenu');
    const moodIcon = document.getElementById('moodIcon');
    const moodOptions = [
        {icon: 'fa-question', color: 'mood-color-gray', bg: 'mood-bg-gray', label: 'Bitte auswählen'},
        {icon: 'fa-face-grin-stars', color: 'mood-color-green', bg: 'mood-bg-green', label: 'Sehr gut'},
        {icon: 'fa-face-smile', color: 'mood-color-lime', bg: 'mood-bg-lime', label: 'Gut'},
        {icon: 'fa-face-meh', color: 'mood-color-yellow', bg: 'mood-bg-yellow', label: 'Neutral'},
        {icon: 'fa-face-frown', color: 'mood-color-orange', bg: 'mood-bg-orange', label: 'Schlecht'},
        {icon: 'fa-face-angry', color: 'mood-color-red', bg: 'mood-bg-red', label: 'Sehr schlecht'}
    ];

    // Ensure that the mood menu is initially hidden
    if (moodMenu) {
        moodMenu.classList.add('hidden');
    }

    moodBtn.addEventListener('click', function(e) {
        moodMenu.classList.toggle('hidden');
        e.stopPropagation();
    });
    document.addEventListener('click', function(e) {
        if (!moodMenu.classList.contains('hidden')) {
            if (!moodMenu.contains(e.target) && !moodBtn.contains(e.target)) {
                moodMenu.classList.add('hidden');
            }
        }
    });
    moodMenu.querySelectorAll('button[data-mood]').forEach(btn => {
        btn.addEventListener('click', function() {
            const mood = parseInt(this.getAttribute('data-mood'));
            setMood(mood);
            moodMenu.classList.add('hidden');
        });
    });
    window.moodLabel = document.getElementById('moodLabel');
    window.moodIcon = moodIcon;
    window.moodOptions = moodOptions;
    window.setMood = function(mood) {
        currentMood = mood;
        const opt = moodOptions[mood-1];
        moodIcon.className = `fa-solid ${opt.icon} ${opt.color}`;
        moodIcon.title = opt.label;
        moodLabel.textContent = opt.label;
        moodLabel.className = `${opt.color} mood-label-font`;
    }
    // setMood will be called correctly in loadEntry()
    // Insert notification container
    const notifContainer = document.createElement('div');
    notifContainer.id = 'notification-container';
    notifContainer.className = 'notification-container';
    document.body.appendChild(notifContainer);

    // Initialize calendar
    const today = new Date();
    calendarYear = today.getFullYear();
    calendarMonth = today.getMonth();
    initCalendar(calendarYear, calendarMonth);

    // Month navigation buttons
    document.getElementById('prevMonthBtn').addEventListener('click', function() {
        calendarMonth--;
        if (calendarMonth < 0) {
            calendarMonth = 11;
            calendarYear--;
        }
        initCalendar(calendarYear, calendarMonth);
    });
    document.getElementById('nextMonthBtn').addEventListener('click', function() {
        calendarMonth++;
        if (calendarMonth > 11) {
            calendarMonth = 0;
            calendarYear++;
        }
        initCalendar(calendarYear, calendarMonth);
    });

    // Insert blur overlay
    const blurOverlay = document.createElement('div');
    blurOverlay.id = 'blurOverlay';
    blurOverlay.className = 'blur-overlay';
    document.body.insertBefore(blurOverlay, document.body.children[1]);

    // Sidebar event listeners - optimized with helper functions
    const sidebar = document.getElementById('sidebar');
    const sidebarTab = document.getElementById('sidebarTab');
    
    const showSidebar = () => {
        sidebar.classList.remove('sidebar-hidden');
        blurOverlay.classList.remove('blur-hidden');
        blurOverlay.classList.add('blur-visible');
        blurOverlay.style.pointerEvents = 'auto';
    };
    
    const hideSidebar = () => {
        sidebar.classList.add('sidebar-hidden');
        blurOverlay.classList.remove('blur-visible');
        blurOverlay.classList.add('blur-hidden');
        blurOverlay.style.pointerEvents = 'none';
    };
    
    sidebarTab.addEventListener('click', function(e) {
        showSidebar();
        e.stopPropagation();
    });
    
    document.getElementById('closeSidebar').addEventListener('click', function(e) {
        hideSidebar();
        e.stopPropagation();
    });
    
    document.addEventListener('click', function(e) {
        if (!sidebar.classList.contains('sidebar-hidden')) {
            if (!sidebar.contains(e.target) && !sidebarTab.contains(e.target)) {
                hideSidebar();
            }
        }
    });
    
    blurOverlay.addEventListener('click', hideSidebar);

    // Save button
    document.getElementById('saveBtn').addEventListener('click', function() {
        // End speech-to-text recording if active
        stopSpeechRecording();
        saveEntry();
    });

    // Load today's entry
    loadEntry(currentDate);

    // Settings button interaction
    const settingsBtn = document.getElementById('settingsBtn');
    const settingsPanel = document.getElementById('settingsPanel');
    let settingsActive = false;

    settingsBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (!settingsActive) {
            // Button animates upward with custom classes
            settingsBtn.classList.add('settings-btn-active');
            // Show panel
            settingsPanel.style.display = 'block';
            settingsPanel.style.top = '2.8rem';
            settingsPanel.style.position = 'absolute';
            // Hide calendar
            document.getElementById('calendar').style.display = 'none';
            settingsActive = true;
        } else {
            // Retract panel and button
            settingsBtn.classList.remove('settings-btn-active');
            settingsPanel.style.display = 'none';
            document.getElementById('calendar').style.display = '';
            settingsActive = false;
        }
    });

    // SETTINGS: Style, Music & AI Therapist
    const styleSelect = document.getElementById('settingsStyle');
    const musicApiSelect = document.getElementById('settingsMusicApi');
    const youtubeApiGroup = document.getElementById('youtubeApiGroup');
    const spotifyApiGroup = document.getElementById('spotifyApiGroup');
    const itunesApiGroup = document.getElementById('itunesApiGroup');
    const youtubeApiKeyInput = document.getElementById('settingsYouTubeApiKey');
    const spotifyClientIdInput = document.getElementById('settingsSpotifyClientId');
    const spotifyClientSecretInput = document.getElementById('settingsSpotifyClientSecret');
    const aiModeSelect = document.getElementById('settingsAiMode');
    const aiServerGroup = document.getElementById('aiServerGroup');
    const aiApiKeyGroup = document.getElementById('aiApiKeyGroup');
    const aiServerUrlInput = document.getElementById('settingsAiServerUrl');
    const aiApiKeyInput = document.getElementById('settingsAiApiKey');

    function reflectMusicApi(api) {        
        // Hide all API groups
        if (youtubeApiGroup) youtubeApiGroup.classList.add('hidden');
        if (spotifyApiGroup) spotifyApiGroup.classList.add('hidden');
        if (itunesApiGroup) itunesApiGroup.classList.add('hidden');
        const localApiGroup = document.getElementById('localApiGroup');
        if (localApiGroup) localApiGroup.classList.add('hidden');
        
        // Show corresponding API group
        if (api === 'local' && localApiGroup) {
            localApiGroup.classList.remove('hidden');
            showMusicSearch(false); // Show music search field, but without play button
        } else if (api === 'youtube' && youtubeApiGroup) {
            youtubeApiGroup.classList.remove('hidden');
            showMusicSearch(true); // Show music search field with play button
            
            // Load YouTube API if not already done
            if (!youtubeApiLoaded && !youtubeApiLoading) {
                loadYouTubeAPI().then(() => {
                    console.log('YouTube API successfully loaded');
                }).catch(error => {
                    console.error('Error loading YouTube API:', error);
                    showNotification('Error loading YouTube API', 'error');
                });
            }
        } else if (api === 'spotify' && spotifyApiGroup) {
            spotifyApiGroup.classList.remove('hidden');
            showMusicSearch(true); // Show music search field with play button
        } else if (api === 'itunes' && itunesApiGroup) {
            itunesApiGroup.classList.remove('hidden');
            showMusicSearch(true); // Show music search field with play button
        } else {
            hideMusicSearch(); // Hide music search field when no API selected
        }
    }

    function reflectAiMode(mode) {
        aiServerGroup.classList.add('hidden');
        aiApiKeyGroup.classList.add('hidden');
        if (mode === 'server') aiServerGroup.classList.remove('hidden');
        if (mode === 'api') aiApiKeyGroup.classList.remove('hidden');
    }

    // Save settings to database
    function saveSetting(key, value) {
        return fetch('php/app_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: key, value: value })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Failed to save setting:', data.message);
                showNotification('Fehler beim Speichern der Einstellung: ' + data.message, 'error');
            }
            return data;
        })
        .catch(error => {
            console.error('Fehler beim Speichern der Einstellung:', error);
            showNotification('Fehler beim Speichern der Einstellung', 'error');
        });
    }

    function saveAiSettings() {
        saveSetting('ai.mode', aiModeSelect.value);
        saveSetting('ai.serverUrl', aiServerUrlInput.value.trim());
        if (aiApiKeyInput.value.trim()) {
            saveSetting('ai.apiKey', aiApiKeyInput.value.trim());
        }
    }

    function saveMusicApiSettings() {
        const promises = [];
        
        // Save music API
        promises.push(saveSetting('music.api', musicApiSelect ? musicApiSelect.value : ''));
        
        // Save API-specific settings
        if (youtubeApiKeyInput && youtubeApiKeyInput.value.trim()) {
            promises.push(saveSetting('music.youtube.apiKey', youtubeApiKeyInput.value.trim()));
        }
        if (spotifyClientIdInput && spotifyClientIdInput.value.trim()) {
            promises.push(saveSetting('music.spotify.clientId', spotifyClientIdInput.value.trim()));
        }
        if (spotifyClientSecretInput && spotifyClientSecretInput.value.trim()) {
            promises.push(saveSetting('music.spotify.clientSecret', spotifyClientSecretInput.value.trim()));
        }
        
        // Wait until all settings are saved
        Promise.all(promises).then(() => {
            showNotification('Musik-Einstellungen gespeichert', 'success');
        });
    }

    function loadSettings() {
        // Load all settings from database
        fetch('php/app_settings.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.settings) {
                    const settings = data.settings;
                    
                    // Style settings
                    const style = settings['style'] || 'standard';
                    if (styleSelect) {
                        styleSelect.value = style;
                    }
                    applyStyle(style);
                    
                    // Music settings
                    if (musicApiSelect && settings['music.api']) {
                        musicApiSelect.value = settings['music.api'];
                        reflectMusicApi(settings['music.api']);
                    }
                    
                    if (youtubeApiKeyInput && settings['music.youtube.apiKey']) {
                        youtubeApiKeyInput.value = settings['music.youtube.apiKey'];
                    }
                    
                    if (spotifyClientIdInput && settings['music.spotify.clientId']) {
                        spotifyClientIdInput.value = settings['music.spotify.clientId'];
                    }
                    
                    if (spotifyClientSecretInput && settings['music.spotify.clientSecret']) {
                        spotifyClientSecretInput.value = settings['music.spotify.clientSecret'];
                    }
                    
                    // AI settings
                    const aiMode = settings['ai.mode'] || 'off';
                    if (aiModeSelect) {
                        aiModeSelect.value = aiMode;
                        reflectAiMode(aiMode);
                    }
                    
                    if (aiServerUrlInput && settings['ai.serverUrl']) {
                        aiServerUrlInput.value = settings['ai.serverUrl'];
                    }
                    
                    if (aiApiKeyInput && settings['ai.apiKey']) {
                        aiApiKeyInput.value = settings['ai.apiKey'];
                    }
                }
            })
            .catch(error => {
                console.error('Fehler beim Laden der Einstellungen:', error);
            });
    }

    if (styleSelect) {
        styleSelect.addEventListener('change', () => {
            applyStyle(styleSelect.value);
            saveSetting('style', styleSelect.value);
        });
    }

    if (musicApiSelect) {
        musicApiSelect.addEventListener('change', () => {
            reflectMusicApi(musicApiSelect.value);
            saveMusicApiSettings();
        });
    }

    if (youtubeApiKeyInput) {
        youtubeApiKeyInput.addEventListener('blur', saveMusicApiSettings);
        youtubeApiKeyInput.addEventListener('input', saveMusicApiSettings);
    }
    if (spotifyClientIdInput) {
        spotifyClientIdInput.addEventListener('blur', saveMusicApiSettings);
        spotifyClientIdInput.addEventListener('input', saveMusicApiSettings);
    }
    if (spotifyClientSecretInput) {
        spotifyClientSecretInput.addEventListener('blur', saveMusicApiSettings);
        spotifyClientSecretInput.addEventListener('input', saveMusicApiSettings);
    }

    if (aiModeSelect) {
        aiModeSelect.addEventListener('change', () => {
            reflectAiMode(aiModeSelect.value);
            saveAiSettings();
        });
    }
    if (aiServerUrlInput) {
        aiServerUrlInput.addEventListener('blur', saveAiSettings);
    }
    if (aiApiKeyInput) {
        aiApiKeyInput.addEventListener('blur', saveAiSettings);
    }

    loadSettings();

    // Music search field functionality
    initMusicSearch();

    // Sidebar Toggle Functionality - moved from inline script
    initSidebarToggle();
    
    // Music Info Bubble Toggle - moved from inline script
    initMusicInfoBubble();
});

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const blurOverlay = document.getElementById('blurOverlay');
    const isOpen = sidebar.classList.contains('sidebar-hidden');
    if (isOpen) {
        sidebar.classList.remove('sidebar-hidden');
        blurOverlay.classList.remove('blur-hidden');
        blurOverlay.classList.add('blur-visible');
        blurOverlay.style.pointerEvents = 'auto';
    } else {
        sidebar.classList.add('sidebar-hidden');
        blurOverlay.classList.remove('blur-visible');
        blurOverlay.classList.add('blur-hidden');
        blurOverlay.style.pointerEvents = 'none';
    }
}

// Shared mood colors configuration - avoid duplication
const MOOD_COLORS = [
    'mood-dot-gray',   // 0: no mood (not used)
    'mood-dot-green',  // 1: Very good
    'mood-dot-lime',   // 2: Good  
    'mood-dot-yellow', // 3: Neutral
    'mood-dot-orange', // 4: Bad
    'mood-dot-red'     // 5: Very bad
];

function initCalendar() {
    const calendar = document.getElementById('calendar');
    let year = calendarYear;
    let month = calendarMonth;
    // Set month/year in header
    const calendarHeader = document.getElementById('calendarHeader');
    if (calendarHeader) {
        calendarHeader.textContent = getMonthName(month) + ' ' + year;
    }

    // Load mood points for the month
    fetch(`php/load_month_moods.php?year=${year}&month=${month+1}`)
        .then(response => response.json())
        .then(data => {
            // Simple calendar for the current month
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const firstDay = new Date(year, month, 1).getDay();

            let html = '';
            html += '<div class="calendar-grid">';
            const days = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
            days.forEach(day => html += '<div class="calendar-day-header">' + day + '</div>');

            // Calculate the weekday of the first day (0=Sun, 1=Mon, ...)
            // Shift so that Monday = 0
            let offset = firstDay === 0 ? 6 : firstDay - 1;
            for (let i = 0; i < offset; i++) {
                html += '<div></div>';
            }

            // Use shared mood colors configuration

            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                const isToday = dateStr === currentDate;
                const now = new Date();
                const thisDate = new Date(year, month, day);
                const isFuture = thisDate > now;
                const className = isToday ? 'calendar-day calendar-day-today' : 'calendar-day calendar-day-hover';
                let mood = 0;
                if (data.success && data.moods && dateStr in data.moods) {
                    mood = data.moods[dateStr] ? data.moods[dateStr] : 0;
                }
                // Only show dot if entry exists and mood is set (> 0)
                const showDot = (data.success && data.moods && dateStr in data.moods && mood > 0);
                if (isFuture) {
                    html += `<div class="calendar-day calendar-day-future">
                        ${day}
                    </div>`;
                } else {
                    html += `<div class="${className}" onclick="selectDate('${dateStr}')">
                        ${day}
                        ${showDot ? `<div class="calendar-mood-dot ${MOOD_COLORS[mood - 1]}" title="Mood"></div>` : ''}
                    </div>`;
                }
            }

            html += '</div>';
            calendar.innerHTML = html;
        })
        .catch(err => {
            // Fallback: Calendar without mood points
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const firstDay = new Date(year, month, 1).getDay();
            let html = '';
            html += '<div class="calendar-grid">';
            const days = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
            days.forEach(day => html += '<div class="calendar-day-header">' + day + '</div>');
            let offset = firstDay === 0 ? 6 : firstDay - 1;
            for (let i = 0; i < offset; i++) {
                html += '<div></div>';
            }
            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                const isToday = dateStr === currentDate;
                const now = new Date();
                const thisDate = new Date(year, month, day);
                const isFuture = thisDate > now;
                const className = isToday ? 'calendar-day calendar-day-today' : 'calendar-day calendar-day-hover';
                if (isFuture) {
                    html += '<div class="calendar-day calendar-day-future">' + day + '</div>';
                } else {
                    html += '<div class="' + className + '" onclick="selectDate(\'' + dateStr + '\')">' + day + '</div>';
                }
            }
            html += '</div>';
            calendar.innerHTML = html;
        });
}

function getMonthName(month) {
    const months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    return months[month];
}

function updateCalendarMood(dateStr, mood) {
    // Check if the date is in the currently displayed calendar month
    const [year, month, day] = dateStr.split('-');
    const entryYear = parseInt(year);
    const entryMonth = parseInt(month) - 1; // JavaScript months are 0-based
    
    // Only update if the date is in the currently displayed month
    if (entryYear !== calendarYear || entryMonth !== calendarMonth) {
        return;
    }
    
    // Find the corresponding calendar day
    const calendarDays = document.querySelectorAll('#calendar .calendar-day');
    const dayNumber = parseInt(day);
    
    calendarDays.forEach(dayElement => {
        const dayText = dayElement.textContent.trim();
        // Remove existing mood dots for comparison
        const dayNumberOnly = dayText.replace(/\s+/g, '').match(/^\d+/);
        
        if (dayNumberOnly && parseInt(dayNumberOnly[0]) === dayNumber) {
            // Remove existing mood dot
            const existingDot = dayElement.querySelector('.calendar-mood-dot');
            if (existingDot) {
                existingDot.remove();
            }
            
            // Add new mood dot using shared configuration
            if (mood >= 1 && mood <= 6) {
                const moodDot = document.createElement('div');
                moodDot.className = `calendar-mood-dot ${MOOD_COLORS[mood - 1]}`;
                moodDot.title = 'Mood';
                dayElement.appendChild(moodDot);
            }
            
            return; // End the loop when day is found
        }
    });
}

function selectDate(date) {
    // Check if changes are present - optimized comparison
    const hasChanges = () => {
        const currentContent = getEditorContent();
        return currentMood !== lastSavedMood || 
               currentContent !== lastSavedContent || 
               JSON.stringify(currentMusicData) !== JSON.stringify(lastSavedMusicData);
    };
    
    if (hasChanges()) {
        if (confirm('Du hast Änderungen vorgenommen. Möchtest du diese speichern, bevor du den Tag wechselst?')) {
            saveEntryWithCallback(() => {
                currentDate = date;
                loadEntry(date);
                highlightSelectedDate();
            });
            return;
        }
    }
    
    currentDate = date;
    loadEntry(date);
    highlightSelectedDate();
    
    // Close sidebar after date selection - reuse existing elements
    const sidebar = document.getElementById('sidebar');
    const blurOverlay = document.getElementById('blurOverlay');
    
    if (sidebar) sidebar.style.transform = 'translateX(-100%)';
    if (blurOverlay) {
        blurOverlay.classList.remove('blur-visible');
        blurOverlay.classList.add('blur-hidden');
        blurOverlay.style.pointerEvents = 'none';
    }
}

function highlightSelectedDate() {
    // Highlight selected date
    const days = document.querySelectorAll('#calendar .calendar-day');
    days.forEach(day => day.classList.remove('calendar-day-today'));
    if (event && event.target) {
        event.target.classList.add('calendar-day-today');
    }
    // Close sidepanel
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.add('sidebar-hidden');
}

function saveEntry() {
    saveEntryWithCallback(null);
}

function saveEntryWithCallback(callback) {
    const content = getEditorContent();
    fetch('php/save_entry.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            date: currentDate, 
            content: content, 
            mood: currentMood,
            music_title: currentMusicData.title || currentMusicTitle,
            music_api: currentMusicData.api,
            music_id: currentMusicData.id,
            music_artist: currentMusicData.artist,
            music_url: currentMusicData.url
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Eintrag gespeichert!', 'success');
            lastSavedContent = content;
            lastSavedMood = currentMood;
            lastSavedMusicTitle = currentMusicData.title || currentMusicTitle;
            lastSavedMusicData = { ...currentMusicData };
            
            // Update calendar mood immediately
            updateCalendarMood(currentDate, currentMood);
            
            // Execute callback if available
            if (callback && typeof callback === 'function') {
                // Short delay so the update is visible
                setTimeout(callback, 300);
            }
        } else {
            showNotification('Fehler beim Speichern: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Fehler beim Speichern!', 'error');
        console.error('Error:', error);
    });
}
// Custom Notification
function showNotification(message, type = 'info', duration = 3000) {
    const notif = document.createElement('div');
    const typeClass = type === 'success' ? 'notification-success' : type === 'error' ? 'notification-error' : 'notification-info';
    notif.className = `notification ${typeClass}`;
    notif.textContent = message;
    document.getElementById('notification-container').appendChild(notif);
    // Fade in
    setTimeout(() => notif.classList.add('notification-visible'), 10);
    // Fade out & remove
    setTimeout(() => {
        notif.classList.remove('notification-visible');
        setTimeout(() => notif.remove(), 500);
    }, duration);
}

function loadEntry(date) {
    updateEntryTitle(date);
    fetch('php/load_entry.php?date=' + date)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            setEditorContent(data.content || '');
            setMood(data.mood ? parseInt(data.mood) : 1);
            
            // Load complete music data - use factory function
            currentMusicTitle = data.music_title || '';
            currentMusicData = createMusicData(
                data.music_title || '',
                data.music_api,
                data.music_id,
                data.music_artist || '',
                data.music_url
            );
            
            // Display music title in search field
            const musicSearchInput = document.getElementById('musicSearchInput');
            if (musicSearchInput) {
                musicSearchInput.value = currentMusicTitle;
            }
            
            // Prepare music for playback if available
            if (currentMusicData.api && currentMusicData.id) {
                loadMusicForPlayback(currentMusicData);
            }
            
            lastSavedContent = getEditorContent();
            lastSavedMood = data.mood ? parseInt(data.mood) : 1;
            lastSavedMusicTitle = currentMusicTitle;
            lastSavedMusicData = { ...currentMusicData };
        } else {
            setEditorContent('');
            setMood(1);
            currentMusicTitle = '';
            currentMusicData = createMusicData();
            
            // Clear search field
            const musicSearchInput = document.getElementById('musicSearchInput');
            if (musicSearchInput) {
                musicSearchInput.value = '';
            }
            
            lastSavedContent = '';
            lastSavedMood = 1;
            lastSavedMusicTitle = '';
            lastSavedMusicData = { ...currentMusicData };
        }
    })
    .catch(error => console.error('Error:', error));
}

function loadMusicForPlayback(musicData) {
    const musicPlayBtn = document.getElementById('musicPlayBtn');
    const musicPauseBtn = document.getElementById('musicPauseBtn');
    
    if (!musicData || !musicData.api || !musicData.id) {
        return;
    }
    
    switch (musicData.api) {
        case 'youtube':
            // YouTube API laden falls nötig und Player vorbereiten
            if (!youtubeApiLoaded) {
                loadYouTubeAPI().then(() => {
                    prepareYouTubeMusic(musicData);
                }).catch(error => {
                    console.error('Error loading YouTube API:', error);
                });
            } else {
                prepareYouTubeMusic(musicData);
            }
            break;
            
        case 'itunes':
            prepareITunesMusic(musicData);
            break;
            
        case 'spotify':
            // Extend Spotify integration here
            console.log('Spotify Integrationnot implemented yet.');
            break;
    }
}

function prepareYouTubeMusic(musicData) {
    const musicPlayBtn = document.getElementById('musicPlayBtn');
    const musicPauseBtn = document.getElementById('musicPauseBtn');
    
    currentVideoId = musicData.id;
    
    // Initialize player if necessary
    if (!isPlayerReady && youtubeApiLoaded) {
        initYouTubePlayer();
    }
    
    // Show play/pause buttons
    if (musicPlayBtn && musicPauseBtn) {
        musicPlayBtn.classList.remove('hidden');
        musicPauseBtn.classList.add('hidden');
    }
}

function prepareITunesMusic(musicData) {
    const musicPlayBtn = document.getElementById('musicPlayBtn');
    const musicPauseBtn = document.getElementById('musicPauseBtn');
    
    if (musicData.url) {
        // Prepare audio element
        if (bgAudio) {
            bgAudio.pause();
        }
        
        bgAudio = new Audio(musicData.url);
        bgAudio.volume = 0.5;
        currentVideoId = musicData.url; // For play/pause control
        
        // Show play/pause buttons
        if (musicPlayBtn && musicPauseBtn) {
            musicPlayBtn.classList.remove('hidden');
            musicPauseBtn.classList.add('hidden');
        }
    }
}

function showMusicSearch(showPlayButton = true) {
    const musicSearchContainer = document.getElementById('musicSearchContainer');
    const musicPlayBtn = document.getElementById('musicPlayBtn');
    const musicPauseBtn = document.getElementById('musicPauseBtn');
    
    if (musicSearchContainer) {
        musicSearchContainer.classList.remove('hidden');
    }
    
    // Show or hide play/pause buttons depending on mode
    if (musicPlayBtn && musicPauseBtn) {
        if (showPlayButton) {
            musicPlayBtn.classList.remove('hidden');
            musicPauseBtn.classList.remove('hidden');
        } else {
            musicPlayBtn.classList.add('hidden');
            musicPauseBtn.classList.add('hidden');
        }
    }
}

function hideMusicSearch() {
    const musicSearchContainer = document.getElementById('musicSearchContainer');
    if (musicSearchContainer) {
        musicSearchContainer.classList.add('hidden');
    }
}

// YouTube Player global variables
let youtubePlayer = null;
let currentVideoId = null;
let isPlayerReady = false;
let youtubeApiLoaded = false;
let youtubeApiLoading = false;

// YouTube Player functions (globally available)
function initYouTubePlayer() {
    if (!youtubeApiLoaded || typeof YT === 'undefined' || !YT.Player) {
        console.error('YouTube API not loaded or YT.Player not available');
        return;
    }

    if (youtubePlayer) {
        // Player already initialized
        return;
    }

    try {
        youtubePlayer = new YT.Player('youtubePlayer', {
            height: '0',
            width: '0',
            playerVars: {
                autoplay: 0,
                controls: 0,
                disablekb: 1,
                fs: 0,
                iv_load_policy: 3,
                modestbranding: 1,
                playsinline: 1,
                rel: 0
            },
            events: {
                onReady: function(event) {
                    isPlayerReady = true;
                    console.log('YouTube Player ready');
                },
                onStateChange: onPlayerStateChange,
                onError: function(event) {
                    console.error('YouTube Player Error:', event.data);
                    showNotification('YouTube Player Fehler', 'error');
                }
            }
        });
    } catch (error) {
        console.error('Error initializing YouTube Player:', error);
        showNotification('Error loading YouTube Player', 'error');
    }
}

function onPlayerStateChange(event) {
    const musicPlayBtn = document.getElementById('musicPlayBtn');
    const musicPauseBtn = document.getElementById('musicPauseBtn');
    
    if (event.data === YT.PlayerState.PLAYING) {
        if (musicPlayBtn) musicPlayBtn.classList.add('hidden');
        if (musicPauseBtn) musicPauseBtn.classList.remove('hidden');
    } else if (event.data === YT.PlayerState.PAUSED || event.data === YT.PlayerState.ENDED) {
        if (musicPauseBtn) musicPauseBtn.classList.add('hidden');
        if (musicPlayBtn) musicPlayBtn.classList.remove('hidden');
    }
}

function initMusicSearch() {
    const musicSearchInput = document.getElementById('musicSearchInput');
    const musicSearchResults = document.getElementById('musicSearchResults');
    const musicPlayBtn = document.getElementById('musicPlayBtn');
    const musicPauseBtn = document.getElementById('musicPauseBtn');
    let searchTimeout = null;

    if (!musicSearchInput || !musicSearchResults) return;

    // Suchfeld Event-Listener
    musicSearchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        currentMusicTitle = query; // Sofort den aktuellen Wert speichern
        
        // Debounce search
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (query.length >= 2) {
                searchMusic(query);
            } else {
                hideMusicResults();
            }
        }, 300);
    });

    // Additional event listener for manual input without search
    musicSearchInput.addEventListener('blur', function(e) {
        currentMusicTitle = e.target.value.trim();
    });

    // Play/Pause button event listeners
    if (musicPlayBtn) {
        musicPlayBtn.addEventListener('click', function() {
            if (youtubePlayer && youtubePlayer.getVideoData && youtubePlayer.getVideoData().video_id) {
                // YouTube Player with loaded video
                youtubePlayer.playVideo();
                musicPlayBtn.classList.add('hidden');
                musicPauseBtn.classList.remove('hidden');
            } else if (youtubePlayer && currentVideoId && typeof currentVideoId === 'string' && currentVideoId.length === 11) {
                // YouTube Player with saved video ID
                youtubePlayer.loadVideoById(currentVideoId);
                setTimeout(() => {
                    youtubePlayer.playVideo();
                    musicPlayBtn.classList.add('hidden');
                    musicPauseBtn.classList.remove('hidden');
                }, 500);
            } else if (bgAudio && currentVideoId) {
                // iTunes/Audio Player
                bgAudio.play().then(() => {
                    musicPlayBtn.classList.add('hidden');
                    musicPauseBtn.classList.remove('hidden');
                }).catch(error => {
                    console.error('Audio play error:', error);
                    showNotification('Fehler beim Abspielen', 'error');
                });
            } else if (currentVideoId && youtubeApiLoaded && typeof YT !== 'undefined' && YT.Player) {
                // Initialize YouTube Player if not already done
                if (!isPlayerReady) {
                    initYouTubePlayer();
                    setTimeout(() => {
                        if (youtubePlayer && typeof currentVideoId === 'string' && currentVideoId.length === 11) {
                            youtubePlayer.loadVideoById(currentVideoId);
                            setTimeout(() => {
                                youtubePlayer.playVideo();
                                musicPlayBtn.classList.add('hidden');
                                musicPauseBtn.classList.remove('hidden');
                            }, 500);
                        }
                    }, 1000);
                } else if (youtubePlayer && typeof currentVideoId === 'string' && currentVideoId.length === 11) {
                    youtubePlayer.loadVideoById(currentVideoId);
                    setTimeout(() => {
                        youtubePlayer.playVideo();
                        musicPlayBtn.classList.add('hidden');
                        musicPauseBtn.classList.remove('hidden');
                    }, 500);
                }
            } else if (currentVideoId && !youtubeApiLoaded && typeof currentVideoId === 'string' && currentVideoId.length === 11) {
                // YouTube API not yet loaded, but YouTube video selected
                showNotification('YouTube API wird geladen...', 'info');
                loadYouTubeAPI().then(() => {
                    // Try again after loading
                    musicPlayBtn.click();
                }).catch(error => {
                    console.error('Error loading YouTube API:', error);
                    showNotification('Error loading YouTube API', 'error');
                });
            } else {
                showNotification('No music track selected', 'warning');
            }
        });
    }

    if (musicPauseBtn) {
        musicPauseBtn.addEventListener('click', function() {
            if (youtubePlayer && youtubePlayer.pauseVideo) {
                // YouTube Player
                youtubePlayer.pauseVideo();
                musicPauseBtn.classList.add('hidden');
                musicPlayBtn.classList.remove('hidden');
            } else if (bgAudio) {
                // iTunes/Audio Player
                bgAudio.pause();
                musicPauseBtn.classList.add('hidden');
                musicPlayBtn.classList.remove('hidden');
            }
        });
    }

    // Click outside closes search results
    document.addEventListener('click', function(e) {
        if (!musicSearchResults.contains(e.target) && !musicSearchInput.contains(e.target)) {
            hideMusicResults();
        }
    });

    function searchMusic(query) {
        // Get API type from settings
        fetch('php/app_settings.php?key=music.api')
            .then(response => response.json())
            .then(data => {
                const musicApi = data.success ? data.value : null;
                
                if (!musicApi || musicApi === '') {
                    musicSearchResults.innerHTML = '<div class="music-result-message music-result-warning">Bitte Musik-API in den Einstellungen auswählen</div>';
                    musicSearchResults.classList.remove('hidden');
                    return;
                }
                
                switch (musicApi) {
                    case 'local':
                        searchLocalMusic(query);
                        break;
                    case 'youtube':
                        searchYouTubeMusic(query);
                        break;
                    case 'spotify':
                        searchSpotifyMusic(query);
                        break;
                    case 'itunes':
                        searchITunesMusic(query);
                        break;
                    default:
                        musicSearchResults.innerHTML = '<div class="music-result-message music-result-error">Unbekannte Musik-API</div>';
                        musicSearchResults.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error fetching API settings:', error);
                musicSearchResults.innerHTML = '<div class="music-result-message music-result-error">Fehler beim Laden der Einstellungen</div>';
                musicSearchResults.classList.remove('hidden');
            });
    }

    function searchYouTubeMusic(query) {
        // Load YouTube API if not already done
        if (!youtubeApiLoaded) {
            musicSearchResults.innerHTML = '<div class="music-result-message music-result-info">YouTube API wird geladen...</div>';
            musicSearchResults.classList.remove('hidden');
            
            loadYouTubeAPI().then(() => {
                searchYouTubeMusic(query); // Try again
            }).catch(error => {
                console.error('Fehler beim Laden der YouTube API:', error);
                musicSearchResults.innerHTML = '<div class="music-result-message music-result-error">Fehler beim Laden der YouTube API</div>';
                musicSearchResults.classList.remove('hidden');
            });
            return;
        }
        
        fetch('php/app_settings.php?key=music.youtube.apiKey')
            .then(response => response.json())
            .then(data => {
                const apiKey = data.success ? data.value : null;
                
                if (!apiKey) {
                    musicSearchResults.innerHTML = '<div class="music-result-message music-result-warning">Bitte YouTube API Key in den Einstellungen eingeben</div>';
                    musicSearchResults.classList.remove('hidden');
                    return;
                }

                // YouTube Data API v3 Search
                const searchUrl = `https://www.googleapis.com/youtube/v3/search?part=snippet&maxResults=10&q=${encodeURIComponent(query + ' music')}&type=video&key=${apiKey}`;
                
                fetch(searchUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            throw new Error(data.error.message);
                        }
                        displayYouTubeResults(data.items, query);
                    })
                    .catch(error => {
                        console.error('YouTube API Error:', error);
                        musicSearchResults.innerHTML = '<div class="music-result-message music-result-error">YouTube API Fehler: ' + error.message + '</div>';
                        musicSearchResults.classList.remove('hidden');
                    });
            });
    }

    function searchSpotifyMusic(query) {
        // Spotify Web API Implementation
        fetch('php/app_settings.php?key=music.spotify.clientId')
            .then(response => response.json())
            .then(data => {
                const clientId = data.success ? data.value : null;
                if (!clientId) {
                    musicSearchResults.innerHTML = '<div class="music-result-message music-result-warning">Bitte Spotify Client ID in den Einstellungen eingeben</div>';
                    musicSearchResults.classList.remove('hidden');
                    return;
                }
                
                // Spotify Search API (simplified - normally OAuth is required)
                // A complete Spotify integration would be implemented here
                musicSearchResults.innerHTML = '<div class="music-result-message music-result-info">Spotify-Integration wird implementiert...</div>';
                musicSearchResults.classList.remove('hidden');
            });
    }

    function searchLocalMusic(query) {
        // Local music search
        const searchUrl = `php/search_local_music.php?q=${encodeURIComponent(query)}`;
        
        fetch(searchUrl)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    displayLocalResults(data.results, query);
                } else {
                    musicSearchResults.innerHTML = '<div class="music-result-message music-result-error">' + data.message + '</div>';
                    musicSearchResults.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Local Music Search Error:', error);
                musicSearchResults.innerHTML = '<div class="music-result-message music-result-error">Fehler beim Laden der lokalen Musik: ' + error.message + '</div>';
                musicSearchResults.classList.remove('hidden');
            });
    }

    function searchITunesMusic(query) {
        // iTunes Search API (free, no API key required)
        const searchUrl = `https://itunes.apple.com/search?term=${encodeURIComponent(query)}&media=music&limit=10`;
        
        fetch(searchUrl)
            .then(response => response.json())
            .then(data => {
                displayITunesResults(data.results, query);
            })
            .catch(error => {
                console.error('iTunes API Error:', error);
                musicSearchResults.innerHTML = '<div class="music-result-message music-result-error">iTunes API Fehler: ' + error.message + '</div>';
                musicSearchResults.classList.remove('hidden');
            });
    }

    function displayYouTubeResults(results, query) {
        if (!results || results.length === 0) {
            musicSearchResults.innerHTML = '<div class="music-result-message music-result-empty">Keine YouTube Ergebnisse für "' + query + '" gefunden</div>';
        } else {
            musicSearchResults.innerHTML = results.map(video => {
                const title = video.snippet.title;
                const channel = video.snippet.channelTitle;
                const thumbnail = video.snippet.thumbnails.default.url;
                
                return `
                    <div class="music-result" data-type="youtube" data-video-id="${video.id.videoId}" data-title="${title}" data-channel="${channel}">
                        <div class="music-result-content">
                            <img src="${thumbnail}" alt="${title}" class="music-result-thumbnail">
                            <div class="music-result-info">
                                <div class="music-result-title" title="${title}">${title}</div>
                                <div class="music-result-artist" title="${channel}">${channel}</div>
                            </div>
                            <div class="music-result-source music-result-youtube">
                                <i class="fa-brands fa-youtube"></i>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            // Event listeners for YouTube selection
            musicSearchResults.querySelectorAll('.music-result[data-type="youtube"]').forEach(result => {
                result.addEventListener('click', function() {
                    const videoId = this.getAttribute('data-video-id');
                    const title = this.getAttribute('data-title');
                    const channel = this.getAttribute('data-channel');
                    selectYouTubeMusic(videoId, title, channel);
                });
            });
        }
        
        musicSearchResults.classList.remove('hidden');
    }

    function displayLocalResults(results, query) {
        if (!results || results.length === 0) {
            musicSearchResults.innerHTML = '<div class="music-result-message music-result-empty">Keine lokalen Titel für "' + query + '" gefunden</div>';
        } else {
            musicSearchResults.innerHTML = results.map(track => {
                const title = track.title;
                const artist = track.artist;
                const album = track.album;
                const duration = track.duration;
                const genre = track.genre;
                
                // Convert seconds to MM:SS format
                const formatDuration = (seconds) => {
                    if (!seconds || seconds <= 0) return '';
                    const mins = Math.floor(seconds / 60);
                    const secs = Math.floor(seconds % 60);
                    return `${mins}:${secs.toString().padStart(2, '0')}`;
                };
                
                return `
                    <div class="music-result" data-type="local" data-id="${track.id}" data-title="${title}" data-artist="${artist}" data-album="${album}">
                        <div class="music-result-content">
                            <div class="music-result-icon">
                                <i class="fa-solid fa-music"></i>
                            </div>
                            <div class="music-result-info">
                                <div class="music-result-title" title="${title}">${title}</div>
                                <div class="music-result-artist" title="${artist} - ${album}">${artist} - ${album}</div>
                                <div class="music-result-details">
                                    <span class="music-duration">${formatDuration(duration)}</span>
                                    <span class="music-genre">${genre}</span>
                                </div>
                            </div>
                            <div class="music-result-source music-result-local">
                                <i class="fa-regular fa-hard-drive"></i>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            // Event listeners for local music selection
            musicSearchResults.querySelectorAll('.music-result[data-type="local"]').forEach(result => {
                result.addEventListener('click', function() {
                    const trackId = this.getAttribute('data-id');
                    const title = this.getAttribute('data-title');
                    const artist = this.getAttribute('data-artist');
                    const album = this.getAttribute('data-album');
                    selectLocalMusic(trackId, title, artist, album);
                });
            });
        }
        
        musicSearchResults.classList.remove('hidden');
    }

    function displayITunesResults(results, query) {
        if (!results || results.length === 0) {
            musicSearchResults.innerHTML = '<div class="music-result-message music-result-empty">Keine iTunes Ergebnisse für "' + query + '" gefunden</div>';
        } else {
            musicSearchResults.innerHTML = results.map(track => {
                const title = track.trackName;
                const artist = track.artistName;
                const album = track.collectionName;
                const artwork = track.artworkUrl30;
                const previewUrl = track.previewUrl;
                
                return `
                    <div class="music-result" data-type="itunes" data-preview-url="${previewUrl}" data-title="${title}" data-artist="${artist}">
                        <div class="music-result-content">
                            <img src="${artwork}" alt="${title}" class="music-result-thumbnail">
                            <div class="music-result-info">
                                <div class="music-result-title" title="${title}">${title}</div>
                                <div class="music-result-artist" title="${artist} - ${album}">${artist} - ${album}</div>
                            </div>
                            <div class="music-result-source music-result-itunes">
                                <i class="fa-brands fa-apple"></i>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            // Event listeners for iTunes selection
            musicSearchResults.querySelectorAll('.music-result[data-type="itunes"]').forEach(result => {
                result.addEventListener('click', function() {
                    const previewUrl = this.getAttribute('data-preview-url');
                    const title = this.getAttribute('data-title');
                    const artist = this.getAttribute('data-artist');
                    selectITunesMusic(previewUrl, title, artist);
                });
            });
        }
        
        musicSearchResults.classList.remove('hidden');
    }

    function hideMusicResults() {
        musicSearchResults.classList.add('hidden');
    }

    function selectYouTubeMusic(videoId, title, channel) {
        // Select and display YouTube music
        const fullTitle = `${title} - ${channel}`;
        musicSearchInput.value = fullTitle;
        currentMusicTitle = fullTitle;
        
        // Save complete music data using factory function
        currentMusicData = createMusicData(fullTitle, 'youtube', videoId, channel, null);
        
        hideMusicResults();
        
        if (videoId) {
            currentVideoId = videoId;
            
            // Ensure YouTube API is loaded
            if (!youtubeApiLoaded) {
                showNotification(`YouTube Musik ausgewählt: ${title} (API wird geladen...)`, 'info');
                
                loadYouTubeAPI().then(() => {
                    selectYouTubeMusic(videoId, title, channel); // Try again
                }).catch(error => {
                    console.error('Error loading YouTube API:', error);
                    showNotification('Error loading YouTube API', 'error');
                });
                return;
            }
            
            // YouTube API is loaded, initialize player if not already done
            if (!isPlayerReady) {
                initYouTubePlayer();
            }
            
            // Wait until player is ready, then load video
            const checkPlayerReady = () => {
                if (youtubePlayer && youtubePlayer.loadVideoById) {
                    youtubePlayer.loadVideoById(videoId);
                    // Show play/pause buttons
                    musicPlayBtn.classList.remove('hidden');
                    musicPauseBtn.classList.add('hidden');
                    showNotification(`YouTube Musik ausgewählt: ${title}`, 'success');
                } else if (!isPlayerReady) {
                    // Player is still being initialized, wait
                    setTimeout(checkPlayerReady, 500);
                } else {
                    // Player should be ready, but not fully loaded yet
                    setTimeout(checkPlayerReady, 500);
                }
            };
            
            if (youtubePlayer) {
                checkPlayerReady();
            } else {
                // Player is being initialized, wait briefly
                setTimeout(checkPlayerReady, 1000);
            }
        } else {
            showNotification('Fehler beim Laden der YouTube Musik', 'error');
        }
    }

    function selectLocalMusic(trackId, title, artist, album) {
        // Select and display local music
        const fullTitle = `${title} - ${artist}`;
        musicSearchInput.value = fullTitle;
        currentMusicTitle = fullTitle;
        
        // Save complete music data using factory function
        currentMusicData = createMusicData(fullTitle, 'local', trackId, artist, album);
        
        hideMusicResults();
        showNotification('Lokaler Titel ausgewählt: ' + fullTitle, 'success');
        
        // No player is initialized for local music
        // since only the title information is saved
    }

    function selectITunesMusic(previewUrl, title, artist) {
        // iTunes Musik auswählen und anzeigen
        const fullTitle = `${title} - ${artist}`;
        musicSearchInput.value = fullTitle;
        currentMusicTitle = fullTitle;
        
        // Save complete music data using factory function
        currentMusicData = createMusicData(fullTitle, 'itunes', previewUrl, artist, previewUrl);
        
        hideMusicResults();
        
        if (previewUrl) {
            // iTunes uses audio element instead of YouTube Player
            if (bgAudio) {
                bgAudio.pause();
            }
            
            bgAudio = new Audio(previewUrl);
            bgAudio.volume = 0.5;
            currentVideoId = previewUrl; // For play/pause control
            
            // Show play/pause buttons
            musicPlayBtn.classList.remove('hidden');
            musicPauseBtn.classList.add('hidden');
            
            showNotification(`iTunes Musik ausgewählt: ${title} (30s Preview)`, 'success');
        } else {
            showNotification('Keine Vorschau verfügbar', 'error');
        }
    }
}

// Moved from inline script
function initSidebarToggle() {
    const sidebar = document.getElementById('sidebar');
    const sidebarTab = document.getElementById('sidebarTab');
    const closeSidebar = document.getElementById('closeSidebar');
    const toggleSidebar = document.getElementById('toggleSidebar');
    
    function openSidebar() {
        sidebar.style.transform = 'translateX(0)';
    }
    
    function closeSidebarFn() {
        sidebar.style.transform = 'translateX(-100%)';
    }
    
    // Event listeners for sidebar
    if (sidebarTab) {
        sidebarTab.addEventListener('click', openSidebar);
    }
    
    if (closeSidebar) {
        closeSidebar.addEventListener('click', closeSidebarFn);
    }
    
    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', openSidebar);
    }
    
    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (sidebar && !sidebar.contains(e.target) && 
            (!sidebarTab || !sidebarTab.contains(e.target)) && 
            (!toggleSidebar || !toggleSidebar.contains(e.target))) {
            closeSidebarFn();
        }
    });
}

// Moved from inline script
function initMusicInfoBubble() {
    const musicInfoBtn = document.getElementById('musicInfoBtn');
    const musicInfoBubble = document.getElementById('musicInfoBubble');
    
    if (musicInfoBtn && musicInfoBubble) {
        musicInfoBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = musicInfoBubble.classList.contains('opacity-100');
            
            if (isVisible) {
                // Hide bubble
                musicInfoBubble.classList.remove('opacity-100', 'scale-100', 'pointer-events-auto');
                musicInfoBubble.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
            } else {
                // Show bubble
                musicInfoBubble.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
                musicInfoBubble.classList.add('opacity-100', 'scale-100', 'pointer-events-auto');
            }
        });
        
        // Hide bubble when clicking outside
        document.addEventListener('click', function(e) {
            if (!musicInfoBtn.contains(e.target) && !musicInfoBubble.contains(e.target)) {
                musicInfoBubble.classList.remove('opacity-100', 'scale-100', 'pointer-events-auto');
                musicInfoBubble.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
            }
        });
    }
}