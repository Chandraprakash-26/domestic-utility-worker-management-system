<?php
if (!defined('DB_HOST')) {
    require_once '../includes/config.php';
}
requireUserType('admin');

$conn = getDBConnection();

// Get statistics
$stats = [];

// Total counts
$stats['total_customers'] = $conn->query("SELECT COUNT(*) as count FROM customer")->fetch_assoc()['count'];
$stats['total_workers'] = $conn->query("SELECT COUNT(*) as count FROM worker")->fetch_assoc()['count'];
$stats['total_bookings'] = $conn->query("SELECT COUNT(*) as count FROM booking")->fetch_assoc()['count'];
$stats['total_categories'] = $conn->query("SELECT COUNT(*) as count FROM service_category")->fetch_assoc()['count'];

// Pending booking requests (no worker yet)
$stats['pending_bookings'] = $conn->query("SELECT COUNT(*) as count FROM booking WHERE status = 'pending' AND worker_id IS NULL")->fetch_assoc()['count'];

// Total revenue
$stats['total_revenue'] = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM booking WHERE status = 'completed'")->fetch_assoc()['total'];

// Recent bookings (assigned)
$recent_bookings = $conn->query("SELECT * FROM booking_details_view ORDER BY booking_id DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Pending booking requests (to be assigned)
$pending_requests = $conn->query("
    SELECT b.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
           sc.category_name
    FROM booking b
    JOIN customer c ON b.customer_id = c.customer_id
    LEFT JOIN service_category sc ON b.category_id = sc.category_id
    WHERE b.status = 'pending' AND b.worker_id IS NULL
    ORDER BY b.booking_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Worker performance
$top_workers = $conn->query("SELECT * FROM worker_performance_view ORDER BY total_bookings DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Recent feedback from customers
$recent_feedback = $conn->query("
    SELECT f.*, b.service_date, b.service_time,
           c.name AS customer_name,
           w.worker_name
    FROM feedback f
    JOIN booking b ON f.booking_id = b.booking_id
    JOIN customer c ON b.customer_id = c.customer_id
    JOIN worker w ON b.worker_id = w.worker_id
    ORDER BY f.feedback_date DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Get all categories
$categories = $conn->query("SELECT * FROM service_category ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

// Get all workers with category info
$workers = $conn->query("
    SELECT w.*, sc.category_name
    FROM worker w
    LEFT JOIN service_category sc ON w.category_id = sc.category_id
    ORDER BY w.worker_id DESC
")->fetch_all(MYSQLI_ASSOC);

// Pre-calculate worker availability for all pending requests
$worker_availability_map = [];
foreach ($pending_requests as $req) {
    $service_date = $req['service_date'];
    $service_time = $req['service_time'];
    $category_id = $req['category_id'] ?? 0;
    $booking_id = $req['booking_id'];

    $available_workers_query = "
        SELECT w.worker_id, w.worker_name,
               get_worker_availability_status(w.worker_id, '$service_date', '$service_time') as availability_status
        FROM worker w
        WHERE (w.category_id = $category_id OR $category_id = 0 OR $category_id IS NULL)
        ORDER BY w.worker_name
    ";
    $worker_availability_map[$booking_id] = $conn->query($available_workers_query)->fetch_all(MYSQLI_ASSOC);
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/components.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo SITE_NAME; ?></h2>
                <p class="user-role">Admin Panel</p>
            </div>
            <nav class="sidebar-nav">
                <a href="#overview" class="nav-item active">Overview</a>
                <a href="#requests" class="nav-item">Booking Requests</a>
                <a href="#bookings" class="nav-item">Bookings</a>
                <a href="#workers" class="nav-item">Workers</a>
                <a href="#categories" class="nav-item">Categories</a>
                <a href="#customers" class="nav-item">Customers</a>
                <a href="../root/logout.php" class="nav-item">Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="dashboard-main">
            <header class="dashboard-header">
                <h1>Welcome, <?php echo getUserName(); ?></h1>
                <div class="header-actions">
                    <span class="user-email"><?php echo $_SESSION['user_email']; ?></span>
                </div>
            </header>

            <?php if ($msg = getSuccess()): ?>
                <div class="alert alert-success"><?php echo $msg; ?></div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_customers']; ?></h3>
                        <p>Total Customers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👷</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_workers']; ?></h3>
                        <p>Total Workers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_bookings']; ?></h3>
                        <p>Total Bookings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_bookings']; ?></h3>
                        <p>Pending Bookings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏷️</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_categories']; ?></h3>
                        <p>Service Categories</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Pending Booking Requests -->
            <section class="dashboard-section" id="requests">
                <h2>Pending Booking Requests</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Category</th>
                                <th>Date & Time</th>
                                <th>Available Workers</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $req):
                                // Get pre-calculated worker availability
                                $booking_id = $req['booking_id'];
                                $available_workers = $worker_availability_map[$booking_id] ?? [];
                                $available_count = count(array_filter($available_workers, fn($w) => $w['availability_status'] === 'available'));
                            ?>
                                <tr>
                                    <td>#<?php echo $req['booking_id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($req['customer_name']); ?><br>
                                        <small><?php echo htmlspecialchars($req['customer_phone']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($req['category_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php echo formatDate($req['service_date']); ?><br>
                                        <small><?php echo date('h:i A', strtotime($req['service_time'])); ?></small>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <?php foreach ($available_workers as $w):
                                                $dot_color = $w['availability_status'] === 'available' ? 'green' : 'red';
                                                $title = htmlspecialchars($w['worker_name']) . ' - ' . ucfirst($w['availability_status']);
                                            ?>
                                                <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?php echo $dot_color; ?>; cursor: help;" title="<?php echo $title; ?>"></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <small><?php echo $available_count; ?> available</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($req['service_description'] ?? ''); ?></td>
                                    <td>
                                        <a href="../admin/assign_booking.php?id=<?php echo $req['booking_id']; ?>" class="btn btn-sm btn-primary">
                                            Assign Worker
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Recent Bookings -->
            <section class="dashboard-section" id="bookings">
                <h2>Recent Bookings</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Worker</th>
                                <th>Service</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo $booking['booking_id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['worker_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['category_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo formatDate($booking['service_date']); ?></td>
                                    <td><?php echo formatCurrency($booking['total_amount']); ?></td>
                                    <td><span class="badge badge-<?php echo $booking['booking_status']; ?>"><?php echo ucfirst($booking['booking_status']); ?></span></td>
                                    <td><span class="badge badge-<?php echo $booking['payment_status'] ?? 'pending'; ?>"><?php echo ucfirst($booking['payment_status'] ?? 'N/A'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Top Performing Workers -->
            <section class="dashboard-section">
                <h2>Top Performing Workers</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Category</th>
                                <th>Rating</th>
                                <th>Total Jobs</th>
                                <th>Completed</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_workers as $worker): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($worker['worker_name']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['category_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($worker['worker_rating'], 2); ?> ⭐</td>
                                    <td><?php echo $worker['total_bookings']; ?></td>
                                    <td><?php echo $worker['completed_bookings']; ?></td>
                                    <td><?php echo formatCurrency($worker['total_revenue'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Recent Customer Feedback -->
            <section class="dashboard-section">
                <h2>Recent Customer Feedback</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Customer</th>
                                <th>Worker</th>
                                <th>Rating</th>
                                <th>Comments</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_feedback as $fb): ?>
                                <tr>
                                    <td>#<?php echo $fb['booking_id']; ?></td>
                                    <td><?php echo htmlspecialchars($fb['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($fb['worker_name']); ?></td>
                                    <td><?php echo number_format($fb['rating'], 1); ?> ⭐</td>
                                    <td><?php echo nl2br(htmlspecialchars($fb['comments'] ?? '')); ?></td>
                                    <td><?php echo formatDate($fb['feedback_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Workers Management -->
            <section class="dashboard-section" id="workers">
                <h2>Workers Management</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Category</th>
                                <th>Skill</th>
                                <th>Experience</th>
                                <th>Rating</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workers as $worker): ?>
                                <tr>
                                    <td>#<?php echo $worker['worker_id']; ?></td>
                                    <td><?php echo htmlspecialchars($worker['worker_name']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['email']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['category_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($worker['skill_type']); ?></td>
                                    <td><?php echo $worker['experience_years']; ?> years</td>
                                    <td><?php echo number_format($worker['rating'], 2); ?> ⭐</td>
                                    <td><span class="badge badge-<?php echo $worker['availability_status']; ?>"><?php echo ucfirst($worker['availability_status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Service Categories -->
            <section class="dashboard-section" id="categories">
                <h2>Service Categories</h2>
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card">
                            <h3><?php echo htmlspecialchars($category['category_name']); ?></h3>
                            <p><?php echo htmlspecialchars($category['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
