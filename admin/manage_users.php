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
    <title>Manage Users - Admin Dashboard</title>
    
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
            --font-size-sm: 0.875rem; --font-size-base: 1rem; --font-size-2xl: 1.5rem; --font-size-3xl: 2.25rem;
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
        .header { height: var(--header-height); background: var(--header-bg); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 var(--spacing-8); position: sticky; top: 0; z-index: 999; }
        .page-title { font-size: var(--font-size-2xl); font-weight: 700; color: var(--heading-color); }
        .card { background: var(--card-bg); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); margin-bottom: 2rem; }
        .card-header { padding: var(--spacing-5) var(--spacing-6); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;}
        .card-title { font-size: var(--font-size-lg); font-weight: 600; color: var(--heading-color); }
        .card-body { padding: var(--spacing-6); }
        .search-and-actions { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .filter-controls { display: flex; gap: var(--spacing-3); align-items: center; flex-wrap: wrap;}
        .search-wrapper { position: relative; }
        .search-input, .filter-select { padding: var(--spacing-2) var(--spacing-3); border: 1px solid var(--border-color); border-radius: var(--radius-lg); font-size: var(--font-size-sm); transition: var(--transition-fast); }
        .search-input { padding-left: 2.5rem; }
        .search-input:focus, .filter-select:focus { outline: none; border-color: var(--primary-500); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .search-icon { position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); }
        .btn { display: inline-flex; align-items: center; gap: var(--spacing-2); padding: 0.6rem 1.2rem; border-radius: var(--radius-lg); font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: var(--transition-fast); }
        .btn-primary { background: var(--primary-500); color: white; }
        .btn-primary:hover { background: var(--primary-600); }
        .btn-danger { background: var(--danger-500); color: white; }
        .btn-danger:hover { background: var(--danger-600); }
        .btn-secondary { background: var(--gray-200); color: var(--gray-800); }
        .btn-secondary:hover { background: var(--gray-300); }
        .table-wrapper { overflow-x: auto; }
        .user-table { width: 100%; border-collapse: collapse; }
        .user-table th, .user-table td { padding: var(--spacing-3) var(--spacing-4); text-align: left; border-bottom: 1px solid var(--border-color); }
        .user-table thead { background-color: var(--gray-50); }
        .user-table th { font-size: var(--font-size-sm); font-weight: 600; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.05em; }
        .user-table tbody tr:hover { background-color: var(--gray-50); }
        .action-buttons { display: flex; gap: var(--spacing-3); }
        .action-btn { background: none; border: none; cursor: pointer; color: var(--gray-400); font-size: 1rem; }
        .action-btn:hover { color: var(--primary-500); }
        .action-btn.delete:hover { color: var(--danger-500); }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity var(--transition-fast); }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content { background: var(--card-bg); padding: var(--spacing-8); border-radius: var(--radius-xl); max-width: 500px; width: 90%; box-shadow: var(--shadow-lg); position: relative; transform: scale(0.95); transition: transform var(--transition-fast); }
        .modal-overlay.active .modal-content { transform: scale(1); }
        .modal-close { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; color: var(--gray-400); cursor: pointer; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-4); }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: var(--spacing-2); font-weight: 500; }
        .form-control { padding: var(--spacing-2) var(--spacing-3); border: 1px solid var(--border-color); border-radius: var(--radius-lg); }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6); margin-bottom: 2rem; }
        .stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: var(--spacing-6); display: flex; align-items: center; gap: var(--spacing-5); }
        .stat-icon { width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; font-size: 1.5rem; flex-shrink: 0; }
        .stat-icon.students { background: var(--primary-50); color: var(--primary-500); }
        .stat-icon.faculty { background: var(--success-100); color: var(--success-500); }
        .stat-value { font-size: var(--font-size-3xl); font-weight: 700; color: var(--heading-color); line-height: 1; }
        .stat-label { font-size: var(--font-size-base); color: var(--text-color); }
        .loader-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(5px); z-index: 9999; display: none; align-items: center; justify-content: center; }
        .loader-spinner { border: 5px solid var(--gray-200); border-top: 5px solid var(--primary-500); border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animated {
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .content { padding: 2rem 1rem; }
            .header { padding: 0 1rem; }
            .search-and-actions { flex-direction: column; align-items: stretch; }
            .form-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div id="loadingOverlay" class="loader-overlay">
        <div class="loader-spinner"></div>
    </div>
    <div class="admin-layout">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" style="text-decoration: none;">
                    <div class="sidebar-logo">
                        <i class="fas fa-graduation-cap"></i>
                        <div>
                            <div>College Feedback</div>
                            <div class="sidebar-subtitle">Admin Panel</div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="#" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="manage_users.php" class="nav-item active"><i class="fas fa-users"></i> Users</a>
                    <a href="manage_forms.php" class="nav-item"><i class="fas fa-file-alt"></i> Manage Forms</a>
                    <a href="view_feedback.php" class="nav-item"><i class="fas fa-comments"></i> View Feedback</a>
                    <a href="student_feedback_list.php" class="nav-item"><i class="fas fa-user-graduate"></i> Student Data</a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="#" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
                    <a href="../includes/logout.php" class="nav-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                 <div>
                    <h1 class="page-title">User Management</h1>
                 </div>
            </header>

            <div class="content">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card animated" style="animation-delay: 0.1s;">
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value" data-count="<?php echo $student_count; ?>">0</div>
                            <div class="stat-label">Total Students</div>
                        </div>
                    </div>
                    <div class="stat-card animated" style="animation-delay: 0.2s;">
                        <div class="stat-icon faculty">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value" data-count="<?php echo $faculty_count; ?>">0</div>
                            <div class="stat-label">Total Faculty</div>
                        </div>
                    </div>
                </div>

                <!-- Students Table Card -->
                <div class="card animated" style="animation-delay: 0.3s;">
                    <div class="card-header">
                        <h2 class="card-title">Students List</h2>
                        <div class="search-and-actions">
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
                            <div class="search-wrapper">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="search-input" id="studentSearch" onkeyup="filterStudentTable()" placeholder="Search students...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('addStudentModal')"><i class="fas fa-plus"></i> Add Student</button>
                            <button class="btn btn-secondary" onclick="openModal('bulkUploadModal')"><i class="fas fa-upload"></i> Bulk Upload</button>
                        </div>
                    </div>
                    <div class="card-body">
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
                                    <?php while($row = $students_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['sin_number']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email'] ?? 'Not provided'); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td><?php echo htmlspecialchars($row['year']); ?></td>
                                        <td><?php echo htmlspecialchars($row['semester']); ?></td>
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

                <!-- Faculty Table Card -->
                <div class="card animated" style="animation-delay: 0.4s;">
                    <div class="card-header">
                        <h2 class="card-title">Faculty List</h2>
                        <div class="search-and-actions">
                             <div class="filter-controls">
                                <select id="facultyDeptFilter" class="filter-select" onchange="filterFacultyTable()">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="search-wrapper">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="search-input" id="facultySearch" onkeyup="filterFacultyTable()" placeholder="Search for faculty...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('addFacultyModal')"><i class="fas fa-plus"></i> Add Faculty</button>
                        </div>
                    </div>
                    <div class="card-body">
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
                                    <?php while($row = $faculty_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
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
            </div>
        </main>
    </div>

    <!-- Add Student Modal -->
    <div id="addStudentModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
            <h2>Add New Student</h2>
            <form method="POST" onsubmit="showLoader()">
                <div class="form-grid">
                    <div class="form-group"><label for="s_name">Name</label><input id="s_name" type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label for="s_sin">SIN Number</label><input id="s_sin" type="text" name="sin_number" class="form-control" required></div>
                    <div class="form-group"><label for="s_email">Email</label><input id="s_email" type="email" name="email" class="form-control" placeholder="student@example.com"></div>
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
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="s_sem">Semester</label>
                        <select id="s_sem" name="semester" class="form-control" required>
                            <option value="">Select Semester</option>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="s_pass">Password</label><input id="s_pass" type="password" name="password" class="form-control" required></div>
                </div>
                <button type="submit" name="add_student" class="btn btn-primary" style="margin-top: 1rem;">Add Student</button>
            </form>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editStudentModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('editStudentModal')">&times;</button>
            <h2>Edit Student Details</h2>
            <form method="POST" onsubmit="showLoader()">
                <input type="hidden" id="edit_student_id" name="student_id">
                <div class="form-grid">
                    <div class="form-group"><label for="edit_s_name">Name</label><input id="edit_s_name" type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label for="edit_s_sin">SIN Number</label><input id="edit_s_sin" type="text" name="sin_number" class="form-control" required></div>
                    <div class="form-group"><label for="edit_s_email">Email</label><input id="edit_s_email" type="email" name="email" class="form-control" placeholder="student@example.com"></div>
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
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_s_sem">Semester</label>
                        <select id="edit_s_sem" name="semester" class="form-control" required>
                             <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <p style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: 1rem;">Password cannot be changed from this panel.</p>
                <button type="submit" name="edit_student" class="btn btn-primary" style="margin-top: 1rem;">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Add Faculty Modal -->
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
                <button type="submit" name="add_faculty" class="btn btn-primary" style="margin-top: 1rem;">Add Faculty</button>
            </form>
        </div>
    </div>
    
    <!-- Edit Faculty Modal -->
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
                    <div class="form-group" style="grid-column: 1 / -1;"><label for="edit_f_email">Email</label><input id="edit_f_email" type="email" name="email" class="form-control" required></div>
                </div>
                 <p style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: 1rem;">Password cannot be changed from this panel.</p>
                <button type="submit" name="edit_faculty" class="btn btn-primary" style="margin-top: 1rem;">Save Changes</button>
            </form>
        </div>
    </div>
    
    <!-- Bulk Upload Modal -->
    <div id="bulkUploadModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('bulkUploadModal')">&times;</button>
            <h2>Bulk Upload Students</h2>
            <div style="background: var(--primary-50); padding: 1rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem;">
                <h3 style="color: var(--primary-600); margin-bottom: 0.5rem;"><i class="fas fa-info-circle"></i> File Format Requirements</h3>
                <p style="font-size: var(--font-size-sm); color: var(--gray-600); margin-bottom: 0.5rem;">Your CSV or Excel file should have the following columns in this exact order:</p>
                <code style="background: white; padding: 0.5rem; border-radius: 4px; display: block; font-size: var(--font-size-sm);">
                    Name, SIN Number, Email, Department, Year, Semester
                </code>
                <p style="font-size: var(--font-size-sm); color: var(--gray-600); margin-top: 0.5rem; margin-bottom: 0;">
                    <strong>Supported formats:</strong> CSV (.csv), Excel (.xls, .xlsx)<br>
                    <strong>Note:</strong> Default password will be set to 'student123' for all uploaded students.
                </p>
            </div>
            
            <div style="background: var(--success-100); padding: 1rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem;">
                <h4 style="color: var(--success-600); margin-bottom: 0.5rem;"><i class="fas fa-download"></i> Download Sample Files</h4>
                <p style="font-size: var(--font-size-sm); color: var(--gray-600); margin-bottom: 0.5rem;">Download sample files to see the correct format:</p>
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
                    <label for="csv_file">Select File (CSV or Excel)</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv,.xls,.xlsx" class="form-control" required>
                    <small style="color: var(--gray-500); font-size: var(--font-size-sm); margin-top: 0.25rem; display: block;">
                        Supported formats: CSV (.csv), Excel (.xls, .xlsx)
                    </small>
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
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationModal" class="modal-overlay">
        <div class="modal-content" style="text-align: center; max-width: 400px;">
            <div id="messageIcon" style="font-size: 3rem; margin-bottom: 1rem; color: var(--danger-500);"><i class="fas fa-exclamation-triangle"></i></div>
            <h2 style="margin-bottom: 1rem;">Are you sure?</h2>
            <p id="deleteConfirmationText" style="margin-bottom: 1.5rem;">This action cannot be undone.</p>
            <form id="deleteForm" method="POST" onsubmit="showLoader()">
                <input type="hidden" id="delete_id" name="">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteConfirmationModal')">Cancel</button>
                <button type="submit" id="delete_submit_button" name="" class="btn btn-danger">Yes, Delete</button>
            </form>
        </div>
    </div>

    <!-- Success/Error Modal -->
    <div id="messageModal" class="modal-overlay">
        <div class="modal-content" style="text-align: center;">
            <div id="messageIcon" style="font-size: 3rem; margin-bottom: 1rem;"></div>
            <h2 id="messageText" style="margin-bottom: 1.5rem;"></h2>
            <button class="btn btn-primary" onclick="closeModal('messageModal')">Close</button>
        </div>
    </div>

    <script>
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
        
        function showBulkUploadModal(message, type) {
            const icon = document.getElementById('messageIcon');
            const text = document.getElementById('messageText');
            if (type === 'success') {
                icon.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success-500);"></i>';
            } else {
                icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger-500);"></i>';
            }
            text.innerHTML = message.replace(/\n/g, '<br>');
            text.style.textAlign = 'left';
            text.style.fontSize = 'var(--font-size-sm)';
            openModal('messageModal');
        }

        function filterStudentTable() {
            const searchFilter = document.getElementById('studentSearch').value.toUpperCase();
            const deptFilter = document.getElementById('studentDeptFilter').value.toUpperCase();
            const yearFilter = document.getElementById('studentYearFilter').value;
            const semFilter = document.getElementById('studentSemFilter').value;
            const table = document.getElementById('studentTable');
            const tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let nameTd = tr[i].getElementsByTagName("td")[0];
                let sinTd = tr[i].getElementsByTagName("td")[1];
                let deptTd = tr[i].getElementsByTagName("td")[3];
                let yearTd = tr[i].getElementsByTagName("td")[4];
                let semTd = tr[i].getElementsByTagName("td")[5];

                if (nameTd && sinTd && deptTd && yearTd && semTd) {
                    let nameMatch = nameTd.textContent.toUpperCase().indexOf(searchFilter) > -1;
                    let sinMatch = sinTd.textContent.toUpperCase().indexOf(searchFilter) > -1;
                    let deptMatch = deptFilter === '' || deptTd.textContent.toUpperCase() === deptFilter;
                    let yearMatch = yearFilter === '' || yearTd.textContent === yearFilter;
                    let semMatch = semFilter === '' || semTd.textContent === semFilter;

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
            <?php if (!empty($message)): ?>
                <?php if (!empty($bulk_errors)): ?>
                    let errorDetails = "<?php echo addslashes($message); ?>\\n\\nError Details:\\n";
                    <?php foreach($bulk_errors as $error): ?>
                        errorDetails += "• <?php echo addslashes($error); ?>\\n";
                    <?php endforeach; ?>
                    showBulkUploadModal(errorDetails, '<?php echo $message_type; ?>');
                <?php else: ?>
                    showMessageModal('<?php echo addslashes($message); ?>', '<?php echo $message_type; ?>');
                <?php endif; ?>
            <?php endif; ?>

            document.querySelectorAll('[data-count]').forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                let current = 0;
                const step = (target / 1000) * 16;
                const update = () => {
                    if (current < target) {
                        current = Math.min(current + step, target);
                        counter.textContent = Math.ceil(current).toLocaleString();
                        requestAnimationFrame(update);
                    } else {
                        counter.textContent = target.toLocaleString();
                    }
                };
                update();
            });
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
                <head>
                    <meta charset="utf-8">
                </head>
                <body>
                    <table>
                        <tr>
                            <td>Name</td>
                            <td>SIN Number</td>
                            <td>Email</td>
                            <td>Department</td>
                            <td>Year</td>
                            <td>Semester</td>
                        </tr>
                        <tr>
                            <td>John Doe</td>
                            <td>E24CS001</td>
                            <td>john.doe@example.com</td>
                            <td>CSE</td>
                            <td>2</td>
                            <td>3</td>
                        </tr>
                        <tr>
                            <td>Jane Smith</td>
                            <td>E24EC002</td>
                            <td>jane.smith@example.com</td>
                            <td>ECE</td>
                            <td>1</td>
                            <td>2</td>
                        </tr>
                        <tr>
                            <td>Mike Johnson</td>
                            <td>E24ME003</td>
                            <td>mike.johnson@example.com</td>
                            <td>MECH</td>
                            <td>3</td>
                            <td>5</td>
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



indha code la filter work aagala andha filter ah correct pani kudu

" code between  and  in the most up-to-date Canvas "admin/manage_users.php (Updated)" document above and am asking a query about/based on this code below.
Instructions to follow:
  * Don't output/edit the document if the query is Direct/Simple. For example, if the query asks for a simple explanation, output a direct answer.
  * Make sure to **edit** the document if the query shows the intent of editing the document, in which case output the entire edited document, **not just that section or the edits**.
    * Don't output the same document/empty document and say that you have edited it.
    * Don't change unrelated code in the document.
  * Don't output  and  in your final response.
  * Any references like "this" or "selected code" refers to the code between  and  tags.
  * Just acknowledge my request in the introduction.
  * Make sure to refer to the document as "Canvas" in your response.
