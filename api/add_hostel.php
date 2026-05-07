<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$db = Database::getInstance()->getConnection();
$message = '';
$messageType = '';

// Function to create thumbnail (same as above)
function createThumbnail($source, $destination, $width, $height) {
    // Copy the same function from manage_hostel_images.php
    list($orig_width, $orig_height, $type) = getimagesize($source);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src_img = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $src_img = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $src_img = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            $src_img = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    $aspect_ratio = $orig_width / $orig_height;
    if ($width / $height > $aspect_ratio) {
        $new_width = $height * $aspect_ratio;
        $new_height = $height;
    } else {
        $new_width = $width;
        $new_height = $width / $aspect_ratio;
    }
    
    $thumb_img = imagecreatetruecolor($new_width, $new_height);
    
    if ($type == IMAGETYPE_PNG) {
        imagealphablending($thumb_img, false);
        imagesavealpha($thumb_img, true);
        $transparent = imagecolorallocatealpha($thumb_img, 255, 255, 255, 127);
        imagefilledrectangle($thumb_img, 0, 0, $new_width, $new_height, $transparent);
    }
    
    imagecopyresampled($thumb_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumb_img, $destination, 80);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumb_img, $destination, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumb_img, $destination);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($thumb_img, $destination, 80);
            break;
    }
    
    imagedestroy($src_img);
    imagedestroy($thumb_img);
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    $image = null;
    $image_thumbnail = null;
    
    // Handle image upload
    if (isset($_FILES['hostel_image']) && $_FILES['hostel_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/hostels/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['hostel_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            if ($_FILES['hostel_image']['size'] <= 5 * 1024 * 1024) {
                $file_name = 'hostel_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['hostel_image']['tmp_name'], $file_path)) {
                    $thumbnail_path = $upload_dir . 'thumb_' . $file_name;
                    createThumbnail($file_path, $thumbnail_path, 300, 200);
                    $image = $file_name;
                    $image_thumbnail = 'thumb_' . $file_name;
                }
            } else {
                $message = 'File is too large. Maximum size is 5MB.';
                $messageType = 'danger';
            }
        } else {
            $message = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP';
            $messageType = 'danger';
        }
    }
    
    if (empty($message)) {
        $stmt = $db->prepare("INSERT INTO hostels (name, location, description, status, image, image_thumbnail) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $location, $description, $status, $image, $image_thumbnail])) {
            $message = 'Hostel added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to add hostel.';
            $messageType = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Hostel</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <h2>Add New Hostel</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Hostel Name *</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="form-group">
                    <label>Location *</label>
                    <input type="text" name="location" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Hostel Image</label>
                    <input type="file" name="hostel_image" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small>Recommended size: 800x600 pixels. Max 5MB. Supported: JPG, PNG, GIF, WEBP</small>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Add Hostel</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>