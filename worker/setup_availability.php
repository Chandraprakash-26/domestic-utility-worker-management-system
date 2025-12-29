<?php
require_once '../includes/config.php';
requireUserType('worker');

$conn = getDBConnection();
$worker_id = getUserId();

// Check if worker has already set up default availability
$check = $conn->query("SELECT COUNT(*) as count FROM worker_default_availability WHERE worker_id = $worker_id")->fetch_assoc();
$has_setup = $check['count'] > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete existing default availability
    $conn->query("DELETE FROM worker_default_availability WHERE worker_id = $worker_id");

    $success_count = 0;
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    foreach ($days as $day) {
        $is_available = isset($_POST['available_' . $day]) ? 1 : 0;

        if ($is_available) {
            $start_time = sanitize($_POST['start_' . $day]);
            $end_time = sanitize($_POST['end_' . $day]);

            if ($start_time && $end_time) {
                $stmt = $conn->prepare("INSERT INTO worker_default_availability (worker_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, 1)");
                $stmt->bind_param('isss', $worker_id, $day, $start_time, $end_time);

                if ($stmt->execute()) {
                    $success_count++;
                }
                $stmt->close();
            }
        }
    }

    if ($success_count > 0) {
        setSuccess('Default availability schedule saved successfully!');
        header('Location: ../root/dashboard.php');
        exit();
    } else {
        setError('Please set at least one available day with time slots.');
    }
}

// Get existing default availability
$existing_availability = [];
$result = $conn->query("SELECT * FROM worker_default_availability WHERE worker_id = $worker_id ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
while ($row = $result->fetch_assoc()) {
    $existing_availability[$row['day_of_week']] = $row;
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Default Availability - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/components.css">
    <link rel="stylesheet" href="../css/availability.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1><?php echo $has_setup ? 'Update' : 'Setup'; ?> Default Availability</h1>
            <a href="../root/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <?php if ($msg = getSuccess()): ?>
            <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if ($error = getError()): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="availability-setup">
            <div class="info-box">
                <h3>Set Your Regular Working Hours</h3>
                <p>Define your default weekly schedule. You can mark specific dates as unavailable later in "Manage Availability".</p>
            </div>

            <div class="quick-fill">
                <h4>Quick Fill Options:</h4>
                <div class="quick-fill-buttons">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="fillWeekdays()">Weekdays 9-5</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="fillAllDays()">All Days 9-5</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="fillCustom('08:00', '18:00')">8 AM - 6 PM</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="clearAll()">Clear All</button>
                </div>
            </div>

            <form method="POST" action="" id="availabilityForm">
                <?php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                foreach ($days as $day):
                    $existing = $existing_availability[$day] ?? null;
                    $is_checked = $existing ? 'checked' : '';
                    $start_time = $existing ? $existing['start_time'] : '09:00';
                    $end_time = $existing ? $existing['end_time'] : '17:00';
                ?>
                    <div class="day-row" id="row_<?php echo $day; ?>">
                        <div class="day-label">
                            <label>
                                <input type="checkbox"
                                       name="available_<?php echo $day; ?>"
                                       id="available_<?php echo $day; ?>"
                                       onchange="toggleDay('<?php echo $day; ?>')"
                                       <?php echo $is_checked; ?>>
                                <?php echo $day; ?>
                            </label>
                        </div>
                        <div class="time-inputs">
                            <label>From:</label>
                            <input type="time"
                                   name="start_<?php echo $day; ?>"
                                   id="start_<?php echo $day; ?>"
                                   value="<?php echo $start_time; ?>"
                                   <?php echo $existing ? '' : 'disabled'; ?>>
                            <label>To:</label>
                            <input type="time"
                                   name="end_<?php echo $day; ?>"
                                   id="end_<?php echo $day; ?>"
                                   value="<?php echo $end_time; ?>"
                                   <?php echo $existing ? '' : 'disabled'; ?>>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary btn-block">Save Default Availability</button>
            </form>
        </div>
    </div>

    <script>
        function toggleDay(day) {
            const checkbox = document.getElementById('available_' + day);
            const startInput = document.getElementById('start_' + day);
            const endInput = document.getElementById('end_' + day);
            const row = document.getElementById('row_' + day);

            if (checkbox.checked) {
                startInput.disabled = false;
                endInput.disabled = false;
                row.classList.remove('disabled');
            } else {
                startInput.disabled = true;
                endInput.disabled = true;
                row.classList.add('disabled');
            }
        }

        function fillWeekdays() {
            const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            weekdays.forEach(day => {
                document.getElementById('available_' + day).checked = true;
                document.getElementById('start_' + day).value = '09:00';
                document.getElementById('end_' + day).value = '17:00';
                toggleDay(day);
            });
        }

        function fillAllDays() {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            days.forEach(day => {
                document.getElementById('available_' + day).checked = true;
                document.getElementById('start_' + day).value = '09:00';
                document.getElementById('end_' + day).value = '17:00';
                toggleDay(day);
            });
        }

        function fillCustom(start, end) {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            days.forEach(day => {
                const checkbox = document.getElementById('available_' + day);
                if (checkbox.checked) {
                    document.getElementById('start_' + day).value = start;
                    document.getElementById('end_' + day).value = end;
                }
            });
        }

        function clearAll() {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            days.forEach(day => {
                document.getElementById('available_' + day).checked = false;
                toggleDay(day);
            });
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            days.forEach(day => toggleDay(day));
        });
    </script>
</body>
</html>
