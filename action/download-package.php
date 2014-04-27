<?php

include '../define.php';

/**
 * @var array
 * - 1 to downloaded package successfully
 * - 2 to invalid request
 * - 3 to invalid build id
 * - 4 to failed package downloading
 * - 5 to failed package extracting
 */
$response = array(
    'code'    => 1,
    'message' => 'package downloaded successfully',
);

switch (validateAjaxRequest($_SERVER))
{
    case 0 : 

        $response['code']    = 2;
        $response['message'] = 'invalid request';

        break;

    case 1 :  // valid request

        // checks if the package was downloaded successfully
        if (downloadPackage($_POST['name'], $_POST['type']))
        {
            // checks if occurred any error on the package extraction
            if (!extractPackage($_POST['name'], $_POST['type']))
            {
                $response['code']    = 5;
                $response['message'] = 'failed package extracting';
            }
        }
        else
        {
            $response['code']    = 4;
            $response['message'] = 'failed package downloading';
        }

        break;

    case 2 :

        $response['code']    = 3;
        $response['message'] = 'invalid build id';

        break;
}

echo json_encode($response); exit;