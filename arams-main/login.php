<?php
session_start();
include "config/db.php";

if(isset($_POST['login'])){

$email=$_POST['email'];
$password=$_POST['password'];

$sql="SELECT * FROM users WHERE email='$email'";
$result=mysqli_query($conn,$sql);
$user=mysqli_fetch_assoc($result);

if($user && $password==$user['password']){

$_SESSION['user_id']=$user['id'];
$_SESSION['role']=$user['role'];

if($user['role']=="admin"){
header("Location: admin/dashboard.php");
}else{
header("Location: lecturer/dashboard.php");
}

}else{
echo "Login Failed";
}

}
?>

<h2>ARAMS Login</h2>

<form method="POST">

Email<br>
<input type="email" name="email"><br><br>

Password<br>
<input type="password" name="password"><br><br>

<button name="login">Login</button>

</form>