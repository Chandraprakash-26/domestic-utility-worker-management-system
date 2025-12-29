<?php
require_once '../includes/config.php';
requireUserType('admin');

$conn = getDBConnection();

$booking_id = (int)($_GET['id'] ?? 0);

// Get booking request (must be pending and not yet assigned)
$booking_stmt = $conn->prepare("
    SELECT b.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
           sc.category_name
    FROM booking b
    JOIN customer c ON b.customer_id = c.customer_id
    LEFT JOIN service_category sc ON b.category_id = sc.category_id
    WHERE b.booking_id = ? AND b.status = 'pending' AND b.worker_id IS NULL
");
$booking_stmt->bind_param('i', $booking_id);
$booking_stmt->execute();
$booking = $booking_stmt->get_result()->fetch_assoc();
$booking_stmt->close();

if (!$booking) {
    setError('Booking request not found or already assigned.');
    header('Location: ../root/dashboard.php');
    exit();
}

$category_id = (int)$booking['category_id'];
$service_date = $booking['service_date'];
$service_time = $booking['service_time'];

// Get workers for this category with availability status
// We'll fetch all workers and sort in PHP to avoid collation issues
$workers_stmt = $conn->prepare("
    SELECT w.*, sc.category_name,
           get_worker_availability_status(w.worker_id, ?, ?) as availability_status
    FROM worker w
    LEFT JOIN service_category sc ON w.category_id = sc.category_id
    WHERE (? = 0 OR w.category_id = ?)
");
$workers_stmt->bind_param('ssii', $service_date, $service_time, $category_id, $category_id);
$workers_stmt->execute();
$workers = $workers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$workers_stmt->close();

// Sort workers: available first, then by rating
usort($workers, function($a, $b) {
    // Define priority for availability status
    $priority = ['available' => 1, 'unavailable' => 2, 'booked' => 3];
    $a_priority = $priority[$a['availability_status']] ?? 4;
    $b_priority = $priority[$b['availability_status']] ?? 4;

    // First sort by availability status
    if ($a_priority != $b_priority) {
        return $a_priority - $b_priority;
    }

    // Then sort by rating (descending)
    return $b['rating'] <=> $a['rating'];
});

// Get availability for requested date/time
$availability_stmt = $conn->prepare("
    SELECT a.*, w.worker_name
    FROM availability a
    JOIN worker w ON a.worker_id = w.worker_id
    WHERE a.available_date = ? AND a.available_time = ? AND a.status = 'available'
      AND (? = 0 OR w.category_id = ?)
");
$availability_stmt->bind_param('ssii', $service_date, $service_time, $category_id, $category_id);
$availability_stmt->execute();
$availability_rows = $availability_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$availability_stmt->close();

// Map worker_id => slot_id for quick lookup
$available_slots = [];
foreach ($availability_rows as $row) {
    $available_slots[$row['worker_id']] = $row['slot_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $worker_id = (int)($_POST['worker_id'] ?? 0);
    $total_amount = (float)($_POST['total_amount'] ?? 0);

    if ($worker_id && $total_amount > 0) {
        $slot_id = $available_slots[$worker_id] ?? null;

        // Assign worker, set amount and update status
        if ($slot_id) {
            $update_stmt = $conn->prepare("
                UPDATE booking
                SET worker_id = ?, category_id = ?, slot_id = ?, total_amount = ?, status = 'confirmed'
                WHERE booking_id = ?
            ");
            $update_stmt->bind_param('iiidi', $worker_id, $category_id, $slot_id, $total_amount, $booking_id);
        } else {
            $update_stmt = $conn->prepare("
                UPDATE booking
                SET worker_id = ?, category_id = ?, total_amount = ?, status = 'confirmed'
                WHERE booking_id = ?
            ");
            $update_stmt->bind_param('iidi', $worker_id, $category_id, $total_amount, $booking_id);
        }

        if ($update_stmt->execute()) {
            $update_stmt->close();

            // Mark slot as booked if we used one
            if ($slot_id) {
                $slot_update = $conn->prepare("UPDATE availability SET status = 'booked' WHERE slot_id = ?");
                $slot_update->bind_param('i', $slot_id);
                $slot_update->execute();
                $slot_update->close();
            }

            setSuccess('Worker assigned and amount set successfully.');
            header('Location: ../root/dashboard.php');
            exit();
        } else {
            setError('Failed to assign worker. Please try again.');
            $update_stmt->close();
        }
    } else {
        setError('Please select a worker and enter a valid amount.');
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Booking - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/components.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>Assign Booking Request</h1>
            <a href="../root/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <?php if ($msg = getSuccess()): ?>
            <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if ($error = getError()): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="booking-summary">
            <h2>Request Details</h2>
            <p><strong>Request ID:</strong> #<?php echo $booking['booking_id']; ?></p>
            <p><strong>Customer:</strong> <?php echo htmlspecialchars($booking['customer_name']); ?></p>
            <p><strong>Contact:</strong> <?php echo htmlspecialchars($booking['customer_phone']); ?> (<?php echo htmlspecialchars($booking['customer_email']); ?>)</p>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($booking['category_name'] ?? 'N/A'); ?></p>
            <p><strong>Service Date & Time:</strong>
                <?php echo formatDate($booking['service_date']); ?> at
                <?php echo date('h:i A', strtotime($booking['service_time'])); ?>
            </p>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($booking['service_description'] ?? 'N/A'); ?></p>
        </div>

        <div class="form-container">
            <h2>Assign Worker & Set Amount</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="worker_id">Select Worker *</label>
                    <select name="worker_id" id="worker_id" required>
                        <option value="">Choose worker</option>
                        <?php foreach ($workers as $worker):
                            $status = $worker['availability_status'];
                            $status_icon = $status === 'available' ? '🟢' : ($status === 'booked' ? '🔵' : '🔴');
                            $status_text = ucfirst($status);
                        ?>
                            <option value="<?php echo $worker['worker_id']; ?>"
                                    style="<?php echo $status !== 'available' ? 'color: #999;' : ''; ?>">
                                <?php echo $status_icon; ?>
                                <?php echo htmlspecialchars($worker['worker_name']); ?>
                                (<?php echo htmlspecialchars($worker['category_name'] ?? 'N/A'); ?>,
                                ⭐ <?php echo number_format($worker['rating'], 2); ?>,
                                <?php echo $status_text; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display: block; margin-top: 0.5rem;">
                        🟢 Available | 🔴 Unavailable | 🔵 Already Booked
                    </small>
                </div>

                <div class="form-group">
                    <label for="total_amount">Amount to Charge (₹) *</label>
                    <input type="number" id="total_amount" name="total_amount" required
                           min="0" step="0.01" placeholder="Enter service amount">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Assign Worker</button>
            </form>
        </div>

        <div class="table-container" style="margin-top: 2rem;">
            <h2>Available Slots for Requested Time</h2>
            <?php if ($availability_rows): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Worker</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availability_rows as $slot): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($slot['worker_name']); ?></td>
                                <td><?php echo formatDate($slot['available_date']); ?></td>
                                <td><?php echo date('h:i A', strtotime($slot['available_time'])); ?></td>
                                <td><span class="badge badge-<?php echo $slot['status']; ?>"><?php echo ucfirst($slot['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No workers have marked availability for this exact date and time.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


