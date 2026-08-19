<?php
include "../config/db.php";

$result=mysqli_query($conn,"SELECT * FROM grants");
?>

<h2>Grant List</h2>

<table border="1">

<tr>
<th>Title</th>
<th>Amount</th>
<th>Year</th>
<th>Status</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['grant_title'] ?></td>
<td><?php echo $row['amount'] ?></td>
<td><?php echo $row['year'] ?></td>
<td><?php echo $row['status'] ?></td>

</tr>

<?php } ?>

</table>