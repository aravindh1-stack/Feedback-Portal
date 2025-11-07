<?php
// Entha session'il user login seithullara enbathaiyum, avarudaya role'aiyum ariya, session'ai thodanga.
session_start();

// User login seyyamal irunthalo allathu admin aaga illamalo irunthal, login pakkathirku thiruppi anuppa vendum.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Database configuration'ai ullekka.
require_once __DIR__ . '/../config/db.php';

// Database aatchiyil ethenum thavarugal erpattal, athai velipaduththu.
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Puthiya form number'ai uruvaakkum seyarkadu
function generateFormNumber() {
    return 'FF' . date('Y') . mt_rand(1000, 9999);
}

// --- FORM SUBMISSION HANDLING ( படிவம் சமர்ப்பிப்பு கையாளுதல் ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department = $_POST['department'] ?? '';
    $year = $_POST['year'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $assignments = $_POST['assignments'] ?? [];
    $questions = $_POST['questions'] ?? [];
    $form_number = $_POST['form_number'] ?? '';

    if (!empty($department) && !empty($year) && !empty($semester) && !empty($questions) && !empty($assignments) && !empty($form_number)) {
        try {
            $conn->begin_transaction();
            // Ensure form_questions table exists
            $conn->query("CREATE TABLE IF NOT EXISTS form_questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                form_number VARCHAR(64) NOT NULL,
                question_text VARCHAR(500) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_form_question (form_number, question_text)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Insert unique questions for this form into form_questions
            $seenTexts = [];
            $stmtQ = $conn->prepare("INSERT IGNORE INTO form_questions (form_number, question_text) VALUES (?, ?)");
            foreach ($questions as $q) {
                $qtext = trim($q['text'] ?? '');
                if ($qtext !== '' && !isset($seenTexts[$qtext])) {
                    $seenTexts[$qtext] = true;
                    $stmtQ->bind_param("ss", $form_number, $qtext);
                    $stmtQ->execute();
                }
            }

            // Cross-assign all question texts to every subject/faculty assignment
            foreach ($assignments as $row) {
                $subject_code = trim($row['code'] ?? '');
                $subject_name = trim($row['name'] ?? ''); // optional, not stored currently
                $faculty_id = intval($row['faculty'] ?? 0);
                if ($subject_code !== '' && $faculty_id > 0) {
                    foreach ($questions as $q) {
                        $qtext = trim($q['text'] ?? '');
                        if ($qtext !== '') {
                            $stmt = $conn->prepare("INSERT INTO feedback_forms (form_number, department, year, semester, subject_code, faculty_id, question, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                            $stmt->bind_param("sssssis", $form_number, $department, $year, $semester, $subject_code, $faculty_id, $qtext);
                            $stmt->execute();
                        }
                    }
                }
            }
            $conn->commit();
            $_SESSION['message'] = "Feedback form created successfully! Form Number: " . htmlspecialchars($form_number);
            $_SESSION['message_type'] = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Error creating form: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = "Please fill all required fields, add at least one subject/faculty assignment and at least one question.";
        $_SESSION['message_type'] = 'error';
    }
    header("Location: create_feedback_form.php");
    exit();
}

// Puthiya form'ukku oru form number'ai uruvaakkavum
$current_form_number = generateFormNumber();

// Aasiriyar thagavalgalai database'il irunthu eduththal
$facultyData = [];
$sql = "SELECT id, name, department FROM faculty ORDER BY department, name";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $facultyData[] = $row;
    }
}

// Thanithuvaana thuraigalin pattiyalai eduththal
$departments_query = $conn->query("SELECT DISTINCT department FROM students UNION SELECT DISTINCT department FROM faculty ORDER BY department ASC");
$departments = [];
while($row = $departments_query->fetch_assoc()) {
    if (!empty($row['department'])) {
        $departments[] = $row['department'];
    }
}


// Session'il irukkum seithiyai eduththu, piraku aliththuvida vendum
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>
<?php
    $page_title = 'Create Feedback Form - Admin Dashboard';
    $extra_head = <<<HTML
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0iIzQzMzhDMyIvPgo8cGF0aCBkPSJNOCAxMkg5VjIwSDhWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTEgMTJIMTJWMjBIMTFWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTQgMTJIMTVWMjBIMTRWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-50: #eff6ff; --primary-500: #3b82f6; --primary-600: #2563eb;
            --success-100: #dcfce7; --success-500: #16a34a;
            --danger-100: #fee2e2; --danger-500: #dc2626; --danger-600: #b91c1c;
            --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-400: #9ca3af;
            --gray-500: #6b7280; --gray-600: #4b5563; --gray-800: #1f2937; --gray-900: #111827;
            --body-bg: var(--gray-50); --sidebar-bg: #ffffff; --card-bg: #ffffff; --header-bg: rgba(255, 255, 255, 0.85);
            --border-color: var(--gray-200); --text-color: var(--gray-600); --heading-color: var(--gray-900);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05); --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius-lg: 0.75rem; --radius-xl: 1rem;
            --spacing-2: 0.5rem; --spacing-3: 0.75rem; --spacing-4: 1rem; --spacing-5: 1.25rem; --spacing-6: 1.5rem; --spacing-8: 2rem;
            --font-size-sm: 0.875rem; --font-size-base: 1rem; --font-size-lg: 1.125rem; --font-size-2xl: 1.5rem;
            --transition-fast: all 0.2s ease-in-out; --transition-normal: all 0.3s ease-in-out;
            --sidebar-width: 280px; --header-height: 80px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--body-bg); color: var(--text-color); line-height: 1.6; }
        .admin-layout { display: flex; }
        .main-content { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .content { flex: 1; padding: 3rem var(--spacing-8); }
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border-color); position: fixed; height: 100vh; display: flex; flex-direction: column; z-index: 1000; transition: var(--transition-normal); }
        .sidebar-header { height: var(--header-height); padding: 0 var(--spacing-6); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; }
        .sidebar-logo { display: flex; align-items: center; gap: var(--spacing-3); font-size: var(--font-size-lg); font-weight: 600; color: var(--heading-color); }
        .sidebar-logo i { width: 40px; height: 40px; background: var(--primary-500); color: white; border-radius: var(--radius-lg); display: grid; place-items: center; font-size: 1.2rem; }
        .sidebar-subtitle { font-size: var(--font-size-sm); color: var(--gray-500); font-weight: 500; }
        .sidebar-nav { padding: var(--spacing-4) 0; flex-grow: 1; }
        .nav-section-title { font-size: var(--font-size-sm); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; padding: 0 var(--spacing-6) var(--spacing-3); color: var(--gray-400); }
        .nav-item { display: flex; align-items: center; gap: var(--spacing-4); padding: var(--spacing-3) var(--spacing-6); color: var(--text-color); text-decoration: none; margin: var(--spacing-2) var(--spacing-4); border-radius: var(--radius-lg); font-weight: 500; transition: var(--transition-fast); }
        .nav-item:hover { background: var(--primary-50); color: var(--primary-600); }
        .nav-item.active { background-color: var(--primary-50); color: var(--primary-600); font-weight: 700; position: relative; }
        .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 4px; height: 24px; background: var(--primary-500); border-radius: 0 4px 4px 0; }
        .nav-item.danger:hover { background-color: var(--danger-100); color: var(--danger-600); }
        .nav-item i { width: 20px; text-align: center; }
        .header { height: var(--header-height); background: var(--header-bg); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 var(--spacing-8); position: sticky; top: 0; z-index: 999; flex-wrap: nowrap; }
        .page-title { font-size: var(--font-size-2xl); font-weight: 700; color: var(--heading-color); }
        .header-left { display:flex; align-items:center; gap: var(--spacing-4); }
        .header-right { display: flex; align-items: center; gap: var(--spacing-4); }
        .header-actions { display:flex; align-items:center; gap: var(--spacing-3); }
        .header-search { position: relative; width: 280px; max-width: 100%; }
        .header-btn { width: 44px; height: 44px; border: 1px solid var(--border-color); background: var(--card-bg); border-radius: var(--radius-lg); display: grid; place-items: center; cursor: pointer; transition: var(--transition-fast); position: relative; color: var(--text-color); font-size: 1.1rem; box-shadow: var(--shadow-sm); }
        .header-btn:hover { border-color: var(--primary-500); color: var(--primary-500); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: var(--danger-500); color: white; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 10px; border: 2px solid var(--card-bg); }
        .user-avatar { width: 44px; height: 44px; background: var(--primary-500); border-radius: var(--radius-lg); display: grid; place-items: center; color: white; font-weight: 600; cursor: pointer; transition: var(--transition-fast); }
        .user-avatar:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .card { background: var(--card-bg); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); margin-bottom: 2rem; }
        .card-header { padding: var(--spacing-5) var(--spacing-6); border-bottom: 1px solid var(--border-color); }
        .card-title { font-size: var(--font-size-lg); font-weight: 600; color: var(--heading-color); }
        .card-body { padding: var(--spacing-6); }
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--spacing-6); margin-bottom: 2rem; }
        .form-group label { display: block; margin-bottom: var(--spacing-2); font-weight: 500; color: var(--gray-800); }
        .form-control { width: 100%; padding: var(--spacing-2) var(--spacing-3); border: 1px solid var(--border-color); border-radius: var(--radius-lg); transition: var(--transition-fast); }
        .form-control:focus { outline: none; border-color: var(--primary-500); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .form-control.textarea-lg { min-height: 120px; padding-top: var(--spacing-3); padding-right: 3rem; }
        .btn { display: inline-flex; align-items: center; gap: var(--spacing-2); padding: 0.6rem 1.2rem; border-radius: var(--radius-lg); font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: var(--transition-fast); }
        .btn-primary { background: var(--primary-500); color: white; }
        .btn-primary:hover { background: var(--primary-600); }
        .btn-success { background: var(--success-500); color: white; }
        .btn-success:hover { background-color: #15803d; }
        .btn-danger { background: var(--danger-500); color: white; }
        .btn-danger:hover { background: var(--danger-600); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); }
        .btn-secondary:hover { background: var(--gray-200); }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity var(--transition-fast); }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content { background: var(--card-bg); padding: var(--spacing-8); border-radius: var(--radius-xl); max-width: 500px; width: 90%; box-shadow: var(--shadow-lg); position: relative; transform: scale(0.95); transition: transform var(--transition-fast); }
        .modal-overlay.active .modal-content { transform: scale(1); }
        .modal-close { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; color: var(--gray-400); cursor: pointer; }
        #questionsContainer .card { margin-top: 1.5rem; }
        #questionsContainer .card-header { display: flex; justify-content: space-between; align-items: center; background-color: var(--gray-50); }
        .question-number { font-weight: 600; color: var(--heading-color); }
        .actions-container { display: flex; gap: 1rem; margin-top: 1.5rem; align-items: center; }
        .textarea-wrapper { position: relative; }
        .suggestion-btn { position: absolute; top: 0.75rem; right: 0.75rem; background: var(--success-100); color: var(--success-500); border: 1px solid var(--success-500); width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: grid; place-items: center; transition: var(--transition-fast); }
        .suggestion-btn:hover { background: var(--success-500); color: white; transform: rotate(15deg) scale(1.1); }
        
        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animated { animation: fadeInUp 0.5s ease-out forwards; opacity: 0; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .content { padding: 2rem 1rem; }
            .header { padding: 0 1rem; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
    HTML;
    include __DIR__ . '/includes/header.php';
?>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php $page_heading='Create Feedback Form'; $show_theme_toggle=false; $show_search=false; include __DIR__ . '/includes/topbar.php'; ?>

            <div class="content">
                <form id="feedbackForm" method="post" onsubmit="showLoader()">
                    <input type="hidden" name="form_number" value="<?php echo htmlspecialchars($current_form_number); ?>">
                    
                    <!-- Step 1: Form Details -->
                    <div class="card animated" style="animation-delay: 0.1s;">
                        <div class="card-header">
                            <h2 class="card-title">Step 1: Form Details (Form Number: <?php echo htmlspecialchars($current_form_number); ?>)</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="department">Department</label>
                                    <select id="department" name="department" class="form-control" required>
                                        <option value="">Select Department</option>
                                         <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="year">Academic Year</label>
                                    <select id="year" name="year" class="form-control" required>
                                        <option value="">Select Year</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="semester">Semester</label>
                                    <select id="semester" name="semester" class="form-control" required>
                                        <option value="">Select Semester</option>
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Assign Subjects & Faculty -->
                    <div class="card animated" style="animation-delay: 0.2s;">
                        <div class="card-header">
                            <h2 class="card-title">Step 2: Assign Subjects & Faculty</h2>
                        </div>
                        <div class="card-body">
                            <div id="assignmentsContainer">
                                <!-- Assignment rows will be dynamically inserted here -->
                            </div>
                            <div class="actions-container">
                                <button type="button" class="btn btn-secondary" onclick="addAssignmentRow()">
                                    <i class="fas fa-plus"></i> Add Another Subject
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Create Questions -->
                    <div class="card animated" style="animation-delay: 0.25s;">
                        <div class="card-header">
                            <h2 class="card-title">Step 3: Create Questions</h2>
                        </div>
                        <div class="card-body">
                            <div id="questionsContainer">
                                <!-- Question cards will be dynamically inserted here -->
                            </div>
                            <div class="actions-container">
                                <button type="button" class="btn btn-secondary" onclick="addQuestion()">
                                    <i class="fas fa-plus"></i> Add Blank Question
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                     <div class="animated" style="animation-delay: 0.3s; margin-top: 2rem; text-align: right; display: flex; justify-content: flex-end; gap: 1rem;">
                        <button type="button" class="btn btn-primary" onclick="previewForm()"><i class="fas fa-eye"></i> Preview Form</button>
                        <button type="submit" class="btn btn-success" style="padding: 0.8rem 2rem; font-size: 1rem;"><i class="fas fa-save"></i> Create Form</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div id="messageModal" class="modal-overlay">
        <div class="modal-content" style="text-align: center;">
            <div id="messageIcon" style="font-size: 3rem; margin-bottom: 1rem;"></div>
            <h2 id="messageText" style="margin-bottom: 1.5rem;"></h2>
            <button class="btn btn-primary" onclick="closeModal('messageModal')">Close</button>
        </div>
    </div>
    
    <div id="previewModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 700px;">
             <button class="modal-close" onclick="closeModal('previewModal')">&times;</button>
            <h2 id="previewTitle">Form Preview</h2>
            <div id="previewBody"></div>
        </div>
    </div>
    
    <div id="loadingOverlay" class="loader-overlay">
        <div class="loader-spinner"></div>
    </div>

    <script>
        let questionCount = 0;
        let assignmentCount = 0;
        const facultyData = <?php echo json_encode($facultyData); ?>;
        const sampleQuestions = {
            'CSE': [
                'How would you rate the clarity of explanation for Data Structures concepts?',
                'Was the pace of the Operating Systems course appropriate?',
                'How relevant were the programming assignments in the Web Development course?',
                'Rate the faculty\'s ability to answer questions in the Algorithms class.',
                'Were the lab resources for Machine Learning adequate and accessible?'
            ],
            'ECE': [
                'How effective was the practical demonstration in the Analog Circuits lab?',
                'Rate the explanation of concepts in Digital Signal Processing.',
                'Was the course material for Microprocessors and Microcontrollers up-to-date?',
                'How would you rate the faculty\'s guidance on the final year project?',
                'Were the concepts of Communication Systems explained with sufficient real-world examples?'
            ],
            'Default': [
                'How would you rate the overall teaching effectiveness of the faculty?',
                'Was the faculty available for consultation outside of class hours?',
                'Did the faculty cover the entire syllabus as per the course plan?',
                'How would you rate the quality of the course materials provided?',
                'Did the faculty encourage interactive sessions and discussions?'
            ]
        };

        function showLoader() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function openModal(modalId) { 
            const modal = document.getElementById(modalId);
            modal.classList.add('active');
        }
        function closeModal(modalId) { 
            const modal = document.getElementById(modalId);
            modal.classList.remove('active');
        }
        
        function showMessageModal(message, type) {
            const icon = document.getElementById('messageIcon');
            const text = document.getElementById('messageText');
            if (type === 'success') {
                icon.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success-500);"></i>';
            } else {
                icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger-500);"></i>';
            }
            text.textContent = message;
            openModal('messageModal');
        }

        function generateFacultyOptions() {
            let options = '<option value="">Select Faculty</option>';
            facultyData.forEach(faculty => {
                options += `<option value="${faculty.id}">${faculty.name} (${faculty.department})</option>`;
            });
            return options;
        }

        function addSampleQuestion(index) {
            const department = document.getElementById('department').value;
            if (!department) {
                showMessageModal('Please select a department first to get relevant question suggestions.', 'error');
                return;
            }
            const questions = sampleQuestions[department] || sampleQuestions['Default'];
            const randomQuestion = questions[Math.floor(Math.random() * questions.length)];
            const textarea = document.querySelector(`[data-index="${index}"] textarea`);
            if (textarea) {
                textarea.value = randomQuestion;
            }
        }

        function addQuestion(questionText = '') {
            const container = document.getElementById('questionsContainer');
            const newIndex = questionCount++;
            const card = document.createElement('div');
            card.className = 'card animated';
            card.setAttribute('data-index', newIndex);
            card.style.animationDelay = `${(container.children.length * 0.05)}s`;

            card.innerHTML = `
                <div class="card-header">
                    <span class="question-number">Question ${questionCount}</span>
                    <button type="button" class="btn btn-danger" style="padding: 0.3rem 0.6rem;" onclick="removeQuestion(${newIndex})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Question Text</label>
                        <div class="textarea-wrapper">
                            <textarea class="form-control textarea-lg" name="questions[${newIndex}][text]" placeholder="E.g., Rate the clarity of concepts explained..." required>${questionText}</textarea>
                            <button type="button" class="suggestion-btn" title="Get a suggestion" onclick="addSampleQuestion(${newIndex})">
                                <i class="fas fa-lightbulb"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(card);
            updateQuestionNumbers();
        }

        function removeQuestion(index) {
            const card = document.querySelector(`[data-index="${index}"]`);
            if (card) {
                card.remove();
                updateQuestionNumbers();
            }
        }
        
        function updateQuestionNumbers() {
            document.querySelectorAll('#questionsContainer .card').forEach((card, index) => {
                card.querySelector('.question-number').textContent = `Question ${index + 1}`;
            });
        }

        function addAssignmentRow() {
            const container = document.getElementById('assignmentsContainer');
            const idx = assignmentCount++;
            const row = document.createElement('div');
            row.className = 'card animated';
            row.setAttribute('data-assign-index', idx);
            row.style.animationDelay = `${(container.children.length * 0.05)}s`;
            row.innerHTML = `
                <div class="card-header">
                    <span class="question-number">Subject/Faculty ${container.children.length + 1}</span>
                    <button type="button" class="btn btn-danger" style="padding: 0.3rem 0.6rem;" onclick="removeAssignmentRow(${idx})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="form-grid" style="grid-template-columns: 1fr 2fr 1.5fr;">
                        <div class="form-group">
                            <label>Subject Code</label>
                            <input type="text" class="form-control" name="assignments[${idx}][code]" placeholder="E.g., CS101" required>
                        </div>
                        <div class="form-group">
                            <label>Subject Name</label>
                            <input type="text" class="form-control" name="assignments[${idx}][name]" placeholder="E.g., Data Structures">
                        </div>
                        <div class="form-group">
                            <label>Faculty</label>
                            <select class="form-control" name="assignments[${idx}][faculty]" required>
                                ${generateFacultyOptions()}
                            </select>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(row);
            updateAssignmentNumbers();
        }

        function removeAssignmentRow(idx) {
            const row = document.querySelector(`[data-assign-index="${idx}"]`);
            if (row) {
                row.remove();
                updateAssignmentNumbers();
            }
        }

        function updateAssignmentNumbers() {
            document.querySelectorAll('#assignmentsContainer .card').forEach((card, index) => {
                const label = card.querySelector('.question-number');
                if (label) label.textContent = `Subject/Faculty ${index + 1}`;
            });
        }
        
        function previewForm() {
            const formNumber = document.querySelector('input[name="form_number"]').value;
            const deptSelect = document.getElementById('department');
            const yearSelect = document.getElementById('year');
            const semSelect = document.getElementById('semester');

            const department = deptSelect.options[deptSelect.selectedIndex].text;
            const year = yearSelect.options[yearSelect.selectedIndex].text;
            const semester = semSelect.options[semSelect.selectedIndex].text;
            
            const assignments = Array.from(document.querySelectorAll('#assignmentsContainer .card')).map((card, index) => {
                const code = card.querySelector('input[name^="assignments"][name$="[code]"]').value;
                const name = card.querySelector('input[name^="assignments"][name$="[name]"]').value;
                const facultySelect = card.querySelector('select[name^="assignments"][name$="[faculty]"]');
                const facultyName = facultySelect && facultySelect.selectedIndex >= 0 ? facultySelect.options[facultySelect.selectedIndex].text : '';
                return `<li><strong>${code}</strong> - ${name || 'Untitled'}<br><small>${facultyName}</small></li>`;
            }).join('');

            const questions = Array.from(document.querySelectorAll('#questionsContainer .card')).map((card, index) => {
                const text = card.querySelector('textarea').value;
                return `<li>Q${index+1}: ${text}</li>`;
            }).join('');
            
            const previewBody = document.getElementById('previewBody');
            previewBody.innerHTML = `
                <p><strong>Form Number:</strong> ${formNumber}</p>
                <p><strong>Department:</strong> ${department}</p>
                <p><strong>Year:</strong> ${year}</p>
                <p><strong>Semester:</strong> ${semester}</p>
                <hr style="margin: 1rem 0; border-color: var(--border-color);">
                <h4>Subjects & Faculty:</h4>
                <ul style="list-style-position: inside; padding-left: 0;">${assignments || '<li>No subjects assigned yet.</li>'}</ul>
                <h4 style="margin-top: 1rem;">Questions:</h4>
                <ul style="list-style-position: inside; padding-left: 0;">${questions || '<li>No questions added yet.</li>'}</ul>
            `;
            openModal('previewModal');
        }

        document.addEventListener('DOMContentLoaded', function() {
            addAssignmentRow(); // Start with one subject/faculty row
            addQuestion(); // Start with one blank question
            
            <?php if (!empty($message)): ?>
                showMessageModal('<?php echo addslashes($message); ?>', '<?php echo $message_type; ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>

