<?php
if (!defined('DB_HOST')) {
    require_once '../includes/config.php';
}
requireUserType('worker');

$conn = getDBConnection();
$worker_id = getUserId();

// Get worker information
$worker_info = $conn->query("
    SELECT w.*, sc.category_name
    FROM worker w
    LEFT JOIN service_category sc ON w.category_id = sc.category_id
    WHERE w.worker_id = $worker_id
")->fetch_assoc();

// Get bookings for this worker with feedback info
$bookings = $conn->query("
    SELECT b.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
           CONCAT(c.street, ', ', c.city, ', ', c.pincode) AS customer_address,
           p.status AS payment_status,
           f.rating AS feedback_rating, f.comments AS feedback_comments
    FROM booking b
    JOIN customer c ON b.customer_id = c.customer_id
    LEFT JOIN payment p ON b.booking_id = p.booking_id
    LEFT JOIN feedback f ON b.booking_id = f.booking_id
    WHERE b.worker_id = $worker_id
    ORDER BY b.service_date DESC, b.service_time DESC
")->fetch_all(MYSQLI_ASSOC);

// Get availability slots
$availability_slots = $conn->query("
    SELECT * FROM availability
    WHERE worker_id = $worker_id AND available_date >= CURDATE()
    ORDER BY available_date, available_time
")->fetch_all(MYSQLI_ASSOC);

// Statistics
$stats = [];
$stats['total_bookings'] = count($bookings);
$stats['pending'] = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$stats['in_progress'] = count(array_filter($bookings, fn($b) => $b['status'] === 'in_progress'));
$stats['completed'] = count(array_filter($bookings, fn($b) => $b['status'] === 'completed'));
$stats['total_earned'] = array_sum(array_map(fn($b) => $b['status'] === 'completed' ? $b['total_amount'] : 0, $bookings));

// Get default availability count
$default_count = $conn->query("SELECT COUNT(*) as count FROM worker_default_availability WHERE worker_id = $worker_id")->fetch_assoc()['count'];

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Dashboard - <?php echo SITE_NAME; ?></title>
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
                <p class="user-role">Worker Panel</p>
            </div>
            <nav class="sidebar-nav">
                <a href="#overview" class="nav-item active">Overview</a>
                <a href="#bookings" class="nav-item">My Bookings</a>
                <a href="#availability" class="nav-item">Availability</a>
                <a href="#profile" class="nav-item">Profile</a>
                <a href="../root/logout.php" class="nav-item">Logout</a>
            </nav>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-header">
                <h1>Welcome, <?php echo getUserName(); ?></h1>
                <div class="header-actions">
                    <span class="user-email"><?php echo $_SESSION['user_email']; ?></span>
                    <span class="badge badge-<?php echo $worker_info['availability_status']; ?>">
                        <?php echo ucfirst($worker_info['availability_status']); ?>
                    </span>
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
                        <p>Total Bookings</p>
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
                    <div class="stat-icon">⭐</div>
                    <div class="stat-info">
                        <h3><?php echo number_format($worker_info['rating'], 2); ?></h3>
                        <p>Rating</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($stats['total_earned']); ?></h3>
                        <p>Total Earned</p>
                    </div>
                </div>
            </div>

            <!-- Profile Section -->
            <section class="dashboard-section" id="profile">
                <h2>My Profile</h2>
                <div class="profile-card">
                    <div class="profile-info">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($worker_info['worker_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($worker_info['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($worker_info['phone_no']); ?></p>
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($worker_info['category_name'] ?? 'N/A'); ?></p>
                        <p><strong>Skill:</strong> <?php echo htmlspecialchars($worker_info['skill_type']); ?></p>
                        <p><strong>Experience:</strong> <?php echo $worker_info['experience_years']; ?> years</p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($worker_info['street'] . ', ' . $worker_info['city'] . ', ' . $worker_info['pincode']); ?></p>
                    </div>
                </div>
            </section>

            <!-- Bookings Section -->
            <section class="dashboard-section" id="bookings">
                <h2>My Bookings</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Contact</th>
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
                                    <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($booking['customer_phone']); ?><br>
                                        <small><?php echo htmlspecialchars($booking['customer_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['service_description'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php echo formatDate($booking['service_date']); ?><br>
                                        <small><?php echo date('h:i A', strtotime($booking['service_time'])); ?></small>
                                    </td>
                                    <td><?php echo formatCurrency($booking['total_amount']); ?></td>
                                    <td><span class="badge badge-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                    <td><span class="badge badge-<?php echo $booking['payment_status'] ?? 'pending'; ?>"><?php echo ucfirst($booking['payment_status'] ?? 'N/A'); ?></span></td>
                                    <td>
                                        <?php if ($booking['feedback_rating']): ?>
                                            <strong><?php echo number_format($booking['feedback_rating'], 1); ?> ⭐</strong><br>
                                            <small><?php echo nl2br(htmlspecialchars($booking['feedback_comments'] ?? '')); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">No feedback yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../worker/update_booking_status.php?id=<?php echo $booking['booking_id']; ?>" class="btn btn-sm btn-primary">Update</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Availability Section -->
            <section class="dashboard-section" id="availability">
                <h2>My Availability</h2>
                <div style="margin-bottom: 1rem;">
                    <a href="../worker/setup_availability.php" class="btn btn-primary">Setup Default Hours</a>
                    <a href="../worker/manage_availability.php" class="btn btn-secondary">Manage Availability</a>
                </div>

                <?php if ($default_count == 0): ?>
                    <div class="alert alert-warning">
                        <strong>Action Required:</strong> You haven't set up your default working hours yet.
                        <a href="../worker/setup_availability.php">Click here to set your availability schedule</a>.
                    </div>
                <?php endif; ?>

                <div class="availability-grid">
                    <?php foreach ($availability_slots as $slot): ?>
                        <div class="availability-card">
                            <h4><?php echo formatDate($slot['available_date']); ?></h4>
                            <p><?php echo date('h:i A', strtotime($slot['available_time'])); ?></p>
                            <span class="badge badge-<?php echo $slot['status']; ?>"><?php echo ucfirst($slot['status']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
