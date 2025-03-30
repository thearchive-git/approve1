<?php
session_start();
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$database = "approve";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$successMessage = '';

// Check if there is a message in the URL
if (isset($_GET['message'])) {
    $successMessage = urldecode($_GET['message']);
}

// Handle approve request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_id'])) {
    $approveId = (int)$_POST['approve_id'];
    $stmtSelect = $conn->prepare("SELECT * FROM pending_claim_reports WHERE id = ?");
    $stmtSelect->bind_param("i", $approveId);
    $stmtSelect->execute();
    $resultSelect = $stmtSelect->get_result();

    if ($resultSelect->num_rows > 0) {
        $reportData = $resultSelect->fetch_assoc();

        $pendingPath = 'uploads/pending/' . $reportData['picture'];
        $approvedPath = 'uploads/approved/' . $reportData['picture'];

        // Insert into approved_reports
        // Assign 'Unclaimed' to a variable
        $status = 'Unclaimed';
        // Prepare the query
        $stmtInsert = $conn->prepare("
    INSERT INTO approved_claim_reports 
    (item_name, date_found, category, time_found, brand, location_found, 
     primary_color, picture, description, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
        if (!$stmtInsert) {
            die("Error preparing the query: " . $conn->error);
        }

        // Bind the parameters
        $stmtInsert->bind_param(
            "ssssssssss",
            $reportData['item_name'],
            $reportData['date_found'],
            $reportData['category'],
            $reportData['time_found'],
            $reportData['brand'],
            $reportData['location_found'],
            $reportData['primary_color'],
            $reportData['picture'],
            $reportData['description'],
            $status
        );
        if ($stmtInsert->execute()) {
            $stmtDelete = $conn->prepare("DELETE FROM pending_claim_reports WHERE id = ?");
            $stmtDelete->bind_param("i", $approveId);
            if ($stmtDelete->execute()) {
                if (!empty($reportData['picture']) && file_exists($pendingPath)) {
                    rename($pendingPath, $approvedPath); // Move image
                }
                $successMessage = "Report approved successfully.";
            } else {
                $successMessage = "Error deleting report from pending reports: " . $stmtDelete->error;
            }
            $stmtDelete->close();
        } else {
            $successMessage = "Error approving report: " . $stmtInsert->error;
        }
        $stmtInsert->close();
    } else {
        $successMessage = "No report found with the given ID.";
    }

    $stmtSelect->close();
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];

    $stmtSelect = $conn->prepare("SELECT picture FROM pending_claim_reports WHERE id = ?");
    $stmtSelect->bind_param("i", $deleteId);
    $stmtSelect->execute();
    $resultSelect = $stmtSelect->get_result();

    if ($resultSelect->num_rows > 0) {
        $reportData = $resultSelect->fetch_assoc();
        $picturePath = "uploads/pending/" . $reportData['picture'];

        $stmtDelete = $conn->prepare("DELETE FROM pending_claim_reports WHERE id = ?");
        $stmtDelete->bind_param("i", $deleteId);

        if ($stmtDelete->execute()) {
            if (!empty($reportData['picture']) && file_exists($picturePath)) {
                unlink($picturePath);
            }
            $successMessage = "Report deleted successfully.";
        } else {
            $successMessage = "Error deleting report: " . $stmtDelete->error;
        }
        $stmtDelete->close();
    } else {
        $successMessage = "Report not found.";
    }
    $stmtSelect->close();
}

// Handle approve all request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_all'])) {
    $resultSelect = $conn->query("SELECT * FROM pending_claim_reports WHERE status = 'Unclaimed'");

    if ($resultSelect->num_rows > 0) {
        while ($reportData = $resultSelect->fetch_assoc()) {
            $stmtInsert = $conn->prepare("
                INSERT INTO approved_claim_reports 
                (item_name, date_found, category, time_found, brand, location_found, 
                 primary_color, picture, description, first_name, last_name, phone_number, 
                 email, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->bind_param(
                "ssssssssssssss",
                $reportData['item_name'],
                $reportData['date_found'],
                $reportData['category'],
                $reportData['time_found'],
                $reportData['brand'],
                $reportData['location_found'],
                $reportData['primary_color'],
                $reportData['picture'],
                $reportData['description'],
                $reportData['first_name'],
                $reportData['last_name'],
                $reportData['phone_number'],
                $reportData['email'],
                $reportData['status']
            );

            if ($stmtInsert->execute()) {
                $stmtDelete = $conn->prepare("DELETE FROM pending_claim_reports WHERE id = ?");
                $stmtDelete->bind_param("i", $reportData['id']);
                $stmtDelete->execute();
                $stmtDelete->close();

                if (!empty($reportData['picture']) && file_exists("uploads/pending/" . $reportData['picture'])) {
                    rename("uploads/pending/" . $reportData['picture'], "uploads/approved/" . $reportData['picture']);
                }
            }
            $stmtInsert->close();
        }
        $successMessage = "All reports approved successfully.";
    } else {
        $successMessage = "No reports to approve.";
    }
}

// Handle delete all request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_all'])) {
    $resultSelect = $conn->query("SELECT picture FROM pending_claim_reports WHERE status = 'Unclaimed'");

    if ($resultSelect->num_rows > 0) {
        while ($reportData = $resultSelect->fetch_assoc()) {
            if (!empty($reportData['picture']) && file_exists("uploads/pending/" . $reportData['picture'])) {
                unlink("uploads/pending/" . $reportData['picture']);
            }
        }
        $conn->query("DELETE FROM pending_claim_reports WHERE status = 'Unclaimed'");
        $successMessage = "All reports deleted successfully.";
    } else {
        $successMessage = "No reports to delete.";
    }
}

// Initialize search
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Set default limit and page
$limit = isset($_POST['entry_limit']) ? (int)$_POST['entry_limit'] : 5;
if ($limit === 'all') {
    $limit = 10000;  // Show all entries if 'all' is selected
}

// Get current page number
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build the SQL query for counting the total number of entries
$sqlCount = "SELECT COUNT(*) AS total FROM pending_claim_reports WHERE status='unclaimed'";
if (!empty($search)) {
    $sqlCount .= " AND (item_name LIKE '%$search%' OR location_found LIKE '%$search%' OR category LIKE '%$search%' OR user_id LIKE '%$search%')";
}

// Get the total number of results
$resultCount = $conn->query($sqlCount);
$totalRecords = 0;
if ($resultCount) {
    $totalRecords = $resultCount->fetch_assoc()['total'];
}

// Build the SQL query for fetching reports with limit and search functionality
$sql = "SELECT * FROM pending_claim_reports WHERE status='unclaimed'";

if (!empty($search)) {
    $sql .= " AND (item_name LIKE '%$search%' OR location_found LIKE '%$search%' OR category LIKE '%$search%' OR user_id LIKE '%$search%')";
}

$sql .= " LIMIT $offset, $limit"; // apply limit and offset to the query

// Execute the query
$result = $conn->query($sql);

// Calculate total pages for pagination
if ($limit == 10000) {
    // If "All" is selected, set total pages to 1
    $totalPages = 1;
} else {
    // Calculate total pages as usual
    $totalPages = ceil($totalRecords / $limit);
}

// Calculate the range for the current page
$currentEntriesStart = $offset + 1;  // First entry on this page
$currentEntriesEnd = min($offset + $limit, $totalRecords);  // Last entry on this page

$userName = htmlspecialchars($_SESSION['name'] ?? 'User');

//start deleting here to remove the count on see details
if (!isset($_SESSION['user_id'])) {
    die("User ID not set. Please log in.");
}



$userId = $_SESSION['user_id'];

// Count user reports
$approvedReportCount = countUserReports($conn, $userId);

function countUserReports($conn, $userId)
{
    $query = "
        SELECT COUNT(*) AS total_reports FROM (
            SELECT id FROM pending_lost_reports WHERE user_id = ?
            UNION ALL
            SELECT id FROM pending_found_reports WHERE user_id = ?
            UNION ALL
            SELECT id FROM approved_lost_reports WHERE user_id = ?
            UNION ALL
            SELECT id FROM approved_found_reports WHERE user_id = ?
        ) AS user_reports
    ";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('ssss', $userId, $userId, $userId, $userId);
        $stmt->execute();

        // Initialize $totalReports to prevent IDE warnings
        $totalReports = 0;
        $stmt->bind_result($totalReports);
        $stmt->fetch();
        $stmt->close();

        return $totalReports;
    }


    return 0; // Default to 0 if query fails
}
// Check if logout is requested
if (isset($_GET['logout'])) {
    // Destroy session
    session_unset();
    session_destroy();

    // Redirect to login page
    header("Location: login_admin.php");
    exit;
}

$pending_claim_reports = [];
$pending_claim_reports = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pending_claim_reports[] = $row;
    }
}

$pending_claim_reports = fetchReports("pending_claim_reports", $conn);
$pending_found_reports = fetchReports("pending_found_reports", $conn);
$pending_lost_reports = fetchReports("pending_lost_reports", $conn);

// Function to fetch reports with pending position

// Function to fetch reports with reporter name

// Function to fetch reports with reporter name
function fetchReports($table, $conn)
{
    // Debugging: Log the query being executed
    $query = "SELECT $table.*, user.name AS reporter_name 
              FROM $table 
              JOIN user 
              ON $table.user_id = user.card_number 
              WHERE $table.position = 'Pending'";

    // Debug: Display query (for testing purposes only, remove in production)
    // echo $query;

    $result = $conn->query($query);

    // Check for query errors
    if (!$result) {
        die("Query failed: " . $conn->error);
    }

    $reports = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
    }
    return $reports;
}

// Fetch pending reports for each table
$pendingClaimReports = fetchReports("pending_claim_reports", $conn);
$pendingFoundReports = fetchReports("pending_found_reports", $conn);
$pendingLostReports = fetchReports("pending_lost_reports", $conn);

// Count the number of pending reports for each table
$claim_count = count($pendingClaimReports);
$found_count = count($pendingFoundReports);
$lost_count = count($pendingLostReports);

// Total pending notifications
$total_notifications = $claim_count + $found_count + $lost_count;




?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim History</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:ital,wght@0,100..900;1,100..900&family=Londrina+Outline&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Rubik+80s+Fade&family=Rubik+Burned&family=Source+Code+Pro:ital,wght@0,200..900;1,200..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="admin_report.css">
    <style>
                @import url("https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;600&display=swap"

        );
    /* General styles */
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Hanken Grotesk', Arial, sans-serif;
    }


    body {
        background-color: #fff;
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        display: flex;
        color: #545454;
        flex-direction: column;
        min-height: 100vh;
        background-size: cover;
        background-position: center center;
        background-attachment: fixed;
        background-repeat: no-repeat;
        background-color: #FAF9F6;
    }

    /* Navbar styles */
    .navbar {
        background-color: #fff;
        padding: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        color: #545454;
        position: sticky;
        top: 0;

        width: 100%;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
    }

    /* Navigation main container */
    .nav-main {
        display: flex;
        align-items: center;
        /* Center items vertically */
        gap: 20px;
    }

    .nav-content {
        background-image: url('images/f1.png');
        background-size: cover;
        background-position: center center;
        background-attachment: fixed;
        background-repeat: no-repeat;
        padding: 97px 0;


    }

    .nav-content-cont {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        margin-left: 70px;

    }

    .nav-title h1 {
    font-size: 78px;
    color: #f6efe0;
    text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.3);
    font-style: italic;
    font-weight: bold;
    width: 700px;
    font-family: 'Hanken Grotesk', Arial, sans-serif;
    }

    .navbar-logo {
        height: 90px;
        width: auto;
        /* Maintain aspect ratio */
        margin-right: 20px;
        margin-left: 20px;
        margin-top: 10px;
        margin-bottom: 10px;
    }

     /* UCC */
     .main-title {
        font-family: "Times New Roman", Times, serif;
        font-size: 36px;
        font-weight: bold;
        white-space: nowrap;
        color: #000 !important;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .subtitle {
        font-family: 'Work Sans', sans-serif;
        display: block; 
        font-size: 24px; 
        color: black; 
        text-shadow: 0px 0px 0px;
        font-weight: normal;
        padding-left: 3px;
    }

    .navbar a {
        color: #545454;
        text-decoration: none;
        margin: 20px;
        display: flex;
        margin-top: 12px;
    }

    .navbar a:hover {
        text-decoration: underline;
        text-decoration-thickness: 1px;
    }

     /* -----------Dropdown container--------------- */
     .navbar .dropdown {
        position: relative;
        display: inline-block;
    }

    .navbar .dropbtn {
        background-color: transparent;
        color: #545454;
        padding-left: 20px;
        border: none;
        cursor: pointer;
        text-align: center;
        font-size: 16px;
        margin: 20px;
        display: inline-block;
    }

    .navbar .dropdown-content {
        display: none;
        position: absolute;
        background-color: #f9f9f9;
        width: 200px;
        box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
        z-index: 1;
        color: #545454;
        border-radius: 10px;
    }

    .navbar .dropdown-content a {
        color: #545454;
        padding: 6px 10px;
        text-decoration: none;
        display: block;
    }

    .navbar .dropdown-content a:hover {
        text-decoration: underline;
    }

    .navbar .dropdown:hover .dropdown-content {
        display: block;
    }

    .navbar .dropdown:hover .dropbtn {
        text-decoration: underline;
    }
    .dropdown {
        position: relative;
        display: inline-block;
        margin-bottom: 10px;
        z-index: 1;
    }

    .dropdown-btn {
        padding: 5px 20px;
        background-color: #e5e5e5;
        color: #545454;
        border: 3px solid #545454;
        border-radius: 22px;
        cursor: pointer;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.3s ease;
    }

    .dropdown-btn::after {
        content: '';
        width: 0;
        height: 0;
        border-top: 5px solid transparent;
        border-bottom: 5px solid transparent;
        border-left: 5px solid #545454;
        margin-left: 10px;
        transition: background-color 0.3s ease;
        transform: rotate(270deg);
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background-color: white;
        min-width: 180px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        z-index: 1;
        margin-top: 0px;
        border-radius: 4px;
    }

    .dropdown:hover .dropdown-content {
        display: block;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .dropdown-content a {
        padding: 0;
        text-decoration: none;
        display: block;
        color: #545454;
    }

    .dropdown-content a:hover {
        background-color: #f1f1f1;
    }

    /* Rotate the arrow when hovering over the dropdown button */
    .dropdown:hover .dropdown-btn::after {
        transform: rotate(90deg);
        /* Rotates the arrow */
    }

    /* Hover effect on the button (optional, for visual feedback) */
    .dropdown-btn:hover {
        background-color: #ccc
    }

    /* -----------Dropdown container--------------- */

    .notif-btn {
        position: relative;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 24px;
        color: #FF7701;
        outline: none;
        margin-left: auto;
        margin-right: -70px;
        margin-top: 10px;
    }


    .notif-badge {
        position: absolute;
        top: -5px;
        /* Adjust as needed */
        right: -5px;
        /* Adjust as needed */
        background: red;
        color: white;
        font-size: 12px;
        font-weight: bold;
        padding: 2px 6px;
        border-radius: 50%;
        display: inline-block;
        z-index: 1;
        /* Ensures it stays on top */
    }

    .notif-btn:hover {
        color: #ccc;
        /* Hover effect */
        transform: scale(1.1);
        /* Slight zoom effect */
        transition: 0.2s ease-in-out;
    }

    .navbar>.icon-btn {
        background-color: #f4f5f6;
        /* Transparent background for the button */
        border: 2px solid #000;
        /* Consistent border color */
        /* Border for circular shape */
        border-radius: 50%;
        /* Makes the border circular */
        cursor: pointer;
        /* Pointer cursor */
        padding: 3px;
        /* Space around the icon */
        display: flex;
        /* Center the icon inside the button */
        align-items: center;
        /* Vertical centering */
        justify-content: center;
        /* Horizontal centering */
        margin-left: 630px;
        /* Push the button to the far right */
        transition: background-color 0.3s ease, border-color 0.3s ease;
        /* Smooth hover effect */
        z-index: 99999;

    }

    .icon-btn {
        z-index: 99999;
    }

    /* Hamburger Icon */
    .hamburger-icon {
        background: none;
        border: none;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 22px;
        width: 30px;
        margin-left: auto;
        margin-right: 20px;
        padding: 0;
    }

    .hamburger-icon span {
        background-color: black;
        height: 3px;
        width: 100%;
        border-radius: 2px;
        transition: all 0.3s;
    }

    /* Side Navigation */
    .side-nav {
        height: 100%;
        width: 0;
        position: fixed;
        top: 0;
        right: 0;
        background-color: #fff;
        overflow-x: hidden;
        transition: 0.3s;
        padding-top: 60px;
        box-shadow: -2px 0 6px rgba(0, 0, 0, 0.2);
        z-index: 2;
    }

    .side-nav a {
        padding: 10px 20px;
        text-decoration: none;
        font-size: 20px;
        color: #545454;
        display: block;
        transition: 0.3s;
    }

    .side-nav a:hover {
        color: #f1f1f1;
    }

    .side-nav .close-btn {
        position: absolute;
        top: 10px;
        right: 20px;
        font-size: 30px;
        color: #545454;
    }

    /* Show the side-nav */
    .side-nav.open {
        width: 250px;
    }





    .navbar>.icon-btn:hover {
        background-color: #f4f4f9;
        /* Light background on hover */
        border-color: #000;
        /* Darker border on hover */
    }



    .navbar>.icon-btn:hover .user-icon {
        color: #000;
        /* Darker icon color on hover */
    }

    .user-icon {
        font-size: 24px;
        /* Icon size */
        color: #545454;
        transition: color 0.3s ease;
        /* Smooth color change on hover */
    }

    .user-icon:hover {
        color: #fff;
        /* Darken color on hover */
    }


    .dropdown {
        position: relative;
        display: inline-block;
        margin-bottom: 10px;
        z-index: 1;
    }

    .dropdown-btn {
        padding: 5px 20px;
        background-color: #e5e5e5;
        color: #545454;
        border: 3px solid #545454;
        border-radius: 22px;
        cursor: pointer;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.3s ease;
    }

    .dropdown-btn::after {
        content: '';
        width: 0;
        height: 0;
        border-top: 5px solid transparent;
        border-bottom: 5px solid transparent;
        border-left: 5px solid #545454;
        margin-left: 10px;
        transition: background-color 0.3s ease;
        transform: rotate(270deg);
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background-color: white;
        min-width: 180px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        z-index: 1;
        margin-top: 0px;
        border-radius: 4px;
    }

    .dropdown:hover .dropdown-content {
        display: block;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .dropdown-content a {
        padding: 0;
        text-decoration: none;
        display: block;
        color: #545454;
    }

    .dropdown-content a:hover {
        background-color: #f1f1f1;
    }

    /* Rotate the arrow when hovering over the dropdown button */
    .dropdown:hover .dropdown-btn::after {
        transform: rotate(90deg);
        /* Rotates the arrow */
    }

    /* Hover effect on the button (optional, for visual feedback) */
    .dropdown-btn:hover {
        background-color: #ccc
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 999;
    }

    .modal-content2 {
        background-color: #fefefe;
        padding: 30px;
        color: #545454;
        border-radius: 10px;
        border: 1px solid #fefefe;
        width: 200px;
        max-width: 100%;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        animation: fadeIn 0.3s ease-out;
        margin-bottom: 0px;
        position: absolute;
        top: 17%;
        right: 0;
        transform: translate(-50%, -50%);
        padding-top: 20px;

    }

    /* Adding the arrow */
    .modal-content2::after {
        content: "";
        position: absolute;
        top: 5px;
        /* Position the arrow vertically */
        right: -10px;
        /* Place the arrow to the right side of the modal */
        width: 0;
        height: 0;
        border-top: 10px solid transparent;
        /* Transparent top edge */
        border-bottom: 10px solid transparent;
        /* Transparent bottom edge */
        border-left: 10px solid #fff;
        /* The arrow color matches the modal background */
        z-index: 1000;
        /* Ensures it appears above other elements */
    }

    /* Style for the close button */
    .close-btn {
        position: absolute;
        top: 0px;
        /* Adjust based on your design */
        right: 10px;
        /* Adjust based on your design */
        background: transparent;
        border: none;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        color: #333;
        /* Change color to match your theme */
    }

    .modal-title2 {
        display: inline;
        text-align: center;
    }

    .modal-title2 h3 {
        margin-bottom: 2px;
        font-size: 17px;
    }

    .modal-title2 p {
        margin-bottom: 2px;
        font-size: 14px;
    }

    .butclass {
        display: flex;
        /* Enables flexbox */
        flex-direction: column;
        /* Align items vertically */
        align-items: center;
        /* Center items horizontally */
        gap: 10px;
        /* Adds spacing between the buttons */
        margin-top: 20px;
        /* Optional: add some spacing above the buttons */
    }

    .btn-ok2 {
        padding: 5px 20px;
        color: #545454;
        border: none;
        border-radius: 0px;
        cursor: pointer;
        margin-bottom: 10px;
        text-align: center;
        border: 2px solid #545454;

        /* Allow the button to resize based on content */
        width: 120px;
        /* Optional: Ensure buttons have consistent size */
    }

    .btn-ok2:hover {
        background-color: #ccc;
    }


    .close-btn:hover {
        color: #f00;
        /* Optional: Add hover effect */
    }

    .modal-content3 {
        background-color: #fefefe;
        padding: 30px;
        color: #545454;
        border-radius: 10px;
        border: 1px solid #fefefe;
        width: 240px;
        max-width: 100%;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        animation: fadeIn 0.3s ease-out;
        margin-bottom: 0px;
        position: absolute;
        top: 30%;
        right: 0;
        transform: translate(-50%, -50%);
        padding-top: 20px;

    }

    /* Adding the arrow */


    /* Style for the close button */
    .close-btn {
        position: absolute;
        top: 0px;
        /* Adjust based on your design */
        right: 10px;
        /* Adjust based on your design */
        background: transparent;
        border: none;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        color: #333;
        /* Change color to match your theme */
    }

    .modal-title2 {
        display: inline;
        text-align: center;
    }

    .modal-title3 h2 {
        margin-bottom: 2px;

    }

    .modal-title3 hr {
        margin-bottom: 12px;

    }

    .transparent-btn {
        background: transparent;
        /* Makes the background transparent */
        text-align: start;
        /* Optional: Add a border for visibility */
        color: #333;
        /* Text color */

        /* Adjust padding */
        border: none;
        /* Rounded edges */
        font-size: 14px;
        /* Adjust font size */
        cursor: pointer;
        /* Changes cursor to pointer on hover */
        transition: all 0.3s ease;
        margin-bottom: 10px;
        /* Smooth hover effect */
    }

    .transparent-btn:hover {
        background: rgba(0, 0, 0, 0.1);
        /* Slightly darkens background on hover */

        /* Darkens border on hover */
        color: #000;
        /* Darkens text color on hover */
    }



    .modal-title2 p {
        margin-bottom: 2px;
        font-size: 14px;
    }

    .butclass1 {
        display: flex;
        /* Enables flexbox */
        flex-direction: column;
        /* Align items vertically */
        align-items: center;
        /* Center items horizontally */
        gap: 10px;
        /* Adds spacing between the buttons */
        margin-top: 20px;
        /* Optional: add some spacing above the buttons */
    }

    .btn-ok2 {
        padding: 5px 20px;
        color: #545454;
        border: none;
        border-radius: 0px;
        cursor: pointer;
        margin-bottom: 10px;
        text-align: center;
        border: 2px solid #545454;

        /* Allow the button to resize based on content */
        width: 120px;
        /* Optional: Ensure buttons have consistent size */
    }

    .btn-ok3:hover {
        background-color: #ccc;
    }


    .close-btn:hover {
        color: #f00;
        /* Optional: Add hover effect */
    }

    .boxFP {
            height: 240px;
            width: 1278px;
            border: 1px solid #fff;
            background-image: url(Copy\ of\ Lost\ and\ Found.png);
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            font-family: 'Canva Sans', sans-serif;
            font-weight: bold;
            font-size: 50px;
            color: #fff;
            text-align: center;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.3);

            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .orangeBox {
            margin-top: 30px;
            margin-bottom: 30px;
            height: 150px;
            width: 1580px;
            background-color: #f8d0b4;
        }

        .orangeBtxt {
            font-family: 'Work Sans', sans-serif;
            font-size: 18px;
            text-align: justify;
            font-weight: normal;
            color: #726e6e;
            padding-top: 35px;
            margin-left: 60px;
            margin-right: 60px;
        }

        /*PARENT1*/
        .container2 {
            display: flex;
            align-items: center; /* Align text and link vertically */
            gap: 10px; /* Space between heading and link */
            margin-top: 30px;
            margin-left: 80px;
            height: 60px;
            width: 1000px;
            background-color: #fff;
            box-shadow: 5px 5px 15px rgba(0.1, 0.1, 0.1, 0.1);
        }   

        .claimh3 {
            padding-top: 5px;
            font-family: 'Canva Sans', sans-serif;
            font-weight: normal;
            font-size: 25px;
            color: #726e6e;
            padding-left: 20px;
        }

        .claimDownloadLink {
            font-family: 'Canva Sans', sans-serif;
            font-style: italic;
            font-size: 16px;
            color: #007bff;
            text-decoration: none;
            padding-top: 7px;
            transition: background 0.3s ease;
        }

        .claimDownloadLink:hover {
            color: orange;
        }


        .container3 {
            display: flex;
            align-items: center; /* Align text and link vertically */
            gap: 10px; /* Space between heading and link */
            margin-top: 30px;
            margin-left: 80px;
            height: 60px;
            width: 1000px;
            background-color: #fff;
            box-shadow: 5px 5px 15px rgba(0.1, 0.1, 0.1, 0.1);
        }   

        .losth3 {
            padding-top: 5px;
            font-family: 'Canva Sans', sans-serif;
            font-weight: normal;
            font-size: 25px;
            color: #726e6e;
            padding-left: 20px;
        }

        .reportedLostItemDownloadLink {
            font-family: 'Canva Sans', sans-serif;
            font-style: italic;
            font-size: 16px;
            color: #007bff;
            text-decoration: none;
            padding-top: 7px;
            transition: background 0.3s ease;
        }

        .reportedLostItemDownloadLink:hover {
            color: orange;
        }

        .container4{
            display: flex;
            align-items: center; /* Align text and link vertically */
            gap: 10px; /* Space between heading and link */
            margin-top: 30px;
            margin-left: 80px;
            height: 60px;
            width: 1000px;
            background-color: #fff;
            box-shadow: 5px 5px 15px rgba(0.1, 0.1, 0.1, 0.1);
        }   

        .foundh3 {
            padding-top: 5px;
            font-family: 'Canva Sans', sans-serif;
            font-weight: normal;
            font-size: 25px;
            color: #726e6e;
            padding-left: 20px;
        }

        .reportedFoundItemDownloadLink {
            font-family: 'Canva Sans', sans-serif;
            font-style: italic;
            font-size: 16px;
            color: #007bff;
            text-decoration: none;
            padding-top: 7px;
            transition: background 0.3s ease;
        }

        .reportedFoundItemDownloadLink:hover {
            color: orange;
        }

    /* Footer */ 
    .footer {
        background-color: #fff;
        padding: 20px 0;
        color: #545454;
        font-family: 'Hanken Grotesk', sans-serif;
        width: 100%;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        position: relative;
        text-align: center;
        margin-top: 100px;
    }

    .footer-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        /* Space out logo and contact text */
        width: 90%;
        margin: 0 auto;
        padding-bottom: 20px;
    }

    .footer-logo {
        align-self: flex-start;
        margin-top: 10px;
    }

    .footer-logo img {
        max-width: 70px;
    }

    .footer-contact {
        text-align: right;
        /* Align text to the right */
        font-size: 14px;
        margin-left: auto;
        width: 20%;
        margin-bottom: 25px;
    }

    .footer-contact h4 {
        font-size: 18px;
        margin-bottom: 10px;
    }

    .footer-contact p {
        font-size: 14px;
        margin-top: 0;

    }

    .all-links {
        display: flex;

        width: 100%;
        margin-top: 20px;
        position: absolute;

        justify-content: center;
    }

    .footer-others {
        display: flex;
        justify-content: center;
        /* Align links in the center */
        gap: 30px;
        top: 190px;
        left: 30%;
        margin-left: 140px;
        margin-top: 20px;
        transform: translateX(-50%);
    }


    .footer-others a {
        color: #545454;
        text-decoration: none;
        font-size: 14px;
    }

    .footer-separator {
        width: 90%;
        height: 1px;
        background-color: #545454;
        margin: 10px auto;
        border: none;
        position: absolute;
        bottom: 40px;
        left: 50%;
        margin-top: 20px;
        transform: translateX(-50%);
    }

    .footer-text {
        font-size: 14px;
        margin-top: 20px;
        color: #545454;
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);

    }







    @media (max-width: 768px) {

        th,
        td {
            padding: 8px;
            font-size: 14px;
        }

        th:nth-child(1),
        td:nth-child(1),
        th:nth-child(3),
        td:nth-child(3),
        th:nth-child(5),
        td:nth-child(5) {
            display: none;
        }

        .form-container {
            flex-direction: column;
            align-items: flex-start;
        }

        .search-form {
            margin-right: 14px;
            margin-bottom: 10px;
            width: 50%;
        }

        .approve-delete-form {
            justify-content: flex-start;
            width: 50%;
        }
    }

    @media (max-width: 480px) {

        th,
        td {
            padding: 5px;
            font-size: 12px;
        }

        .form-container {
            align-items: flex-start;
        }

        .search-form input[type="text"] {
            margin-right: 5px;
        }

        .search-form select {
            margin-right: 5px;
        }

        .approve-delete-form button {
            margin-right: 3px;
        }
    }

    .alert {
        padding: 10px;
        color: #4CAF50;
        background-color: #e8f5e9;
        margin-bottom: 20px;
        border-radius: 5px;
        text-align: center;
    }
    
    .LAFh1 {
        font-family: "League Spartan", sans-serif;
        font-optical-sizing: auto;
        font-weight: bold;
    }
      
    
    </style>

        <body>
            <main>
                    <div class="navbar">
                        <img src="images/logo.png" alt="Logo" class="navbar-logo">
                        <h1 class="main-title">
                            UNIVERSITY OF CALOOCAN CITY
                            <span class="subtitle">  LOST AND FOUND ADMIN</span>
                        </h1>

                        <!-- Claim Reports Dropdown -->
                        <div class="dropdown">
                            <button class="dropbtn">Claim Requests</button>
                            <div class="dropdown-content">
                                <a href="pending_claim.php">Pending Claims</a>
                                <a href="approved_claim_report.php">Approved Claims</a>
                            </div>
                        </div>

                        <!-- Lost Reports Dropdown -->
                        <div class="dropdown">
                            <button class="dropbtn">Lost Reports</button>
                            <div class="dropdown-content">
                                <a href="pending_lost_report.php">Pending Lost</a>
                                <a href="approved_lost_report.php">Approved Lost</a>
                            </div>
                        </div>

                        <!-- Found Reports Dropdown -->
                        <div class="dropdown">
                            <button class="dropbtn">Found Reports</button>
                            <div class="dropdown-content">
                                <a href="pending_found_report.php">Pending Found</a>
                                <a href="approved_found_report.php">Approved Found</a>
                            </div>
                        </div>

                        <!-- guidelines Link -->
                        <a href="guidelinesAdmin.php">Guidelines</a> 

                        <!-- Notification Icon Button -->
                        <button class="notif-btn" onclick="showModal('notif')">
                            <ion-icon name="notifications"></ion-icon>
                            <span class="notif-badge">
                                <?= htmlspecialchars($total_notifications) ?>
                            </span>
                        </button>



                        <!-- Side Navigation Toggle -->
                        <button class="hamburger-icon" onclick="toggleSideNav()">
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>
                    </div>

    


                    <div class="nav-content">
                        <div class="nav-content-cont">
                            <div class="nav-title">
                                <h1 class="LAFh1">GUIDELINES AND PROCEDURES</h1>
                            </div>
                        </div>
                    </div>    



                <div class="orangeBox">
                <p class="orangeBtxt">
                    <b>Mission and Vision Statement:</b> Our mission is to provide a reliable, transparent, and efficient Lost and Found service, maintaining the highest standards of integrity and professionalism. 
                    We are committed to safeguarding the belongings of our community and ensuring a fair and responsible process for all lost or found items. 
                    Through our dedication to honesty, accountability, and exceptional service, we strive to build trust, enhance the experience for all individuals involved, and create a seamless, reliable system where lost property is handled with care and respect.
                </p>
                </div>

                <div class="container2">
                    <h3 class="claimh3">
                        <span style="color: #726e6e; font-weight: bold;">CLAIMING AN ITEM</span> Guidelines, Policy, and Procedures.
                    </h3>
                    <a href="uploads/GUIDE IN CLAIMING.pdf" download class="claimDownloadLink">View Here</a>
                </div>

                <div class="container3">
                    <h3 class="losth3">
                        <span style="color: #726e6e;font-weight: bold">REPORTED LOST ITEMS</span> Guidelines and Policy
                    </h3>
                    <a href="uploads/GUIDE IN CLAIMING.pdf" download class="reportedLostItemDownloadLink">View Here</a>
                </div>

                <div class="container4">
                    <h3 class="foundh3">
                        <span style="color: #726e6e; font-weight: bold">REPORTED FOUND ITEMS</span> Guidelines and Policy
                    </h3>
                    <a href="uploads/GUIDE IN CLAIMING.pdf" download class="reportedFoundItemDownloadLink">View Here</a>
                </div>

                <div id="notif" class="modal-overlay" style="display: none;">
                    <div class="modal-content3">
                        <!-- Close Button -->
                        <button class="close-btn" onclick="closeModal('notif')">&times;</button>

                        <div class="modal-title3">
                            <h2>Notification</h2>
                            <hr>
                        </div>

                        <div class="reports-container">
                            <!-- Pending Claim Reports -->
                            <div class="reports-btn">
                                <a href="pending_claim.php" class="">
                                    <button class="transparent-btn">
                                        <div class="claim-reports-container">
                                            <?php if (!empty($pendingClaimReports)): ?>
                                            <?php foreach ($pendingClaimReports as $row): ?>
                                            <div class="claim-card">
                                                <div class="claim-card-header"></div>
                                                <div class="claim-card-body">
                                                    <p><span
                                                            style="font-weight: bold !important;"><?= htmlspecialchars($row['reporter_name']) ?></span>
                                                        submitted a request claim.</p>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <p>No pending claim reports.</p>
                                            <?php endif; ?>
                                        </div>
                                    </button>
                                </a>
                            </div>

                            <!-- Pending Found Reports -->
                            <div class="reports-btn">
                                <a href="pending_found_report.php" class="">
                                    <button class="transparent-btn">
                                        <div class="found-reports-container">
                                            <?php if (!empty($pendingFoundReports)): ?>
                                            <?php foreach ($pendingFoundReports as $row): ?>
                                            <div class="found-card">
                                                <div class="found-card-header"></div>
                                                <div class="found-card-body">
                                                    <p><span
                                                            style="font-weight: bold !important;"><?= htmlspecialchars($row['reporter_name']) ?></span>
                                                        submitted a found report.</p>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <p>No pending found reports.</p>
                                            <?php endif; ?>
                                        </div>
                                    </button>
                                </a>
                            </div>

                            <!-- Pending Lost Reports -->
                            <div class="reports-btn">
                                <a href="pending_lost_report.php?_id=<?= htmlspecialchars($row["id"]) ?>" class="">
                                    <button class="transparent-btn">
                                        <div class="lost-reports-container">
                                            <?php if (!empty($pendingLostReports)): ?>
                                            <?php foreach ($pendingLostReports as $row): ?>
                                            <div class="lost-card">
                                                <div class="lost-card-header"></div>
                                                <div class="lost-card-body">
                                                    <p><strong><?= htmlspecialchars($row['reporter_name']) ?></strong> submitted
                                                        a lost report.</p>

                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <p>No pending lost reports.</p>
                                            <?php endif; ?>
                                        </div>
                                    </button>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>


            <footer class="footer">
                <div class="footer-content">
                    <div class="footer-logo">
                        <img src="images/logo.png" alt="Logo" />
                        <img src="images/caloocan.png" alt="Logo" />

                    </div>
                    <div class="all-links">
                        <nav class="footer-others">
                            <a href="">ABOUT US</a>
                            <a href="">TERMS</a>
                            <a href="">FAQ</a>
                            <a href="">PRIVACY</a>
                        </nav>
                    </div>


                    <div class="footer-contact">
                        <h4>Contact us</h4>
                        <p>This website is currently under construction. For futher inquires, please contact us at
                            universityofcaloocan@gmailcom</p>
                    </div>
                        <hr class="footer-separator">
                        <p class="footer-text">&copy; University of Caloocan City, All rights reserved.</p>
                    </div>
                </footer>

        </main>
    </body>
</head>

</html>