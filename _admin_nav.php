<?php
$adminUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
?>
<style>
    /* Sidebar layout for admin pages */
    .admin-sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 16rem; background: #000; color: #fff; z-index: 40; }
    .admin-sidebar .nav-links a { display: block; padding: 0.5rem 0; color: #e5e7eb; text-decoration: none; }
    .admin-sidebar .nav-links a:hover { color: #fff; text-decoration: none; }
    /* push page content to the right when sidebar present */
    body.admin-with-sidebar { margin-left: 16rem; }
    @media (max-width: 768px) {
        .admin-sidebar { position: relative; width: 100%; height: auto; }
        body.admin-with-sidebar { margin-left: 0; }
    }
</style>

<aside class="admin-sidebar px-6 py-6">
    <div class="mb-6">
        <a href="dashboard.php" class="text-lg font-bold block mb-4">Admin - T-SCOTIK</a>
        <div class="text-sm text-gray-300">
            <?php if ($adminUser): ?>
                <div class="mb-2">Hi, <?= htmlspecialchars($adminUser['name']); ?></div>
                <a href="../public/logout.php" class="text-xs text-gray-400 hover:text-white">Logout</a>
            <?php else: ?>
                <a href="login.php" class="text-xs text-gray-400 hover:text-white">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <nav class="nav-links text-sm">
        <a href="dashboard.php" class="hover:text-gray-100">Dashboard</a>
        <a href="orders.php" class="hover:text-gray-100">Orders</a>
        <a href="products.php" class="hover:text-gray-100">Products</a>
        <a href="templates.php" class="hover:text-gray-100">Templates</a>
        <a href="company_artworks.php" class="hover:text-gray-100">Company Artworks</a>
        <a href="footer_pages.php" class="hover:text-gray-100">Pages</a>
        <a href="social_links.php" class="hover:text-gray-100">Social Links</a>
        <a href="artwork_options.php" class="hover:text-gray-100">Artwork Prices</a>
        <a href="users.php" class="hover:text-gray-100">Users</a>
        <a href="expenses.php" class="hover:text-gray-100">Expenses</a>
        <a href="expense_history.php" class="hover:text-gray-100">Expense History</a>
    </nav>
</aside>

<script>
    // Mark the body so content is pushed to the right when sidebar exists
    document.body.classList.add('admin-with-sidebar');
</script>
