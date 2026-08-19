<?php
include "../config/db.php";

$result=mysqli_query($conn,"SELECT * FROM research_income");
?>

<h2>Research Income</h2>

<table>

<tr>
<th>Source</th>
<th>Amount</th>
<th>Year</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['source'] ?></td>
<td><?php echo $row['amount'] ?></td>
<td><?php echo $row['year'] ?></td>

</tr>

<?php } ?>

</table>