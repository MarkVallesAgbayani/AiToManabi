<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

// Accept image upload
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/content_images/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $file_extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $new_filename;
    
    // Validate file type
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_extension, $allowed_types)) {
        die(json_encode(['error' => 'Invalid file type']));
    }
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
        // Return the location of the uploaded file
        echo json_encode([
            'location' => $file_path
        ]);
    } else {
        echo json_encode(['error' => 'Failed to upload file']);
    }
} else {
    echo json_encode(['error' => 'No file uploaded']);
}
?> 