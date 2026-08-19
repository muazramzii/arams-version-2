<?php
include "../config/db.php";

if(isset($_POST['submit'])){

$title=$_POST['title'];
$amount=$_POST['amount'];
$year=$_POST['year'];

$sql="INSERT INTO grants(grant_title,amount,year)
VALUES('$title','$amount','$year')";

mysqli_query($conn,$sql);

echo "Grant Added";
}
?>

<h2>Add Grant</h2>

<form method="POST">

Grant Title<br>
<input type="text" name="title"><br><br>

Amount<br>
<input type="text" name="amount"><br><br>

Year<br>
<input type="number" name="year"><br><br>

<button name="submit">Save</button>

</form>