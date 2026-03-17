document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('add-word-form');
    const thaiInput = document.getElementById('thai-word');
    const engInput = document.getElementById('eng-word');
    const tbody = document.getElementById('word-list-body');
    const messageEl = document.getElementById('add-message');

    const loadWords = async () => {
        try {
            const res = await fetch('api/words.php');
            const words = await res.json();
            renderTable(words);
        } catch (error) {
            console.error('Failed to load words', error);
        }
    };

    const renderTable = (words) => {
        tbody.innerHTML = '';
        if (words.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color: var(--text-muted);">No words added yet.</td></tr>';
            return;
        }

        // Reverse to show newest first
        words.slice().reverse().forEach(word => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHTML(word.thai_word)}</td>
                <td>${escapeHTML(word.english_word)}</td>
                <td>
                    <button class="btn-delete" data-id="${word.id}">Delete</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
                deleteWord(id);
            });
        });
    };

    const deleteWord = async (id) => {
        if (!confirm('Are you sure you want to delete this word?')) return;
        try {
            const res = await fetch('api/words.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            if (data.success) {
                loadWords();
            } else {
                alert(data.error || 'Failed to delete');
            }
        } catch (error) {
            console.error('Error deleting word', error);
            alert('Failed to delete word');
        }
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const thai_word = thaiInput.value.trim();
        const english_word = engInput.value.trim();

        if (!thai_word || !english_word) return;

        try {
            const res = await fetch('api/words.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ thai_word, english_word })
            });
            const data = await res.json();
            
            if (data.success) {
                showMessage('Word added successfully!', 'success');
                thaiInput.value = '';
                engInput.value = '';
                loadWords();
                thaiInput.focus();
            } else {
                showMessage(data.error || 'Failed to add word', 'error');
            }
        } catch (error) {
            console.error('Error adding word', error);
            showMessage('Network error. Failed to add word.', 'error');
        }
    });

    const showMessage = (msg, type) => {
        messageEl.textContent = msg;
        messageEl.className = `message ${type}`;
        setTimeout(() => {
            messageEl.classList.add('hidden');
        }, 3000);
    };

    const escapeHTML = (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    loadWords();
});
