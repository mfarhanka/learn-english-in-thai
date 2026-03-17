document.addEventListener('DOMContentLoaded', () => {
    let words = [];
    let currentWord = null;
    let score = 0;

    const displayEl = document.getElementById('thai-display');
    const inputEl = document.getElementById('eng-input');
    const form = document.getElementById('game-form');
    const checkBtn = document.getElementById('submit-btn');
    const showAnswerBtn = document.getElementById('show-answer-btn');
    const nextBtn = document.getElementById('next-btn');
    const feedbackEl = document.getElementById('feedback');
    const scoreEl = document.getElementById('score');
    const cardEl = document.getElementById('word-card');

    const loadGameData = async () => {
        try {
            const res = await fetch('api/game.php');
            words = await res.json();
            
            if (words && words.length > 0) {
                nextRound();
            } else {
                displayEl.textContent = 'No words found.';
                displayEl.style.fontSize = '2rem';
                inputEl.placeholder = 'Add words in admin panel';
                inputEl.disabled = true;
                checkBtn.disabled = true;
            }
        } catch (error) {
            console.error('Error loading game data', error);
            displayEl.textContent = 'Connection Error';
            displayEl.style.fontSize = '2rem';
            inputEl.disabled = true;
            checkBtn.disabled = true;
        }
    };

    const nextRound = () => {
        // Pick a random word
        currentWord = words[Math.floor(Math.random() * words.length)];
        
        displayEl.textContent = currentWord.thai_word;
        inputEl.value = '';
        inputEl.disabled = false;
        checkBtn.disabled = true; // Disabled until user types
        
        // Timeout for mobile keyboard to process DOM change before focus
        setTimeout(() => inputEl.focus(), 100);
        
        checkBtn.classList.remove('hidden');
        showAnswerBtn.classList.remove('hidden');
        nextBtn.classList.add('hidden');
        
        feedbackEl.classList.remove('show');
        cardEl.classList.remove('success-anim', 'shake');
    };

    inputEl.addEventListener('input', () => {
        if (inputEl.value.trim().length > 0) {
            checkBtn.disabled = false;
        } else {
            checkBtn.disabled = true;
        }
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // If next button is visible, user pressed enter to go next
        if (!nextBtn.classList.contains('hidden')) {
            nextRound();
            return;
        }

        const answer = inputEl.value.trim().toLowerCase();
        if (!answer) return;

        if (answer === currentWord.english_word.toLowerCase()) {
            // Correct
            score += 10;
            updateScore();
            sendNotification('correct');
            showFeedback('Correct! Well done 🎉', '#10b981'); // success color
            inputEl.disabled = true;
            
            checkBtn.classList.add('hidden');
            showAnswerBtn.classList.add('hidden');
            nextBtn.classList.remove('hidden');
            nextBtn.focus();
            
            cardEl.classList.remove('shake');
            // Trigger reflow for animation
            void cardEl.offsetWidth;
            cardEl.classList.add('success-anim');
        } else {
            // Incorrect
            showFeedback(`Incorrect. Try again!`, '#fecaca'); // light red on dark bg
            
            cardEl.classList.remove('success-anim');
            void cardEl.offsetWidth;
            cardEl.classList.add('shake');
            
            sendNotification('incorrect', answer);
            inputEl.value = '';
            inputEl.focus();
            
            if (score > 0) score -= 2;
            updateScore();
        }
    });

    showAnswerBtn.addEventListener('click', () => {
        if (!currentWord) return;
        
        inputEl.value = currentWord.english_word;
        showFeedback(`The answer is: ${currentWord.english_word}`, '#fbbf24'); // Yellow/Amber
        inputEl.disabled = true;
        
        checkBtn.classList.add('hidden');
        showAnswerBtn.classList.add('hidden');
        nextBtn.classList.remove('hidden');
        nextBtn.focus();
        
        if (score > 0) score -= 1; // Minor penalty for revealing
        updateScore();
        sendNotification('revealed');
        
        cardEl.classList.remove('shake', 'success-anim');
    });

    nextBtn.addEventListener('click', () => {
        nextRound();
    });

    const showFeedback = (msg, color) => {
        feedbackEl.textContent = msg;
        feedbackEl.style.color = color;
        feedbackEl.classList.add('show');
        
        // Auto hide error after 2s
        if (color !== '#10b981') {
            setTimeout(() => {
                feedbackEl.classList.remove('show');
            }, 2000);
        }
    };

    const updateScore = () => {
        scoreEl.textContent = score;
        scoreEl.style.transform = 'scale(1.5)';
        scoreEl.style.color = '#10b981';
        
        setTimeout(() => {
            scoreEl.style.transform = 'scale(1)';
            scoreEl.style.color = 'white';
            scoreEl.style.transition = 'transform 0.2s, color 0.5s';
        }, 300);
    };

    const sendNotification = (result, userAnswer = '') => {
        if (!currentWord) return;
        fetch('api/notify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                thai_word: currentWord.thai_word,
                english_word: currentWord.english_word,
                result,
                score,
                user_answer: userAnswer,
            }),
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) console.error('Telegram error:', data.error);
        })
        .catch((err) => console.warn('Telegram notification failed:', err));
    };

    loadGameData();
});
