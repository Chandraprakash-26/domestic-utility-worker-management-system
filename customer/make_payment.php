<?php
require_once '../includes/config.php';
requireUserType('customer');

$conn = getDBConnection();
$customer_id = getUserId();

$booking_id = (int)($_GET['id'] ?? 0);

// Get booking details
$booking = $conn->query("
    SELECT b.*, w.worker_name, c.name as customer_name
    FROM booking b
    JOIN worker w ON b.worker_id = w.worker_id
    JOIN customer c ON b.customer_id = c.customer_id
    WHERE b.booking_id = $booking_id AND b.customer_id = $customer_id
")->fetch_assoc();

if (!$booking) {
    setError('Booking not found');
    header('Location: ../root/dashboard.php');
    exit();
}

// Check if payment already exists
$existing_payment = $conn->query("SELECT * FROM payment WHERE booking_id = $booking_id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = sanitize($_POST['payment_method']);
    $amount = (float)$_POST['amount'];
    $transaction_id = sanitize($_POST['transaction_id'] ?? '');

    if ($payment_method && $amount > 0) {
        if ($existing_payment) {
            // Update existing payment
            $stmt = $conn->prepare("UPDATE payment SET payment_method = ?, amount = ?, status = 'completed', transaction_id = ? WHERE booking_id = ?");
            $stmt->bind_param('sdsi', $payment_method, $amount, $transaction_id, $booking_id);
        } else {
            // Insert new payment
            $stmt = $conn->prepare("INSERT INTO payment (booking_id, payment_method, amount, status, transaction_id) VALUES (?, ?, ?, 'completed', ?)");
            $stmt->bind_param('isds', $booking_id, $payment_method, $amount, $transaction_id);
        }

        if ($stmt->execute()) {
            setSuccess('Payment processed successfully!');
            header('Location: ../root/dashboard.php');
            exit();
        } else {
            setError('Payment failed. Please try again.');
        }
        $stmt->close();
    } else {
        setError('Please fill all required fields.');
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/components.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>Make Payment</h1>
            <a href="../root/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <?php if ($error = getError()): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="booking-summary">
            <h2>Booking Details</h2>
            <p><strong>Booking ID:</strong> #<?php echo $booking['booking_id']; ?></p>
            <p><strong>Worker:</strong> <?php echo htmlspecialchars($booking['worker_name']); ?></p>
            <p><strong>Service Date:</strong> <?php echo formatDate($booking['service_date']); ?></p>
            <p><strong>Total Amount:</strong> <?php echo formatCurrency($booking['total_amount']); ?></p>
        </div>

        <div class="form-container">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="amount">Payment Amount *</label>
                    <input type="number" id="amount" name="amount" required
                           value="<?php echo $booking['total_amount']; ?>"
                           min="0" step="0.01" readonly>
                </div>

                <div class="form-group">
                    <label for="payment_method">Payment Method *</label>
                    <select name="payment_method" id="payment_method" required>
                        <option value="">Select Payment Method</option>
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="upi">UPI</option>
                        <option value="net_banking">Net Banking</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="transaction_id">Transaction ID (Optional)</label>
                    <input type="text" id="transaction_id" name="transaction_id"
                           placeholder="Enter transaction ID if applicable">
                </div>

                <button type="submit" class="btn btn-success btn-block">Process Payment</button>
            </form>
        </div>
    </div>
</body>
</html>
