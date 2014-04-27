<?php

include '../define.php';

/**
 * @var array
 * - 1 to build packaged successfully
 * - 2 to invalid request
 * - 3 to invalid build id
 * - 4 to failed build packaging
 */
$response = array(
    'code'    => 1,
    'message' => 'build packaged successfully',
);

switch (validateAjaxRequest($_SERVER))
{
    case 0 : 

        $response['code']    = 2;
        $response['message'] = 'invalid request';

        break;

    case 1 :  // valid request

        // checks if the package was downloaded successfully
        $buildId = getBuildId();
        if (packagingBuild($buildId))
        {
            $response['data'] = array(
                'buildId' => $buildId
            );
        }
        else
        {
            $response['code']    = 4;
            $response['message'] = 'failed package extracting';
        }

        break;

    case 2 :

        $response['code']    = 3;
        $response['message'] = 'invalid build id';

        break;
}

echo json_encode($response); exit;