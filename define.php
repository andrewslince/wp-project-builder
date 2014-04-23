<?php

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
    } else {
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

function getPluginList()
{
    return readJsonFile(PLUGIN_LIST_FILE);
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

        // get tags
        $pluginTags = $html->find('a[rel=tag]');
        $tagList    = array();
        foreach ($pluginTags as $tag)
        {
            $tagList[] = $tag->plaintext;
        }
        
        $pluginInformation = array(
            'name'        => $pluginName,
            'title'       => $pluginTitle,
            'version'     => $pluginVersion,
            'tags'        => implode(',', $tagList),
            'description' => $pluginDescription,
            'pluginUrl'   => $url,
            'downloadUrl' => $pluginDownloadUrl,
            'ts'          => date("Y-m-d H:i:s")
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
        // checks if the plugin package was successfully downloaded
        $pluginInfo = getPluginInformation($url)['data'];
        if (downloadPlugin($pluginInfo['downloadUrl'], $pluginInfo['name']))
        {
            chmod(PLUGIN_DIR . $pluginInfo['name'] . '.zip', 0775);
            
            $pluginList   = getPluginList();
            $pluginList[] = $pluginInfo;

            // checks if the plugin list was successfully updated
            if (!updatePluginList($pluginList))
            {
                $returnInfo['statusCode'] = 4;
                $returnInfo['message']    = 'problemas ao atualizar a lista de plugins';
            }
        }
        else
        {
            $returnInfo['statusCode'] = 3;
            $returnInfo['message']    = 'problemas ao fazer o download do plugin';
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

function getBuildId()
{
    return base64_encode('build_' . time());
}

function getDownloadBuildFile($buildId)
{
    return BASE_DIR . 'wp-files' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'wp_build_' . $buildId . '.zip';
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

function buildProject($config)
{
    $buildId = buildIdToDownload();

    $tmpBuildFolder = BUILD_TMP_DIR . $buildId . DIRECTORY_SEPARATOR;

    $tmpBuildPluginFolder = $tmpBuildFolder . 'wordpress' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;

    // extract wp core
    extractWpCore($config['core'], $tmpBuildFolder);

    // extract plugins
    foreach ($config['plugins'] as $plugin)
    {
        extractPlugin($plugin, $tmpBuildPluginFolder);
    }

    // zip file to download
    if (zipBuildProject($tmpBuildFolder, getDownloadBuildFile($buildId)))
    {
        return $buildId;
    }
    else
    {
        return '';
    }
}