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

// Function to create thumbnail
function createThumbnail($source, $destination, $width, $height) {
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
    
    // Preserve transparency for PNG
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

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    $hostel_id = (int)$_POST['hostel_id'];
    
    if (isset($_FILES['hostel_image']) && $_FILES['hostel_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/hostels/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['hostel_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $file_name = 'hostel_' . $hostel_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            // Check file size (max 5MB)
            if ($_FILES['hostel_image']['size'] > 5 * 1024 * 1024) {
                $message = 'File is too large. Maximum size is 5MB.';
                $messageType = 'danger';
            } elseif (move_uploaded_file($_FILES['hostel_image']['tmp_name'], $file_path)) {
                // Create thumbnail
                $thumbnail_path = $upload_dir . 'thumb_' . $file_name;
                createThumbnail($file_path, $thumbnail_path, 300, 200);
                
                // Update database
                $stmt = $db->prepare("UPDATE hostels SET image = ?, image_thumbnail = ? WHERE id = ?");
                $stmt->execute([$file_name, 'thumb_' . $file_name, $hostel_id]);
                
                $message = 'Image uploaded successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to upload image.';
                $messageType = 'danger';
            }
        } else {
            $message = 'Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP';
            $messageType = 'danger';
        }
    } else {
        $message = 'Please select a file to upload.';
        $messageType = 'danger';
    }
}

// Handle image removal
if (isset($_GET['remove_image'])) {
    $hostel_id = (int)$_GET['remove_image'];
    
    // Get current image names
    $stmt = $db->prepare("SELECT image, image_thumbnail FROM hostels WHERE id = ?");
    $stmt->execute([$hostel_id]);
    $hostel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($hostel) {
        // Delete files
        if ($hostel['image'] && file_exists('../uploads/hostels/' . $hostel['image'])) {
            unlink('../uploads/hostels/' . $hostel['image']);
        }
        if ($hostel['image_thumbnail'] && file_exists('../uploads/hostels/' . $hostel['image_thumbnail'])) {
            unlink('../uploads/hostels/' . $hostel['image_thumbnail']);
        }
        
        // Update database
        $stmt = $db->prepare("UPDATE hostels SET image = NULL, image_thumbnail = NULL WHERE id = ?");
        $stmt->execute([$hostel_id]);
        
        $message = 'Image removed successfully!';
        $messageType = 'success';
    }
}

// Get all hostels
$hostels = $db->query("SELECT * FROM hostels ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Hostel Images - Admin Panel</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 0 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .header h1 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
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
        
        .hostel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .hostel-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 20px;
        }
        
        .hostel-card h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .hostel-location {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .image-preview {
            margin: 15px 0;
            text-align: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-preview img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            max-height: 150px;
        }
        
        .no-image {
            background: #e9ecef;
            padding: 30px;
            text-align: center;
            border-radius: 8px;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Manage Hostel Images</h1>
            <p>Upload and manage images for all hostels</p>
        </div>
        
        <a href="dashboard.php" class="btn btn-secondary back-link">Back to Dashboard</a>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="hostel-grid">
            <?php foreach ($hostels as $hostel): ?>
                <div class="hostel-card">
                    <h3><?php echo htmlspecialchars($hostel['name']); ?></h3>
                    <div class="hostel-location">
                        Location: <?php echo htmlspecialchars($hostel['location']); ?>
                    </div>
                    
                    <div class="image-preview">
                        <?php if ($hostel['image'] && file_exists('../uploads/hostels/' . $hostel['image_thumbnail'])): ?>
                            <img src="../uploads/hostels/<?php echo $hostel['image_thumbnail']; ?>" 
                                 alt="<?php echo htmlspecialchars($hostel['name']); ?>">
                        <?php else: ?>
                            <div class="no-image">
                                No Image Uploaded
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($hostel['image']): ?>
                        <div class="button-group">
                            <a href="?remove_image=<?php echo $hostel['id']; ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Are you sure you want to remove this image?')">
                                Remove Image
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                        <input type="hidden" name="hostel_id" value="<?php echo $hostel['id']; ?>">
                        <div class="form-group">
                            <label>Upload New Image:</label>
                            <input type="file" name="hostel_image" accept="image/jpeg,image/png,image/gif,image/webp" required>
                            <small>Recommended size: 800x600 pixels. Maximum file size: 5MB. Supported formats: JPG, PNG, GIF, WEBP</small>
                        </div>
                        <button type="submit" name="upload_image" class="btn btn-primary">Upload Image</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>