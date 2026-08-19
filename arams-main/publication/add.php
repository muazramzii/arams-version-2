<?php
include "../config/db.php";

if(isset($_POST['submit'])){

$title=$_POST['title'];
$journal=$_POST['journal'];
$year=$_POST['year'];

$filename=$_FILES['file']['name'];
$tmp=$_FILES['file']['tmp_name'];

move_uploaded_file($tmp,"../uploads/".$filename);

$sql="INSERT INTO publications
(title,journal,year,file_proof)
VALUES
('$title','$journal','$year','$filename')";

mysqli_query($conn,$sql);

echo "Publication Added";
}
?>

<h2>Add Publication</h2>

<form method="POST" enctype="multipart/form-data">

Title<br>
<input type="text" name="title"><br><br>

Journal<br>
<input type="text" name="journal"><br><br>

Year<br>
<input type="number" name="year"><br><br>

Upload Proof<br>
<input type="file" name="file"><br><br>

<button name="submit">Save</button>

</form>