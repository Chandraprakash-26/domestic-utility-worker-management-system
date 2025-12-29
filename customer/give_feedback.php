<?php
require_once '../includes/config.php';
requireUserType('customer');

$conn = getDBConnection();
$customer_id = getUserId();

$booking_id = (int)($_GET['id'] ?? 0);

// Get booking details
$booking = $conn->query("
    SELECT b.*, w.worker_name, w.worker_id
    FROM booking b
    JOIN worker w ON b.worker_id = w.worker_id
    WHERE b.booking_id = $booking_id AND b.customer_id = $customer_id AND b.status = 'completed'
")->fetch_assoc();

if (!$booking) {
    setError('Booking not found or not completed');
    header('Location: ../root/dashboard.php');
    exit();
}

// Check if feedback already exists
$existing_feedback = $conn->query("SELECT * FROM feedback WHERE booking_id = $booking_id")->fetch_assoc();

if ($existing_feedback) {
    setError('Feedback already submitted for this booking');
    header('Location: ../root/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (float)$_POST['rating'];
    $comments = sanitize($_POST['comments']);

    if ($rating >= 0 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO feedback (booking_id, rating, comments) VALUES (?, ?, ?)");
        $stmt->bind_param('ids', $booking_id, $rating, $comments);

        if ($stmt->execute()) {
            // Update worker rating is handled by trigger
            setSuccess('Thank you for your feedback!');
            header('Location: ../root/dashboard.php');
            exit();
        } else {
            setError('Failed to submit feedback. Please try again.');
        }
        $stmt->close();
    } else {
        setError('Invalid rating value');
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Give Feedback - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/components.css">

</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>Give Feedback</h1>
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
        </div>

        <div class="form-container">
            <form method="POST" action="" id="feedbackForm">
                <div class="form-group">
                    <label for="rating">Rating (0-5) *</label>
                    <div class="rating-input">
                        <div class="star-rating" id="starRating">
                            <span class="star" data-value="1">★</span>
                            <span class="star" data-value="2">★</span>
                            <span class="star" data-value="3">★</span>
                            <span class="star" data-value="4">★</span>
                            <span class="star" data-value="5">★</span>
                        </div>
                        <input type="number" id="rating" name="rating" required
                               min="0" max="5" step="0.5" value="5" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label for="comments">Your Comments</label>
                    <textarea id="comments" name="comments" rows="6"
                              placeholder="Share your experience with this service..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Submit Feedback</button>
            </form>
        </div>
    </div>

    <script>
        const stars = document.querySelectorAll('.star');
        const ratingInput = document.getElementById('rating');

        stars.forEach(star => {
            star.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                ratingInput.value = value;
                updateStars(value);
            });

            star.addEventListener('mouseenter', function() {
                const value = this.getAttribute('data-value');
                updateStars(value);
            });
        });

        document.getElementById('starRating').addEventListener('mouseleave', function() {
            updateStars(ratingInput.value);
        });

        function updateStars(value) {
            stars.forEach(star => {
                if (star.getAttribute('data-value') <= value) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }

        // Initialize with default value
        updateStars(5);
    </script>
</body>
</html>
