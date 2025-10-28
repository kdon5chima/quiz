<?php
// logout.php

// 1. Start the session
// You must start the session before you can manipulate session variables.
session_start();

// 2. Unset all of the session variables
// This clears the data stored for the current user (e.g., user_id, user_type).
$_SESSION = array();

// 3. Destroy the session
// This removes the session file/data from the server storage.
session_destroy();

// 4. Redirect to the landing page
header("location: index.php");
exit;
?>