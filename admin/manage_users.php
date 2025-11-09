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

// Simple Excel reader function for .xlsx files
function readExcelFile($filename) {
    $data = [];
    
    // For .xlsx files (which are ZIP archives)
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filename) === TRUE) {
            // Read the shared strings
            $sharedStrings = [];
            $sharedStringsXML = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedStringsXML) {
                $xml = simplexml_load_string($sharedStringsXML);
                foreach ($xml->si as $si) {
                    $sharedStrings[] = (string)$si->t;
                }
            }
            
            // Read the worksheet
            $worksheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($worksheetXML) {
                $xml = simplexml_load_string($worksheetXML);
                foreach ($xml->sheetData->row as $row) {
                    $rowData = [];
                    foreach ($row->c as $cell) {
                        $value = '';
                        if (isset($cell->v)) {
                            if (isset($cell['t']) && $cell['t'] == 's') {
                                // Shared string
                                $value = $sharedStrings[(int)$cell->v];
                            } else {
                                $value = (string)$cell->v;
                            }
                        }
                        $rowData[] = $value;
                    }
                    $data[] = $rowData;
                }
            }
            $zip->close();
        }
    }
    
    return $data;
}

// Simple function to read .xls files (basic implementation)
function readXlsFile($filename) {
    // For .xls files, we'll use a simple approach
    // This is a basic implementation - for production, consider using PhpSpreadsheet
    $data = [];
    
    // Try to read as CSV if possible (some .xls files can be read this way)
    if (($handle = fopen($filename, 'r')) !== FALSE) {
        while (($row = fgetcsv($handle, 1000, "\t")) !== FALSE) {
            $data[] = $row;
        }
        fclose($handle);
    }
    
    return $data;
}

// Database aatchiyil ethenum thavarugal erpattal, athai velipaduththu.
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- FORM SUBMISSION HANDLING ( படிவம் சமர்ப்பிப்பு கையாளுதல் ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Puthiya maanavarai serkkum seyalkadu
    if (isset($_POST['add_student'])) {
        $sin_number = $_POST['sin_number'];

        // --- DUPLICATE CHECK ---
        $check_stmt = $conn->prepare("SELECT id FROM students WHERE sin_number = ?");
        $check_stmt->bind_param("s", $sin_number);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $_SESSION['message'] = 'This SIN Number already exists. Cannot approve.';
            $_SESSION['message_type'] = 'error';
        } else {
            // No duplicate, proceed with insertion
            $name = $_POST['name'];
            $email = $_POST['email'] ?? '';
            $department = $_POST['department'];
            $year = $_POST['year'];
            $semester = $_POST['semester'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO students (name, sin_number, email, department, year, semester, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiss", $name, $sin_number, $email, $department, $year, $semester, $password);
            if ($stmt->execute()) {
                $_SESSION['message'] = 'Student added successfully!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error adding student: ' . $stmt->error;
                $_SESSION['message_type'] = 'error';
            }
            $stmt->close();
        }
        $check_stmt->close();
        header("Location: manage_users.php");
        exit();
    }
    // Puthiya aasiriyarai serkkum seyalkadu
    elseif (isset($_POST['add_faculty'])) {
        $email = $_POST['email'];

        // --- DUPLICATE CHECK ---
        $check_stmt = $conn->prepare("SELECT id FROM faculty WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $_SESSION['message'] = 'This Email already exists. Cannot approve.';
            $_SESSION['message_type'] = 'error';
        } else {
            // No duplicate, proceed with insertion
            $name = $_POST['name'];
            $department = $_POST['department'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO faculty (name, department, email, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $department, $email, $password);
            if ($stmt->execute()) {
                $_SESSION['message'] = 'Faculty added successfully!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error adding faculty: ' . $stmt->error;
                $_SESSION['message_type'] = 'error';
            }
            $stmt->close();
        }
        $check_stmt->close();
        header("Location: manage_users.php");
        exit();
    }
    // Maanavarai neekkum seyalkadu
    elseif (isset($_POST['delete_student'])) {
        $student_id = $_POST['student_id'];
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Student removed successfully!';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error removing student: ' . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        header("Location: manage_users.php");
        exit();
    }
    // Aasiriyarai neekkum seyalkadu
    elseif (isset($_POST['delete_faculty'])) {
        $faculty_id = $_POST['faculty_id'];
        $stmt = $conn->prepare("DELETE FROM faculty WHERE id = ?");
        $stmt->bind_param("i", $faculty_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Faculty removed successfully!';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error removing faculty: ' . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        header("Location: manage_users.php");
        exit();
    }
    // Maanavarin thagavalgalai thiruththum seyalkadu
    elseif (isset($_POST['edit_student'])) {
        $id = $_POST['student_id'];
        $name = $_POST['name'];
        $sin_number = $_POST['sin_number'];
        $email = $_POST['email'] ?? '';
        $department = $_POST['department'];
        $year = $_POST['year'];
        $semester = $_POST['semester'];

        $stmt = $conn->prepare("UPDATE students SET name = ?, sin_number = ?, email = ?, department = ?, year = ?, semester = ? WHERE id = ?");
        $stmt->bind_param("ssssiis", $name, $sin_number, $email, $department, $year, $semester, $id);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Student details updated successfully!';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error updating student: ' . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        header("Location: manage_users.php");
        exit();
    }
    // Aasiriyarin thagavalgalai thiruththum seyalkadu
    elseif (isset($_POST['edit_faculty'])) {
        $id = $_POST['faculty_id'];
        $name = $_POST['name'];
        $department = $_POST['department'];
        $email = $_POST['email'];

        $stmt = $conn->prepare("UPDATE faculty SET name = ?, department = ?, email = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $department, $email, $id);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Faculty details updated successfully!';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error updating faculty: ' . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        header("Location: manage_users.php");
        exit();
    }
    
    // Bulk upload students from CSV or Excel
    elseif (isset($_POST['bulk_upload_students'])) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file = $_FILES['csv_file']['tmp_name'];
            $file_extension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, ['csv', 'xls', 'xlsx'])) {
                $_SESSION['message'] = 'Please upload a CSV or Excel file only.';
                $_SESSION['message_type'] = 'error';
            } else {
                $row_count = 0;
                $success_count = 0;
                $error_count = 0;
                $errors = [];
                $all_data = [];
                
                // Read file based on extension
                if ($file_extension === 'csv') {
                    // Read CSV file
                    if (($handle = fopen($uploaded_file, 'r')) !== FALSE) {
                        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                            $all_data[] = $row;
                        }
                        fclose($handle);
                    }
                } elseif ($file_extension === 'xlsx') {
                    // Read Excel .xlsx file
                    $all_data = readExcelFile($uploaded_file);
                } elseif ($file_extension === 'xls') {
                    // Read Excel .xls file
                    $all_data = readXlsFile($uploaded_file);
                }
                
                if (!empty($all_data)) {
                    // Skip header row
                    array_shift($all_data);
                    
                    foreach ($all_data as $data) {
                        $row_count++;
                        
                        // Validate CSV format (should have 6 columns: name, sin_number, email, department, year, semester)
                        if (count($data) < 6) {
                            $error_count++;
                            $errors[] = "Row $row_count: Invalid format (missing columns)";
                            continue;
                        }
                        
                        $name = trim($data[0]);
                        $sin_number = trim($data[1]);
                        $email = trim($data[2]);
                        $department = trim($data[3]);
                        $year = trim($data[4]);
                        $semester = trim($data[5]);
                        
                        // Validate required fields
                        if (empty($name) || empty($sin_number) || empty($department) || empty($year) || empty($semester)) {
                            $error_count++;
                            $errors[] = "Row $row_count: Missing required fields";
                            continue;
                        }
                        
                        // Check for duplicate SIN number
                        $check_stmt = $conn->prepare("SELECT id FROM students WHERE sin_number = ?");
                        $check_stmt->bind_param("s", $sin_number);
                        $check_stmt->execute();
                        $check_stmt->store_result();
                        
                        if ($check_stmt->num_rows > 0) {
                            $error_count++;
                            $errors[] = "Row $row_count: SIN Number $sin_number already exists";
                            $check_stmt->close();
                            continue;
                        }
                        $check_stmt->close();
                        
                        // Generate default password (can be changed later)
                        $default_password = 'student123';
                        $password = password_hash($default_password, PASSWORD_DEFAULT);
                        
                        // Insert student
                        $stmt = $conn->prepare("INSERT INTO students (name, sin_number, email, department, year, semester, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssiss", $name, $sin_number, $email, $department, $year, $semester, $password);
                        
                        if ($stmt->execute()) {
                            $success_count++;
                        } else {
                            $error_count++;
                            $errors[] = "Row $row_count: Database error - " . $stmt->error;
                        }
                        $stmt->close();
                    }
                    
                    // Set success/error message
                    if ($success_count > 0) {
                        $message = "Bulk upload completed! $success_count students added successfully.";
                        if ($error_count > 0) {
                            $message .= " $error_count rows had errors.";
                        }
                        $_SESSION['message'] = $message;
                        $_SESSION['message_type'] = 'success';
                        $_SESSION['bulk_errors'] = $errors;
                    } else {
                        $_SESSION['message'] = "No students were added. Please check your file format.";
                        $_SESSION['message_type'] = 'error';
                        $_SESSION['bulk_errors'] = $errors;
                    }
                } else {
                    $_SESSION['message'] = 'Error reading file. Please check the file format and try again.';
                    $_SESSION['message_type'] = 'error';
                }
            }
        } else {
            $_SESSION['message'] = 'Please select a CSV or Excel file to upload.';
            $_SESSION['message_type'] = 'error';
        }
        header("Location: manage_users.php");
        exit();
    }
}

// --- DATA FETCHING ( தரவுகளைப் பெறுதல் ) ---
$students_result = $conn->query("SELECT * FROM students ORDER BY name ASC");
$faculty_result = $conn->query("SELECT * FROM faculty ORDER BY name ASC");
$student_count_result = $conn->query("SELECT COUNT(id) as count FROM students");
$faculty_count_result = $conn->query("SELECT COUNT(id) as count FROM faculty");

$student_count = ($student_count_result) ? $student_count_result->fetch_assoc()['count'] : 0;
$faculty_count = ($faculty_count_result) ? $faculty_count_result->fetch_assoc()['count'] : 0;

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
$bulk_errors = [];
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
if (isset($_SESSION['bulk_errors'])) {
    $bulk_errors = $_SESSION['bulk_errors'];
    unset($_SESSION['bulk_errors']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Aarasys</title>

    <link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0iIzQzMzhDMyIvPgo8cGF0aCBkPSJNOCAxMkg5VjIwSDhWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTEgMTJIMTJWMjBIMTFWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTQgMTJIMTVWMjBIMTRWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* === NEW DESIGN STYLES (Based on Image) === */

        /* 1. CSS Variables (Theme) */
        :root {
            /* Palette */
            --primary-blue: #3b82f6; 
            --primary-purple: #6366F1;
            --dark-bg: #1f2937;
            --light-bg: #f3f4f6;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
            --text-dark: #111827;
            --text-body: #4b5563;
            --text-light: #f9fafb;
            --text-muted: #9ca3af;
            --text-blue: #2563eb;
            --success-bg: #dcfce7;
            --success-text: #16a34a;
            --danger-bg: #fee2e2;
            --danger-text: #dc2626;
            --info-bg: #eff6ff;
            --info-text: #2563eb;
            
            /* Sizing & Spacing */
            --sidebar-width: 280px;
            --header-height: 88px;
            --radius-sm: 0.375rem; --radius-md: 0.5rem; --radius-lg: 0.75rem;
            --radius-xl: 1rem; --radius-2xl: 1.5rem; --radius-full: 9999px;

            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1);
        }

        /* 2. Base & Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-body);
            -webkit-font-smoothing: antialiased;
        }
        
        a { text-decoration: none; color: inherit; }
        button { font-family: inherit; }

        /* 3. Main Layout */
        .admin-layout { display: flex; }
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .content-area {
            padding: 2rem 2.5rem;
            flex: 1;
        }

        /* 4. Header (Topbar) */
        .header {
            height: var(--header-height);
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2.5rem;
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .header-title {
            font-size: 1.75rem; 
            font-weight: 700;
            color: var(--text-dark);
        }
        .header-actions { display: flex; align-items: center; gap: 1rem; }
        .search-wrapper { position: relative; }
        .search-wrapper i {
            position: absolute; left: 1rem; top: 50%;
            transform: translateY(-50%); color: var(--text-muted);
        }
        .search-input {
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background-color: var(--light-bg);
            font-size: 0.9rem;
            width: 280px;
            transition: all 0.2s ease;
        }
        .search-input:focus {
            outline: none; border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            background-color: var(--card-bg);
        }
        .header-btn {
            width: 44px; height: 44px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            display: grid; place-items: center;
            font-size: 1.1rem; color: var(--text-body);
            cursor: pointer; transition: all 0.2s ease;
        }
        .header-btn:hover { border-color: var(--primary-blue); color: var(--primary-blue); }
        .user-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            background-color: var(--primary-purple);
            color: var(--text-light);
            display: grid; place-items: center;
            font-weight: 600; font-size: 1.1rem;
            border: 2px solid var(--card-bg);
            box-shadow: 0 0 0 2px var(--primary-purple);
            cursor: pointer;
        }

        /* 5. Buttons */
        .btn {
            display: inline-flex; align-items: center;
            gap: 0.5rem; padding: 0.65rem 1rem;
            border-radius: var(--radius-md);
            font-weight: 600; text-decoration: none;
            border: none; cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .btn-primary { background-color: var(--primary-blue); color: var(--text-light); }
        .btn-primary:hover { background-color: var(--text-blue); box-shadow: var(--shadow-md); }
        .btn-secondary {
            background-color: var(--card-bg);
            color: var(--text-body);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .btn-secondary:hover { background-color: var(--light-bg); border-color: #d1d5db; }
        .btn-danger { background-color: var(--danger-text); color: var(--text-light); }
        .btn-danger:hover { background-color: #b91c1c; box-shadow: var(--shadow-md); }

        /* 6. Tabs */
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .tab-btn {
            padding: 0.6rem 1.25rem;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-body);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .tab-btn.active, .tab-btn:hover {
            background: var(--card-bg);
            color: var(--primary-blue);
            border-color: var(--primary-blue);
            box-shadow: var(--shadow-sm);
        }
        .tab-btn.active {
             background: var(--info-bg);
        }

        /* 7. Card */
        .grid-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }
        .grid-card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .search-and-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-controls {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-select, .form-control {
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            background-color: var(--card-bg);
            color: var(--text-body);
            transition: all 0.2s ease;
        }
        .filter-select:focus, .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .grid-card-header .search-wrapper {
             flex-grow: 1;
        }
        .grid-card-header .search-input {
            width: 100%;
            min-width: 200px;
        }
        .grid-card-body {
            padding: 0; /* Remove padding for full-width table */
        }

        /* 8. Table */
        .table-wrapper { overflow-x: auto; }
        .user-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .user-table th, .user-table td {
            padding: 0.85rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }
        .user-table thead {
            background-color: var(--light-bg);
        }
        .user-table th {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .user-table tbody tr:hover {
            background-color: var(--light-bg);
        }
        .user-table td .badge {
            font-weight: 500;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.6rem;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 500;
            background: var(--light-bg);
            color: var(--text-body);
            border: 1px solid var(--border-color);
        }
        .action-buttons { display: flex; gap: 0.5rem; }
        .action-btn {
            width: 32px; height: 32px;
            border-radius: var(--radius-md);
            display: grid;
            place-items: center;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .action-btn:hover {
            background-color: var(--info-bg);
            color: var(--info-text);
        }
        .action-btn.delete:hover {
            background-color: var(--danger-bg);
            color: var(--danger-text);
        }
        
        /* 9. Modals */
        .modal-overlay {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(5px);
            z-index: 1001;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .modal-content {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: var(--radius-xl);
            max-width: 600px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            position: relative;
            transform: scale(0.95) translateY(10px);
            transition: all 0.2s ease-out;
        }
        .modal-overlay.active .modal-content {
            transform: scale(1) translateY(0);
        }
        .modal-close {
            position: absolute; top: 0.75rem; right: 0.75rem;
            width: 36px; height: 36px;
            border-radius: 50%;
            background: none; border: none;
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            display: grid; place-items: center;
            transition: all 0.2s ease;
        }
        .modal-close:hover {
            background-color: var(--light-bg);
            color: var(--text-dark);
        }
        .modal-content h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }
        
        /* 10. Forms in Modals */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        .form-control[type="file"] {
            padding: 0.5rem 0.75rem;
        }

        /* 11. Bulk Upload & Info Boxes */
        .info-box {
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            background: var(--info-bg);
            border: 1px solid var(--primary-blue);
        }
        .info-box h3, .info-box h4 {
            color: var(--info-text);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .info-box p, .info-box li {
            font-size: 0.9rem;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .info-box code {
            background: rgba(255,255,255,0.7);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            color: #4b5563;
        }
        .info-box.success {
             background: var(--success-bg);
             border-color: var(--success-text);
        }
        .info-box.success h4 { color: #15803d; }
        .info-box.success p { color: #166534; }
        
        /* 12. Loader */
        .loader-overlay {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .loader-spinner {
            border: 5px solid var(--border-color);
            border-top: 5px solid var(--primary-blue);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* 13. Responsive */
        @media (max-width: 1200px) {
            .grid-card-header { flex-direction: column; align-items: stretch; }
            .search-and-actions { width: 100%; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { display: none; /* Add JS to toggle */ }
            .content-area { padding: 1.5rem 1rem; }
            .header { padding: 0 1rem; }
            .header-title { display: none; }
            .header .search-wrapper { display: none; }
            .form-grid { grid-template-columns: 1fr; }
        }

    </style>
</head>
<body>
    
    <div id="loadingOverlay" class="loader-overlay">
        <div class="loader-spinner"></div>
    </div>

    <div class="admin-layout">
        
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            
            <header class="header">
                <h1 class="header-title">Manage Users</h1>
                
                <div class="header-actions">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="globalSearch" class="search-input" placeholder="Search users...">
                    </div>
                    
                    <button class="header-btn" title="Toggle Theme">
                        <i class="fas fa-sun"></i>
                    </button>
                    <button class="header-btn" title="Notifications">
                        <i class="fas fa-bell"></i>
                    </button>
                    
                    <div class="user-avatar" title="Admin">
                        AD
                    </div>
                </div>
            </header>
            
            <div class="content-area">
                
                <div class="tabs">
                    <button class="tab-btn active" data-tab="students">
                        <i class="fas fa-user-graduate"></i> Students (<?php echo $student_count; ?>)
                    </button>
                    <button class="tab-btn" data-tab="faculty">
                        <i class="fas fa-user-tie"></i> Faculty (<?php echo $faculty_count; ?>)
                    </button>
                </div>

                <section id="students-section">
                    <div class="grid-card">
                        <div class="grid-card-header">
                            <div class="filter-controls">
                                <select id="studentDeptFilter" class="filter-select" onchange="filterStudentTable()">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="studentYearFilter" class="filter-select" onchange="filterStudentTable()">
                                    <option value="">All Years</option>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                                <select id="studentSemFilter" class="filter-select" onchange="filterStudentTable()">
                                    <option value="">All Semesters</option>
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="search-and-actions">
                                <div class="search-wrapper">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="search-input" id="studentSearch" onkeyup="filterStudentTable()" placeholder="Search by name or SIN...">
                                </div>
                                <button class="btn btn-secondary" onclick="openModal('bulkUploadModal')"><i class="fas fa-upload"></i> Bulk Upload</button>
                                <button class="btn btn-primary" onclick="openModal('addStudentModal')"><i class="fas fa-plus"></i> Add Student</button>
                            </div>
                        </div>
                        <div class="grid-card-body">
                            <div class="table-wrapper">
                                <table class="user-table" id="studentTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>SIN Number</th>
                                            <th>Email</th>
                                            <th>Department</th>
                                            <th>Year</th>
                                            <th>Semester</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $students_result->data_seek(0); // Reset pointer ?>
                                        <?php while($row = $students_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['sin_number']); ?></td>
                                            <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                                            <td><span class="badge"><?php echo htmlspecialchars($row['department']); ?></span></td>
                                            <td><span class="badge"><?php echo htmlspecialchars($row['year']); ?></span></td>
                                            <td><span class="badge"><?php echo htmlspecialchars($row['semester']); ?></span></td>
                                            <td class="action-buttons">
                                                <button class="action-btn" title="Edit" onclick='openEditStudentModal(<?php echo json_encode($row); ?>)'><i class="fas fa-pencil-alt"></i></button>
                                                <button class="action-btn delete" title="Delete" onclick="openDeleteConfirmationModal(<?php echo $row['id']; ?>, 'student')"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="faculty-section" style="display:none;">
                    <div class="grid-card">
                        <div class="grid-card-header">
                             <div class="filter-controls">
                                <select id="facultyDeptFilter" class="filter-select" onchange="filterFacultyTable()">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="search-and-actions">
                                <div class="search-wrapper">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="search-input" id="facultySearch" onkeyup="filterFacultyTable()" placeholder="Search by name or email...">
                                </div>
                                <button class="btn btn-primary" onclick="openModal('addFacultyModal')"><i class="fas fa-plus"></i> Add Faculty</button>
                            </div>
                        </div>
                        <div class="grid-card-body">
                            <div class="table-wrapper">
                                <table class="user-table" id="facultyTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Department</th>
                                            <th>Email</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $faculty_result->data_seek(0); // Reset pointer ?>
                                        <?php while($row = $faculty_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><span class="badge"><?php echo htmlspecialchars($row['department']); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td class="action-buttons">
                                                <button class="action-btn" title="Edit" onclick='openEditFacultyModal(<?php echo json_encode($row); ?>)'><i class="fas fa-pencil-alt"></i></button>
                                                <button class="action-btn delete" title="Delete" onclick="openDeleteConfirmationModal(<?php echo $row['id']; ?>, 'faculty')"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
                
            </div> </main> </div> <div id="addStudentModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
            <h2>Add New Student</h2>
            <form method="POST" onsubmit="showLoader()">
                <div class="form-grid">
                    <div class="form-group"><label for="s_name">Name</label><input id="s_name" type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label for="s_sin">SIN Number</label><input id="s_sin" type="text" name="sin_number" class="form-control" required></div>
                    <div class="form-group full-width"><label for="s_email">Email</label><input id="s_email" type="email" name="email" class="form-control" placeholder="student@example.com"></div>
                    <div class="form-group">
                        <label for="s_dept">Department</label>
                        <select id="s_dept" name="department" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="s_year">Year</label>
                        <select id="s_year" name="year" class="form-control" required>
                            <option value="">Select Year</option>
                            <option value="1">1st Year</option><option value="2">2nd Year</option><option value="3">3rd Year</option><option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="s_sem">Semester</label>
                        <select id="s_sem" name="semester" class="form-control" required>
                            <option value="">Select Semester</option>
                            <?php for ($i = 1; $i <= 8; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="s_pass">Password</label><input id="s_pass" type="password" name="password" class="form-control" required></div>
                </div>
                <button type="submit" name="add_student" class="btn btn-primary" style="margin-top: 1.5rem;">Add Student</button>
            </form>
        </div>
    </div>

    <div id="editStudentModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('editStudentModal')">&times;</button>
            <h2>Edit Student Details</h2>
            <form method="POST" onsubmit="showLoader()">
                <input type="hidden" id="edit_student_id" name="student_id">
                <div class="form-grid">
                    <div class="form-group"><label for="edit_s_name">Name</label><input id="edit_s_name" type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label for="edit_s_sin">SIN Number</label><input id="edit_s_sin" type="text" name="sin_number" class="form-control" required></div>
                    <div class="form-group full-width"><label for="edit_s_email">Email</label><input id="edit_s_email" type="email" name="email" class="form-control" placeholder="student@example.com"></div>
                    <div class="form-group">
                        <label for="edit_s_dept">Department</label>
                        <select id="edit_s_dept" name="department" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_s_year">Year</label>
                        <select id="edit_s_year" name="year" class="form-control" required>
                            <option value="1">1st Year</option><option value="2">2nd Year</option><option value="3">3rd Year</option><option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_s_sem">Semester</label>
                        <select id="edit_s_sem" name="semester" class="form-control" required>
                             <?php for ($i = 1; $i <= 8; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?>
                        </select>
                    </div>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 1.5rem;">Password cannot be changed from this panel.</p>
                <button type="submit" name="edit_student" class="btn btn-primary" style="margin-top: 0.5rem;">Save Changes</button>
            </form>
        </div>
    </div>

    <div id="addFacultyModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('addFacultyModal')">&times;</button>
            <h2>Add New Faculty</h2>
            <form method="POST" onsubmit="showLoader()">
                <div class="form-grid">
                    <div class="form-group"><label for="f_name">Name</label><input id="f_name" type="text" name="name" class="form-control" required></div>
                    <div class="form-group">
                        <label for="f_dept">Department</label>
                        <select id="f_dept" name="department" class="form-control" required>
                            <option value="">Select Department</option>
                             <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="f_email">Email</label><input id="f_email" type="email" name="email" class="form-control" required></div>
                    <div class="form-group"><label for="f_pass">Password</label><input id="f_pass" type="password" name="password" class="form-control" required></div>
                </div>
                <button type="submit" name="add_faculty" class="btn btn-primary" style="margin-top: 1.5rem;">Add Faculty</button>
            </form>
        </div>
    </div>
    
    <div id="editFacultyModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('editFacultyModal')">&times;</button>
            <h2>Edit Faculty Details</h2>
            <form method="POST" onsubmit="showLoader()">
                <input type="hidden" id="edit_faculty_id" name="faculty_id">
                <div class="form-grid">
                    <div class="form-group"><label for="edit_f_name">Name</label><input id="edit_f_name" type="text" name="name" class="form-control" required></div>
                    <div class="form-group">
                        <label for="edit_f_dept">Department</label>
                        <select id="edit_f_dept" name="department" class="form-control" required>
                            <option value="">Select Department</option>
                           <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full-width"><label for="edit_f_email">Email</label><input id="edit_f_email" type="email" name="email" class="form-control" required></div>
                </div>
                 <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 1.5rem;">Password cannot be changed from this panel.</p>
                <button type="submit" name="edit_faculty" class="btn btn-primary" style="margin-top: 0.5rem;">Save Changes</button>
            </form>
        </div>
    </div>
    
    <div id="bulkUploadModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('bulkUploadModal')">&times;</button>
            <h2>Bulk Upload Students</h2>
            
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> File Format Requirements</h3>
                <p>Your file must have the following columns in this exact order. The first row should be the header.</p>
                <code>Name, SIN Number, Email, Department, Year, Semester</code>
                <p style="margin-top: 0.5rem; margin-bottom: 0;"><strong>Note:</strong> Default password will be set to 'student123' for all new students.</p>
            </div>

            <div class="info-box success">
                <h4><i class="fas fa-download"></i> Download Sample Files</h4>
                <p>Use these samples to ensure your format is correct.</p>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="downloadSampleCSV()">
                        <i class="fas fa-file-csv"></i> CSV Sample
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="downloadSampleExcel()">
                        <i class="fas fa-file-excel"></i> Excel Sample
                    </button>
                </div>
            </div>
            
            <form method="POST" enctype="multipart/form-data" onsubmit="showLoader()">
                <div class="form-group">
                    <label for="csv_file">Select File to Upload</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv,.xls,.xlsx" class="form-control" required>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('bulkUploadModal')">Cancel</button>
                    <button type="submit" name="bulk_upload_students" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Students
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="deleteConfirmationModal" class="modal-overlay">
        <div class="modal-content" style="text-align: center; max-width: 420px;">
            <div style="font-size: 3rem; margin-bottom: 1rem; color: var(--danger-text);"><i class="fas fa-exclamation-triangle"></i></div>
            <h2 style="margin-bottom: 0.5rem;">Are you sure?</h2>
            <p id="deleteConfirmationText" style="margin-bottom: 1.5rem; font-size: 1rem;">This action cannot be undone.</p>
            <form id="deleteForm" method="POST" onsubmit="showLoader()">
                <input type="hidden" id="delete_id" name="">
                <div style="display: flex; gap: 0.75rem; justify-content: center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteConfirmationModal')">Cancel</button>
                    <button type="submit" id="delete_submit_button" name="" class="btn btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>

    <div id="messageModal" class="modal-overlay">
        <div class="modal-content" style="text-align: center; max-width: 450px;">
            <div id="messageIcon" style="font-size: 3rem; margin-bottom: 1rem;"></div>
            <h2 id="messageText" style="margin-bottom: 1.5rem; font-size: 1.1rem; line-height: 1.6;"></h2>
            <button class="btn btn-primary" onclick="closeModal('messageModal')">Close</button>
        </div>
    </div>

    <script>
        function showLoader() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function openModal(modalId) { 
            const modal = document.getElementById(modalId);
            if(modal) modal.classList.add('active');
        }
        function closeModal(modalId) { 
            const modal = document.getElementById(modalId);
            if(modal) modal.classList.remove('active');
        }

        function openEditStudentModal(studentData) {
            document.getElementById('edit_student_id').value = studentData.id;
            document.getElementById('edit_s_name').value = studentData.name;
            document.getElementById('edit_s_sin').value = studentData.sin_number;
            document.getElementById('edit_s_email').value = studentData.email || '';
            document.getElementById('edit_s_dept').value = studentData.department;
            document.getElementById('edit_s_year').value = studentData.year;
            document.getElementById('edit_s_sem').value = studentData.semester;
            openModal('editStudentModal');
        }
        
        function openEditFacultyModal(facultyData) {
            document.getElementById('edit_faculty_id').value = facultyData.id;
            document.getElementById('edit_f_name').value = facultyData.name;
            document.getElementById('edit_f_dept').value = facultyData.department;
            document.getElementById('edit_f_email').value = facultyData.email;
            openModal('editFacultyModal');
        }

        function openDeleteConfirmationModal(id, type) {
            const form = document.getElementById('deleteForm');
            const idInput = document.getElementById('delete_id');
            const submitButton = document.getElementById('delete_submit_button');
            if (type === 'student') {
                idInput.name = 'student_id';
                submitButton.name = 'delete_student';
                document.getElementById('deleteConfirmationText').textContent = 'Do you really want to delete this student? This action cannot be undone.';
            } else {
                idInput.name = 'faculty_id';
                submitButton.name = 'delete_faculty';
                document.getElementById('deleteConfirmationText').textContent = 'Do you really want to delete this faculty member? This action cannot be undone.';
            }
            idInput.value = id;
            openModal('deleteConfirmationModal');
        }
        
        function showMessageModal(message, type) {
            const modal = document.getElementById('messageModal');
            const icon = modal.querySelector('#messageIcon');
            const text = modal.querySelector('#messageText');
            if (type === 'success') {
                icon.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success-text);"></i>';
            } else {
                icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger-text);"></i>';
            }
            text.innerHTML = message.replace(/\n/g, '<br>'); // Use innerHTML for <br>
            text.style.textAlign = 'center';
            text.style.fontSize = '1.1rem';
            openModal('messageModal');
        }
        
        function showBulkUploadModal(message, type) {
            const modal = document.getElementById('messageModal');
            const icon = modal.querySelector('#messageIcon');
            const text = modal.querySelector('#messageText');
            if (type === 'success') {
                icon.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success-text);"></i>';
            } else {
                icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger-text);"></i>';
            }
            text.innerHTML = message.replace(/\n/g, '<br>');
            text.style.textAlign = 'left'; // Align left for lists
            text.style.fontSize = '0.9rem'; // Smaller for details
            openModal('messageModal');
        }

        function filterStudentTable() {
            const searchFilter = document.getElementById('studentSearch').value.toUpperCase();
            const deptFilter = document.getElementById('studentDeptFilter').value.toUpperCase();
            const yearFilter = document.getElementById('studentYearFilter').value;
            const semFilter = document.getElementById('studentSemFilter').value;
            const table = document.getElementById('studentTable');
            const tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) { // Start from 1 to skip header
                let nameTd = tr[i].getElementsByTagName("td")[0];
                let sinTd = tr[i].getElementsByTagName("td")[1];
                let deptTd = tr[i].getElementsByTagName("td")[3];
                let yearTd = tr[i].getElementsByTagName("td")[4];
                let semTd = tr[i].getElementsByTagName("td")[5];

                if (nameTd && sinTd && deptTd && yearTd && semTd) {
                    let nameMatch = nameTd.textContent.toUpperCase().indexOf(searchFilter) > -1;
                    let sinMatch = sinTd.textContent.toUpperCase().indexOf(searchFilter) > -1;
                    let deptMatch = deptFilter === '' || deptTd.textContent.toUpperCase() === deptFilter;
                    let yearMatch = yearFilter === '' || yearTd.textContent.includes(yearFilter);
                    let semMatch = semFilter === '' || semTd.textContent.includes(semFilter);

                    if ((nameMatch || sinMatch) && deptMatch && yearMatch && semMatch) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
        }

        function filterFacultyTable() {
            const searchFilter = document.getElementById('facultySearch').value.toUpperCase();
            const deptFilter = document.getElementById('facultyDeptFilter').value.toUpperCase();
            const table = document.getElementById('facultyTable');
            const tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let nameTd = tr[i].getElementsByTagName("td")[0];
                let deptTd = tr[i].getElementsByTagName("td")[1];
                let emailTd = tr[i].getElementsByTagName("td")[2];

                if (nameTd && deptTd && emailTd) {
                    let textMatch = (nameTd.textContent + emailTd.textContent).toUpperCase().indexOf(searchFilter) > -1;
                    let deptMatch = deptFilter === '' || deptTd.textContent.toUpperCase() === deptFilter;
                    
                    if (textMatch && deptMatch) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Show session message if it exists
            <?php if (!empty($message)): ?>
                <?php if (!empty($bulk_errors)): ?>
                    let errorDetails = "<?php echo addslashes($message); ?>\n\nError Details:\n";
                    <?php foreach($bulk_errors as $error): ?>
                        errorDetails += "• <?php echo addslashes($error); ?>\n";
                    <?php endforeach; ?>
                    showBulkUploadModal(errorDetails, '<?php echo $message_type; ?>');
                <?php else: ?>
                    showMessageModal('<?php echo addslashes($message); ?>', '<?php echo $message_type; ?>');
                <?php endif; ?>
            <?php endif; ?>

            // Tab switching
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function(){
                    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
                    this.classList.add('active');
                    var tab = this.getAttribute('data-tab');
                    document.getElementById('students-section').style.display = tab === 'students' ? 'block' : 'none';
                    document.getElementById('faculty-section').style.display = tab === 'faculty' ? 'block' : 'none';
                    
                    // Clear search fields when switching tabs
                    document.getElementById('studentSearch').value = '';
                    document.getElementById('facultySearch').value = '';
                    document.getElementById('globalSearch').value = '';
                    filterStudentTable();
                    filterFacultyTable();
                });
            });

            // Global search in topbar -> routes to active tab input
            const globalSearch = document.getElementById('globalSearch');
            if (globalSearch) {
                globalSearch.addEventListener('input', function(){
                    const value = this.value;
                    const activeTab = document.querySelector('.tab-btn.active')?.getAttribute('data-tab') || 'students';
                    if (activeTab === 'students') {
                        const input = document.getElementById('studentSearch');
                        if (input) { input.value = value; }
                        if (typeof filterStudentTable === 'function') filterStudentTable();
                    } else {
                        const input = document.getElementById('facultySearch');
                        if (input) { input.value = value; }
                        if (typeof filterFacultyTable === 'function') filterFacultyTable();
                    }
                });
            }

            // Theme Toggle
            const themeToggleBtn = document.querySelector('.header-btn[title="Toggle Theme"]');
            if(themeToggleBtn) {
                themeToggleBtn.addEventListener('click', () => {
                    document.body.classList.toggle('dark-theme');
                    const icon = themeToggleBtn.querySelector('i');
                    if (document.body.classList.contains('dark-theme')) {
                        icon.classList.remove('fa-sun'); icon.classList.add('fa-moon');
                    } else {
                        icon.classList.remove('fa-moon'); icon.classList.add('fa-sun');
                    }
                });
            }
        });

        // Function to download sample CSV
        function downloadSampleCSV() {
            const csvContent = "Name,SIN Number,Email,Department,Year,Semester\n" +
                             "John Doe,E24CS001,john.doe@example.com,CSE,2,3\n" +
                             "Jane Smith,E24EC002,jane.smith@example.com,ECE,1,2\n" +
                             "Mike Johnson,E24ME003,mike.johnson@example.com,MECH,3,5";
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "student_bulk_upload_sample.csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Function to download sample Excel file
        function downloadSampleExcel() {
            // Create a simple HTML table that Excel can open
            const htmlContent = `
                <html>
                <head><meta charset="utf-8"></head>
                <body>
                    <table>
                        <tr>
                            <td>Name</td><td>SIN Number</td><td>Email</td>
                            <td>Department</td><td>Year</td><td>Semester</td>
                        </tr>
                        <tr>
                            <td>John Doe</td><td>E24CS001</td><td>john.doe@example.com</td>
                            <td>CSE</td><td>2</td><td>3</td>
                        </tr>
                        <tr>
                            <td>Jane Smith</td><td>E24EC002</td><td>jane.smith@example.com</td>
                            <td>ECE</td><td>1</td><td>2</td>
                        </tr>
                        <tr>
                            <td>Mike Johnson</td><td>E24ME003</td><td>mike.johnson@example.com</td>
                            <td>MECH</td><td>3</td><td>5</td>
                        </tr>
                    </table>
                </body>
                </html>
            `;
            
            const blob = new Blob([htmlContent], { type: 'application/vnd.ms-excel' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "student_bulk_upload_sample.xls");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
    </body>
    </html>