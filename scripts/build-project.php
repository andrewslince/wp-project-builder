<?php

include '../define.php';

$buildConfig = array(
    // 'core' => 'pt_BR',
    'core' => 'en_US',
    'plugins' => array(
        'wordpress-seo',
        'easy-media-gallery',
        'wp-no-category-base'
    )
);

buildProject($buildConfig);