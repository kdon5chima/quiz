<?php
// db_test.php - For diagnosing database connection issues

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Database Connection Test</h2>";

// Temporarily include the config file
require 'config.php'; 

if (isset($pdo) && $pdo instanceof PDO) {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: PDO object created.</p>";
    
    try {
        // Attempt a simple query to confirm connection is active
        $stmt = $pdo->query("SELECT 1+1 AS result");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && $row['result'] == 2) {
             echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: Connection is active and running queries. </p>";
             echo "<p>Test Result (1+1): " . htmlspecialchars($row['result']) . "</p>";
             echo "<p>If you see 'SUCCESS' twice, the problem is NOT the database connection or the code in config.php.</p>";
             echo "<p>You can now delete this file.</p>";
        } else {
             echo "<p style='color: orange;'>⚠️ WARNING: PDO object created, but a simple query failed to return expected results.</p>";
             echo "<p>Check the privileges of the database user.</p>";
        }
        
    } catch (PDOException $e) {
        // Catch exceptions from the query itself
        echo "<p style='color: red; font-weight: bold;'>❌ ERROR: Query failed.</p>";
        echo "<p>Details: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ ERROR: The \$pdo object was not created in config.php.</p>";
    echo "<p>Check syntax errors in config.php or ensure the database variables are defined.</p>";
}

?>