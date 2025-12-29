<?php
if (!defined('DB_HOST')) {
    require_once '../includes/config.php';
}
requireUserType('customer');

$conn = getDBConnection();
$customer_id = getUserId();

// Get customer bookings with feedback info
$bookings = $conn->query("
    SELECT b.*, w.worker_name, w.phone_no AS worker_phone, w.email AS worker_email,
           sc.category_name, p.status AS payment_status,
           f.rating AS feedback_rating, f.comments AS feedback_comments
    FROM booking b
    LEFT JOIN worker w ON b.worker_id = w.worker_id
    LEFT JOIN service_category sc ON b.category_id = sc.category_id
    LEFT JOIN payment p ON b.booking_id = p.booking_id
    LEFT JOIN feedback f ON b.booking_id = f.booking_id
    WHERE b.customer_id = $customer_id
    ORDER BY b.service_date DESC, b.service_time DESC
")->fetch_all(MYSQLI_ASSOC);

// Get available workers
$available_workers = $conn->query("
    SELECT w.*, sc.category_name,
           (SELECT COUNT(*) FROM availability a WHERE a.worker_id = w.worker_id AND a.status = 'available' AND a.available_date >= CURDATE()) as available_slots
    FROM worker w
    LEFT JOIN service_category sc ON w.category_id = sc.category_id
    WHERE w.availability_status = 'available'
    ORDER BY w.rating DESC
")->fetch_all(MYSQLI_ASSOC);

// Get service categories
$categories = $conn->query("SELECT * FROM service_category ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

// Statistics
$stats = [];
$stats['total_bookings'] = count($bookings);
$stats['pending'] = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$stats['in_progress'] = count(array_filter($bookings, fn($b) => $b['status'] === 'in_progress'));
$stats['completed'] = count(array_filter($bookings, fn($b) => $b['status'] === 'completed'));
$stats['total_spent'] = array_sum(array_map(fn($b) => $b['total_amount'], $bookings));

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/components.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo SITE_NAME; ?></h2>
                <p class="user-role">Customer Panel</p>
            </div>
            <nav class="sidebar-nav">
                <a href="#overview" class="nav-item active">Overview</a>
                <a href="#bookings" class="nav-item">My Bookings</a>
                <a href="#book-service" class="nav-item">Book Service</a>
                <a href="#workers" class="nav-item">Available Workers</a>
                <a href="../root/logout.php" class="nav-item">Logout</a>
            </nav>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-header">
                <h1>Welcome, <?php echo getUserName(); ?></h1>
                <div class="header-actions">
                    <span class="user-email"><?php echo $_SESSION['user_email']; ?></span>
                    <a href="../customer/create_booking.php" class="btn btn-primary">Book a Service</a>
                </div>
            </header>

            <?php if ($msg = getSuccess()): ?>
                <div class="alert alert-success"><?php echo $msg; ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_bookings']; ?></h3>
                        <p>Total Requests/Bookings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔧</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['in_progress']; ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['completed']; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($stats['total_spent']); ?></h3>
                        <p>Total Spent</p>
                    </div>
                </div>
            </div>

            <!-- Service Categories -->
            <section class="dashboard-section" id="book-service">
                <h2>Service Categories</h2>
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card">
                            <h3><?php echo htmlspecialchars($category['category_name']); ?></h3>
                            <p><?php echo htmlspecialchars($category['description']); ?></p>
                            <a href="../customer/create_booking.php?category=<?php echo $category['category_id']; ?>" class="btn btn-sm btn-primary">Request Service</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- My Bookings -->
            <section class="dashboard-section" id="bookings">
                <h2>My Bookings</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Worker</th>
                                <th>Service</th>
                                <th>Date & Time</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                        <th>Feedback</th>
                        <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo $booking['booking_id']; ?></td>
                                    <td>
                                        <?php if ($booking['worker_id']): ?>
                                            <?php echo htmlspecialchars($booking['worker_name']); ?><br>
                                            <small><?php echo htmlspecialchars($booking['worker_phone']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['category_name'] ?? 'N/A'); ?></strong><br>
                                        <small><?php echo htmlspecialchars($booking['service_description'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <?php echo formatDate($booking['service_date']); ?><br>
                                        <small><?php echo date('h:i A', strtotime($booking['service_time'])); ?></small>
                                    </td>
                                    <td><?php echo formatCurrency($booking['total_amount']); ?></td>
                                    <td><span class="badge badge-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                    <td><span class="badge badge-<?php echo $booking['payment_status'] ?? 'pending'; ?>"><?php echo ucfirst($booking['payment_status'] ?? 'Pending'); ?></span></td>
                                    <td>
                                        <?php if ($booking['feedback_rating']): ?>
                                            <strong><?php echo number_format($booking['feedback_rating'], 1); ?> ⭐</strong><br>
                                            <small><?php echo nl2br(htmlspecialchars($booking['feedback_comments'] ?? '')); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">No feedback yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($booking['status'] === 'pending' && !$booking['worker_id']): ?>
                                            <span class="text-muted">Waiting for admin assignment</span>
                                        <?php elseif ($booking['status'] === 'completed' && !$booking['feedback_rating']): ?>
                                            <a href="../customer/give_feedback.php?id=<?php echo $booking['booking_id']; ?>" class="btn btn-sm btn-primary">Give Feedback</a>
                                        <?php elseif ($booking['payment_status'] !== 'completed' && $booking['worker_id'] && $booking['total_amount'] > 0): ?>
                                            <a href="../customer/make_payment.php?id=<?php echo $booking['booking_id']; ?>" class="btn btn-sm btn-success">Pay Now</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Available Workers -->
            <section class="dashboard-section" id="workers">
                <h2>Available Workers</h2>
                <div class="workers-grid">
                    <?php foreach ($available_workers as $worker): ?>
                        <div class="worker-card">
                            <h3><?php echo htmlspecialchars($worker['worker_name']); ?></h3>
                            <p class="worker-category"><?php echo htmlspecialchars($worker['category_name'] ?? 'N/A'); ?></p>
                            <p class="worker-skill"><?php echo htmlspecialchars($worker['skill_type']); ?></p>
                            <div class="worker-stats">
                                <span>⭐ <?php echo number_format($worker['rating'], 2); ?></span>
                                <span>📅 <?php echo $worker['available_slots']; ?> slots</span>
                                <span>🎓 <?php echo $worker['experience_years']; ?>y exp</span>
                            </div>
                            <a href="../customer/create_booking.php?worker=<?php echo $worker['worker_id']; ?>" class="btn btn-sm btn-primary btn-block">Book Now</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
