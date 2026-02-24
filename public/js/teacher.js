// ==============================================
//  StudyQuest — Teacher Dashboard
//  Features: Add Levels, Generate MCQ, Manage Levels
// ==============================================

document.addEventListener("DOMContentLoaded", () => {

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

    // ----- Level Form Submission (AJAX) -----
    const form = document.getElementById("levelForm");
    if (form) {
        form.addEventListener("submit", (e) => {
            e.preventDefault();

            const topic = form.topic.value.trim();
            if (topic === "") {
                showToast("Please enter a topic!", "error");
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner"></span> Adding...';
            submitBtn.disabled = true;

            const formData = new FormData(form);

            fetch("../api/add_level.php", {
                method: "POST",
                body: formData
            })
                .then(res => res.text())
                .then(resp => {
                    showToast("Topic added successfully! ✅", "success");
                    form.reset();
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                })
                .catch(err => {
                    showToast("Failed to add topic. Try again.", "error");
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        });
    }

    // ----- Hide All Sections -----
    function hideAllSections() {
        document.getElementById("selectClassSubject").classList.add("hidden");
        document.getElementById("levelFormContainer").classList.add("hidden");
        document.getElementById("mcqFormContainer").classList.add("hidden");
        document.getElementById("manageLevelsContainer").classList.add("hidden");
        const sc = document.getElementById("studentsContainer");
        if (sc) sc.classList.add("hidden");
    }

    // ----- Show Level Form -----
    window.showAddLevelForm = function () {
        const classSelect = document.getElementById("classSelect");
        const subjectSelect = document.getElementById("subjectSelect");

        document.getElementById("formClass").value = classSelect.value;
        document.getElementById("formSubject").value = subjectSelect.value;

        // Show which class/subject the topic will be added to
        const badge = document.getElementById("addTopicBadge");
        if (badge) badge.textContent = `📋 Class ${classSelect.value} — ${subjectSelect.value}`;

        hideAllSections();
        document.getElementById("levelFormContainer").classList.remove("hidden");
    };

    // ----- Show MCQ Generator -----
    window.showMCQForm = function () {
        hideAllSections();
        document.getElementById("mcqFormContainer").classList.remove("hidden");
        document.getElementById("mcqOutput").innerHTML = "";
        loadMCQTopics();
    };

    // ----- Load Topics for MCQ Dropdown -----
    function loadMCQTopics() {
        const classValue = document.getElementById("classSelect").value;
        const subjectValue = document.getElementById("subjectSelect").value;
        const select = document.getElementById("mcqTopicSelect");

        select.innerHTML = '<option value="">Loading...</option>';

        fetch(`../api/get_levels.php?class=${encodeURIComponent(classValue)}&subject=${encodeURIComponent(subjectValue)}`)
            .then(res => res.json())
            .then(levels => {
                if (!levels || levels.length === 0) {
                    select.innerHTML = '<option value="">No topics found — add a level first</option>';
                    return;
                }
                let html = '';
                levels.forEach(level => {
                    html += `<option value="${level.id}">Topic ${escapeHtml(level.level_no)} — ${escapeHtml(level.topic)}</option>`;
                });
                select.innerHTML = html;
            })
            .catch(() => {
                select.innerHTML = '<option value="">Error loading topics</option>';
            });
    }

    // ----- Show Manage Levels -----
    window.showManageLevels = function () {
        hideAllSections();
        document.getElementById("manageLevelsContainer").classList.remove("hidden");
        loadTeacherLevels();
    };

    // ----- Load Levels for Management -----
    function loadTeacherLevels() {
        const classValue = document.getElementById("classSelect").value;
        const subjectValue = document.getElementById("subjectSelect").value;
        const container = document.getElementById("manageLevelsList");

        container.innerHTML = `
            <div class="loading-state">
                <div class="spinner spinner-dark"></div>
                <span>Loading levels...</span>
            </div>
        `;

        fetch(`../api/get_levels.php?class=${encodeURIComponent(classValue)}&subject=${encodeURIComponent(subjectValue)}`)
            .then(res => res.json())
            .then(levels => {
                if (!levels || levels.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <h3>No topics found</h3>
                            <p>No topics for <strong>Class ${escapeHtml(classValue)} — ${escapeHtml(subjectValue)}</strong>. Add one first!</p>
                        </div>
                    `;
                    return;
                }

                let html = `
                    <div class="manage-summary">
                        <span class="badge badge-primary" style="font-size:0.8125rem; padding:0.375rem 0.75rem;">
                            📊 ${levels.length} topic${levels.length > 1 ? 's' : ''} • Class ${escapeHtml(classValue)} • ${escapeHtml(subjectValue)}
                        </span>
                    </div>
                    <div class="manage-levels-list">
                `;

                // Store level data for click handlers
                window._levelData = {};

                levels.forEach(level => {
                    window._levelData[level.id] = level;
                    const contentPreview = level.content ? level.content.substring(0, 80) + (level.content.length > 80 ? '...' : '') : 'No content';
                    html += `
                        <div class="manage-level-item">
                            <div class="manage-level-info">
                                <div class="manage-level-header">
                                    <span class="manage-level-number">Topic ${escapeHtml(level.level_no)}</span>
                                    <strong class="manage-level-topic">${escapeHtml(level.topic)}</strong>
                                </div>
                                <p class="manage-level-preview">${escapeHtml(contentPreview)}</p>
                            </div>
                            <div class="manage-level-actions">
                                <button class="btn btn-sm btn-primary" data-action="edit" data-id="${level.id}">
                                    ✏️ Edit
                                </button>
                                <button class="btn btn-sm btn-danger" data-action="delete" data-id="${level.id}">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    `;
                });

                html += `</div>`;
                container.innerHTML = html;

                // Attach click handlers via event delegation
                container.querySelectorAll('[data-action="edit"]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const lvl = window._levelData[btn.dataset.id];
                        if (lvl) openEditModal(lvl.id, lvl.topic, lvl.content);
                    });
                });
                container.querySelectorAll('[data-action="delete"]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const lvl = window._levelData[btn.dataset.id];
                        if (lvl) deleteLevel(lvl.id, lvl.topic);
                    });
                });
            })
            .catch(() => {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">⚠️</div>
                        <h3>Error loading levels</h3>
                        <p>Make sure XAMPP is running and the database is set up.</p>
                    </div>
                `;
            });
    }

    // ----- Open Edit Modal -----
    window.openEditModal = function (id, topic, content) {
        document.getElementById("editLevelId").value = id;
        document.getElementById("editTopic").value = topic;
        document.getElementById("editContent").value = content;
        document.getElementById("editModal").classList.remove("hidden");
    };

    // ----- Close Edit Modal -----
    window.closeEditModal = function () {
        document.getElementById("editModal").classList.add("hidden");
    };

    // ----- Save Edit Level -----
    window.saveEditLevel = function () {
        const id = document.getElementById("editLevelId").value;
        const topic = document.getElementById("editTopic").value.trim();
        const content = document.getElementById("editContent").value.trim();

        if (!topic) {
            showToast("Topic cannot be empty!", "error");
            return;
        }

        const formData = new FormData();
        formData.append("id", id);
        formData.append("topic", topic);
        formData.append("content", content);

        fetch("../api/update_level.php", {
            method: "POST",
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast("Topic updated successfully! ✅", "success");
                    closeEditModal();
                    loadTeacherLevels();
                } else {
                    showToast("Error: " + (data.error || "Unknown error"), "error");
                }
            })
            .catch(() => {
                showToast("Failed to update level.", "error");
            });
    };

    // ----- Delete Level -----
    window.deleteLevel = function (id, topic) {
        if (!confirm(`Are you sure you want to delete "${topic}"? This will also delete all MCQs and progress for this topic.`)) {
            return;
        }

        const formData = new FormData();
        formData.append("id", id);

        fetch("../api/delete_level.php", {
            method: "POST",
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast("Topic deleted! 🗑️", "success");
                    loadTeacherLevels();
                } else {
                    showToast("Error: " + (data.error || "Unknown error"), "error");
                }
            })
            .catch(() => {
                showToast("Failed to delete level.", "error");
            });
    };

    // ----- Generate MCQ -----
    window.generateMCQ = function () {
        const classValue = document.getElementById("classSelect").value;
        const subjectValue = document.getElementById("subjectSelect").value;
        const output = document.getElementById("mcqOutput");
        const btn = document.getElementById("generateBtn");

        const originalBtnText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> Generating...';
        btn.disabled = true;

        output.innerHTML = `
            <div class="loading-state" style="padding: 2rem;">
                <div class="spinner spinner-dark"></div>
                <span>AI is generating a question...</span>
            </div>
        `;

        const levelId = document.getElementById("mcqTopicSelect").value;

        fetch("../api/generate_mcq.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ class: classValue, subject: subjectValue, level_id: levelId })
        })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = originalBtnText;
                btn.disabled = false;

                if (data.success) {
                    output.innerHTML = `
                    <div class="mcq-output">
                        <div class="mcq-label">Generated Question</div>
                        <div class="mcq-question">${escapeHtml(data.question)}</div>
                        <div class="mcq-options">
                            <div class="mcq-option-item ${data.correct_option === 'A' ? 'correct-answer' : ''}">
                                <strong>A.</strong> ${escapeHtml(data.option_a)}
                                ${data.correct_option === 'A' ? ' ✅' : ''}
                            </div>
                            <div class="mcq-option-item ${data.correct_option === 'B' ? 'correct-answer' : ''}">
                                <strong>B.</strong> ${escapeHtml(data.option_b)}
                                ${data.correct_option === 'B' ? ' ✅' : ''}
                            </div>
                            <div class="mcq-option-item ${data.correct_option === 'C' ? 'correct-answer' : ''}">
                                <strong>C.</strong> ${escapeHtml(data.option_c)}
                                ${data.correct_option === 'C' ? ' ✅' : ''}
                            </div>
                            <div class="mcq-option-item ${data.correct_option === 'D' ? 'correct-answer' : ''}">
                                <strong>D.</strong> ${escapeHtml(data.option_d)}
                                ${data.correct_option === 'D' ? ' ✅' : ''}
                            </div>
                        </div>
                    </div>
                `;
                    showToast("MCQ generated and saved! 🎉", "success");
                } else {
                    output.innerHTML = `
                    <div class="mcq-output" style="border-color: var(--rose-400);">
                        <div class="mcq-label" style="color: var(--rose-500);">Error</div>
                        <p style="color:var(--gray-600);">${escapeHtml(data.error || "Unknown error occurred.")}</p>
                        ${data.raw_ai ? `<pre style="margin-top:0.75rem; font-size:0.8125rem; background:var(--gray-100); padding:0.75rem; border-radius:var(--radius-sm); overflow-x:auto;">${escapeHtml(data.raw_ai)}</pre>` : ''}
                    </div>
                `;
                    showToast("Failed to generate MCQ", "error");
                }
            })
            .catch(err => {
                btn.innerHTML = originalBtnText;
                btn.disabled = false;
                output.innerHTML = `
                <div class="mcq-output" style="border-color: var(--rose-400);">
                    <div class="mcq-label" style="color: var(--rose-500);">Network Error</div>
                    <p style="color:var(--gray-600);">Could not reach the server. Make sure XAMPP is running.</p>
                </div>
            `;
                showToast("Network error", "error");
            });
    };

    // ----- Back to Selection -----
    window.backToSelect = function () {
        hideAllSections();
        document.getElementById("selectClassSubject").classList.remove("hidden");
    };

    // ----- Show Students Section -----
    window.showStudents = function () {
        hideAllSections();
        document.getElementById("studentsContainer").classList.remove("hidden");
        loadStudents();
    };

    // ----- Load Students -----
    window.loadStudents = function () {
        const container = document.getElementById("studentsList");
        const gradeFilter = document.getElementById("studentGradeFilter");
        const selectedGrade = gradeFilter ? gradeFilter.value : '';

        container.innerHTML = `
            <div class="loading-state">
                <div class="spinner spinner-dark"></div>
                <span>Loading students...</span>
            </div>
        `;

        const url = selectedGrade
            ? `../api/get_students.php?grade=${encodeURIComponent(selectedGrade)}`
            : `../api/get_students.php`;

        fetch(url)
            .then(res => res.json())
            .then(students => {
                if (!students || students.length === 0) {
                    const filterMsg = selectedGrade ? ` in Class ${escapeHtml(selectedGrade)}` : '';
                    container.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">👨‍🎓</div>
                            <h3>No students found${filterMsg}</h3>
                            <p>No students have registered${filterMsg}. ${selectedGrade ? 'Try selecting a different class or "All Classes".' : 'Students can create accounts from the Student page.'}</p>
                        </div>
                    `;
                    return;
                }

                let totalXpAll = 0;
                students.forEach(s => totalXpAll += s.total_xp);

                let html = `
                    <div class="manage-summary mb-2">
                        <span class="badge badge-primary" style="font-size:0.8125rem; padding:0.375rem 0.75rem;">
                            👨‍🎓 ${students.length} student${students.length > 1 ? 's' : ''} registered • ${totalXpAll} total XP earned
                        </span>
                    </div>
                    <div style="overflow-x:auto;">
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Email</th>
                                <th>Grade</th>
                                <th>XP Earned</th>
                                <th>Topics Done</th>
                                <th>Joined</th>
                                <th>AI Analysis</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                students.forEach(s => {
                    const joinDate = new Date(s.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    html += `
                        <tr>
                            <td class="student-name">${escapeHtml(s.username)}</td>
                            <td>${escapeHtml(s.email)}</td>
                            <td><span class="student-grade">Class ${escapeHtml(s.grade)}</span></td>
                            <td class="student-xp">⭐ ${s.total_xp}</td>
                            <td>${s.levels_completed}</td>
                            <td>${joinDate}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="analyzeStudent(${s.id}, '${escapeAttr(s.username)}')" style="font-size:0.75rem; padding:0.3rem 0.6rem;">
                                    🤖 Analyze
                                </button>
                            </td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                    </table>
                    </div>
                `;
                container.innerHTML = html;
            })
            .catch(() => {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">⚠️</div>
                        <h3>Error loading students</h3>
                        <p>Make sure you've run setup_updates.php to create the students table.</p>
                    </div>
                `;
            });
    };

    // ----- AI Analyze Student -----
    window.analyzeStudent = function (studentId, studentName) {
        // Show analysis area below the table
        let analysisBox = document.getElementById("aiAnalysisBox");
        if (!analysisBox) {
            const container = document.getElementById("studentsList");
            analysisBox = document.createElement("div");
            analysisBox.id = "aiAnalysisBox";
            container.appendChild(analysisBox);
        }

        analysisBox.innerHTML = `
            <div class="ai-analysis-card" style="margin-top:1.5rem;">
                <div class="card-header" style="display:flex; align-items:center; gap:0.75rem; padding:1rem 1.25rem;">
                    <span>🤖</span>
                    <h3 style="margin:0;">AI Analysis — ${escapeHtml(studentName)}</h3>
                </div>
                <div class="card-body" style="padding:1.25rem;">
                    <div class="loading-state">
                        <div class="spinner spinner-dark"></div>
                        <span>AI is analyzing ${escapeHtml(studentName)}'s progress...</span>
                    </div>
                </div>
            </div>
        `;

        analysisBox.scrollIntoView({ behavior: 'smooth', block: 'center' });

        fetch("../api/analyze_student.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ student_id: studentId })
        })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    analysisBox.innerHTML = `
                        <div class="ai-analysis-card" style="margin-top:1.5rem; border-color:var(--rose-300);">
                            <div class="card-header" style="padding:1rem 1.25rem;">
                                <h3 style="margin:0; color:var(--rose-500);">Analysis Error</h3>
                            </div>
                            <div class="card-body" style="padding:1.25rem;">
                                <p style="color:var(--gray-600);">${escapeHtml(data.error)}</p>
                            </div>
                        </div>
                    `;
                    return;
                }

                // Format markdown-like bold text
                let formatted = escapeHtml(data.analysis)
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\n/g, '<br>');

                const stats = data.stats || {};
                const completion = stats.total_levels > 0
                    ? Math.round((stats.levels_completed / stats.total_levels) * 100)
                    : 0;

                analysisBox.innerHTML = `
                    <div class="ai-analysis-card" style="margin-top:1.5rem;">
                        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.25rem;">
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <span>🤖</span>
                                <h3 style="margin:0;">AI Analysis — ${escapeHtml(data.student)}</h3>
                            </div>
                            <button class="btn btn-ghost btn-sm" onclick="document.getElementById('aiAnalysisBox').innerHTML=''">✕ Close</button>
                        </div>
                        <div class="card-body" style="padding:1.25rem;">
                            <div class="progress-stats-grid" style="margin-bottom:1.25rem; display:grid; grid-template-columns:repeat(3,1fr); gap:0.75rem;">
                                <div style="text-align:center; padding:0.75rem; background:var(--gray-50); border-radius:var(--radius-md);">
                                    <div style="font-size:1.5rem; font-weight:800; color:var(--primary-500);">${stats.total_xp || 0}</div>
                                    <div style="font-size:0.75rem; color:var(--gray-400); text-transform:uppercase; font-weight:600;">Total XP</div>
                                </div>
                                <div style="text-align:center; padding:0.75rem; background:var(--gray-50); border-radius:var(--radius-md);">
                                    <div style="font-size:1.5rem; font-weight:800; color:var(--emerald-500);">${stats.levels_completed || 0}/${stats.total_levels || 0}</div>
                                    <div style="font-size:0.75rem; color:var(--gray-400); text-transform:uppercase; font-weight:600;">Topics Done</div>
                                </div>
                                <div style="text-align:center; padding:0.75rem; background:var(--gray-50); border-radius:var(--radius-md);">
                                    <div style="font-size:1.5rem; font-weight:800; color:var(--amber-500);">${completion}%</div>
                                    <div style="font-size:0.75rem; color:var(--gray-400); text-transform:uppercase; font-weight:600;">Completion</div>
                                </div>
                            </div>
                            <div style="line-height:1.7; color:var(--gray-600); font-size:0.9375rem;">
                                ${formatted}
                            </div>
                        </div>
                    </div>
                `;
                showToast("AI analysis complete! 🤖", "success");
            })
            .catch(() => {
                analysisBox.innerHTML = `
                    <div class="ai-analysis-card" style="margin-top:1.5rem; border-color:var(--rose-300);">
                        <div class="card-body" style="padding:1.25rem;">
                            <p style="color:var(--rose-500);">Network error — make sure XAMPP is running.</p>
                        </div>
                    </div>
                `;
            });
    };

    // ----- Generate Weekly Report -----
    window.generateWeeklyReport = function () {
        const gradeFilter = document.getElementById("studentGradeFilter");
        const selectedGrade = gradeFilter ? gradeFilter.value : '';

        let reportBox = document.getElementById("weeklyReportBox");
        if (!reportBox) {
            const container = document.getElementById("studentsList");
            reportBox = document.createElement("div");
            reportBox.id = "weeklyReportBox";
            container.appendChild(reportBox);
        }

        const gradeLabel = selectedGrade ? `Class ${selectedGrade}` : 'All Classes';

        reportBox.innerHTML = `
            <div class="ai-analysis-card" style="margin-top:1.5rem;">
                <div class="card-header" style="display:flex; align-items:center; gap:0.75rem; padding:1rem 1.25rem;">
                    <span>📊</span>
                    <h3 style="margin:0;">Generating Weekly Report — ${escapeHtml(gradeLabel)}...</h3>
                </div>
                <div class="card-body" style="padding:1.25rem;">
                    <div class="loading-state">
                        <div class="spinner spinner-dark"></div>
                        <span>Analyzing student data...</span>
                    </div>
                </div>
            </div>
        `;

        reportBox.scrollIntoView({ behavior: 'smooth', block: 'center' });

        const url = selectedGrade
            ? `../api/weekly_report.php?grade=${encodeURIComponent(selectedGrade)}`
            : `../api/weekly_report.php`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    reportBox.innerHTML = `
                        <div class="ai-analysis-card" style="margin-top:1.5rem; border-color:var(--rose-300);">
                            <div class="card-body" style="padding:1.25rem;">
                                <p style="color:var(--rose-500);">${escapeHtml(data.error || 'Failed to generate report')}</p>
                            </div>
                        </div>
                    `;
                    return;
                }

                const r = data.report;

                // Top students leaderboard
                let topHtml = '';
                if (r.top_students && r.top_students.length > 0) {
                    const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
                    topHtml = r.top_students.map((s, i) => `
                        <tr>
                            <td style="text-align:center;">${medals[i] || (i + 1)}</td>
                            <td class="student-name">${escapeHtml(s.username)}</td>
                            <td><span class="student-grade">Class ${escapeHtml(s.grade)}</span></td>
                            <td class="student-xp">⭐ ${s.total_xp}</td>
                            <td>${s.levels_done}</td>
                        </tr>
                    `).join('');
                } else {
                    topHtml = '<tr><td colspan="5" style="text-align:center; color:var(--gray-400);">No student data yet</td></tr>';
                }

                // Subject performance
                let subjectHtml = '';
                if (r.subject_performance && r.subject_performance.length > 0) {
                    subjectHtml = r.subject_performance.map(s => `
                        <tr>
                            <td style="font-weight:600;">${escapeHtml(s.subject)}</td>
                            <td>${s.students_active}</td>
                            <td class="student-xp">⭐ ${s.total_xp}</td>
                            <td>${s.levels_done}</td>
                        </tr>
                    `).join('');
                } else {
                    subjectHtml = '<tr><td colspan="4" style="text-align:center; color:var(--gray-400);">No subject data yet</td></tr>';
                }

                // Students needing attention
                let attentionHtml = '';
                if (r.need_attention && r.need_attention.length > 0) {
                    attentionHtml = r.need_attention.map(s => `
                        <span style="display:inline-block; padding:0.25rem 0.625rem; background:var(--rose-50); color:var(--rose-600); border-radius:var(--radius-md); font-size:0.8125rem; margin:0.25rem;">
                            ${escapeHtml(s.username)} (Class ${escapeHtml(s.grade)})
                        </span>
                    `).join('');
                } else {
                    attentionHtml = '<span style="color:var(--gray-400);">All students have earned XP! 🎉</span>';
                }

                reportBox.innerHTML = `
                    <div class="ai-analysis-card" style="margin-top:1.5rem;">
                        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.25rem;">
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <span>📊</span>
                                <h3 style="margin:0;">Weekly Report — ${escapeHtml(gradeLabel)}</h3>
                            </div>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span style="font-size:0.75rem; color:var(--gray-400);">${r.generated_at}</span>
                                <button class="btn btn-ghost btn-sm" onclick="document.getElementById('weeklyReportBox').innerHTML=''">✕ Close</button>
                            </div>
                        </div>
                        <div class="card-body" style="padding:1.25rem;">

                            <!-- Overview Stats -->
                            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:0.75rem; margin-bottom:1.5rem;">
                                <div style="text-align:center; padding:0.75rem; background:var(--gray-50); border-radius:var(--radius-md);">
                                    <div style="font-size:1.5rem; font-weight:800; color:var(--primary-500);">${r.total_students}</div>
                                    <div style="font-size:0.7rem; color:var(--gray-400); text-transform:uppercase; font-weight:600;">Total Students</div>
                                </div>
                                <div style="text-align:center; padding:0.75rem; background:var(--gray-50); border-radius:var(--radius-md);">
                                    <div style="font-size:1.5rem; font-weight:800; color:var(--emerald-500);">${r.active_students}</div>
                                    <div style="font-size:0.7rem; color:var(--gray-400); text-transform:uppercase; font-weight:600;">Active Students</div>
                                </div>
                                <div style="text-align:center; padding:0.75rem; background:var(--gray-50); border-radius:var(--radius-md);">
                                    <div style="font-size:1.5rem; font-weight:800; color:var(--amber-500);">${r.total_xp_earned}</div>
                                    <div style="font-size:0.7rem; color:var(--gray-400); text-transform:uppercase; font-weight:600;">Total XP Earned</div>
                                </div>
                                <div style="text-align:center; padding:0.75rem; background:var(--gray-50); border-radius:var(--radius-md);">
                                    <div style="font-size:1.5rem; font-weight:800; color:var(--primary-400);">${r.total_topics_completed}</div>
                                    <div style="font-size:0.7rem; color:var(--gray-400); text-transform:uppercase; font-weight:600;">Topics Done</div>
                                </div>
                            </div>

                            <!-- Top 5 Students -->
                            <h4 style="font-size:0.9375rem; font-weight:700; margin-bottom:0.5rem; color:var(--gray-700);">🏆 Top 5 Students</h4>
                            <div style="overflow-x:auto; margin-bottom:1.25rem;">
                                <table class="students-table" style="font-size:0.875rem;">
                                    <thead><tr><th>Rank</th><th>Student</th><th>Grade</th><th>XP</th><th>Topics</th></tr></thead>
                                    <tbody>${topHtml}</tbody>
                                </table>
                            </div>

                            <!-- Subject Performance -->
                            <h4 style="font-size:0.9375rem; font-weight:700; margin-bottom:0.5rem; color:var(--gray-700);">📚 Subject-wise Performance</h4>
                            <div style="overflow-x:auto; margin-bottom:1.25rem;">
                                <table class="students-table" style="font-size:0.875rem;">
                                    <thead><tr><th>Subject</th><th>Active Students</th><th>Total XP</th><th>Topics Done</th></tr></thead>
                                    <tbody>${subjectHtml}</tbody>
                                </table>
                            </div>

                            <!-- Students Needing Attention -->
                            <h4 style="font-size:0.9375rem; font-weight:700; margin-bottom:0.5rem; color:var(--gray-700);">⚠️ Students Needing Attention (0 XP)</h4>
                            <div style="margin-bottom:0.5rem;">
                                ${attentionHtml}
                            </div>
                        </div>
                    </div>
                `;
                showToast("Weekly report generated! 📊", "success");
            })
            .catch(() => {
                reportBox.innerHTML = `
                    <div class="ai-analysis-card" style="margin-top:1.5rem; border-color:var(--rose-300);">
                        <div class="card-body" style="padding:1.25rem;">
                            <p style="color:var(--rose-500);">Network error — make sure XAMPP is running.</p>
                        </div>
                    </div>
                `;
            });
    };

    // ----- Utility: Escape HTML -----
    function escapeHtml(str) {
        if (!str) return "";
        const div = document.createElement("div");
        div.textContent = str;
        return div.innerHTML;
    }

    // ----- Utility: Escape for attributes -----
    function escapeAttr(str) {
        if (!str) return "";
        return str.replace(/\\/g, "\\\\").replace(/'/g, "\\'").replace(/"/g, "&quot;").replace(/\n/g, "\\n").replace(/\r/g, "");
    }
});