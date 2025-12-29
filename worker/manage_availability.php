<?php
require_once '../includes/config.php';
requireUserType('worker');

$conn = getDBConnection();
$worker_id = getUserId();

// Handle adding unavailability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_unavailable'])) {
    $unavailable_date = sanitize($_POST['unavailable_date']);
    $unavailable_start = sanitize($_POST['unavailable_start']);
    $unavailable_end = sanitize($_POST['unavailable_end']);
    $reason = sanitize($_POST['reason']);

    if ($unavailable_date && $unavailable_start && $unavailable_end) {
        $stmt = $conn->prepare("INSERT INTO worker_unavailability (worker_id, unavailable_date, unavailable_start_time, unavailable_end_time, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('issss', $worker_id, $unavailable_date, $unavailable_start, $unavailable_end, $reason);

        if ($stmt->execute()) {
            setSuccess('Unavailability marked successfully');
        } else {
            setError('Failed to mark unavailability');
        }
        $stmt->close();
    }
}

// Handle adding availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_slot'])) {
    $available_date = sanitize($_POST['available_date']);
    $available_time = sanitize($_POST['available_time']);

    if ($available_date && $available_time) {
        // Check if slot already exists
        $check = $conn->query("SELECT slot_id FROM availability WHERE worker_id = $worker_id AND available_date = '$available_date' AND available_time = '$available_time'")->fetch_assoc();

        if ($check) {
            setError('You already have a slot at this date and time');
        } else {
            $stmt = $conn->prepare("INSERT INTO availability (worker_id, available_date, available_time, status) VALUES (?, ?, ?, 'available')");
            $stmt->bind_param('iss', $worker_id, $available_date, $available_time);

            if ($stmt->execute()) {
                setSuccess('Availability slot added successfully');
            } else {
                setError('Failed to add slot');
            }
            $stmt->close();
        }
    }
}

// Handle deleting unavailability
if (isset($_GET['delete_unavailable'])) {
    $unavailability_id = (int)$_GET['delete_unavailable'];
    $conn->query("DELETE FROM worker_unavailability WHERE unavailability_id = $unavailability_id AND worker_id = $worker_id");
    setSuccess('Unavailability removed successfully');
    header('Location: ../root/dashboard.php');
    exit();
}

// Handle deleting availability
if (isset($_GET['delete'])) {
    $slot_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM availability WHERE slot_id = $slot_id AND worker_id = $worker_id AND status = 'available'");
    setSuccess('Slot deleted successfully');
    header('Location: ../root/dashboard.php');
    exit();
}

// Get default availability
$default_availability = $conn->query("
    SELECT * FROM worker_default_availability
    WHERE worker_id = $worker_id
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
")->fetch_all(MYSQLI_ASSOC);

// Get unavailability slots
$unavailable_slots = $conn->query("
    SELECT * FROM worker_unavailability
    WHERE worker_id = $worker_id AND unavailable_date >= CURDATE()
    ORDER BY unavailable_date, unavailable_start_time
")->fetch_all(MYSQLI_ASSOC);

// Get all availability slots
$slots = $conn->query("
    SELECT * FROM availability
    WHERE worker_id = $worker_id
    ORDER BY available_date, available_time
")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Availability - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/components.css">
    <link rel="stylesheet" href="../css/availability.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>Manage Availability</h1>
            <div>
                <a href="../worker/setup_availability.php" class="btn btn-primary">Setup Default Hours</a>
                <a href="../root/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </header>

        <?php if ($msg = getSuccess()): ?>
            <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if ($error = getError()): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Default Availability Schedule -->
        <div class="form-container">
            <h2>Your Default Weekly Schedule</h2>
            <?php if (count($default_availability) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($default_availability as $default): ?>
                            <tr>
                                <td><strong><?php echo $default['day_of_week']; ?></strong></td>
                                <td><?php echo date('h:i A', strtotime($default['start_time'])); ?> - <?php echo date('h:i A', strtotime($default['end_time'])); ?></td>
                                <td><span class="badge badge-available">Available</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top: 1rem;"><em>These are your regular working hours. Mark specific dates as unavailable below.</em></p>
            <?php else: ?>
                <div class="alert alert-warning">
                    You haven't set up your default availability yet.
                    <a href="../worker/setup_availability.php">Click here to set your regular working hours</a>.
                </div>
            <?php endif; ?>
        </div>

        <!-- Mark Unavailable Time -->
        <div class="form-container unavailable-section" style="margin-top: 2rem;">
            <h2>Mark Unavailable Time</h2>
            <p>Block out specific dates/times when you're NOT available (vacation, appointments, etc.)</p>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="unavailable_date">Date *</label>
                        <input type="date" id="unavailable_date" name="unavailable_date" required
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="unavailable_start">From *</label>
                        <input type="time" id="unavailable_start" name="unavailable_start" required>
                    </div>
                    <div class="form-group">
                        <label for="unavailable_end">To *</label>
                        <input type="time" id="unavailable_end" name="unavailable_end" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reason">Reason (optional)</label>
                    <input type="text" id="reason" name="reason" placeholder="e.g., Vacation, Personal appointment">
                </div>
                <button type="submit" name="add_unavailable" class="btn btn-danger">Mark as Unavailable</button>
            </form>
        </div>

        <!-- Unavailable Slots -->
        <div class="table-container" style="margin-top: 2rem;">
            <h2>Your Unavailable Time Slots</h2>
            <?php if (count($unavailable_slots) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time Range</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unavailable_slots as $unavailable): ?>
                            <tr>
                                <td><?php echo formatDate($unavailable['unavailable_date']); ?></td>
                                <td>
                                    <?php echo date('h:i A', strtotime($unavailable['unavailable_start_time'])); ?> -
                                    <?php echo date('h:i A', strtotime($unavailable['unavailable_end_time'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($unavailable['reason'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="?delete_unavailable=<?php echo $unavailable['unavailability_id']; ?>"
                                       class="btn btn-sm btn-delete"
                                       onclick="return confirm('Remove this unavailability?')">Remove</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No unavailable time slots marked.</p>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
