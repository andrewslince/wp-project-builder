<?php

include '../define.php';

echo json_encode(addPlugin($_POST['newPlugin'])); exit;

dbg($_POST, 0);
dbg($_SERVER);

$pluginList = array(
    'https://wordpress.org/plugins/atcontent/',
    'https://wordpress.org/plugins/w3-total-cache/',
    'https://wordpress.org/plugins/easy-media-gallery/',
    'https://wordpress.org/plugins/wordpress-seo/',
    'https://wordpress.org/plugins/wp-no-category-base/'
);

foreach ($pluginList as $pluginUrl)
{
    $rc = addPlugin($_POST['newPlugin']);

    dbg($rc['msg'] . "\n", 0);
}