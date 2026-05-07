<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Verify user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
$stmt = $db->query("
    SELECT 
        h.id, 
        h.name, 
        h.location, 
        h.description,
        COUNT(r.id) as total_rooms,
        SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) as available_rooms,
        MIN(r.price_per_semester) as min_price,
        AVG(r.capacity) as avg_capacity
    FROM hostels h 
    LEFT JOIN rooms r ON h.id = r.hostel_id 
    WHERE h.status = 'available' 
    GROUP BY h.id, h.name, h.location, h.description 
    ORDER BY h.name
");
    $hostels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($hostels);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
