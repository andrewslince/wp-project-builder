<?php

include '../define.php';

if (isset($_GET['build_id']))
{
    $buildId = cleanXss($_GET['build_id']);

    $file = getDownloadBuildFile($buildId);

    header('Content-Type: ' . $file);
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: attachment; filename=' . basename($file));
    readfile($file);
    exit;
}
else
{
    die('requisição inválida');
}