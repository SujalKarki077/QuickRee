// ==============================================
//  StudyQuest — Student Interface
//  Features: Quiz, AI Explanations, Badges, Progress
// ==============================================

let studentName = "";
let studentId = null; // Logged-in student ID
let currentLevel = null;
let currentQuestions = [];
let currentQuestionIndex = 0;
let score = 0;
let completedLevelIds = []; // Track which levels are completed
let retryUsed = false; // Track if the student already had a second chance on this question

// ----- Toast Notification System -----
function showToast(message, type = "default") {
    const container = document.getElementById("toastContainer");
    const toast = document.createElement("div");
    toast.className = `toast ${type === "success" ? "toast-success" : type === "error" ? "toast-error" : ""}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add("toast-exit");
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ----- Show Login Form -----
function showLoginForm() {
    document.getElementById("loginForm").classList.remove("hidden");
    document.getElementById("registerForm").classList.add("hidden");
}

// ----- Show Register Form -----
function showRegisterForm() {
    document.getElementById("loginForm").classList.add("hidden");
    document.getElementById("registerForm").classList.remove("hidden");
}

// ----- Login Student -----
async function loginStudent() {
    const username = document.getElementById("loginUsername").value.trim();
    const password = document.getElementById("loginPassword").value;

    if (!username || !password) {
        showToast("Please fill in all fields.", "error");
        return;
    }

    try {
        const res = await fetch("../api/student_login.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ username, password })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.error || "Login failed.", "error");
            return;
        }

        // Save session
        localStorage.setItem("student_session", JSON.stringify(data.student));
        onLoginSuccess(data.student);
    } catch (e) {
        showToast("Connection error. Is XAMPP running?", "error");
    }
}

// ----- Register Student -----
async function registerStudent() {
    const username = document.getElementById("regUsername").value.trim();
    const email = document.getElementById("regEmail").value.trim();
    const password = document.getElementById("regPassword").value;
    const grade = document.getElementById("regGrade").value;

    if (!username || !email || !password || !grade) {
        showToast("Please fill in all fields.", "error");
        return;
    }

    try {
        const res = await fetch("../api/student_register.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ username, email, password, grade })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.error || "Registration failed.", "error");
            return;
        }

        // Save session and proceed
        localStorage.setItem("student_session", JSON.stringify(data.student));
        showToast("Account created! Welcome to QuickRee! 🎉", "success");
        onLoginSuccess(data.student);
    } catch (e) {
        showToast("Connection error. Is XAMPP running?", "error");
    }
}

// ----- On Login / Register Success -----
function onLoginSuccess(student) {
    studentName = student.username;
    studentId = student.id;

    // Collapse hero to show logged-in header
    const hero = document.getElementById("heroSection");
    hero.innerHTML = `
        <div class="flex-between" style="max-width:600px; margin:0 auto;">
            <div>
                <p style="font-size:0.875rem; color:var(--gray-400); font-weight:500;">Welcome back,</p>
                <h2 style="font-size:1.35rem; font-weight:800; color:var(--gray-800);">${escapeHtml(studentName)} 👋</h2>
                <p style="font-size:0.75rem; color:var(--gray-400);">Class ${escapeHtml(student.grade)}</p>
            </div>
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <span class="xp-badge" id="totalXpBadge">⭐ 0 XP</span>
                <button class="btn btn-ghost btn-sm" onclick="logoutStudent()">Logout</button>
            </div>
        </div>
    `;
    hero.style.padding = "1.5rem 2rem";

    // Show class/subject section (class is auto-set from student's grade)
    document.getElementById("classSelect").value = student.grade;
    document.getElementById("classSubjectSection").classList.remove("hidden");

    // Load progress stats first, then load levels
    loadProgressStats().finally(() => {
        loadLevels();
    });

    showToast(`Welcome, ${studentName}! Choose your subject to start.`, "success");
}

// ----- Logout Student -----
function logoutStudent() {
    localStorage.removeItem("student_session");
    studentName = "";
    studentId = null;
    location.reload();
}

// ----- Load Progress Stats -----
async function loadProgressStats() {
    try {
        const query = studentId ? `student_id=${studentId}` : `name=${encodeURIComponent(studentName)}`;
        const res = await fetch(`../api/get_progress.php?${query}`);
        const data = await res.json();

        completedLevelIds = data.completed_levels || [];
        const totalXp = data.total_xp || 0;
        const levelsCompleted = data.levels_completed || 0;

        // Update XP badge in header
        const xpBadge = document.getElementById("totalXpBadge");
        if (xpBadge) {
            xpBadge.textContent = `⭐ ${totalXp} XP`;
        }

        // Show progress stats section
        const statsSection = document.getElementById("progressStats");
        if (statsSection) {
            statsSection.classList.remove("hidden");
            statsSection.innerHTML = `
                <div class="progress-stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">${levelsCompleted}</div>
                        <div class="stat-label">Topics Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${totalXp}</div>
                        <div class="stat-label">Total XP Earned</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${levelsCompleted > 0 ? '🔥' : '—'}</div>
                        <div class="stat-label">${levelsCompleted > 0 ? 'On Fire!' : 'Start Playing'}</div>
                    </div>
                </div>
            `;
        }
    } catch (e) {
        completedLevelIds = [];
    }
}

// ----- Load Levels (filtered by class & subject) -----
function loadLevels() {
    const selectedClass = document.getElementById("classSelect").value;
    const selectedSubject = document.getElementById("subjectSelect").value;
    const container = document.getElementById("levels");

    container.innerHTML = `
        <div class="loading-state">
            <div class="spinner spinner-dark"></div>
            <span>Loading topics...</span>
        </div>
    `;

    fetch(`../api/get_levels.php?class=${encodeURIComponent(selectedClass)}&subject=${encodeURIComponent(selectedSubject)}`)
        .then(res => res.json())
        .then(levels => {
            if (!levels || levels.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📚</div>
                        <h3>No topics yet</h3>
                        <p>No topics found for <strong>Class ${escapeHtml(selectedClass)} — ${escapeHtml(selectedSubject)}</strong>.<br>Ask your teacher to add some!</p>
                    </div>
                `;
                return;
            }

            let html = `
                <div class="section-header mt-2">
                    <div class="icon">🗺️</div>
                    <h2>Class ${escapeHtml(selectedClass)} — ${escapeHtml(selectedSubject)}</h2>
                </div>
                <p class="section-subtitle">${levels.length} topic${levels.length > 1 ? 's' : ''} available. Select one to start learning.</p>
                <div class="levels-grid stagger-children">
            `;

            levels.forEach(level => {
                const isCompleted = completedLevelIds.includes(parseInt(level.id));
                const completedClass = isCompleted ? 'completed' : '';
                html += `
                    <div class="level-card ${completedClass}" onclick="startLevel(${level.id}, '${escapeAttr(level.topic)}', \`${escapeAttr(level.content)}\`, '${escapeAttr(level.level_no)}')">
                        <div class="level-number">${escapeHtml(level.level_no)}</div>
                        <div class="level-topic">${escapeHtml(level.topic)}</div>
                        <div class="level-subject">${escapeHtml(level.subject || selectedSubject)}</div>
                        ${isCompleted ? '<div class="level-completed-badge">✅ Completed</div>' : ''}
                    </div>
                `;
            });

            html += `</div>`;
            container.innerHTML = html;
        })
        .catch(() => {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <h3>Could not load topics</h3>
                    <p>Make sure XAMPP is running and the database is set up.</p>
                </div>
            `;
        });
}

// ----- Start Level -----
function startLevel(id, topic, content, levelNo) {
    if (studentName === "") {
        showToast("Enter your name first!", "error");
        document.getElementById("student").focus();
        return;
    }

    currentLevel = id;
    score = 0;
    currentQuestionIndex = 0;

    // Hide class/subject selectors and progress stats
    document.getElementById("classSubjectSection").classList.add("hidden");
    document.getElementById("levels").classList.add("hidden");
    const stats = document.getElementById("progressStats");
    if (stats) stats.classList.add("hidden");

    const section = document.getElementById("learning");
    section.innerHTML = `
        <div class="lesson-view">
            <div class="lesson-content">
                <div class="flex-between mb-2">
                    <h3>${escapeHtml(topic)}</h3>
                    <span class="badge badge-primary">Topic ${escapeHtml(levelNo)}</span>
                </div>
                <div class="lesson-body">
                    ${formatContent(content)}
                </div>
            </div>
            <button class="btn btn-primary btn-block" onclick="loadQuestions()">
                🧠 Start Quiz
            </button>
            <button class="btn btn-ghost btn-block mt-1" onclick="backToLevels()">
                ← Back to Topics
            </button>
        </div>
    `;

    section.scrollIntoView({ behavior: "smooth", block: "start" });
}

// ----- Load MCQs -----
function loadQuestions() {
    const section = document.getElementById("learning");
    section.innerHTML = `
        <div class="loading-state">
            <div class="spinner spinner-dark"></div>
            <span>Loading quiz questions...</span>
        </div>
    `;

    fetch(`../api/get_questions.php?level_id=${currentLevel}`)
        .then(res => res.json())
        .then(questions => {
            if (!questions || questions.length === 0) {
                section.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🤷</div>
                        <h3>No questions available</h3>
                        <p>This topic doesn't have any quiz questions yet.</p>
                        <button class="btn btn-ghost mt-2" onclick="backToLevels()">← Back to Topics</button>
                    </div>
                `;
                return;
            }
            currentQuestions = questions;
            currentQuestionIndex = 0;
            score = 0;
            showQuestion();
        })
        .catch(() => {
            section.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <h3>Error loading questions</h3>
                    <p>Please try again later.</p>
                    <button class="btn btn-ghost mt-2" onclick="backToLevels()">← Back</button>
                </div>
            `;
        });
}

// ----- Show Current Question -----
function showQuestion() {
    if (currentQuestionIndex >= currentQuestions.length) {
        finishLevel(score);
        return;
    }

    retryUsed = false; // Reset retry for each new question
    const q = currentQuestions[currentQuestionIndex];
    const total = currentQuestions.length;
    const progress = ((currentQuestionIndex) / total) * 100;

    const section = document.getElementById("learning");
    section.innerHTML = `
        <div class="quiz-container">
            <div class="quiz-progress">
                <div class="quiz-progress-bar">
                    <div class="quiz-progress-fill" style="width: ${progress}%"></div>
                </div>
                <span class="quiz-progress-text">${currentQuestionIndex + 1} / ${total}</span>
            </div>

            <div class="quiz-question-card">
                <div class="quiz-question-number">Question ${currentQuestionIndex + 1}</div>
                <div class="quiz-question-text">${escapeHtml(q.question)}</div>
                <div class="quiz-options">
                    <button class="quiz-option" onclick="checkAnswer('${escapeAttr(q.correct || q.correct_option)}','A', this)">
                        <span class="option-letter">A</span>
                        <span>${escapeHtml(q.option_a)}</span>
                    </button>
                    <button class="quiz-option" onclick="checkAnswer('${escapeAttr(q.correct || q.correct_option)}','B', this)">
                        <span class="option-letter">B</span>
                        <span>${escapeHtml(q.option_b)}</span>
                    </button>
                    <button class="quiz-option" onclick="checkAnswer('${escapeAttr(q.correct || q.correct_option)}','C', this)">
                        <span class="option-letter">C</span>
                        <span>${escapeHtml(q.option_c)}</span>
                    </button>
                    <button class="quiz-option" onclick="checkAnswer('${escapeAttr(q.correct || q.correct_option)}','D', this)">
                        <span class="option-letter">D</span>
                        <span>${escapeHtml(q.option_d)}</span>
                    </button>
                </div>
                <div id="hintBox"></div>
                <div id="explanationBox"></div>
            </div>

            <div class="flex-between mt-2">
                <span class="xp-badge">⭐ ${score} XP</span>
            </div>
        </div>
    `;
}

// ----- Check Answer with Visual Feedback + Retry with Hint -----
function checkAnswer(correct, chosen, btnElement) {
    const q = currentQuestions[currentQuestionIndex];

    // === CORRECT ANSWER ===
    if (correct === chosen) {
        // Disable all options
        const options = document.querySelectorAll(".quiz-option");
        options.forEach(opt => { opt.style.pointerEvents = "none"; });

        // Highlight the correct one
        btnElement.classList.add("correct");

        if (!retryUsed) {
            // Correct on FIRST attempt
            score += 50;
            showToast("+50 XP — Correct! 🎉", "success");
        } else {
            // Correct on SECOND attempt
            score += 20;
            showToast("+20 XP — Nice recovery! 💪", "success");
        }

        currentQuestionIndex++;
        setTimeout(() => showQuestion(), 1200);
        return;
    }

    // === WRONG ANSWER — FIRST ATTEMPT (give retry with hint) ===
    if (!retryUsed) {
        retryUsed = true;

        // Mark chosen option as wrong and disable it
        btnElement.classList.add("wrong", "eliminated");
        btnElement.style.pointerEvents = "none";

        showToast("Not quite! Read the hint and try again 🔁", "error");

        // Show hint box
        const hintBox = document.getElementById("hintBox");
        if (hintBox) {
            const hint = q.explanation || `Think about what relates most to the question.`;
            hintBox.innerHTML = `
                <div class="quiz-hint-box">
                    <div class="quiz-hint-header">
                        <span>💡</span>
                        <strong>Hint — Try Again!</strong>
                    </div>
                    <p>${escapeHtml(hint)}</p>
                </div>
            `;
        }
        return; // Don't advance — let student pick again
    }

    // === WRONG ANSWER — SECOND ATTEMPT (reveal correct & advance) ===
    const options = document.querySelectorAll(".quiz-option");
    options.forEach(opt => { opt.style.pointerEvents = "none"; });

    // Mark this wrong choice
    btnElement.classList.add("wrong");

    // Highlight the correct answer
    options.forEach(opt => {
        const letter = opt.querySelector(".option-letter").textContent.trim();
        if (letter === correct) {
            opt.classList.add("correct");
        }
    });

    score += 10;
    showToast(`+10 XP — The correct answer was ${correct}`, "error");

    // Show AI Explanation
    const explanationBox = document.getElementById("explanationBox");
    if (explanationBox) {
        const explanation = q.explanation || `The correct answer is option ${correct}.`;
        explanationBox.innerHTML = `
            <div class="quiz-explanation">
                <div class="quiz-explanation-header">
                    <span>🤖</span>
                    <strong>AI Explanation</strong>
                </div>
                <p>${escapeHtml(explanation)}</p>
            </div>
        `;
    }

    currentQuestionIndex++;
    setTimeout(() => showQuestion(), 3500);
}

// ----- Finish Level -----
function finishLevel(xp) {
    const progressBody = studentId
        ? `student_id=${studentId}&name=${encodeURIComponent(studentName)}&level_id=${currentLevel}&xp=${xp}`
        : `name=${encodeURIComponent(studentName)}&level_id=${currentLevel}&xp=${xp}`;
    fetch("../api/save_level_progress.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: progressBody
    })
        .then(res => res.text())
        .then(() => {
            // Add to completed list
            if (!completedLevelIds.includes(currentLevel)) {
                completedLevelIds.push(currentLevel);
            }

            const totalQuestions = currentQuestions.length;
            const maxXp = totalQuestions * 50;
            const percentage = Math.round((xp / maxXp) * 100);
            const stars = percentage >= 90 ? '⭐⭐⭐' : percentage >= 60 ? '⭐⭐' : '⭐';

            document.getElementById("learning").innerHTML = `
            <div class="completion-screen">
                <div class="completion-icon">🏆</div>
                <h2 class="completion-title">Topic Complete!</h2>
                <div class="completion-badge">
                    <span class="badge badge-success" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                        ✅ Topic Completed
                    </span>
                </div>
                <p class="completion-xp mt-1">You earned <strong>${xp} XP</strong> ${stars}</p>
                <div class="completion-score-bar mt-1">
                    <div class="completion-score-fill" style="width: ${percentage}%"></div>
                </div>
                <p class="completion-percentage">${percentage}% accuracy</p>
                <p class="completion-message">Great work, ${escapeHtml(studentName)}! Keep going to unlock more topics.</p>
                <button class="btn btn-primary" onclick="backToLevels()">
                    🗺️ Back to Topics
                </button>
            </div>
        `;
        })
        .catch(() => {
            showToast("Error saving progress", "error");
        });
}

// ----- Back to Levels -----
function backToLevels() {
    document.getElementById("learning").innerHTML = "";
    document.getElementById("classSubjectSection").classList.remove("hidden");
    document.getElementById("levels").classList.remove("hidden");
    const stats = document.getElementById("progressStats");
    if (stats) stats.classList.remove("hidden");

    // Refresh progress stats and levels
    loadProgressStats().finally(() => {
        loadLevels();
    });
    window.scrollTo({ top: 0, behavior: "smooth" });
}

// ----- Format Lesson Content for Readability -----
function formatContent(raw) {
    if (!raw) return "<p>No content available.</p>";

    // Escape HTML first for safety
    let text = escapeHtml(raw);

    // Split into lines
    let lines = text.split(/\n/);
    let html = '';
    let inList = false;
    let listType = '';

    lines.forEach(line => {
        let trimmed = line.trim();
        if (!trimmed) {
            if (inList) { html += listType === 'ul' ? '</ul>' : '</ol>'; inList = false; }
            return;
        }

        // Headings: # Heading
        if (/^#{1,3}\s+/.test(trimmed)) {
            if (inList) { html += listType === 'ul' ? '</ul>' : '</ol>'; inList = false; }
            let level = trimmed.match(/^(#{1,3})/)[1].length;
            let tag = `h${level + 2}`;
            let headingText = trimmed.replace(/^#{1,3}\s+/, '');
            html += `<${tag} class="lesson-heading">${headingText}</${tag}>`;
            return;
        }

        // Bullet list: - item or * item
        if (/^[-*]\s+/.test(trimmed)) {
            if (!inList || listType !== 'ul') {
                if (inList) html += '</ol>';
                html += '<ul class="lesson-list">';
                inList = true; listType = 'ul';
            }
            html += `<li>${trimmed.replace(/^[-*]\s+/, '')}</li>`;
            return;
        }

        // Numbered list: 1. item
        if (/^\d+\.\s+/.test(trimmed)) {
            if (!inList || listType !== 'ol') {
                if (inList) html += '</ul>';
                html += '<ol class="lesson-list">';
                inList = true; listType = 'ol';
            }
            html += `<li>${trimmed.replace(/^\d+\.\s+/, '')}</li>`;
            return;
        }

        // Close open list before paragraph
        if (inList) { html += listType === 'ul' ? '</ul>' : '</ol>'; inList = false; }

        html += `<p>${trimmed}</p>`;
    });

    if (inList) html += listType === 'ul' ? '</ul>' : '</ol>';

    // Bold: **text**
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    return html;
}

// ----- Utilities -----
function escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
}

function escapeAttr(str) {
    if (!str) return "";
    return str.replace(/'/g, "\\'").replace(/`/g, "\\`").replace(/"/g, "&quot;");
}

// ----- Init -----
window.addEventListener("DOMContentLoaded", () => {
    // Auto-login if session exists in localStorage
    const saved = localStorage.getItem("student_session");
    if (saved) {
        try {
            const student = JSON.parse(saved);
            if (student && student.id && student.username) {
                onLoginSuccess(student);
            }
        } catch (e) {
            localStorage.removeItem("student_session");
        }
    }
});