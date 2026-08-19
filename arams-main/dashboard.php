<?php
include "config/db.php";

$pub=mysqli_num_rows(mysqli_query($conn,"SELECT * FROM publications"));
$grant=mysqli_num_rows(mysqli_query($conn,"SELECT * FROM grants"));
?>

<h2>Dashboard</h2>

Total Publication: <?php echo $pub ?><br>
Total Grant: <?php echo $grant ?><br>

<br>

<a href="publication/add.php">Add Publication</a><br>
<a href="grant/add.php">Add Grant</a><br>
<a href="publication/view.php">View Publications</a><br>
<a href="grant/view.php">View Grants</a>