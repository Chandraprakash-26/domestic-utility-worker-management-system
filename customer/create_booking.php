<?php
require_once '../includes/config.php';
requireUserType('customer');

$conn = getDBConnection();
$customer_id = getUserId();

// Get service categories for the request
$categories = $conn->query("SELECT category_id, category_name FROM service_category ORDER BY category_name")
                  ->fetch_all(MYSQLI_ASSOC);

// Preselect category if coming from dashboard link
$selected_category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Handle form submission (customer sends booking request only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $service_date = sanitize($_POST['service_date'] ?? '');
    $service_time = sanitize($_POST['service_time'] ?? '');
    $service_description = sanitize($_POST['service_description'] ?? '');

    if ($category_id && $service_date && $service_time) {
        // Insert request without worker and without amount
        $stmt = $conn->prepare("INSERT INTO booking (customer_id, worker_id, category_id, slot_id, service_description, service_date, service_time, status, total_amount)
                               VALUES (?, NULL, ?, NULL, ?, ?, ?, 'pending', 0.00)");
        $stmt->bind_param('issss', $customer_id, $category_id, $service_description, $service_date, $service_time);

        if ($stmt->execute()) {
            $booking_id = $stmt->insert_id;
            setSuccess('Your request has been sent to admin. Request ID: #' . $booking_id);
            header('Location: ../root/dashboard.php');
            exit();
        } else {
            setError('Failed to create booking request. Please try again.');
        }
        $stmt->close();
    } else {
        setError('Please select service category, date and time.');
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Booking - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/components.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>Request a Service</h1>
            <a href="../root/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <?php if ($error = getError()): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="" id="bookingForm">
                <div class="form-group">
                    <label for="category_id">Service Category *</label>
                    <select name="category_id" id="category_id" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                <?php echo ($selected_category_id === (int)$category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="service_date">Service Date *</label>
                    <input type="date" id="service_date" name="service_date" required
                           min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="service_time">Service Time *</label>
                    <input type="time" id="service_time" name="service_time" required>
                </div>

                <div class="form-group">
                    <label for="service_description">Service Description</label>
                    <textarea id="service_description" name="service_description" rows="4"
                              placeholder="Describe the service you need..."></textarea>
                </div>
                <p class="text-muted">
                    Note: You are sending a request. Admin will check worker availability, set the final amount, and assign a worker to you.
                </p>

                <button type="submit" class="btn btn-primary btn-block">Send Request</button>
            </form>
        </div>
    </div>
</body>
</html>
