<?php
$host = '127.0.0.1';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=flatplan_db", $user, $pass);
    $stmt = $pdo->query("SHOW ENGINE INNODB STATUS");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $status = $result['Status'];
    
    // Extract LATEST FOREIGN KEY ERROR
    $start = strpos($status, "LATEST FOREIGN KEY ERROR");
    if ($start !== false) {
        echo substr($status, $start, 1500);
    } else {
        echo "No FK error found.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
