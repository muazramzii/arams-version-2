<?php
include "../config/db.php";

$result=mysqli_query($conn,"SELECT * FROM publications");

?>

<h2>Publication List</h2>

<table border="1">

<tr>
<th>Title</th>
<th>Journal</th>
<th>Year</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php
while($row=mysqli_fetch_assoc($result)){
?>

<tr>

<td><?php echo $row['title'] ?></td>
<td><?php echo $row['journal'] ?></td>
<td><?php echo $row['year'] ?></td>
<td><?php echo $row['status'] ?></td>

<td>
<a href="../admin/approve_publication.php?id=<?php echo $row['id'] ?>">Approve</a>
</td>

</tr>

<?php } ?>

</table>