<?php
include "../config/db.php";

$result=mysqli_query($conn,"SELECT * FROM faculties");
?>

<h2>Faculties</h2>

<table>

<tr>
<th>Faculty Name</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>
<td><?php echo $row['faculty_name'] ?></td>
</tr>

<?php } ?>

</table>