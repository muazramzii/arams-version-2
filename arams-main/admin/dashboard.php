<?php
include "../config/db.php";

$lecturers=mysqli_num_rows(mysqli_query($conn,"SELECT * FROM lecturers"));
$projects=mysqli_num_rows(mysqli_query($conn,"SELECT * FROM projects"));
$publications=mysqli_num_rows(mysqli_query($conn,"SELECT * FROM publications"));
$grants=mysqli_num_rows(mysqli_query($conn,"SELECT * FROM grants"));
?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php include "../includes/sidebar_admin.php"; ?>

<div class="main">

<?php include "../includes/header.php"; ?>

<h2>Admin Dashboard</h2>

<div class="card">Total Lecturers: <?php echo $lecturers ?></div>
<div class="card">Research Projects: <?php echo $projects ?></div>
<div class="card">Publications: <?php echo $publications ?></div>
<div class="card">Grants: <?php echo $grants ?></div>

</div>