<?php
require 'vendor/autoload.php'; // Load MongoDB client via Composer

try {
    $client = new MongoDB\Client("mongodb://localhost:27017");
    echo "✅ MongoDB connection successful!";
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}
?>
