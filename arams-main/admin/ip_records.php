<?php
include "../config/db.php";

$result=mysqli_query($conn,"SELECT * FROM ip_records");
?>

<h2>Intellectual Property Records</h2>

<table>

<tr>
<th>Title</th>
<th>Type</th>
<th>Year</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['title'] ?></td>
<td><?php echo $row['type'] ?></td>
<td><?php echo $row['year'] ?></td>

</tr>

<?php } ?>

</table>