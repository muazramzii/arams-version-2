<?php
include "../config/db.php";

$result=mysqli_query($conn,"SELECT * FROM students");
?>

<h2>Student Supervision</h2>

<table>

<tr>
<th>Name</th>
<th>Level</th>
<th>Research Title</th>
<th>Status</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['name'] ?></td>
<td><?php echo $row['level'] ?></td>
<td><?php echo $row['research_title'] ?></td>
<td><?php echo $row['status'] ?></td>

</tr>

<?php } ?>

</table>