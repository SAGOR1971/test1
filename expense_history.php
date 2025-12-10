<?php
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$errors = [];

// Simple gate for viewing expense history
$history_logged_in = isset($_SESSION['expense_history_login']) && $_SESSION['expense_history_login'] === true;

if (isset($_GET['logout_history'])) {
    unset($_SESSION['expense_history_login']);
    $history_logged_in = false;
}

if (isset($_POST['history_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username === 'sagor' && $password === 'dangersagor') {
        $_SESSION['expense_history_login'] = true;
        $history_logged_in = true;
    } else {
        $errors[] = "Invalid username or password for history access.";
    }
}

// Fetch expenses only if logged in
$expenses = [];
if ($history_logged_in) {
    $q = $conn->query("SELECT e.*, r.reason_name FROM expenses e LEFT JOIN expense_reasons r ON e.reason_id = r.id ORDER BY e.expense_date DESC");
    if ($q) {
        $expenses = $q->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense History</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include '_admin_nav.php'; ?>

<main class="p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Expense History</h1>
            <p class="text-sm text-gray-500">Secure view of all expense records</p>
        </div>
        <?php if ($history_logged_in): ?>
            <a href="?logout_history=1" class="text-sm bg-red-100 text-red-600 px-4 py-2 rounded-lg hover:bg-red-200 transition-colors font-semibold">Logout</a>
        <?php endif; ?>
    </div>

    <?php if ($errors): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-lg shadow-sm">
            <ul class="list-disc pl-6">
                <?php foreach ($errors as $e): ?>
                    <li><?= e($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$history_logged_in): ?>
        <div class="max-w-md mx-auto bg-white rounded-lg shadow p-6 border border-gray-200">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Login to view history</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                    <input type="text" name="username" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                </div>
                <button type="submit" name="history_login" value="1" class="w-full bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">Login</button>
            </form>
        </div>
    <?php else: ?>
        <?php if (empty($expenses)): ?>
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200 text-center text-gray-500">
                No expenses recorded yet.
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-100 border-b-2 border-gray-300">
                                <th class="text-left p-3 font-semibold text-gray-700">Date</th>
                                <th class="text-left p-3 font-semibold text-gray-700">Employee</th>
                                <th class="text-left p-3 font-semibold text-gray-700">Reason</th>
                                <th class="text-right p-3 font-semibold text-gray-700">Amount</th>
                                <th class="text-left p-3 font-semibold text-gray-700">Explanation</th>
                                <th class="text-center p-3 font-semibold text-gray-700">Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $exp): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <td class="p-3 text-gray-700"><?= e(date('M d, Y H:i', strtotime($exp['expense_date']))); ?></td>
                                    <td class="p-3 text-gray-700 font-medium"><?= e($exp['employee_name']); ?></td>
                                    <td class="p-3 text-gray-700">
                                        <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-semibold">
                                            <?= e($exp['reason_name'] ?? '-'); ?>
                                        </span>
                                    </td>
                                    <td class="text-right p-3 font-semibold text-gray-900"><?= number_format($exp['amount'], 2); ?> BDT</td>
                                    <td class="p-3 text-gray-600 text-sm"><?= e(substr($exp['explanation'] ?? '', 0, 60)); ?><?= strlen($exp['explanation'] ?? '') > 60 ? '...' : ''; ?></td>
                                    <td class="text-center p-3">
                                        <?php if ($exp['image_path']): ?>
                                            <a href="../public/assets/uploads/<?= e($exp['image_path']); ?>" target="_blank" class="inline-block bg-blue-100 text-blue-600 px-3 py-1 rounded text-xs font-semibold hover:bg-blue-200 transition-colors">View</a>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 text-right bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <p class="text-gray-700">
                        <span class="text-sm font-medium">Total Expenses:</span>
                        <span class="text-2xl font-bold text-green-600"><?= number_format(array_sum(array_column($expenses, 'amount')), 2); ?> BDT</span>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

</body>
</html>