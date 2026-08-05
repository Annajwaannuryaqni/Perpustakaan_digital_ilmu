<?php
function createNotification($conn, $user_id, $title, $message, $type, $icon = 'fa-bell', $color = '#00f2fe') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, icon, color, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param("isssss", $user_id, $title, $message, $type, $icon, $color);
    $stmt->execute();
}
?>