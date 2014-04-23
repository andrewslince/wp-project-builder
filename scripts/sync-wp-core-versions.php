<?php

include '../define.php';

$coreVersions = array(
    'pt_BR',
    'en_US'
);

foreach ($coreVersions as $version)
{
    downloadWpCore($version);
}