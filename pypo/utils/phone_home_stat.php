<?php

exitIfNotRoot();

date_default_timezone_set("UTC");

$values = parse_ini_file('/etc/airtime/airtime.conf', true);
$CC_CONFIG['phpDir'] = $values['general']['airtime_dir'];
require_once($CC_CONFIG['phpDir'].'/application/configs/conf.php');
$CC_CONFIG = Config::getConfig();

/*
// Name of the web server user
$CC_CONFIG['webServerUser'] = $values['general']['web_server_user'];
$CC_CONFIG['phpDir'] = $values['general']['airtime_dir'];
$CC_CONFIG['rabbitmq'] = $values['rabbitmq'];

//$CC_CONFIG['baseUrl'] = $values['general']['base_url'];
//$CC_CONFIG['basePort'] = $values['general']['base_port'];

// Database config
$CC_CONFIG['dsn']['username'] = $values['database']['dbuser'];
$CC_CONFIG['dsn']['password'] = $values['database']['dbpass'];
$CC_CONFIG['dsn']['hostspec'] = $values['database']['host'];
$CC_CONFIG['dsn']['phptype'] = 'pgsql';
$CC_CONFIG['dsn']['database'] = $values['database']['dbname'];

$CC_CONFIG['apiKey'] = array($values['general']['api_key']);

$CC_CONFIG['soundcloud-connection-retries'] = $values['soundcloud']['connection_retries'];
$CC_CONFIG['soundcloud-connection-wait'] = $values['soundcloud']['time_between_retries'];
*/
require_once($CC_CONFIG['phpDir'].'/application/configs/constants.php');
//require_once($CC_CONFIG['phpDir'].'/application/configs/conf.php');

//$CC_CONFIG['phpDir'] = $values['general']['airtime_dir'];

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
get_include_path(),
realpath($CC_CONFIG['phpDir'] . '/library')
)));

function my_autoload($classname){
    $CC_CONFIG = Config::getConfig();
    $info = explode('_', $classname);
    if (isset($info[1]) && isset($info[2])) {
        $filename = $info[2].".php";
        if ($info[1] == "Model") {
            $folderName = "models";
        } else if ($info[1] == "Common") {
            $folderName = "common";
        }
        require_once($CC_CONFIG['phpDir'].'/application/'.$folderName.'/'.$filename);
    }
}

spl_autoload_register('my_autoload');

require_once 'propel/runtime/lib/Propel.php';
Propel::init($CC_CONFIG['phpDir']."/application/configs/airtime-conf-production.php");

//Zend framework
if (file_exists('/usr/share/php/libzend-framework-php')){
    set_include_path('/usr/share/php/libzend-framework-php' . PATH_SEPARATOR . get_include_path());
}

require_once('Zend/Loader/Autoloader.php');
$autoloader = Zend_Loader_Autoloader::getInstance();

$infoArray = Application_Model_Preference::GetSystemInfo(true);

if(Application_Model_Preference::GetSupportFeedback() == '1'){
    $url = 'http://stat.sourcefabric.org/index.php?p=airtime';
    //$url = 'http://localhost:9999/index.php?p=airtime';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, $url);

    $data = json_encode($infoArray);

    $dataArray = array("data" => $data );

    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataArray);
    $result = curl_exec($ch);
    curl_close($ch);
}

// Get latest version from stat server and store to db
if(Application_Model_Preference::GetPlanLevel() == 'disabled'){
    $url = 'http://stat.sourcefabric.org/airtime-stats/airtime_latest_version';
    //$url = 'http://localhost:9999/index.php?p=airtime';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    $result = curl_exec($ch);

    if(curl_errno($ch)) {
        echo "curl error: " . curl_error($ch) . "\n";
    } else {
        $resultArray = explode("\n", $result);
        if (isset($resultArray[0])) {
            Application_Model_Preference::SetLatestVersion($resultArray[0]);
        }
        if (isset($resultArray[1])) {
            Application_Model_Preference::SetLatestLink($resultArray[1]);
        }
    }

    curl_close($ch);
}

/**
 * Ensures that the user is running this PHP script with root
 * permissions. If not running with root permissions, causes the
 * script to exit.
 */
function exitIfNotRoot()
{
    // Need to check that we are superuser before running this.
    if(posix_geteuid() != 0){
        echo "Must be root user.\n";
        exit(1);
    }
}
