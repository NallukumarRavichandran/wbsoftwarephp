<?php

require_once "apacs_login.php";

$token = getValidApacsToken();

if($token){

    echo "<h2 style='color:green'>VALID TOKEN RECEIVED</h2>";

    echo substr($token,0,80).".....";

}else{

    echo "<h2 style='color:red'>TOKEN NOT AVAILABLE</h2>";

}