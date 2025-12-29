<?php
require_once '../includes/config.php';
requireUserType('worker');

$conn = getDBConnection();
$worker_id = getUserId();

$booking_id = (int)($_GET['id'] ?? 0);

// Get booking details
$booking = $conn->query("
    SELECT b.*, c.name as customer_name, c.phone as customer_phone
    FROM booking b
    JOIN customer c ON b.customer_id = c.customer_id
    WHERE b.booking_id = $booking_id AND b.worker_id = $worker_id
")->fetch_assoc();

if (!$booking) {
    setError('Booking not found');
    header('Location: ../root/dashboard.php');
    exit();
}

// If booking is already completed or cancelled, do not allow further changes
$is_closed = in_array($booking['status'], ['completed', 'cancelled'], true);

if ($is_closed && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    setError('This booking is already closed and cannot be updated.');
    header('Location: ../root/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = sanitize($_POST['status']);

    // Prevent changes if booking is already completed/cancelled
    if ($is_closed) {
        setError('This booking is already closed and cannot be updated.');
        header('Location: ../root/dashboard.php');
        exit();
    }

    if (in_array($new_status, ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], true)) {
        $stmt = $conn->prepare("UPDATE booking SET status = ? WHERE booking_id = ?");
        $stmt->bind_param('si', $new_status, $booking_id);

        if ($stmt->execute()) {
            setSuccess('Booking status updated successfully');
            header('Location: ../root/dashboard.php');
            exit();
        } else {
            setError('Failed to update status');
        }
        $stmt->close();
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Booking Status - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/components.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>Update Booking Status</h1>
            <a href="../root/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <?php if ($error = getError()): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($msg = getSuccess()): ?>
            <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>

        <div class="booking-summary">
            <h2>Booking Details</h2>
            <p><strong>Booking ID:</strong> #<?php echo $booking['booking_id']; ?></p>
            <p><strong>Customer:</strong> <?php echo htmlspecialchars($booking['customer_name']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['customer_phone']); ?></p>
            <p><strong>Service Date:</strong> <?php echo formatDate($booking['service_date']); ?> at <?php echo date('h:i A', strtotime($booking['service_time'])); ?></p>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($booking['service_description'] ?? 'N/A'); ?></p>
            <p><strong>Amount:</strong> <?php echo formatCurrency($booking['total_amount']); ?></p>
            <p><strong>Current Status:</strong> <span class="badge badge-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></p>
        </div>

        <div class="form-container">
            <?php if ($is_closed): ?>
                <div class="alert alert-success">
                    This booking is <strong><?php echo ucfirst($booking['status']); ?></strong> and cannot be changed further.
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="status">Update Status *</label>
                        <select name="status" id="status" required>
                            <option value="pending" <?php echo $booking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $booking['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="in_progress" <?php echo $booking['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $booking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $booking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Update Status</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
