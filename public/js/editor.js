// editor.js - Rich Text Editor and Toolbar Logic

let quill;
let recognition = null;
let isRecording = false;

// Initialize Editor
function initEditor() {
    // Set Clean-Button Icon (FontAwesome Brush)
    setTimeout(() => {
        const toolbar = document.querySelector('.ql-toolbar');
        if (toolbar) {
            const cleanBtn = toolbar.querySelector('button.ql-clean');
            if (cleanBtn) {
                cleanBtn.innerHTML = '<i class="fa fa-brush rotate-180"></i>';
                cleanBtn.title = 'Formatierung entfernen';
            }
        }
    }, 100);

    // Set Tailwind class for zoom cursor on images in the editor
    document.getElementById('editor').addEventListener('mouseover', function(e) {
        if (e.target.tagName === 'IMG') {
            e.target.classList.add('cursor-zoom-in');
        }
    });
    document.getElementById('editor').addEventListener('mouseout', function(e) {
        if (e.target.tagName === 'IMG') {
            e.target.classList.remove('cursor-zoom-in');
        }
    });

    // Combine CSS styles into one style element for better performance
    const editorStyles = document.createElement('style');
    editorStyles.innerHTML = `
        #editor img { 
            cursor: zoom-in; 
            max-width: 500px; 
            max-height: 500px; 
            height: auto; 
            width: auto; 
        }
    `;
    document.head.appendChild(editorStyles);

    // Overlay for image fullscreen
    createImageOverlay();

    // Event delegation for images in editor
    document.getElementById('editor').addEventListener('click', function(e) {
        if (e.target.tagName === 'IMG') {
            const overlayImg = document.getElementById('img-overlay-img');
            const imgOverlay = document.getElementById('img-overlay');
            overlayImg.src = e.target.src;
            imgOverlay.style.display = 'flex';
        }
    });

    // Initialize Quill Editor with Toolbar
    quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Schreibe deinen Tagebucheintrag...',
        modules: {
            toolbar: {
                container: '#toolbar',
                handlers: {
                    speech: speechToTextHandler,
                    image: imageHandler
                }
            }
        }
    });

    // Insert Speech-to-Text Button Icon
    setTimeout(() => {
        const toolbar = document.querySelector('.ql-toolbar');
        if (toolbar && !toolbar.querySelector('.ql-speech')) {
            const btn = toolbar.querySelector('button.ql-speech');
            if (btn) {
                btn.innerHTML = '<i class="fa fa-microphone"></i>';
                btn.title = 'Spracheingabe';
            }
        }
    }, 100);

    return quill;
}

// Create Image Overlay
function createImageOverlay() {
    const imgOverlay = document.createElement('div');
    imgOverlay.id = 'img-overlay';
    imgOverlay.style.display = 'none';
    imgOverlay.style.position = 'fixed';
    imgOverlay.style.top = '0';
    imgOverlay.style.left = '0';
    imgOverlay.style.width = '100vw';
    imgOverlay.style.height = '100vh';
    imgOverlay.style.background = 'rgba(0,0,0,0.85)';
    imgOverlay.style.zIndex = '9999';
    imgOverlay.style.justifyContent = 'center';
    imgOverlay.style.alignItems = 'center';
    imgOverlay.style.flexDirection = 'column';
    imgOverlay.innerHTML = '<button id="closeImgOverlay" style="position:absolute;top:32px;right:32px;font-size:2rem;background:none;border:none;color:#fff;cursor:pointer;z-index:10001">&times;</button><img id="img-overlay-img" style="max-width:90vw;max-height:90vh;border-radius:8px;box-shadow:0 0 32px #000;" />';
    document.body.appendChild(imgOverlay);
    
    document.getElementById('closeImgOverlay').onclick = function() {
        imgOverlay.style.display = 'none';
    };
    imgOverlay.onclick = function(e) {
        if (e.target === imgOverlay) imgOverlay.style.display = 'none';
    };
}

// Speech-to-Text Handler
function speechToTextHandler() {
    const btn = document.querySelector('.ql-speech');
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        if (window.showNotification) {
            window.showNotification('Speech-to-Text wird von deinem Browser nicht unterstützt.', 'error');
        }
        return;
    }

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    
    if (!isRecording) {
        recognition = new SpeechRecognition();
        recognition.lang = 'de-DE';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;
        recognition.continuous = true;
        
        isRecording = true;
        btn.classList.add('text-lime-500');
        
        if (window.showNotification) {
            window.showNotification('Sprich jetzt... (erneut klicken zum Stoppen)');
        }
        
        recognition.start();
        
        recognition.onresult = function(event) {
            let transcript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                transcript += event.results[i][0].transcript + ' ';
            }
            
            // Remove filler words
            const filtered = processTranscript(transcript);
            
            // Insert text at the end and set cursor to end
            const length = quill.getLength();
            quill.insertText(length - 1, filtered, 'user');
            quill.setSelection(quill.getLength() - 1, 0, 'user');
        };
        
        recognition.onerror = function(event) {
            if (window.showNotification) {
                window.showNotification('Spracherkennung Fehler: ' + event.error, 'error');
            }
        };
        
        recognition.onend = function() {
            // Only automatically restart if not manually stopped
            if (isRecording) {
                recognition.start();
            } else {
                btn.classList.remove('text-lime-500');
            }
        };
    } else {
        if (recognition) {
            isRecording = false;
            recognition.stop();
            btn.classList.remove('text-lime-500');
            if (window.showNotification) {
                window.showNotification('Aufnahme gestoppt.', 'info');
            }
        }
    }
}

// Process transcript (filler words and punctuation) - optimized array
function processTranscript(transcript) {
    // Optimized filler words list - removed redundant "ja + word" combinations
    const baseFillerWords = [
        'äh', 'ähm', 'hm', 'hmm', 'mm', 'mhm', 'sozusagen', 'halt', 'irgendwie', 
        'also', 'quasi', 'ja', 'ne', 'nun', 'tja', 'tjoa', 'tjo', 'joa', 'jo', 'yo',
        'eigentlich', 'gell', 'dings', 'und so weiter', 'naja', 'okay', 'so', 'mal', 
        'schon', 'eben', 'am Ende', 'weißt du', 'ganz ehrlich', 'im Prinzip', 
        'letztendlich', 'im Grunde', 'im Endeffekt', 'im Großen und Ganzen', 
        'wie gesagt', 'na', 'eh'
    ];
    
    // Create combined list with "ja + word" variants for most common ones
    const commonJaCombinations = ['also', 'gut', 'klar', 'ne', 'okay', 'so', 'schon'];
    const fillerWords = [
        ...baseFillerWords,
        ...commonJaCombinations.map(word => `ja ${word}`)
    ];
    
    // Regex for filler words (word boundaries, case-insensitive)
    const fillerRegex = new RegExp('\\b(' + fillerWords.join('|') + ')\\b', 'gi');
    let filtered = transcript.replace(fillerRegex, '').replace(/\s{2,}/g, ' ').trim();

    // Replace spoken punctuation marks
    const punctuationMap = [
        { word: 'komma', symbol: ',' },
        { word: 'punkt', symbol: '.' },
        { word: 'fragezeichen', symbol: '?' },
        { word: 'ausrufezeichen', symbol: '!' },
        { word: 'doppelpunkt', symbol: ':' },
        { word: 'strich', symbol: '-' },
        { word: 'minus', symbol: '-' },
        { word: 'semikolon', symbol: ';' },
        { word: 'anführungszeichen', symbol: '"' },
        { word: 'släsh', symbol: '/' }
    ];
    
    punctuationMap.forEach(({ word, symbol }) => {
        // Replace the word only when spoken as a separate word
        const regex = new RegExp('(?:\\s|^)' + word + '(?:\\s|$)', 'gi');
        filtered = filtered.replace(regex, match => {
            // Replace the found word with the punctuation mark, preserve spaces
            let before = match.startsWith(' ') ? ' ' : '';
            let after = match.endsWith(' ') ? ' ' : '';
            return before + symbol + after;
        });
    });
    
    return filtered.replace(/\s{2,}/g, ' ').trim();
}

// Image Handler for Toolbar
function imageHandler() {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');
    input.onchange = function() {
        const file = input.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new window.Image();
            img.onload = function() {
                // Proportionally scale to maximum 1000x1000 (only for DataURL, display via CSS)
                const maxWidth = 1000;
                const maxHeight = 1000;
                let scale = Math.min(maxWidth / img.width, maxHeight / img.height, 1);
                const newWidth = Math.round(img.width * scale);
                const newHeight = Math.round(img.height * scale);
                
                const canvas = document.createElement('canvas');
                canvas.width = newWidth;
                canvas.height = newHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, newWidth, newHeight);
                
                // Compressed as JPEG with quality 0.7
                const scaledDataUrl = canvas.toDataURL('image/jpeg', 0.7);
                const range = quill.getSelection();
                quill.insertEmbed(range ? range.index : 0, 'image', scaledDataUrl, 'user');
                
                // Remove width/height attributes after insertion
                setTimeout(() => {
                    const imgs = quill.root.querySelectorAll('img');
                    imgs.forEach(imgEl => {
                        if (imgEl.src === scaledDataUrl) {
                            imgEl.removeAttribute('width');
                            imgEl.removeAttribute('height');
                            imgEl.style.width = '';
                            imgEl.style.height = '';
                        }
                    });
                }, 10);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    };
    input.click();
}

// Stop Speech-to-Text (for external use)
function stopSpeechRecording() {
    if (typeof isRecording !== 'undefined' && isRecording && typeof recognition !== 'undefined' && recognition) {
        isRecording = false;
        recognition.stop();
        const btn = document.querySelector('.ql-speech');
        if (btn) btn.classList.remove('text-lime-500');
    }
}

// Get Editor Content
function getEditorContent() {
    return quill ? quill.root.innerHTML : '';
}

// Set Editor Content
function setEditorContent(content) {
    if (quill) {
        quill.root.innerHTML = content || '';
    }
}

// Get Quill Instance
function getQuillInstance() {
    return quill;
}

// Exports
window.initEditor = initEditor;
window.stopSpeechRecording = stopSpeechRecording;
window.getEditorContent = getEditorContent;
window.setEditorContent = setEditorContent;
window.getQuillInstance = getQuillInstance;
