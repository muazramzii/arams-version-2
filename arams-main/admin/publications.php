<?php
include "../config/db.php";

$result=mysqli_query($conn,"SELECT * FROM publications");
?>

<h2>Publications</h2>

<table>

<tr>
<th>Title</th>
<th>Journal</th>
<th>Year</th>
<th>Indexed</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['title'] ?></td>
<td><?php echo $row['journal'] ?></td>
<td><?php echo $row['year'] ?></td>
<td><?php echo $row['index_status'] ?></td>

</tr>

<?php } ?>

</table>