<?php

include '../define.php';

$_POST['plugins'] = explode(",", $_POST['plugins']);

echo json_encode(buildProject($_POST)); exit;