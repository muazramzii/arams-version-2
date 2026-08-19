<?php
include "../config/db.php";

if(isset($_POST['save'])){

$name=$_POST['name'];
$faculty=$_POST['faculty'];
$area=$_POST['research'];

mysqli_query($conn,"INSERT INTO lecturers(name,faculty,research_area)
VALUES('$name','$faculty','$area')");

echo "Lecturer Added";

}
?>

<h2>Add Lecturer</h2>

<form method="POST">

Name
<input type="text" name="name">

Faculty
<input type="text" name="faculty">

Research Area
<input type="text" name="research">

<button name="save">Save</button>

</form>