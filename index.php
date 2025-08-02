<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZORNELL - Fast Notes</title>
    <meta name="description" content="Fast, efficient note-taking app with multi-select and keyboard shortcuts">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ZORNELL</h1>
            <div class="controls">
                <div class="search-delete-container">
                    <input type="text" class="search-box" placeholder="Search..." id="searchBox" spellcheck="true" autocomplete="on" autocorrect="on" aria-label="Search notes">
                    <button class="delete-selected-btn" id="deleteSelectedBtn" onclick="deleteSelectedNotes()">DELETE <span id="deleteCount">0</span></button>
                </div>
                <button class="filter-btn active" onclick="setFilter('all', this)">ALL</button>
                <button class="filter-btn" onclick="setFilter('work', this)">WORK</button>
                <button class="filter-btn" onclick="setFilter('personal', this)">PERSONAL</button>
                <button class="filter-btn" onclick="setFilter('urgent', this)">URGENT</button>
                <button class="export-btn" onclick="showExportMenu()" title="More options">⋮</button>
                <div class="export-menu" id="exportMenu" style="display: none;">
                <button class="export-option" onclick="exportAsJSON()">Export as JSON</button>
                <button class="export-option" onclick="exportAsText()">Export as TXT</button>
                    <button class="export-option" onclick="window.print()">Print / PDF</button>
                </div>
            </div>
        </div>

        <div class="notes-container" id="notesContainer">
            <div class="note-card add-note-placeholder" onclick="addNewNote()">
                <div class="placeholder-content">
                    <span class="plus-icon">+</span>
                    <span class="placeholder-text">New Note</span>
                </div>
            </div>
        </div>


        <button class="add-note mobile-only" onclick="addNewNote()">+</button>
    </div>

    <script>
        // Store notes in memory for O(1) access
        const notesMap = new Map();
        let currentFilter = 'all';
        let noteIdCounter = 1;
        const selectedNotes = new Set();
        let lastClickedNote = null;
        let renderScheduled = false;

        // Initialize with sample notes
        function initSampleNotes() {
            const sampleNotes = [
                {
                    id: noteIdCounter++,
                    title: 'Q1 Project Planning',
                    content: '• Define project scope and deliverables\n• Set up team meetings for next week\n• Review budget allocation\n• Create timeline with milestones',
                    type: 'work',
                    urgent: true,
                    date: new Date().toLocaleDateString()
                },
                {
                    id: noteIdCounter++,
                    title: 'Weekend Plans',
                    content: '• Grocery shopping Saturday morning\n• Gym session at 3pm\n• Dinner with friends at 7pm\n• Read new book Sunday',
                    type: 'personal',
                    urgent: false,
                    date: new Date().toLocaleDateString()
                }
            ];

            sampleNotes.forEach(note => notesMap.set(note.id, note));
        }

        function addNewNote() {
            const note = {
                id: noteIdCounter++,
                title: 'New Note',
                content: 'Start typing...',
                type: 'personal',
                urgent: false,
                date: new Date().toLocaleDateString(),
                isNew: true
            };
            
            notesMap.set(note.id, note);
            
            // Optimize rendering for single note addition
            const container = document.getElementById('notesContainer');
            const placeholder = container.querySelector('.add-note-placeholder');
            const noteElement = createNoteElement(note);
            
            // Insert new note before placeholder with animation
            container.insertBefore(noteElement, placeholder);
            
            // Trigger reflow for animation
            noteElement.offsetHeight;
            noteElement.classList.add('new-note');
            
            // Remove animation class after completion
            setTimeout(() => {
                noteElement.classList.remove('new-note');
                delete note.isNew;
            }, 400);
            
            saveToLocalStorage();
            
            // Focus on the new note's title
            requestAnimationFrame(() => {
                const titleElement = noteElement.querySelector('.note-title');
                if (titleElement) {
                    titleElement.focus();
                    // Select all text for easy replacement
                    const range = document.createRange();
                    range.selectNodeContents(titleElement);
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            });
        }

        function setFilter(filter, btn) {
            currentFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderNotes();
        }

        function filterNotes() {
            renderNotes();
        }

        // Debounce function for search
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function renderNotes() {
            if (renderScheduled) return;
            renderScheduled = true;
            
            // Use requestAnimationFrame for smooth rendering
            requestAnimationFrame(() => {
                renderScheduled = false;
                const container = document.getElementById('notesContainer');
                const searchTerm = document.getElementById('searchBox').value.toLowerCase();
                
                // Convert Map to array and filter
                const notesArray = Array.from(notesMap.values());
                
                const filteredNotes = notesArray.filter(note => {
                    // Filter by type
                    let matchesFilter = currentFilter === 'all';
                    if (currentFilter === 'work') matchesFilter = note.type === 'work';
                    if (currentFilter === 'personal') matchesFilter = note.type === 'personal';
                    if (currentFilter === 'urgent') matchesFilter = note.urgent === true;
                    
                    // Filter by search - optimized with early returns
                    if (!matchesFilter) return false;
                    
                    const matchesSearch = !searchTerm || 
                        note.title.toLowerCase().includes(searchTerm) || 
                        note.content.toLowerCase().includes(searchTerm);
                    
                    return matchesSearch;
                });

                // Use DocumentFragment for batch DOM updates
                const fragment = document.createDocumentFragment();
            
                // Reuse existing elements where possible
                const existingElements = new Map();
                container.querySelectorAll('.note-card:not(.add-note-placeholder)').forEach(el => {
                    existingElements.set(parseInt(el.dataset.id), el);
                });
                
                filteredNotes.forEach(note => {
                    let noteCard = existingElements.get(note.id);
                    if (noteCard) {
                        // Reuse existing element
                        existingElements.delete(note.id);
                    } else {
                        // Create new element
                        noteCard = createNoteElement(note);
                    }
                    fragment.appendChild(noteCard);
                });
                
                // Remove unused elements
                existingElements.forEach(el => el.remove());
                
                // Clear container and add all at once
                container.innerHTML = '';
                container.appendChild(fragment);
                
                // Add placeholder last
                const newPlaceholder = document.createElement('div');
                newPlaceholder.className = 'note-card add-note-placeholder';
                newPlaceholder.onclick = addNewNote;
                newPlaceholder.innerHTML = `
                    <div class="placeholder-content">
                        <span class="plus-icon">+</span>
                        <span class="placeholder-text">New Note</span>
                    </div>
                `;
                container.appendChild(newPlaceholder);
            });
        }

        function createNoteElement(note) {
            const div = document.createElement('div');
            div.className = `note-card ${note.type}${note.urgent ? ' urgent' : ''}${selectedNotes.has(note.id) ? ' selected' : ''}`;
            div.dataset.id = note.id;
            
            // Add click handler for selection
            div.onclick = (e) => handleNoteClick(e, note.id);
            
            div.innerHTML = `
                <div class="note-header">
                    <div class="note-title" contenteditable="true" spellcheck="true" autocomplete="on" autocorrect="on" autocapitalize="on" onblur="updateNote(${note.id}, 'title', this.textContent)" onmousedown="handleContentClick(event, ${note.id})" onclick="event.stopPropagation()">${note.title}</div>
                    <div class="note-meta">
                        <span class="tag ${note.type}">${note.type.toUpperCase()}</span>
                        ${note.urgent ? '<span class="tag urgent">URGENT</span>' : ''}
                    </div>
                </div>
                <div class="note-content" contenteditable="true" spellcheck="true" autocomplete="on" autocorrect="on" autocapitalize="sentences" onblur="updateNote(${note.id}, 'content', this.innerText)" onmousedown="handleContentClick(event, ${note.id})" onclick="event.stopPropagation()">${note.content}</div>
                <div class="note-footer">
                    <span class="note-date">${note.date}</span>
                    <div class="note-actions">
                        <button class="action-btn" onclick="event.stopPropagation(); toggleType(${note.id})">${note.type === 'work' ? 'PERSONAL' : 'WORK'}</button>
                        <button class="action-btn" onclick="event.stopPropagation(); toggleUrgent(${note.id})">URGENT</button>
                        <button class="action-btn" onclick="event.stopPropagation(); deleteNote(${note.id})">DELETE</button>
                    </div>
                </div>
            `;
            
            return div;
        }

        function updateNote(id, field, value) {
            const note = notesMap.get(id);
            if (note) {
                note[field] = value;
                saveToLocalStorage();
            }
        }

        function toggleType(id) {
            const note = notesMap.get(id);
            if (note) {
                note.type = note.type === 'work' ? 'personal' : 'work';
                renderNotes();
                saveToLocalStorage();
            }
        }

        function toggleUrgent(id) {
            const note = notesMap.get(id);
            if (note) {
                note.urgent = !note.urgent;
                renderNotes();
                saveToLocalStorage();
            }
        }

        function handleContentClick(event, noteId) {
            // If ctrl/cmd is held and ANY notes are selected, prevent focus
            if ((event.ctrlKey || event.metaKey) && selectedNotes.size > 0) {
                event.preventDefault();
                event.stopPropagation();
                
                // Toggle selection
                const noteCard = document.querySelector(`[data-id="${noteId}"]`);
                if (selectedNotes.has(noteId)) {
                    selectedNotes.delete(noteId);
                    noteCard.classList.remove('selected');
                } else {
                    selectedNotes.add(noteId);
                    noteCard.classList.add('selected');
                }
                updateSelectionUI();
            }
        }

        function deleteNote(id) {
            if (confirm('Delete this note?')) {
                const noteElement = document.querySelector(`[data-id="${id}"]`);
                if (noteElement) {
                    noteElement.classList.add('deleting');
                    setTimeout(() => {
                        notesMap.delete(id);
                        selectedNotes.delete(id);
                        noteElement.remove();
                        saveToLocalStorage();
                        updateSelectionUI();
                    }, 300);
                } else {
                    notesMap.delete(id);
                    selectedNotes.delete(id);
                    saveToLocalStorage();
                    updateSelectionUI();
                }
            }
        }

        function duplicateNote(id) {
            const original = notesMap.get(id);
            if (original) {
                const duplicate = {
                    ...original,
                    id: noteIdCounter++,
                    title: original.title + ' (Copy)',
                    date: new Date().toLocaleDateString()
                };
                notesMap.set(duplicate.id, duplicate);
                renderNotes();
                saveToLocalStorage();
            }
        }

        function exportAsJSON() {
            const notes = Array.from(notesMap.values());
            const blob = new Blob([JSON.stringify(notes, null, 2)], { type: 'application/json' });
            downloadFile(blob, 'zornell_notes.json');
        }

        function exportAsText() {
            let text = 'ZORNELL NOTES\n' + '='.repeat(50) + '\n\n';
            
            notesMap.forEach(note => {
                text += `[${note.type.toUpperCase()}${note.urgent ? ' - URGENT' : ''}] ${note.date}\n`;
                text += note.title + '\n';
                text += '-'.repeat(30) + '\n';
                text += note.content + '\n';
                text += '\n\n';
            });
            
            const blob = new Blob([text], { type: 'text/plain' });
            downloadFile(blob, 'zornell_notes.txt');
        }

        function downloadFile(blob, filename) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        }

        function saveToLocalStorage() {
            try {
                const notes = Array.from(notesMap.values());
                localStorage.setItem('zornellNotes', JSON.stringify(notes));
            } catch (e) {
                // Handle quota exceeded or other storage errors
                if (e.name === 'QuotaExceededError') {
                    alert('Storage quota exceeded. Please delete some notes.');
                }
            }
        }

        function loadFromLocalStorage() {
            const saved = localStorage.getItem('zornellNotes');
            if (saved) {
                try {
                    const notes = JSON.parse(saved);
                    notesMap.clear();
                    notes.forEach(note => {
                        notesMap.set(note.id, note);
                        noteIdCounter = Math.max(noteIdCounter, note.id + 1);
                    });
                } catch (e) {
                    // console.error('Failed to load notes:', e);
                    initSampleNotes();
                }
            } else {
                initSampleNotes();
            }
        }

        // Initialize
        loadFromLocalStorage();
        renderNotes();

        // Auto-save every 10 seconds
        setInterval(saveToLocalStorage, 10000);

        // Debounced search
        const debouncedSearch = debounce(filterNotes, 150);
        document.getElementById('searchBox').addEventListener('input', debouncedSearch);

        // Export menu functionality
        function showExportMenu() {
            const menu = document.getElementById('exportMenu');
            const exportBtn = event.target;
            const isVisible = menu.style.display === 'block';
            
            menu.style.display = isVisible ? 'none' : 'block';
            exportBtn.classList.toggle('active', !isVisible);
        }

        // Close export menu when clicking outside
        document.addEventListener('click', (e) => {
            const menu = document.getElementById('exportMenu');
            const exportBtn = e.target.closest('.export-btn');
            if (!exportBtn && !menu.contains(e.target)) {
                menu.style.display = 'none';
                document.querySelector('.export-btn').classList.remove('active');
            }
        });

        // Multi-select functionality
        function handleNoteClick(e, noteId) {
            const noteCard = e.currentTarget;
            
            // Batch DOM operations
            requestAnimationFrame(() => {
                if (e.ctrlKey || e.metaKey) {
                    // Ctrl/Cmd+Click: Toggle selection
                    if (selectedNotes.has(noteId)) {
                        selectedNotes.delete(noteId);
                        noteCard.classList.remove('selected');
                    } else {
                        selectedNotes.add(noteId);
                        noteCard.classList.add('selected');
                    }
                } else if (e.shiftKey && lastClickedNote !== null) {
                    // Shift+Click: Select range
                    selectRange(lastClickedNote, noteId);
                } else {
                    // Regular click: Select only this note
                    if (selectedNotes.size > 0) clearSelection();
                    selectedNotes.add(noteId);
                    noteCard.classList.add('selected');
                }
                
                lastClickedNote = noteId;
                updateSelectionUI();
            });
        }

        function selectRange(startId, endId) {
            const noteElements = Array.from(document.querySelectorAll('.note-card:not(.add-note-placeholder)'));
            const noteIds = noteElements.map(el => parseInt(el.dataset.id));
            
            const startIndex = noteIds.indexOf(startId);
            const endIndex = noteIds.indexOf(endId);
            
            if (startIndex === -1 || endIndex === -1) return;
            
            const minIndex = Math.min(startIndex, endIndex);
            const maxIndex = Math.max(startIndex, endIndex);
            
            clearSelection();
            
            for (let i = minIndex; i <= maxIndex; i++) {
                const id = noteIds[i];
                selectedNotes.add(id);
                noteElements[i].classList.add('selected');
            }
        }

        function clearSelection() {
            selectedNotes.clear();
            document.querySelectorAll('.note-card.selected').forEach(el => {
                el.classList.remove('selected');
            });
        }

        function updateSelectionUI() {
            const deleteCount = document.getElementById('deleteCount');
            const searchBox = document.getElementById('searchBox');
            
            requestAnimationFrame(() => {
                if (selectedNotes.size > 0) {
                    document.body.classList.add('has-selection');
                    deleteCount.textContent = selectedNotes.size;
                    // Clear search when entering selection mode
                    if (searchBox.value) {
                        searchBox.value = '';
                        renderNotes();
                    }
                } else {
                    document.body.classList.remove('has-selection');
                }
            });
        }

        function deleteSelectedNotes() {
            if (selectedNotes.size === 0) return;
            
            const count = selectedNotes.size;
            const message = count === 1 ? 'Delete this note?' : `Delete ${count} notes?`;
            
            if (confirm(message)) {
                // Animate all selected notes
                const noteElements = [];
                selectedNotes.forEach(id => {
                    const noteElement = document.querySelector(`[data-id="${id}"]`);
                    if (noteElement) {
                        noteElement.classList.add('deleting');
                        noteElements.push(noteElement);
                    }
                });
                
                // Remove after animation
                setTimeout(() => {
                    selectedNotes.forEach(id => {
                        notesMap.delete(id);
                    });
                    noteElements.forEach(el => el.remove());
                    clearSelection();
                    saveToLocalStorage();
                    updateSelectionUI();
                }, 300);
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Check if user is editing text
            const isEditingText = e.target.contentEditable === 'true' || 
                                 e.target.tagName === 'INPUT' || 
                                 e.target.tagName === 'TEXTAREA';
            
            // Ctrl/Cmd+A: Select all (only when not editing text)
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !isEditingText) {
                e.preventDefault();
                selectAll();
            }
            
            // Delete key: Delete selected notes
            if ((e.key === 'Delete' || e.key === 'Backspace') && selectedNotes.size > 0) {
                // Don't delete if user is editing text
                if (isEditingText) return;
                e.preventDefault();
                deleteSelectedNotes();
            }
            
            // Escape: Clear selection
            if (e.key === 'Escape') {
                clearSelection();
                updateSelectionUI();
            }
        });

        function selectAll() {
            clearSelection();
            document.querySelectorAll('.note-card:not(.add-note-placeholder)').forEach(el => {
                const id = parseInt(el.dataset.id);
                selectedNotes.add(id);
                el.classList.add('selected');
            });
            updateSelectionUI();
        }
    </script>
</body>
</html>