<?php

session_start();

// ERROR REPORTING

error_reporting(E_ALL);
ini_set('display_errors', true);

// CONSTANTS

define('BASE_DIR', __DIR__ . DIRECTORY_SEPARATOR);

define('WP_FILES_DIR', BASE_DIR . 'wp-files' . DIRECTORY_SEPARATOR);

define('PLUGIN_LIST_FILE', WP_FILES_DIR . 'plugin-list.json');

define('WP_CORE_LIST_FILE', WP_FILES_DIR . 'wp-core-list.json');

define('PLUGIN_DIR', WP_FILES_DIR . 'plugins' . DIRECTORY_SEPARATOR);

define('WP_CORE_DIR', WP_FILES_DIR . 'core' . DIRECTORY_SEPARATOR);

define('BUILD_TMP_DIR', WP_FILES_DIR . 'tmp' . DIRECTORY_SEPARATOR);

/* ==========================| GENERAL |========================== */

function dbg($param, $exit = true)
{
    if (!isset($_SERVER['argv']))
    {
        echo '<pre>';
    }

    print_r($param);

    if (isset($_SERVER['argv']))
    {
        echo "\n";
        
    }
    else
    {
        echo '</pre>';
    }

    if ($exit)
    {
        exit;
    }
}

function cleanXss($param)
{
    return strip_tags(trim($param));
}

function removeDir($dir)
{
    // dbg("removing dir '$dir'...", 0);

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo)
    {
        $todo = $fileinfo->isDir()
            ? 'rmdir'
            : 'unlink';

        $todo($fileinfo->getRealPath());
    }

    return rmdir($dir);
}

/**
 * @param  array $server[]
 * @param  boolean $validateBuildId
 * @return integer
 * - 0 to invalid request
 * - 1 to valid request
 * - 2 to invalid build id
 */
function validateAjaxRequest($server, $validateBuildId = false)
{
    $validationCode = 0;

    if ($validateBuildId && !strlen(getBuildId()))
    {
        $validationCode = 2;
    }
    else if ($server)
    {
        $validationCode = 1;
    }

    return $validationCode;
}

function downloadFile($url, $filename)
{
    return (file_put_contents($filename, fopen($url, 'r')))
        ? true
        : false;
}

function readJsonFile($filepath)
{
    return json_decode(file_get_contents($filepath), true);
}

function extractFile($filepath, $targetFolder)
{
    $zip = new ZipArchive();
    if ($zip->open($filepath) === true)
    {
        $zip->extractTo($targetFolder);
        $zip->close();

        return true;
    }
    else
    {
        return false;
    }
}

/* ==========================| PLUGIN |========================== */

function pluginUrlIsValid($url)
{
    // defines the url pattern for the wp plugins
    $urlPattern = 'https://wordpress.org/plugins/';

    // validate url
    return (filter_var($url, FILTER_VALIDATE_URL) && strstr($url, $urlPattern))
        ? true
        : false;
}

function getPluginList($pluginName = '')
{
    $pluginList = readJsonFile(PLUGIN_LIST_FILE);

    if (strlen(trim($pluginName)))
    {
        foreach ($pluginList as $plugin)
        {
            if ($plugin['name'] == $pluginName)
            {
                return $plugin;
            }
        }
    }

    return $pluginList;
}

function updatePluginList($pluginList)
{
    // dbg('updating plugin list...', 0);
    return (file_put_contents(PLUGIN_LIST_FILE, json_encode($pluginList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)))
        ? true
        : false;
}

function getPluginNameByUrl($url)
{
    // defines the url pattern for the wp plugins
    $urlPattern = 'https://wordpress.org/plugins/';

    // get the plugin name
    return strtok(str_replace($urlPattern, '', $url), '/');
}

function formatBytes($bytes, $precision = 2, $separator = ' ')
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 

    $bytes = max($bytes, 0); 
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow   = min($pow, count($units) - 1); 

    // Uncomment one of the following alternatives
    // $bytes /= pow(1024, $pow);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision) . $separator . $units[$pow];

    // other implementation
    /*
    $base = log($size) / log(1024);
    $suffixes = array('', 'k', 'M', 'G', 'T');   

    return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    */
} 

/**
 * Returns the size of a file without downloading it, or -1 if the file size could not be determined
 *
 * @param $url - The location of the remote file to download. Cannot be null or empty
 * @return The size of the file referenced by $url, or -1 if the size could not be determined
 */
function getRemoteFileSize($url)
{
    // Assume failure.
    $result = -1;

    $curl = curl_init($url);

    // Issue a HEAD request and follow any redirects.
    curl_setopt($curl, CURLOPT_NOBODY, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    // curl_setopt($curl, CURLOPT_USERAGENT, get_user_agent_string());
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');

    $data = curl_exec($curl);
    curl_close($curl);

    if ($data)
    {
        $content_length = 'unknown';
        $status         = 'unknown';

        if(preg_match( "/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches))
        {
            $status = (int) $matches['1'];
        }

        if(preg_match( "/Content-Length: (\d+)/", $data, $matches))
        {
            $content_length = (int) $matches['1'];
        }

        // http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
        if($status == 200 || ($status > 300 && $status <= 308))
        {
            $result = $content_length;
        }
    }

    return $result;
}

function getPluginInformation($url)
{
    $returnInfo = array(
        'statusCode' => 1, // success code
        'message'    => ''
    );

    $pluginInformation = null;

    try
    {
        include_once 'libs/SimpleHtmlDom/simple_html_dom.php';

        // dbg('parsing plugin page...', 0);
        $html = file_get_html($url);

        // dbg('extract plugin informations...', 0);
        $pluginName        = getPluginNameByUrl($url);
        $pluginTitle       = trim($html->find('h2[itemprop=name]')['0']->plaintext);
        $pluginDescription = trim($html->find('p[itemprop=description]')['0']->plaintext);
        $pluginDownloadUrl = $html->find('a[itemprop=downloadUrl]')['0']->href;
        $pluginVersion     = $html->find('meta[itemprop=softwareVersion]')['0']->content;

        // get filesize
        $rawBytes = getRemoteFileSize($pluginDownloadUrl);

        // get tags
        $pluginTags = $html->find('a[rel=tag]');
        $tagList    = array();
        foreach ($pluginTags as $tag)
        {
            $tagList[] = $tag->plaintext;
        }
        
        $pluginInformation = array(
            'name'           => $pluginName,
            'title'          => $pluginTitle,
            'version'        => $pluginVersion,
            'tags'           => implode(',', $tagList),
            'description'    => $pluginDescription,
            'pluginUrl'      => $url,
            'downloadUrl'    => $pluginDownloadUrl,
            'rawBytes'       => $rawBytes,
            'formattedBytes' => formatBytes($rawBytes),
            'ts'             => date("Y-m-d H:i:s"),
        );
    }
    catch(Exception $e)
    {
        $returnInfo['statusCode'] = 0;
    }

    $returnInfo['data'] = $pluginInformation;

    return $returnInfo;
}

function validateNewPlugin($url)
{
    $isAlreadyRegistered = false;
    $returnInfo          = array(
        'statusCode' => 1, // success code
        'message'    => ''
    );
    
    // checks if plugin url is valid
    if (pluginUrlIsValid($url))
    {
        $pluginName = getPluginNameByUrl($url);

        // checks if plugin is already registered
        $pluginList = getPluginList();
        foreach ($pluginList as $key => $plugin)
        {
            if ($plugin['name'] == $pluginName)
            {
                $isAlreadyRegistered = true;
                break;
            }
        }

        if ($isAlreadyRegistered)
        {
            $returnInfo['statusCode'] = 2;
            $returnInfo['message']    = 'o plugin "<strong>' . mb_strtolower($plugin['title'], 'UTF-8') . '</strong>" já foi adicionado';
        }
    }
    else
    {
        $returnInfo['statusCode'] = 0;
        $returnInfo['message']    = "a url informada não é válida";
    }

    return $returnInfo;
}

/**
 * @param string $url
 * @param boolean $log
 * @return array
 * - statusCode
 *   - 0 > invalid url
 *   - 1 > add successfully
 *   - 2 > plugin is already registered
 *   - 3 > download failed
 *   - 4 > update plugin list failed
 * - msg
 */
function addPlugin($url, $log = false)
{
    $isAlreadyRegistered = false;
    $returnInfo          = array(
        'statusCode' => 1,
        'message'    => 'plugin adicionando com sucesso!'
    );

    // get the plugin name
    $pluginName = getPluginNameByUrl($url);

    if ($log)
    {
        dbg(">> ADDING PLUGIN '$pluginName'...", 0);
    }

    // checks if plugin url is valid
    $rcValidatePlugin = validateNewPlugin($url);
    if ($rcValidatePlugin['statusCode'] == 1)
    {
        $pluginList   = getPluginList();
        $pluginList[] = getPluginInformation($url)['data'];

        // checks if the plugin list was successfully updated
        if (!updatePluginList($pluginList))
        {
            $returnInfo['statusCode'] = 4;
            $returnInfo['message']    = 'problemas ao atualizar a lista de plugins';
        }
    }
    else
    {
        $returnInfo = $rcValidatePlugin;
    }

    return $returnInfo;
}

/**
 * @param $url
 * @param $pluginName
 * @return boolean
 */
function downloadPlugin($url, $pluginName)
{
    // dbg("downloading plugin package '$pluginName'...", 0);
    return downloadFile($url, PLUGIN_DIR . "$pluginName.zip");
}

function extractPlugin($pluginName, $pathTo)
{
    // dbg("extracting plugin '$pluginName'...", 0);
    return extractFile(PLUGIN_DIR . "$pluginName.zip", $pathTo);
}

/* ==========================| WP CORE |========================== */

function downloadWpCore($language)
{
    $wpCore = getWpCoreList($language);

    // dbg("downloading wp core package '$language'...", 0);
    return downloadFile($wpCore['downloadUrl'], WP_CORE_DIR . "$language.zip");
}

function getWpCoreList($language = '')
{
    $coreList = readJsonFile(WP_CORE_LIST_FILE);

    if ($language == '')
    {
        return $coreList;
    }
    else
    {
        foreach ($coreList as $core)
        {
            if ($language == $core['language'])
            {
                return $core;
            }
        }
    }
}

function extractWpCore($language, $pathTo)
{
    return extractFile(WP_CORE_DIR . "$language.zip", $pathTo);
}

/* ==========================| BUILDER |========================== */

function packagingBuild($buildId)
{
    return (zipBuildProject(BUILD_TMP_DIR . $buildId . DIRECTORY_SEPARATOR, getDownloadBuildFile($buildId)))
        ? true
        : false;
}

function getDownloadBuildFile($buildId)
{
    return BUILD_TMP_DIR . 'wp_build_' . $buildId . '.zip';
}

function createBuildId()
{
    $_SESSION['wpb']['buildId'] = base64_encode('build_' . time());
}

function getBuildId()
{
    return (isset($_SESSION['wpb']['buildId']))
        ? $_SESSION['wpb']['buildId']
        : '';
}

function buildIdToDownload()
{
    // return base64_encode('build_1397972487');
    return base64_encode('build_' . time());
}

function zipBuildProject($source, $destination)
{
    // dbg("zipping build project...", 0);

    if (!extension_loaded('zip') || !file_exists($source))
    {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE))
    {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file)
        {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
            {
                continue;
            }

            $file = realpath($file);

            if (is_dir($file) === true)
            {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            }
            else if (is_file($file) === true)
            {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    }
    else if (is_file($source) === true)
    {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    $zipped = $zip->close();

    // remove old folder
    removeDir($source);

    return $zipped;
}

function extractPackage($name, $type)
{

}

function downloadPackage($name, $type)
{
    $downloadedSuccessfully = false;
    $buildId = getBuildId();

    $tmpBuildFolder = BUILD_TMP_DIR . $buildId . DIRECTORY_SEPARATOR;

    if ($type == 'core')
    {
        if (!is_dir($tmpBuildFolder))
        {
            mkdir($tmpBuildFolder, 0777, true);
        }

        $coreZipFile = $tmpBuildFolder . 'wp-core.zip';
        $coreInfo    = getWpCoreList($name);
        if (downloadFile($coreInfo['downloadUrl'], $coreZipFile))
        {
            // get core >> extract
            if (extractFile($coreZipFile, $tmpBuildFolder))
            {
                $downloadedSuccessfully = true;
                unlink($coreZipFile);
            }
        }
    }
    else
    {
        $pluginInfo    = getPluginList($name);

        $pluginFolder  = $tmpBuildFolder . 'wordpress' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;
        $pluginZipFile = $pluginFolder . $name . '.zip';

        if (downloadFile($pluginInfo['downloadUrl'], $pluginZipFile))
        {
            // get plugins >> extract plugin
            if (extractFile($pluginZipFile, $pluginFolder))
            {
                $downloadedSuccessfully = true;
                unlink($pluginZipFile);
            }
            // else
            // {
            //     dbg('falha na extração para o diretório: ' . $tmpBuildFolder . 'wordpress' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $plugin . DIRECTORY_SEPARATOR);
            // }
        }
        // else
        // {
        //     dbg('falha no download');
        // }
    }

    return $downloadedSuccessfully;
}

function buildProject($config)
{
    $buildId = buildIdToDownload();

    $tmpBuildFolder = BUILD_TMP_DIR . $buildId . DIRECTORY_SEPARATOR;

    $tmpBuildPluginFolder = $tmpBuildFolder . 'wordpress' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;

    // get core
    $coreInfo = getWpCoreList($config['core']);

    // get core >> download
    mkdir($tmpBuildFolder, 0777, true);
    $coreZipFile = $tmpBuildFolder . 'wp-core.zip';
    if (downloadFile($coreInfo['downloadUrl'], $coreZipFile))
    {
        // get core >> extract
        if (extractFile($coreZipFile, $tmpBuildFolder))
        {
            unlink($coreZipFile);
        }
    }

    // get plugins
    foreach ($config['plugins'] as $plugin)
    {
        $pluginInfo = getPluginList($plugin);

        $pluginFolder = $tmpBuildFolder . 'wordpress' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;

        // get plugins >> download
        $pluginZipFile = $pluginFolder . $plugin . '.zip';
        if (downloadFile($pluginInfo['downloadUrl'], $pluginZipFile))
        {
            // get plugins >> extract plugin
            if (extractFile($pluginZipFile, $pluginFolder))
            {
                // removes zipped file
                unlink($pluginZipFile);
            }
            else
            {
                dbg('falha na extração para o diretório: ' . $tmpBuildFolder . 'wordpress' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $plugin . DIRECTORY_SEPARATOR);
            }
        }
        else
        {
            dbg('falha no download');
        }
    }

    dbg('pega!!!!', 0);

    // zip file to download
    if (zipBuildProject($tmpBuildFolder, getDownloadBuildFile($buildId)))
    {
        dbg('ifẽẽẽẽẽẽ');
        return $buildId;
    }
    else
    {
        dbg('elsêêêêêê');
        return '';
    }
}