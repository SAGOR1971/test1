<?php
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

require_login();

// Ensure tables exist (runtime-safe schema creation)
$conn->query("CREATE TABLE IF NOT EXISTS expense_reasons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reason_name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_name VARCHAR(255) NOT NULL,
    expense_date DATETIME NOT NULL,
    reason_id INT,
    amount DECIMAL(10, 2) NOT NULL,
    explanation TEXT,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reason_id) REFERENCES expense_reasons(id) ON DELETE SET NULL
)");

// Insert default reasons if table is empty
$checkDefaults = $conn->query("SELECT COUNT(*) as cnt FROM expense_reasons")->fetch_assoc();
if ($checkDefaults['cnt'] == 0) {
    $defaultReasons = ['Salary', 'Office Supplies', 'Travel', 'Meals', 'Equipment', 'Utilities', 'Maintenance'];
    foreach ($defaultReasons as $reason) {
        $stmt = $conn->prepare("INSERT INTO expense_reasons (reason_name) VALUES (?)");
        $stmt->bind_param('s', $reason);
        $stmt->execute();
        $stmt->close();
    }
}

// Pull any flashed data (errors/old input) from previous POST redirect
$errors = $_SESSION['expense_errors'] ?? [];
$old = $_SESSION['expense_old'] ?? [];
unset($_SESSION['expense_errors'], $_SESSION['expense_old']);

$success = isset($_GET['success']);

// Initialize form variables with flashed old input if present
$employee_name = $old['employee_name'] ?? '';
$expense_date = $old['expense_date'] ?? '';
$amount = $old['amount'] ?? '';
$explanation = $old['explanation'] ?? '';
$reason_id = isset($old['reason_id']) ? (int)$old['reason_id'] : 0;
$new_reason = $old['new_reason'] ?? '';

// List of employees
$employees = [
    'Imran Mehedi Nabil',
    'Farhan Labib',
    'MD. Faisal Bin Mozid'
];

// Handle expense form submission
if (is_post()) {
    $employee_name = trim($_POST['employee_name'] ?? '');
    $expense_date = $_POST['expense_date'] ?? '';
    $amount = $_POST['amount'] ?? '0';
    $amountValue = (float)$amount;
    $explanation = trim($_POST['explanation'] ?? '');
    $reason_id = (int)($_POST['reason_id'] ?? 0);
    $new_reason = trim($_POST['new_reason'] ?? '');

    // Validation
    if (empty($employee_name)) $errors[] = "Employee name is required.";
    if (empty($expense_date)) $errors[] = "Date/Time is required.";
    if ($amountValue <= 0) $errors[] = "Amount must be greater than 0.";

    // Handle new reason or existing reason
    if (!empty($new_reason)) {
        // Check if reason already exists
        $checkStmt = $conn->prepare("SELECT id FROM expense_reasons WHERE reason_name = ?");
        $checkStmt->bind_param('s', $new_reason);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $reason_id = $existing['id'];
        } else {
            // Insert new reason
            $insertReasonStmt = $conn->prepare("INSERT INTO expense_reasons (reason_name) VALUES (?)");
            $insertReasonStmt->bind_param('s', $new_reason);
            $insertReasonStmt->execute();
            $reason_id = $insertReasonStmt->insert_id;
            $insertReasonStmt->close();
        }
    }

    if ($reason_id <= 0) $errors[] = "Please select or enter a reason.";

    // Handle image upload
    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $upload_dir = __DIR__ . '/../public/assets/uploads/expenses/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['image']['name']));
        $target = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $image_path = 'expenses/' . $filename;
        } else {
            $errors[] = "Failed to upload image.";
        }
    }

    // Insert expense if no errors
    if (!$errors) {
        $stmt = $conn->prepare("INSERT INTO expenses (employee_name, expense_date, reason_id, amount, explanation, image_path)
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssidss', $employee_name, $expense_date, $reason_id, $amountValue, $explanation, $image_path);
        
        if ($stmt->execute()) {
            header("Location: expenses.php?success=1");
            exit;
        } else {
            $errors[] = "Failed to save expense.";
        }
        $stmt->close();
    }

    // On validation failure, flash errors and old input, then redirect to avoid resubmission prompt
    if ($errors) {
        $_SESSION['expense_errors'] = $errors;
        $_SESSION['expense_old'] = [
            'employee_name' => $employee_name,
            'expense_date' => $expense_date,
            'amount' => $amount,
            'explanation' => $explanation,
            'reason_id' => $reason_id,
            'new_reason' => $new_reason,
        ];
        header("Location: expenses.php");
        exit;
    }
}

// Fetch all reasons for dropdown
$reasons = $conn->query("SELECT * FROM expense_reasons ORDER BY reason_name")->fetch_all(MYSQLI_ASSOC);

// Fetch all expenses
$expenses = $conn->query("SELECT e.*, r.reason_name FROM expenses e 
                         LEFT JOIN expense_reasons r ON e.reason_id = r.id 
                         ORDER BY e.expense_date DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracking - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #f9fafb; }
        .admin-sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 16rem; background: #000; color: #fff; z-index: 40; }
        .admin-sidebar .nav-links a { display: block; padding: 0.75rem 0; color: #d1d5db; font-size: 0.875rem; text-decoration: none; }
        .admin-sidebar .nav-links a:hover { color: #fff; text-decoration: none; }
        body.admin-with-sidebar { margin-left: 16rem; }
        @media (max-width: 768px) {
            .admin-sidebar { position: relative; width: 100%; height: auto; }
            body.admin-with-sidebar { margin-left: 0; }
        }
    </style>
</head>
<body>
<?php include '_admin_nav.php'; ?>

<div class="p-6 max-w-6xl mx-auto">
    <h1 class="text-4xl font-bold mb-8 text-gray-800">Expense Tracking</h1>

    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-lg shadow-sm">
            <p class="font-semibold">✓ Expense added successfully!</p>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-lg shadow-sm">
            <p class="font-semibold mb-2">Please fix the following errors:</p>
            <ul class="list-disc pl-6">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-8 mb-8 border border-gray-100">
        <h2 class="text-2xl font-bold mb-6 text-gray-800">Add New Expense</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Employee Name *</label>
                    <select name="employee_name" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="">-- Select an Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= e($emp); ?>" <?= ($employee_name === $emp) ? 'selected' : ''; ?>>
                                <?= e($emp); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date / Time *</label>
                    <input type="datetime-local" name="expense_date" value="<?= e($expense_date); ?>" 
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           required>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Reason *</label>
                    <select name="reason_id" id="reason_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Select a Reason --</option>
                        <?php foreach ($reasons as $r): ?>
                            <option value="<?= $r['id']; ?>" <?= ($reason_id == $r['id']) ? 'selected' : ''; ?>>
                                <?= e($r['reason_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Or Add New Reason</label>
                    <input type="text" name="new_reason" id="new_reason" value="<?= e($new_reason); ?>" 
                           placeholder="Enter new reason (optional)" 
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-2">Leave blank to use selected reason above.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Amount (BDT) *</label>
                    <input type="number" name="amount" value="<?= e($amount); ?>" step="0.01" 
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           required placeholder="0.00">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Image</label>
                    <input type="file" name="image" accept="image/*" 
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-2">Optional: PNG/JPG recommended.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Explanation</label>
                <textarea name="explanation" rows="4" 
                          class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          placeholder="Enter expense details..."><?= e($explanation); ?></textarea>
            </div>

            <button type="submit" class="w-full md:w-auto bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors shadow-md">
                + Add Expense
            </button>
        </form>
    </div>

    <!-- Expense History moved to dashboard/expense_history page -->
</div>

</body>
</html>
