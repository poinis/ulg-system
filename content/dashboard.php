<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Dashboard</title>
  <meta charset="utf-8">
</head>
<body>
    <h2>ยินดีต้อนรับ <?php echo htmlspecialchars($_SESSION["username"]); ?></h2>
    <p><a href="logout.php">Logout</a></p>
    <!-- ตรงนี้เพิ่มเนื้อหา dashboard ได้เลย -->
</body>
</html>
