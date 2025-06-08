<?php
ini_set('display_errors', 0); // do not show PHP errors in output
ini_set('log_errors', 1);     // log errors to webserver log
error_reporting(E_ALL);

require 'vendor/autoload.php';
session_start();
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

try {
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $db = $client->pollsystem;
    $users = $db->users;

    if ($action === 'register') {
        if (!$username || !$password) {
            echo json_encode(['success' => false, 'message' => 'Username and password required']);
            exit;
        }
        $existing = $users->findOne(['username' => $username]);
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $insert = $users->insertOne([
            'username' => $username,
            'password' => $hashed
        ]);
        if ($insert->getInsertedCount() === 1) {
            echo json_encode(['success' => true, 'message' => 'Registration successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed']);
        }
    } elseif ($action === 'login') {
        $user = $users->findOne(['username' => $username]);
        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            exit;
        }
        $_SESSION['user_id'] = (string)$user->_id;
        echo json_encode(['success' => true, 'message' => 'Login successful', 'user_id' => (string)$user->_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
} catch (Throwable $e) {
    // Always return JSON on error
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>