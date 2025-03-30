<?php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$database = "approve";

// Create database connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the report_id is set and is a valid integer
if (isset($_GET['report_id']) && filter_var($_GET['report_id'], FILTER_VALIDATE_INT)) {
    $report_id = $_GET['report_id']; // Use $report_id consistently

    // Query to fetch item details by report_id from the pending_lost_reports table
    $sql = "SELECT * FROM pending_claim_reports WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $report_id);
    if (!$stmt->execute()) {
        die("Execution failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    // Check if item exists
    if ($result->num_rows > 0) {
        $item = $result->fetch_assoc();
    } else {
        // Redirect or show error message and exit
        echo "Item not found.";
        exit;
    }

    // Close the statement
    $stmt->close();
} else {
    // Redirect if ID is invalid or not provided
    echo "Invalid ID or missing parameter.";
    exit;
}

// Handle Form Submission (Approve)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Approve action
    if (isset($_POST['approve_id'])) {
        $approve_id = $_POST['approve_id'];
        // Update status to 'Approved' for the report
        $sql = "UPDATE pending_claim_reports SET status = 'Approved' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $approve_id);
        if ($stmt->execute()) {
            echo "<script>alert('Report Approved');
           
            </script>";
        } else {
            echo "<script>alert('Failed to approve report');</script>";
        }
        $stmt->close();
    }

    // Reject action
    if (isset($_POST['delete_id'])) {
        $delete_id = $_POST['delete_id'];

        // Delete the report from the database
        $sql = "DELETE FROM pending_claim_reports WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $delete_id);

        if ($stmt->execute()) {
            echo "<script>
                    alert('Report Canceled');
                    window.location.href = 'usersoloview.php';
                  </script>";
        } else {
            echo "<script>alert('Failed to cancel report');</script>";
        }

        $stmt->close();
    }
}


//edit
// Check if report_id is provided and is a valid integer
if (isset($_GET['report_id']) && filter_var($_GET['report_id'], FILTER_VALIDATE_INT)) {
    $report_id = $_GET['report_id'];

    // Fetch the existing data for editing
    $sql = "SELECT * FROM pending_claim_reports WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // If the record exists, fetch the data
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        // If no record is found, show an error message
        $message = 'Item not found or invalid ID.';
    }
} else {
    $message = 'Invalid ID or missing parameter.';
}
// Update action
if (isset($_POST['item_name']) && isset($_POST['date_found'])) {
    $item_name = $_POST['item_name'];
    $date_found = $_POST['date_found'];
    $category = $_POST['category'];
    $time_found = $_POST['time_found'];
    $brand = $_POST['brand'] ?: null; // Handle optional fields
    $location_found = $_POST['location_found'];
    $primary_color = $_POST['primary_color'] ?: null;
    $description = $_POST['description'];


    // File upload handling
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
        // Sanitize file name
        $file_tmp = $_FILES['picture']['tmp_name'];
        $file_name = basename($_FILES['picture']['name']); // Get the base name
        $file_path = 'uploads/' . $file_name;

        // Move uploaded file
        if (move_uploaded_file($file_tmp, $file_path)) {
            // File uploaded successfully
        } else {
            echo "Failed to upload the file.";
            exit;
        }
    } else {
        // If no new picture, use the existing picture
        $file_path = $item['picture'];
    }

    // Update query
    $update_sql = "UPDATE pending_claim_reports SET 
                    item_name = ?, date_found = ?, category = ?, time_found = ?, 
                    brand = ?, location_found = ?, primary_color = ?, description = ?, 
                     picture = ? 
                    WHERE id = ?";

    $stmt = $conn->prepare($update_sql);
    // Correct the bind_param to match 13 placeholders
    $stmt->bind_param(
        "sssssssssi", // 13 parameters (1 for each field)
        $item_name,
        $date_found,
        $category,
        $time_found,
        $brand,
        $location_found,
        $primary_color,
        $description,

        $file_path,
        $report_id
    );

    if ($stmt->execute()) {
        echo "<script>alert('Report updated successfully.'); </script>";
    } else {
        echo "<script>alert('Failed to update report.');</script>";
    }
    $stmt->close();
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
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Get the current script name
$currentPage = basename($_SERVER['PHP_SELF']);

// Set the button label based on the current page
$buttonLabel = ($currentPage === 'found_report.php') ? 'Report Found' : (($currentPage === 'lost_report.php') ? 'Report
Lost' : 'Report');

$userName = htmlspecialchars($_SESSION['name'] ?? 'User');

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Details</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:ital,wght@0,100..900;1,100..900&family=Londrina+Outline&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Rubik+80s+Fade&family=Rubik+Burned&family=Source+Code+Pro:ital,wght@0,200..900;1,200..900&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="admin_report.css">

    <style>
    /* General styles */
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Hanken Grotesk', Arial, sans-serif;
    }

    body {
        background-color: #f4f4f9;
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        display: flex;
        color: #545454;
        flex-direction: column;
        min-height: 100vh;

        background-image: url('images/bgfinalna.png');
        background-size: cover;
        background-position: center center;
        background-attachment: fixed;
        background-repeat: no-repeat;
    }

    /* Navbar styles */
    .navbar {
        background-color: #fff;
        padding: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        color: #545454;
        position: sticky;
        top: 0;
        z-index: 10;
        width: 100%;
        display: flex;
        align-items: flex-start;
        flex-direction: column;

    }

    .nav-content {
        background-image: url('images/bgfinal.png');
        background-size: cover;
        background-position: center center;
        background-attachment: fixed;
        background-repeat: no-repeat;
        padding: 60px 0;


    }

    .nav-content-cont {
        display: flex;
        flex-direction: column;
        align-items: center;
        /* margin-left: 70px;*/
    }

    .nav-main {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 20px;

        /* Add some spacing between nav-main and nav-content */
    }

    .nav-btn {
        background-color: transparent;
        color: #545454;
        border: none;
        font-size: 16px;
        margin-top: 12px;
        margin-left: 30px;
        cursor: pointer;
        text-align: center;
        display: inline-block;
        transition: color 0.3s ease, text-decoration 0.3s ease;
    }

    /* Hover effect on button */
    .nav-btn:hover {

        text-decoration: underline;
    }

    .nav-main>.icon-btn {
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
        margin-left: 830px;
        /* Push the button to the far right */
        transition: background-color 0.3s ease, border-color 0.3s ease;
        /* Smooth hover effect */
        z-index: 99999;

    }

    .icon-btn {
        z-index: 99999;
    }




    .nav-main>.icon-btn:hover {
        background-color: #f4f4f9;
        /* Light background on hover */
        border-color: #000;
        /* Darker border on hover */
    }



    .nav-main>.icon-btn:hover .user-icon {
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

    .navbar a {
        color: #545454;
        padding: 3px;
        text-decoration: none;
        margin: 20px;
        display: inline-block;
    }

    .navbar a:hover {
        text-decoration: underline;
    }

    .navbar-logo {
        height: 90px;
        width: auto;
        /* Maintain aspect ratio */
        margin-right: 20px;
        margin-left: 10px;
        margin-top: 10px;
    }



    .nav-title h1 {
        font-size: 78px;
        color: #fff;
        font-weight: bold;
        line-height: 1.1;



    }

    .nav-text p {
        font-size: 16px;
        color: #fff;
        line-height: 1.4;
        margin-bottom: 20px;

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
        top: 23%;
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


    .container-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;

        margin-top: 0;
        margin-bottom: 0;
    }


    .container {
        max-width: 500px;
        max-height: 550px;
        width: 450px;
        margin: 5px;
        background-color: #fff;
        padding: 40px 40px;
        border-radius: 2px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        box-sizing: border-box;
        z-index: 0;
        align-items: center;
        margin-top: 40px;
        margin-bottom: 40px;
        align-self: self-start;



    }


    .container img {
        display: block;
        /* Ensures the image respects margin auto for centering */
        margin: 0 auto;
        /* Horizontally centers the image */
        max-width: 100%;
        width: 320px;
        /* Makes the image responsive */
        height: auto;
        /* Maintains aspect ratio */
        border: 1px solid #ccc;
        /* Optional: Adds a border to the image */
        padding: 5px;
        /* Optional: Adds padding inside the border */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        /* Optional: Adds shadow */
        margin-top: 20px;
    }

    label {
        font-size: 13px;
    }

    .container-title {
        display: flex;
        align-items: flex-end;
        justify-content: flex-start;
    }

    .container-title2 {
        display: flex;
        align-items: flex-end;
        justify-content: flex-start;
    }



    .container-title2 h2 {
        margin: 0;
        font-size: 22px;
        color: #333;
    }

    .container-title h2 {
        margin: 0;
        font-size: 22px;
        color: #333;
        line-height: 1.2;
    }

    .container-title p {
        margin: 0;
        font-size: 12px;
        color: #777;
        margin-left: 10px;
        line-height: 1.6;
        display: inline-block;
        vertical-align: middle;
    }

    .container-title2 p {
        margin: 0;
        font-size: 12px;
        color: #777;
        margin-left: 10px;

        display: inline-block;
        vertical-align: middle;
    }

    hr {
        margin-bottom: 20px;
        margin-top: 10px;
    }

    .alert {
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
        text-align: center;
        font-size: 12px;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
    }

    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
    }

    .form-group {
        margin-bottom: 15px;
        flex: 1;
    }

    .form-group p {
        font-size: 12px;
        color: #777;
        margin-top: 5px;
    }

    input[type="checkbox"] {
        width: 15px;
        height: 15px;
        vertical-align: middle;
        margin-right: 4px;
        appearance: none;
        border: 1px solid #545454;
        border-radius: 0;
        background-color: #fff;
        cursor: pointer;
        outline: none;
        display: inline-block;
        position: relative;
    }

    input[type="checkbox"]:checked {
        background-color: #fdd400;
        border-color: #545454;
    }

    input[type="checkbox"]:checked::before {
        content: "✓";
        position: absolute;
        top: 0;
        left: 2px;
        font-size: 11px;
        font-weight: bold;
        text-align: center;
        color: #333;
    }

    input[type="checkbox"]:hover {
        border-color: #333;
    }

    label.terms {
        font-size: 12px;
        display: flex;
        align-items: flex-end;
        gap: 5px;
        color: #777;
        flex-wrap: nowrap;
    }

    .terms-link {
        text-decoration: none;
        color: #333;
        font-style: italic;
    }

    .terms-link:hover {
        text-decoration: underline;
    }

    .align-container {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-top: 40px;
    }


    label {
        display: block;
        margin-bottom: 8px;
        font-weight: normal;
        color: #333;
    }

    input[type="text"],
    input[type="number"],
    input[type="date"],
    input[type="time"],
    textarea,
    select {
        width: 100%;
        padding: 6px;
        box-sizing: border-box;
        border: 1px solid #ccc;
        border-radius: 0px;
        font-size: 14px;
    }

    textarea {
        resize: vertical;
    }

    .form-row {
        display: flex;
        justify-content: space-between;
        gap: 4%;
    }

    .form-row .form-group {
        width: 48%;
    }

    .form-row-submit {
        display: flex;
        justify-content: space-between;
        gap: 4%;
        align-items: flex-end;
    }

    .form-row-submit .form-group {
        width: 48%;
    }

    .btn-container {
        display: flex;
        gap: 5px;
        margin-top: 0px;
        width: 100%;
        justify-content: flex-end;
    }

    h2.btn-action {
        margin-top: 20px !important;
        /* Adjust the value as needed */
    }


    .btn {

        border: none;
        border-radius: 2px;
        font-size: 12px;
        text-decoration: none;
        text-align: center;
        transition: background-color 0.3s, box-shadow 0.3s;
        color: #545454;
        font-weight: normal;
        padding: 5px 23px;
        border: 1px solid #545454;
        cursor: pointer;
    }

    .btn-info {
        background-color: #28a745;
        color: #fff;
    }

    .btn-success {
        background-color: #bfbdbc;
        border: 1px solid #545454;
        color: #545454;
        margin-left: 134px;



    }

    .btn-danger {
        background-color: #fab7b0;
        border: 1px solid #545454;
        color: #545454;



    }

    .btn:hover {

        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999999999999;

        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);

        justify-content: center;
        align-items: center;

        margin-top: 0;
        margin-bottom: 0;
    }


    .modal-content {
        max-width: 100%;
        width: 80%;
        margin: 20px;
        background-color: #ffffff;
        padding: 40px 40px;
        border-radius: 2px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        box-sizing: border-box;


        position: absolute;
        /* Position the modal absolutely */
        top: 50%;
        /* Position it vertically in the center */
        left: 49.3%;
        /* Position it horizontally in the center */
        transform: translate(-50%, -50%);


    }

    .modal-header {
        font-size: 1.5rem;
        font-weight: bold;
    }

    .modal-body {
        margin-top: 10px;
    }

    .close2 {
        color: #aaa;
        font-size: 2rem;
        font-weight: bold;
        position: absolute;
        /* Position it relative to the modal */
        top: 0px;
        /* 10px from the top */
        right: 10px;
        /* 10px from the right */
    }

    .close2:hover,
    .close2:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }


    .container-wrapper2 {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin-top: 0;
        margin-bottom: 0;
    }

    .container-title2 {
        display: flex;
        align-items: flex-end;
        justify-content: flex-start;
    }

    .container-title22 h2 {
        margin: 0;
        font-size: 24px;
        color: #333;
    }

    .container-title2 h2 {
        margin: 0;
        font-size: 24px;
        color: #333;
        line-height: 1.2;
    }

    .container-title2 p {
        margin: 0;
        font-size: 13px;
        color: #777;
        margin-left: 10px;
        line-height: 1.6;
        display: inline-block;
        vertical-align: middle;
    }

    hr {
        margin-bottom: 20px;
        margin-top: 10px;
    }

    .alert2 {
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
        text-align: center;
        font-size: 14px;
    }

    .alert-success2 {
        background-color: #d4edda;
        color: #155724;
    }

    .alert-danger2 {
        background-color: #f8d7da;
        color: #721c24;
    }

    .form-group2 {
        margin-bottom: 15px;
        flex: 1;
    }

    .form-group2 p {
        font-size: 13px;
        color: #777;
        margin-top: 5px;
    }

    input[type="checkbox"] {
        width: 15px;
        height: 15px;
        vertical-align: middle;
        margin-right: 4px;
        appearance: none;
        border: 1px solid #545454;
        border-radius: 0;
        background-color: #fff;
        cursor: pointer;
        outline: none;
        display: inline-block;
        position: relative;
    }

    input[type="checkbox"]:checked {
        background-color: #fdd400;
        border-color: #545454;
    }

    input[type="checkbox"]:checked::before {
        content: "✓";
        position: absolute;
        top: 0;
        left: 2px;
        font-size: 12px;
        font-weight: bold;
        text-align: center;
        color: #333;
    }

    input[type="checkbox"]:hover {
        border-color: #333;
    }

    label.terms2 {
        font-size: 14px;
        display: flex;
        align-items: flex-end;
        gap: 5px;
        color: #777;
        flex-wrap: nowrap;
    }

    .terms-link2 {
        text-decoration: none;
        color: #333;
        font-style: italic;
    }

    .terms-link2:hover {
        text-decoration: underline;
    }

    .align-container2 {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-top: 40px;
    }

    .btn2 {
        color: #fff;
        background-color: #545454;
        border: 2px solid #545454;
        border-radius: 4px;
        text-align: center;
        cursor: pointer;
        width: 100px;
        height: 40px;
        font-size: 14px;
        transition: background-color 0.3s ease;
        line-height: normal;
        display: inline-block;
    }

    label2 {
        display: block;
        margin-bottom: 8px;
        font-weight: normal;
        color: #333;
    }

    input[type="text2"],
    input[type="number2"],
    input[type="date2"],
    input[type="time2"],
    textarea2,
    select2 {
        width: 100%;
        padding: 6px;
        box-sizing: border-box;
        border: 1px solid #ccc;
        border-radius: 0px;
        font-size: 14px;
    }

    textarea2 {
        resize: vertical;
    }

    .form-row2 {
        display: flex;
        justify-content: space-between;
        gap: 4%;
    }

    .form-row2 .form-group2 {
        width: 48%;
    }

    .form-row-submit2 {
        display: flex;
        justify-content: space-between;
        gap: 4%;
        align-items: flex-end;
    }

    .form-row-submit2 .form-group2 {
        width: 48%;
    }

    .btn2 {
        display: block;
        align-items: center;
        color: #545454;
        background-color: #fdd400;
        border: 2px solid #545454;
        margin-left: auto;
        margin-right: 0;
        border-radius: 4px;
        text-align: center;
        cursor: pointer;
        width: 180px;
        height: 35px;
        font-size: 14px;
        transition: background-color 0.3s ease;
    }

    .btn2:hover {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
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
    }

    .footer-logo img {
        max-width: 150px;

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
    </style>

</head>

<body>
    <div class="navbar">
        <div class="nav-main">
            <img src="images/star.png" alt="Logo" class="navbar-logo">
            <a href="found_report.php">Home</a>
            <a href="guidelines.php">Guidelines</a>

            <div class="dropdown">
                <button class="nav-btn">Browse Reports</button>
                <div class="dropdown-content">
                    <a href="userview.php">Found Reports</a>
                    <a href="lost_reports.php">Lost Reports</a>
                </div>
            </div>

            <button class="icon-btn" onclick="openModal('loginclickmodal')">
                <ion-icon name="person" class="user-icon"></ion-icon>
            </button>

        </div>
    </div>

    <div id="loginclickmodal" class="modal-overlay" style="display: none;">
        <div class="modal-content2">
            <!-- Close Button -->
            <button class="close-btn" onclick="closeModal('loginclickmodal')">&times;</button>

            <div class="modal-title2">
                <h3>Good day, <strong><?= htmlspecialchars($userName) ?></strong>!</h3>
                <p><?= htmlspecialchars($_SESSION['user_id'] ?? '') ?></p>
                <hr>
            </div>
            <div class="butclass">
                <button class="btn-ok2" onclick="window.location.href='usersoloview.php'">
                    See report details (<?= htmlspecialchars($approvedReportCount); ?>)
                </button>
                <button class="btn-ok2" onclick="window.location.href='usersoloviewclaim.php'">See claim status</button>
                <button class="btn-ok2" onclick="window.location.href='?logout'">LOG OUT</button>
            </div>
        </div>
    </div>

    <div class="container-wrapper">
        <div class="container">
            <div class="container-title">
                <h2>Item Details</h2>
                <p>Here's the complete info. about the item</p>
            </div>
            <hr>
            <div class="form-row">
                <div class="form-group">
                    <label for="item_name">Object Title</label>
                    <input type="text" name="item_name" id="item_name"
                        value="<?= htmlspecialchars($item['item_name']) ?>" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="date_found">Date Loss</label>
                    <input type="date" name="date_found" id="date_found"
                        value="<?= htmlspecialchars($item['date_found']) ?>" class="form-control" readonly>
                </div>
            </div>
            <!-- Category | Time Found -->
            <div class="form-row">
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" type="category" name="category" id="category"
                        value="<?= htmlspecialchars($item['category']) ?>" class="form-control" readonly>
                    <!-- Add options dynamically or statically here -->
                </div>
                <div class="form-group">
                    <label for="time_found">Time Found</label>
                    <input type="time" name="time_found" id="time_found"
                        value="<?= htmlspecialchars($item['time_found']) ?>" class="form-control" readonly>
                </div>
            </div>
            <!-- Brand | Location Found -->
            <div class="form-row">
                <div class="form-group">
                    <label for="brand">Brand</label>
                    <input type="text" name="brand" id="brand" value="<?= htmlspecialchars($item['brand']) ?>"
                        class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="location_found">Location Found</label>
                    <input type="text" name="location_found" id="location_found"
                        value="<?= htmlspecialchars($item['location_found']) ?>" class="form-control" readonly>
                    <!-- Add options dynamically or statically here -->
                </div>
            </div>
            <!-- Primary Color | Image -->
            <div class="form-row">
                <div class="form-group">
                    <label for="primary_color">Primary Color</label>
                    <input type="text" name="primary_color" id="primary_color"
                        value="<?= htmlspecialchars($item['primary_color']) ?>" class="form-control" readonly>
                </div>
                <div class="form-group">
                </div>
            </div>
            <div class="form-row-submit">
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="4" class="form-control"
                        readonly><?= htmlspecialchars($item['description']) ?></textarea>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="container-title">
                <h2>Image</h2>
                <p>Here’s what the item looks like for better identification.</p>

            </div>
            <hr>
            <img src="<?= htmlspecialchars($item["picture"]) ?>" alt="Item Picture" class="item-image">
        </div>
        <div class="container">




            <div class="container-title">
                <h2 class="btn-action">Action</h2>
                <p>Edit or Cancel the report as needed.</p>
            </div>
            <hr>
            <div class="btn-container">
                <a href="usersoloviewclaim.php" class="btn btn-back">Back</a>
                <button id="openModalBtn" class="btn btn-success">Edit</button>
                <form action="" method="POST" style="display:inline;">
                    <input type="hidden" name="delete_id" value="<?= htmlspecialchars($row["id"]) ?>">
                    <button type="submit" class="btn btn-danger" aria-label="Delete Report">Cancel</button>
                </form>

            </div>
        </div>
    </div>





    <!-- Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="container2">
                <span id="closeModalBtn" class="close2">&times;</span>
                <div class="container-title2">
                    <h2>Editing a LOST item</h2>
                    <p>Please double-check all the information provided</p>
                </div>
                <hr>

                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Object Title | Date Found -->
                    <div class="form-row2">
                        <div class="form-group">
                            <label for="item_name2">Object Title</label>
                            <input type="text" name="item_name" id="item_name2" required
                                value="<?= htmlspecialchars($row['item_name'] ?? '') ?>" class="form-control2">
                            <p>eg. lost camera, gold ring, toyota car key</p>
                        </div>


                        <div class="form-group2">
                            <label for="date_found2">Date Loss</label>
                            <input type="date" name="date_found" id="date_found2" required
                                value="<?= htmlspecialchars($row['date_found'] ?? '') ?>" class="form-control2">
                        </div>
                    </div>

                    <!-- Category | Time Found -->
                    <div class="form-row2">
                        <div class="form-group2">
                            <label for="category2">Category</label>
                            <select name="category" id="category2" required>
                                <option value="Electronics & Gadgets"
                                    <?= $row['category'] == 'Electronics & Gadgets' ? 'selected' : '' ?>>Electronics &
                                    Gadgets</option>
                                <option value="Jewelry & Accessories"
                                    <?= $row['category'] == 'Jewelry & Accessories' ? 'selected' : '' ?>>Jewelry &
                                    Accessories</option>
                                <option value="Identification & Documents"
                                    <?= $row['category'] == 'Identification & Documents' ? 'selected' : '' ?>>
                                    Identification & Documents</option>
                                <option value="Clothing & Footwear"
                                    <?= $row['category'] == 'Clothing & Footwear' ? 'selected' : '' ?>>Clothing &
                                    Footwear</option>
                                <option value="Bag & Carriers"
                                    <?= $row['category'] == 'Bag & Carriers' ? 'selected' : '' ?>>Bag & Carriers
                                </option>
                                <option value="Wallet & Money"
                                    <?= $row['category'] == 'Wallet & Money' ? 'selected' : '' ?>>Wallet & Money
                                </option>

                            </select>
                        </div>

                        <div class="form-group2">
                            <label for="time_found2">Time Loss</label>
                            <input type="time" name="time_found" id="time_found2" required
                                value="<?= htmlspecialchars($row['time_found'] ?? '') ?>" class="form-control2">
                        </div>
                    </div>

                    <!-- Brand | Location Found -->
                    <div class="form-row2">
                        <div class="form-group2">
                            <label for="brand2">Brand</label>
                            <input type="text" name="brand" id="brand2"
                                value="<?= htmlspecialchars($row['brand'] ?? '') ?>">
                            <p> (Ralph Lauren, Samsung, KithenAid, etc.)</p>
                        </div>

                        <div class="form-group2">
                            <label for="location_found2">Location Found</label>
                            <select name="location_found" id="location_found2" required>
                                <option value="Main Entrance Hall"
                                    <?= $row['location_found'] == 'Main Entrance Hall' ? 'selected' : '' ?>>Main
                                    Entrance Hall</option>
                                <option value="Food Court"
                                    <?= $row['location_found'] == 'Food Court' ? 'selected' : '' ?>>Food Court</option>
                                <option value="Parking Area A"
                                    <?= $row['location_found'] == 'Parking Area A' ? 'selected' : '' ?>>Parking Area A
                                </option>
                                <option value="Restroom - East Wing"
                                    <?= $row['location_found'] == 'Restroom - East Wing' ? 'selected' : '' ?>>Restroom -
                                    East Wing</option>
                                <option value="Info Center"
                                    <?= $row['location_found'] == 'Info Center' ? 'selected' : '' ?>>Info Center
                                </option>
                                <option value="Gift Shop"
                                    <?= $row['location_found'] == 'Gift Shop' ? 'selected' : '' ?>>Gift Shop</option>
                                <option value="Amusement Arcade"
                                    <?= $row['location_found'] == 'Amusement Arcade' ? 'selected' : '' ?>>Amusement
                                    Arcade</option>


                            </select>
                        </div>
                    </div>

                    <!-- Primary Color | Image -->
                    <div class="form-row2">
                        <div class="form-group2">
                            <label for="primary_color2">Primary Color</label>
                            <input type="text" name="primary_color" id="primary_color2"
                                value="<?= htmlspecialchars($row['primary_color'] ?? '') ?>">

                            <p>Please add the color that best represents the lost property(Black, Red, Blue, etc.)
                            </p>
                        </div>

                        <div class="form-group2">
                            <label for="picture2">Image</label>
                            <input type="file" name="picture" id="picture2" class="form-control2">
                        </div>
                    </div>

                    <div class="form-row-submit2">
                        <div class="form-group2">
                            <label for="description2">Description</label>
                            <textarea name="description" id="description2"
                                rows="4"><?= htmlspecialchars($row['description'] ?? '') ?></textarea>

                            <input type="submit" class="btn2" value="Update">

                        </div>

                    </div>
                    <!-- Contact Information -->



                </form>
            </div>
        </div>
    </div>


    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">
                <img src="images/star.png" alt="Logo" />
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
                    starcity@gmailcom</p>
            </div>
            <hr class="footer-separator">
            <p class="footer-text">&copy; Star City, All rights reserved.</p>
        </div>
    </footer>
    <script>
    // Get modal and buttons
    var modal = document.getElementById("editModal");
    var openModalBtn = document.getElementById("openModalBtn");
    var closeModalBtn = document.getElementById("closeModalBtn");

    // Open the modal when the button is clicked
    openModalBtn.onclick = function() {
        modal.style.display = "block";
    }

    // Close the modal when the close button is clicked
    closeModalBtn.onclick = function() {
        modal.style.display = "none";
    }

    // Close the modal if the user clicks outside the modal content
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }


    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'edit') {
            const editButton = document.getElementById('openModalBtn');
            if (editButton) {
                editButton.click(); // Trigger the edit button programmatically
            }
        }
    };
    </script>
    <script>
    // Function to close the modal by ID
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Function to open the modal by ID
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    // Show success modal if report submission is successful
    <?php if (isset($_GET['success']) && $_GET['success'] == 'true') { ?>
    document.addEventListener('DOMContentLoaded', function() {
        openModal('successModal');

        // Remove 'success' query parameter from URL to prevent modal from showing again on refresh
        const url = new URL(window.location.href);
        url.searchParams.delete('success');
        window.history.replaceState({}, document.title, url.toString());
    });
    <?php } ?>

    // Show greeting modal only if logged in and no report was submitted
    <?php if (isset($_SESSION['user_id']) && !isset($_GET['success']) && !isset($_SESSION['greeting_shown'])) { ?>
    document.addEventListener('DOMContentLoaded', function() {
        openModal('greetingModal');
    });
    <?php } ?>

    // Function to close the modal by ID
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Function to open the modal by ID
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    </script>

</body>

</html>