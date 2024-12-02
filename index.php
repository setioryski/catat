<?php
session_start();

// Enable detailed error reporting (Disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Hardcoded password (you can change this to any password you prefer)
$hardcoded_password = '2143';

// Check if the user is already logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // If form is submitted, check the password
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === $hardcoded_password) {
            // Set session variable if password is correct
            $_SESSION['logged_in'] = true;
        } else {
            // Invalid password
            $login_error = "Invalid password.";
        }
    }

    // Display login form if not authenticated
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login</title>
            <link rel="stylesheet" href="style.css"> <!-- Link to External CSS -->
        </head>
        <body>
            <div class="login-form">
                <h2>Login</h2>
                <form method="POST">
                    <input type="password" name="password" placeholder="Enter password" required>
                    <button type="submit">Login</button>
                </form>
                <?php if (isset($login_error)) : ?>
                    <div class="error"><?php echo $login_error; ?></div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit; // Stop further script execution if not logged in
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Database connection parameters
$servername = "localhost";
$db_username = "root"; // Database username
$db_password = "aejot1234";      // Database password
$dbname = "stickynotes";  // Database name
$max_retries = 3;

// Create connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/**
 * Executes a prepared statement with a retry mechanism.
 *
 * @param mysqli $conn The MySQLi connection object.
 * @param mysqli_stmt $stmt The prepared statement object.
 * @param string $query The SQL query string.
 * @param int $max_retries Maximum number of retries.
 * @return bool True on success, False on failure.
 */
function executeQuery($conn, $stmt, $query, $max_retries = 3) {
    $attempt = 0;
    while ($attempt < $max_retries) {
        try {
            if ($stmt->execute()) {
                return true;
            } else {
                throw new Exception("Error executing query: " . $stmt->error);
            }
        } catch (mysqli_sql_exception $e) {
            if ($conn->errno == 2006 || $conn->errno == 2013) {
                // MySQL server has gone away or lost connection
                $attempt++;
                $conn->close();
                $conn = new mysqli($GLOBALS['servername'], $GLOBALS['db_username'], $GLOBALS['db_password'], $GLOBALS['dbname']);
                if ($conn->connect_error) {
                    throw new Exception("Reconnection failed: " . $conn->connect_error);
                }
                // Re-prepare the statement with the original query
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement after reconnection: " . $conn->error);
                }
                // Note: You may need to re-bind parameters here if required
            } else {
                throw $e;
            }
        }
    }
    return false;
}

// Function to make links clickable
function makeLinksClickable($text) {
    $pattern = '/(https?:\/\/[^\s]+)/';
    $replacement = '<a href="$1" target="_blank">$1</a>';
    return preg_replace($pattern, $replacement, $text);
}

// Function to truncate text
function truncateText($text, $maxLength) {
    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength) . '...';
    }
    return $text;
}

// Toggle pin status
if (isset($_GET['toggle_pin'])) {
    $id = intval($_GET['toggle_pin']);
    // Fetch current pinned status
    $query = "SELECT pinned FROM notes WHERE id=?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Preparation failed: " . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!executeQuery($conn, $stmt, $query, $max_retries)) {
        die("Error: Unable to fetch pinned status after multiple attempts");
    }
    $stmt->bind_result($current_pinned);
    if (!$stmt->fetch()) {
        die("Error: Note not found.");
    }
    $stmt->close();

    // Toggle pinned status
    $new_pinned = $current_pinned ? 0 : 1;
    $update_query = "UPDATE notes SET pinned=? WHERE id=?";
    $update_stmt = $conn->prepare($update_query);
    if (!$update_stmt) {
        die("Preparation failed: " . $conn->error);
    }
    $update_stmt->bind_param("ii", $new_pinned, $id);
    if (!executeQuery($conn, $update_stmt, $update_query, $max_retries)) {
        die("Error: Unable to toggle pin status after multiple attempts");
    }
    $update_stmt->close();

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Add a new note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $query = "INSERT INTO notes (title, content) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Preparation failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $title, $content);
    if (!executeQuery($conn, $stmt, $query, $max_retries)) {
        die("Error: Unable to add note after multiple attempts");
    }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Edit a note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $query = "UPDATE notes SET title=?, content=? WHERE id=?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Preparation failed: " . $conn->error);
    }
    $stmt->bind_param("ssi", $title, $content, $id);
    if (!executeQuery($conn, $stmt, $query, $max_retries)) {
        die("Error: Unable to edit note after multiple attempts");
    }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Delete a note
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $query = "DELETE FROM notes WHERE id=?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Preparation failed: " . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!executeQuery($conn, $stmt, $query, $max_retries)) {
        die("Error: Unable to delete note after multiple attempts");
    }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Retrieve all pinned notes
$pinned_query = "SELECT * FROM notes WHERE pinned = 1 ORDER BY created_at DESC";
$pinned_result = $conn->query($pinned_query);
if (!$pinned_result) {
    die("Error retrieving pinned notes: " . $conn->error);
}

// Retrieve all unpinned notes
$unpinned_query = "SELECT * FROM notes WHERE pinned = 0 ORDER BY created_at DESC";
$unpinned_result = $conn->query($unpinned_query);
if (!$unpinned_result) {
    die("Error retrieving unpinned notes: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NOTES</title>
    <link rel="stylesheet" href="style.css"> <!-- Link to External CSS -->
</head>
<body>
    <a href="?logout=true" class="logout-button">Logout</a>
    <div class="header">
        <h1>CATATAN</h1>
        <img src="siren.gif" alt="Siren GIF">
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="text" name="title" placeholder="Apa judul catatan kamu..." required>
        <textarea name="content" rows="4" placeholder="Tulis catatan"></textarea>
        <button type="submit">SUBMIT</button>
    </form>

    <!-- Pinned Notes Section -->
    <?php if ($pinned_result->num_rows > 0): ?>
        <div class="pinned-notes-container">
            <h2>Pinned Notes</h2>
            <div class="notes pinned-notes">
                <?php while ($row = $pinned_result->fetch_assoc()): ?>
                    <div class="note" id="note-<?php echo $row['id']; ?>">
                        <h2 contenteditable="false"
                            id="title-<?php echo $row['id']; ?>"
                            data-original-title="<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($row['title']); ?>
                        </h2>
                        <p contenteditable="false"
                           id="content-<?php echo $row['id']; ?>"
                           class="note-content"
                           data-full-content="<?php echo htmlspecialchars($row['content'], ENT_QUOTES); ?>"
                           data-original-content="<?php echo htmlspecialchars($row['content'], ENT_QUOTES); ?>">
                            <?php 
                                $content = htmlspecialchars($row['content']);
                                $max_length = 200; // Maximum characters before truncation
                                $is_editing = false;

                                // Check if editing
                                if (isset($_GET['edit_id']) && intval($_GET['edit_id']) === $row['id']) {
                                    $is_editing = true;
                                }

                                if ($is_editing) {
                                    // Show full content when editing
                                    echo nl2br(makeLinksClickable($content));
                                } else {
                                    // Truncate content when not editing
                                    echo nl2br(makeLinksClickable(truncateText($content, $max_length)));
                                }
                            ?>
                        </p>
                        <div class="actions">
                            <span class="copy" onclick="copyToClipboard('content-<?php echo $row['id']; ?>')">Copy</span>
                            <a class="edit" href="javascript:void(0)" onclick="editNote('<?php echo $row['id']; ?>')">Edit</a>
                            <a class="save" href="javascript:void(0)" onclick="saveNote('<?php echo $row['id']; ?>')">Save</a>
                            <a class="cancel" href="javascript:void(0)" onclick="cancelEdit('<?php echo $row['id']; ?>')">Cancel</a>
                            <a class="pin" href="?toggle_pin=<?php echo $row['id']; ?>">
                                <?php echo $row['pinned'] ? 'Unpin' : 'Pin'; ?>
                            </a>
                            <a class="delete" href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this note?');">Delete</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- All Other Notes Section -->
    <div class="all-notes-container">
        <h2>All Notes</h2>
        <div class="notes all-notes">
            <?php while ($row = $unpinned_result->fetch_assoc()): ?>
                <div class="note" id="note-<?php echo $row['id']; ?>">
                    <h2 contenteditable="false"
                        id="title-<?php echo $row['id']; ?>"
                        data-original-title="<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>">
                        <?php echo htmlspecialchars($row['title']); ?>
                    </h2>
                    <p contenteditable="false"
                       id="content-<?php echo $row['id']; ?>"
                       class="note-content"
                       data-full-content="<?php echo htmlspecialchars($row['content'], ENT_QUOTES); ?>"
                       data-original-content="<?php echo htmlspecialchars($row['content'], ENT_QUOTES); ?>">
                        <?php 
                            $content = htmlspecialchars($row['content']);
                            $max_length = 200; // Maximum characters before truncation
                            $is_editing = false;

                            if (isset($_GET['edit_id']) && intval($_GET['edit_id']) === $row['id']) {
                                $is_editing = true;
                            }

                            if ($is_editing) {
                                echo nl2br(makeLinksClickable($content));
                            } else {
                                echo nl2br(makeLinksClickable(truncateText($content, $max_length)));
                            }
                        ?>
                    </p>
                    <div class="actions">
                        <span class="copy" onclick="copyToClipboard('content-<?php echo $row['id']; ?>')">Copy</span>
                        <a class="edit" href="javascript:void(0)" onclick="editNote('<?php echo $row['id']; ?>')">Edit</a>
                        <a class="save" href="javascript:void(0)" onclick="saveNote('<?php echo $row['id']; ?>')">Save</a>
                        <a class="cancel" href="javascript:void(0)" onclick="cancelEdit('<?php echo $row['id']; ?>')">Cancel</a>
                        <a class="pin" href="?toggle_pin=<?php echo $row['id']; ?>">
                            <?php echo $row['pinned'] ? 'Unpin' : 'Pin'; ?>
                        </a>
                        <a class="delete" href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this note?');">Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="copy-message" id="copyMessage">Note copied to clipboard!</div>
    <script src="main.js"></script> <!-- Link to External JS -->
</body>
</html>
<?php $conn->close(); ?>
