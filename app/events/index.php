<?php

require '../../config/config.php';

requireRole('manager');

requireCsrfToken();

// Determine current section
$section = $_GET['section'] ?? 'runners';

// CRUD Operations
$action = $_GET['action'] ?? '';

//-----------------------------------------------------------
// Runners
//-----------------------------------------------------------

if($section === 'runners'){
    $stmt = $pdo->query("
        SELECT *
        FROM runners
        ORDER by runner_id DESC
    ");
    $runners = $stmt->fetchAll();
}

// Create Runner
if($section==='runners' && $action==='create'){
    if($_SERVER['REQUEST_METHOD'] === 'POST'){

        $firstName = trim($_POST['runner_first_name'] ?? '');
        $lastName  = trim($_POST['runner_last_name'] ?? '');
        $category  = trim($_POST['runner_category'] ?? '');

        if($firstName !== '' && $lastName !=='' && $category!==''){

            if (isset($_SESSION['user_id'])) {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'create-runner',
                    'success'
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO runners (
                    runner_first_name,
                    runner_last_name,
                    runner_category
                )
                VALUES (?,?,?)
            ");

            $stmt->execute([$firstName, $lastName, $category]);

            header("Location: index.php?section=runners");
            exit;
        }
    }
}

// Update Runner
if($section==='runners' && $action==='update'){

    $runnerId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT *
        FROM runners
        WHERE runner_id =?
    ");
    $stmt->execute([$runnerId]);
    $runner = $stmt->fetch();

    if($_SERVER['REQUEST_METHOD'] === 'POST'){

        $firstName = trim($_POST['runner_first_name'] ?? '');
        $lastName  = trim($_POST['runner_last_name'] ?? '');
        $category  = trim($_POST['runner_category'] ?? '');

        if($firstName !== '' && $lastName !=='' && $category!==''){

            if (isset($_SESSION['user_id'])) {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'update-runner',
                    'success'
                );
            }

            $stmt = $pdo->prepare("
                UPDATE runners
                SET
                    runner_first_name=?,
                    runner_last_name=?,
                    runner_category =?
                WHERE runner_id=?
            ");

            $stmt->execute([$firstName, $lastName, $category, $runnerId]);

            $_SESSION['alert'] = 'Runner Updated Successfully';

            header("Location: index.php?section=runners");
            exit;
        }
    }
}

//-----------------------------------------------------------
// Events
//-----------------------------------------------------------

if ($section === 'events') {
    $stmt = $pdo->query("
        SELECT *
        FROM backyard_events
        ORDER BY event_id DESC
    ");
    $events = $stmt->fetchAll();
}

// EVENTS CREATE
if ($section === 'events' && $action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $name      = trim($_POST['event_name'] ?? '');
        $organizer = trim($_POST['event_organizer'] ?? '');
        $distance  = trim($_POST['event_distance'] ?? '');

        if ($name !== '' && $organizer !== '' && $distance !== '') {

            if (isset($_SESSION['user_id'])) {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'add-event',
                    'success'
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO backyard_events (
                    event_name,
                    event_organizer,
                    event_distance
                )
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $organizer, $distance]);

            header("Location: index.php?section=events");
            exit;
        }
    }
}

// EVENTS UPDATE
if ($section === 'events' && $action === 'update') {

    $eventId = (int) ($_GET['id'] ?? 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $name      = trim($_POST['event_name'] ?? '');
        $organizer = trim($_POST['event_organizer'] ?? '');
        $distance  = trim($_POST['event_distance'] ?? '');

        if (isset($_SESSION['user_id'])) {
            logActivity(
                $pdo,
                $_SESSION['user_id'],
                $_SESSION['user_email'] ?? null,
                'update-event',
                'success'
            );
        }

        $stmt = $pdo->prepare("
            UPDATE backyard_events
            SET
                event_name = ?,
                event_organizer = ?,
                event_distance = ?
            WHERE event_id = ?
        ");
        $stmt->execute([$name, $organizer, $distance, $eventId]);

        header("Location: index.php?section=events");
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM backyard_events
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        die("Event not found.");
    }
}

//-----------------------------------------------------------
// Registrations
//-----------------------------------------------------------

if ($section === 'registrations') {

    // Runners dropdown
    $stmt = $pdo->query("
        SELECT
            runner_id,
            runner_first_name,
            runner_last_name
        FROM runners
        ORDER BY runner_last_name, runner_first_name
    ");
    $runners = $stmt->fetchAll();

    // Events dropdown
    $stmt = $pdo->query("
        SELECT
            event_id,
            event_name,
            event_distance
        FROM backyard_events
        ORDER BY event_name
    ");
    $events = $stmt->fetchAll();

    // Registration records
    $stmt = $pdo->query("
        SELECT
            race_registrations.registration_id,
            race_registrations.registration_date,
            race_registrations.race_date,
            race_registrations.finish_date,

            runners.runner_first_name,
            runners.runner_last_name,

            backyard_events.event_name,
            backyard_events.event_distance

        FROM race_registrations

        INNER JOIN runners
            ON race_registrations.runner_id = runners.runner_id

        INNER JOIN backyard_events
            ON race_registrations.event_id = backyard_events.event_id

        ORDER BY race_registrations.registration_id DESC
    ");
    $registrationRecords = $stmt->fetchAll();
}

// Register a runner
if ($section === 'registrations' && $action === 'create') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $runnerId = (int) ($_POST['runner_id'] ?? 0);
        $eventId  = (int) ($_POST['event_id'] ?? 0);
        $raceDate = $_POST['race_date'] ?? '';

        if ($runnerId > 0 && $eventId > 0 && $raceDate !== '') {

            // Block if runner already has unfinished race
            $stmt = $pdo->prepare("
                SELECT registration_id
                FROM race_registrations
                WHERE runner_id = ?
                    AND finish_date IS NULL
                LIMIT 1
            ");
            $stmt->execute([$runnerId]);
            $runnerRegistration = $stmt->fetch();

            if ($runnerRegistration) {

                $_SESSION['alert'] =
                    'This runner cannot register for another event because a previous race has not been finished.';

            } else {

                // Block duplicate active registration for same event
                $stmt = $pdo->prepare("
                    SELECT registration_id
                    FROM race_registrations
                    WHERE runner_id = ?
                        AND event_id = ?
                        AND finish_date IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$runnerId, $eventId]);
                $eventRegistration = $stmt->fetch();

                if ($eventRegistration) {

                    $_SESSION['alert'] =
                        'This runner is already registered for this event and has not finished.';

                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO race_registrations (
                            runner_id,
                            event_id,
                            race_date
                        )
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$runnerId, $eventId, $raceDate]);

                    if (isset($_SESSION['user_id'])) {
                        logActivity(
                            $pdo,
                            $_SESSION['user_id'],
                            $_SESSION['user_email'] ?? null,
                            'register-runner',
                            'success'
                        );
                    }

                    $_SESSION['alert'] = 'Runner registered successfully.';
                }
            }

            header("Location: index.php?section=registrations");
            exit;
        }

        $_SESSION['alert'] = 'Please select a runner, event, and race date.';
    }
}

// REGISTRATION FINISH
if ($section === 'registrations' && $action === 'finish') {

    $registrationId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE race_registrations
        SET finish_date = CURRENT_TIMESTAMP
        WHERE registration_id = ?
    ");
    $stmt->execute([$registrationId]);

    $_SESSION['alert'] = 'Race marked as finished.';

    header("Location: index.php?section=registrations");
    exit;
}

// REGISTRATION DELETE
if ($section === 'registrations' && $action === 'delete') {

    $registrationId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        DELETE FROM race_registrations
        WHERE registration_id = ?
    ");
    $stmt->execute([$registrationId]);

    header("Location: index.php?section=registrations");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backyard Running Event</title>
</head>
<body>
    <form method="POST" action="../../auth/signout.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>" >
        <button type="submit"> Sign Out </button>
    </form>
    <h1>Backyard Running Event System</h1>
    <nav>
        <a href="index.php?section=runners">Runners</a>
        <a href="index.php?section=events">Events</a>
        <a href="index.php?section=registrations">Registrations</a>
    </nav>
    <hr>

    <!--RUNNERS SECTION-->
    <?php if($section==='runners'): ?>
        <h1>Runners</h1>
        <p>
            <a href="index.php?section=runners&action=create">Add Runner</a>
        </p>

        <?php if($action==='create'): ?>

            <h2>Add Runner</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>" >
                <p>
                    <label>First Name</label><br>
                    <input type="text" name="runner_first_name" required />
                </p>
                <p>
                    <label>Last Name</label><br>
                    <input type="text" name="runner_last_name" required/>
                </p>
                <p>
                    <label>Category</label><br>
                    <input type="text" name="runner_category" required/>
                </p>
                <button type="submit">Save</button>
                <a href="index.php?section=runners">Cancel</a>
            </form>

        <?php elseif($action==='update'): ?>

            <h2>Update Runner</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>" >
                <p>
                    <label>First Name</label><br>
                    <input type="text" name="runner_first_name" value="<?= htmlspecialchars($runner['runner_first_name'])?>" required/>
                </p>
                <p>
                    <label>Last Name</label><br>
                    <input type="text" name="runner_last_name" value="<?= htmlspecialchars($runner['runner_last_name'])?>" required/>
                </p>
                <p>
                    <label>Category</label><br>
                    <input type="text" name="runner_category" value="<?= htmlspecialchars($runner['runner_category'])?>" required/>
                </p>
                <button type="submit">Update</button>
                <a href="index.php?section=runners">Cancel</a>
            </form>

        <?php else: ?>

            <table border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Category</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($runners as $runner): ?>
                        <tr>
                            <td><?= htmlspecialchars($runner['runner_id']) ?></td>
                            <td><?= htmlspecialchars($runner['runner_first_name']) ?></td>
                            <td><?= htmlspecialchars($runner['runner_last_name']) ?></td>
                            <td><?= htmlspecialchars($runner['runner_category']) ?></td>
                            <td><?= htmlspecialchars($runner['runner_created_at']) ?></td>
                            <td>
                                <a href="index.php?section=runners&action=update&id=<?= $runner['runner_id'] ?>">Edit</a>
                                |
                                <a>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <!--EVENTS SECTION-->
    <?php if($section==='events'): ?>
        <h2>Events</h2>
        <p>
            <a href="index.php?section=events&action=create">Add Event</a>
        </p>

        <?php if ($action === 'create'): ?>

            <h3>Add Event</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>" >
                <p>
                    <label>Event Name:</label><br>
                    <input type="text" name="event_name" required>
                </p>
                <p>
                    <label>Organizer:</label><br>
                    <input type="text" name="event_organizer" required >
                </p>
                <p>
                    <label>Distance:</label><br>
                    <input type="text" name="event_distance" required>
                </p>
                <button type="submit">Save</button>
                <a href="index.php?section=events">Cancel</a>
            </form>

        <?php elseif ($action === 'update'): ?>

            <h3>Update Event</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>" >
                <p>
                    <label>Event Name:</label><br>
                    <input type="text" name="event_name" value="<?= htmlspecialchars($event['event_name']) ?>" required>
                </p>
                <p>
                    <label>Organizer:</label><br>
                    <input type="text" name="event_organizer" value="<?= htmlspecialchars($event['event_organizer']) ?>" required>
                </p>
                <p>
                    <label>Distance:</label><br>
                    <input type="text" name="event_distance" value="<?= htmlspecialchars($event['event_distance']) ?>" required>
                </p>
                <button type="submit">Update</button>
                <a href="index.php?section=events">Cancel</a>
            </form>

        <?php else: ?>

            <table border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Event Name</th>
                        <th>Organizer</th>
                        <th>Distance</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= htmlspecialchars($event['event_id']) ?></td>
                            <td><?= htmlspecialchars($event['event_name']) ?></td>
                            <td><?= htmlspecialchars($event['event_organizer']) ?></td>
                            <td><?= htmlspecialchars($event['event_distance']) ?></td>
                            <td><?= htmlspecialchars($event['event_created_at']) ?></td>
                            <td>
                                <a href="index.php?section=events&action=update&id=<?= $event['event_id'] ?>">Edit</a>
                                |
                                <a href="index.php?section=events&action=delete&id=<?= $event['event_id'] ?>"
                                   onclick="return confirm('Delete this event?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>
    <?php endif; ?>

    <!--REGISTRATIONS SECTION-->
    <?php if($section==='registrations'): ?>
        <h1>Registrations</h1>
        <p>
            <a href="index.php?section=registrations&action=create">Register a Runner</a>
        </p>

        <?php if($action==='create'):?>
            <h3>Register a Runner</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>" >
                <p>
                    <label>Runner: </label><br>
                    <select name="runner_id" required>
                        <option value="">-- Select Runner --</option>
                        <?php foreach($runners as $runner): ?>
                            <option value="<?= $runner['runner_id'] ?>">
                                <?= htmlspecialchars(
                                    $runner['runner_first_name']
                                    .' '.
                                    $runner['runner_last_name']
                                )?>
                            </option>
                        <?php endforeach;?>
                    </select>
                </p>
                <p>
                    <label>Event: </label><br>
                    <select name="event_id" required>
                        <option value="">-- Select Event --</option>
                        <?php foreach($events as $event): ?>
                            <option value="<?= $event['event_id'] ?>">
                                <?= htmlspecialchars(
                                    $event['event_name']
                                    .' - '.
                                    $event['event_distance']
                                )?>
                            </option>
                        <?php endforeach;?>
                    </select>
                </p>
                <p>
                    <label>Race Date:</label><br>
                    <input type="date" name="race_date" min="<?= date('Y-m-d') ?>" required>
                </p>
                <button type="submit">Register</button>
                <a href="index.php?section=registrations">Cancel</a>
            </form>

        <?php else: ?>

            <table border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Runner</th>
                        <th>Event</th>
                        <th>Registration Date</th>
                        <th>Race Date</th>
                        <th>Finish Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrationRecords as $registration): ?>
                        <tr>
                            <td><?= htmlspecialchars($registration['registration_id']) ?></td>
                            <td>
                                <?= htmlspecialchars(
                                    $registration['runner_first_name']
                                    . ' '
                                    . $registration['runner_last_name']
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($registration['event_name']) ?>
                                -
                                <?= htmlspecialchars($registration['event_distance']) ?>
                            </td>
                            <td><?= htmlspecialchars($registration['registration_date']) ?></td>
                            <td>
                                <?php
                                $today    = new DateTime();
                                $raceDate = new DateTime($registration['race_date']);

                                if (empty($registration['finish_date']) && $raceDate < $today) {
                                    $daysOverdue = $today->diff($raceDate)->days;
                                    echo '<strong style="color: red;">Overdue by '
                                        . $daysOverdue
                                        . ' day(s)</strong>';
                                } else {
                                    echo htmlspecialchars($registration['race_date']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($registration['finish_date']): ?>
                                    <?= htmlspecialchars($registration['finish_date']) ?>
                                <?php else: ?>
                                    Not finished
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($registration['finish_date']): ?>
                                    Finished
                                <?php else: ?>
                                    Registered
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$registration['finish_date']): ?>
                                    <a href="index.php?section=registrations&action=finish&id=<?= $registration['registration_id'] ?>"
                                       onclick="return confirm('Mark this race as finished?');">Finish</a>
                                    |
                                <?php endif; ?>
                                <a href="index.php?section=registrations&action=delete&id=<?= $registration['registration_id'] ?>"
                                   onclick="return confirm('Delete this registration record?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif;?>
    <?php endif; ?>
</body>

<?php if (isset($_SESSION['alert'])): ?>
    <script>
        alert(<?=json_encode($_SESSION['alert'])?>);
    </script>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

</html>