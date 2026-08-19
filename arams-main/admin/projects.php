<?php
include "../config/db.php";

$result=mysqli_query($conn,"SELECT * FROM projects");
?>

<h2>Research Projects</h2>

<table>

<tr>
<th>Title</th>
<th>Grant Type</th>
<th>Funding</th>
<th>Status</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['title'] ?></td>
<td><?php echo $row['grant_type'] ?></td>
<td><?php echo $row['funding_amount'] ?></td>
<td><?php echo $row['status'] ?></td>

</tr>

<?php } ?>

</table>