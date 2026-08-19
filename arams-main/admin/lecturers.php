<?php
include "../config/db.php";

$result=mysqli_query($conn,"SELECT * FROM lecturers");
?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php include "../includes/sidebar_admin.php"; ?>

<div class="main">

<h2>Lecturer Management</h2>

<a href="add_lecturer.php">Add Lecturer</a>

<table>

<tr>
<th>Name</th>
<th>Faculty</th>
<th>Research Area</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>
<td><?php echo $row['name'] ?></td>
<td><?php echo $row['faculty'] ?></td>
<td><?php echo $row['research_area'] ?></td>
</tr>

<?php } ?>

</table>

</div>