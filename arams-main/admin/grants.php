<?php
include "../config/db.php";

$result=mysqli_query($conn,"SELECT * FROM grants");
?>

<h2>Grants Management</h2>

<table>

<tr>
<th>Grant Name</th>
<th>Type</th>
<th>Amount</th>
<th>Year</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['grant_name'] ?></td>
<td><?php echo $row['grant_type'] ?></td>
<td><?php echo $row['amount'] ?></td>
<td><?php echo $row['year'] ?></td>

</tr>

<?php } ?>

</table>