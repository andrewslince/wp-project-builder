<?php

include '../define.php';

/*

atcontent
w3-total-cache
easy-media-gallery
wordpress-seo
wp-no-category-base
epic-bootstrap-buttons
wp-super-cache
buddypress
bbpress
theme-check

*/

$url = (isset($_SERVER['argv']))
    ? $_SERVER['argv']['1']
    : $_POST['url'];

echo json_encode(addPlugin($url)); exit;