<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Mage
 * @package    Mage_Connect
 * @copyright  Copyright (c) 2009 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
* Class Controller
*
* @category   Mage
* @package    Mage_Connect
* @copyright  Copyright (c) 2009 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
* @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

final class Maged_Controller
{
    /**
     * Request key of action
     */
    const ACTION_KEY = 'A';

    /**
     * Instance of class
     *
     * @var Maged_Controller
     */
    private static $_instance;

    /**
     * Current action name
     *
     * @var string
     */
    private $_action;

    /**
     * Controller is dispathed flag
     *
     * @var bool
     */
    private $_isDispatched = false;

    /**
     * Redirect to URL
     *
     * @var string
     */
    private $_redirectUrl;

    /**
     * Downloader dir path
     *
     * @var string
     */
    private $_rootDir;

    /**
     * Magento root dir path
     *
     * @var string
     */
    private $_mageDir;

    /**
     * View instance
     *
     * @var Maged_View
     */
    private $_view;

    /**
     * Config instance
     *
     * @var Maged_Model_Config
     */
    private $_config;

    /**
     * Session instance
     *
     * @var Maged_Model_Session
     */
    private $_session;

    /**
     * Root dir is writable flag
     *
     * @var bool
     */
    private $_writable;

    /**
     * Use maintenance flag
     *
     * @var bool
     */
    protected $_maintenance;

    /**
     * Maintenance file path
     *
     * @var string
     */
    protected $_maintenanceFile;

    /**
     * Register array for singletons
     *
     * @var array
     */
    protected $_singletons = array();

    //////////////////////////// ACTIONS


    /**
     * Get ftp string from post data
     * @param array $post post data
     * @return string FTP Url
     */
    private function getFtpPost($post){
        if(!empty($post['ftp_path'])&&strpos($post['ftp_path'], '/')!==0){
            $post['ftp_path']='/'.$post['ftp_path'];
        }
        if(!empty($post['ftp_path'])&&substr($post['ftp_path'], -1)!='/'){
            $post['ftp_path'].='/';
        }
        $post['ftp_proto']='ftp://';
        if($start=stripos($post['ftp_host'],'ftp://')!==false){
            $post['ftp_proto']='ftp://';
            $post['ftp_host']=substr($post['ftp_host'], $start+6-1);
        }
        if($start=stripos($post['ftp_host'],'ftps://')!==false){
            $post['ftp_proto']='ftps://';
            $post['ftp_host']=substr($post['ftp_host'], $start+7-1);
        }
        if(!empty($post['ftp_login'])&&!empty($post['ftp_password'])){
            $ftp=sprintf("%s%s:%s@%s%s", $post['ftp_proto'], $post['ftp_login'],$post['ftp_password'],$post['ftp_host'],$post['ftp_path']);
        }elseif(!empty($post['ftp_login'])){
            $ftp=sprintf("%s%s@%s%s", $post['ftp_proto'], $post['ftp_login'],$post['ftp_host'],$post['ftp_path']);
        }else{
            $ftp=$post['ftp_proto'].$post['ftp_host'].$post['ftp_path'];
        }
        $_POST['ftp'] = $ftp;
        return $ftp;
    }

    /**
     * Generates auth string from post data and puts back it into post
     * 
     * @param array $post post data
     * @return string auth Url
     */
    private function reformAuthPost(&$post) 
    {
        if (!empty($post['auth_username']) and isset($post['auth_password'])) {
            $post['auth'] = $post['auth_username'] .'@'. $post['auth_password'];
            return true;
        }
        return false;
    }

    /**
     * NoRoute
     *
     */
    public function norouteAction()
    {
        header("HTTP/1.0 404 Invalid Action");
        echo $this->view()->template('noroute.phtml');
    }

    /**
     * Login
     *
     */
    public function loginAction()
    {
        $this->view()->set('username', !empty($_GET['username']) ? $_GET['username'] : '');
        echo $this->view()->template('login.phtml');
    }

    /**
     * Logout
     *
     */
    public function logoutAction()
    {
        $this->session()->logout();
        $this->redirect($this->url());
    }

    /**
     * Index
     *
     */
    public function indexAction()
    {
        if (!$this->isInstalled()) {
            if (false&&!$this->isWritable()) {
                echo $this->view()->template('install/writable.phtml');
            } else {
                $config=$this->config();
                $this->view()->set('mage_url', dirname(dirname($_SERVER['SCRIPT_NAME'])));
                $this->view()->set('use_custom_permissions_mode', $config->get('use_custom_permissions_mode')?$config->get('use_custom_permissions_mode'):'0');
                $this->view()->set('mkdir_mode', $config->get('mkdir_mode'));
                $this->view()->set('chmod_file_mode', $config->get('chmod_file_mode'));
                $this->view()->set('protocol', $config->get('protocol'));

                echo $this->view()->template('install/download.phtml');
            }
        } else {
            if (false&&!$this->isWritable()) {
                echo $this->view()->template('writable.phtml');
            } else {
                $this->forward('connectPackages');
            }
        }
    }

    /**
     * Empty Action
     *
     */
    public function emptyAction()
    {
        $this->model('connect', true)->connect()->runHtmlConsole('Please wait, preparing for updates...');
    }

    /**
     * Install all magento
     *
     */
    public function connectInstallAllAction()
    {
        $p=$_POST;
        if( 1 == $p['inst_protocol']){
            $this->model('connect', true)->connect()->setRemoteConfig($this->getFtpPost($p));
        }

        $this->config()->saveConfigPost($_POST);
        $chan = $this->config()->get('root_channel');
        if(empty($chan)) {
            $chan = 'community';
        }
        $this->model('connect', true)->saveConfigPost($_POST);
        $this->model('connect', true)->installAll(!empty($_GET['force']), $chan);
    }

    /**
     * Connect packages
     *
     */
    public function connectPackagesAction()
    {
        $connect = $this->model('connect', true);
        $this->view()->set('connect', $connect);
        echo $this->view()->template('connect/packages.phtml');
    }

    /**
     * Connect packages POST
     *
     */
    public function connectPackagesPostAction()
    {
        $actions = isset($_POST['actions']) ? $_POST['actions'] : array();
        $ignoreLocalModification = isset($_POST['ignore_local_modification'])?$_POST['ignore_local_modification']:'';
        $this->model('connect', true)->applyPackagesActions($actions, $ignoreLocalModification);
    }

    /**
     * Install package
     *
     */
    public function connectInstallPackagePostAction()
    {
        if (!$_POST) {
            echo "INVALID POST DATA";
            return;
        }

        $this->model('connect', true)->installPackage($_POST['install_package_id']);
    }

    /**
     * Install uploaded package
     *
     */
    public function connectInstallPackageUploadAction()
    {
        if (!$_FILES) {
            echo "No file was uploaded";
            return;
        }

        if(empty($_FILES['file'])) {
            echo "No file was uploaded";
            return;
        }

        $info =& $_FILES['file'];

        if(0 !== intval($info['error'])) {
            echo "File upload problem";
            return;
        }

        $target = $this->_mageDir . DS . "var/".uniqid().$info['name'];
        $res = move_uploaded_file($info['tmp_name'], $target);
        if(false === $res) {
            echo "Error moving uploaded file";
            return;
        }

        $this->model('connect', true)->installUploadedPackage($target);
        @unlink($target);
    }

    /**
     * Settings
     *
     */
    public function settingsAction()
    {
        $connectConfig = $this->model('connect', true)->connect()->getConfig();
        $config = $this->config();
        $this->view()->set('preferred_state', $connectConfig->__get('preferred_state'));
        $this->view()->set('protocol', $connectConfig->__get('protocol'));
        $this->view()->set('use_custom_permissions_mode', $config->get('use_custom_permissions_mode'));
        $this->view()->set('mkdir_mode', $config->get('mkdir_mode'));
        $this->view()->set('chmod_file_mode', $config->get('chmod_file_mode'));

        $fs_disabled=!$this->isWritable();
        $ftpParams=$connectConfig->__get('remote_config')?@parse_url($connectConfig->__get('remote_config')):'';

        $this->view()->set('fs_disabled', $fs_disabled);
        $this->view()->set('deployment_type', ($fs_disabled||!empty($ftpParams)?'ftp':'fs'));

        if(!empty($ftpParams)){
            $this->view()->set('ftp_host', sprintf("%s://%s",$ftpParams['scheme'],$ftpParams['host']));
            $this->view()->set('ftp_login', $ftpParams['user']);
            $this->view()->set('ftp_password', $ftpParams['pass']);
            $this->view()->set('ftp_path', $ftpParams['path']);
        }

        echo $this->view()->template('settings.phtml');
    }

    /**
     * Settings post
     *
     */
    public function settingsPostAction()
    {
        if(!strlen($this->config()->get('downloader_path'))){
            $this->config()->set('downloader_path', $this->model('connect', true)->connect()->getConfig()->downloader_path);
        }
        if ($_POST) {
            if( 'ftp' == $_POST['deployment_type']&&!empty($_POST['ftp_host'])){
                $this->model('connect', true)->connect()->setRemoteConfig($this->getFtpPost($_POST));
            }else{
                $this->model('connect', true)->connect()->setRemoteConfig('');
                $_POST['ftp'] = '';
            }
            $this->reformAuthPost($_POST);
            $this->config()->saveConfigPost($_POST);
            $this->model('connect', true)->saveConfigPost($_POST);
        }
        $this->redirect($this->url('settings'));
    }

    //////////////////////////// ABSTRACT

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->_rootDir = dirname(dirname(__FILE__));
        $this->_mageDir = dirname($this->_rootDir);
    }

    /**
     * Run
     *
     */
    public static function run()
    {
        try {
            self::singleton()->dispatch();
        } catch (Exception $e) {
            echo $e->getMessage();
            //echo self::singleton()->view()->set('exception', $e)->template("exception.phtml");
        }
    }

    /**
     * Initialize object of class
     *
     * @return Maged_Controller
     */
    public static function singleton()
    {
        if (!self::$_instance) {
            self::$_instance = new self;

            if (self::$_instance->isDownloaded() && self::$_instance->isInstalled()) {
                Mage::app();
                Mage::getSingleton('adminhtml/url')->turnOffSecretKey();
            }
        }
        return self::$_instance;
    }

    /**
     * Retrieve Downloader root dir
     *
     * @return string
     */
    public function getRootDir()
    {
        return $this->_rootDir;
    }

    /**
     * Retrieve Magento root dir
     *
     * @return string
     */
    public function getMageDir()
    {
        return $this->_mageDir;
    }

    /**
     * Retrieve Mage Class file path
     *
     * @return string
     */
    public function getMageFilename()
    {
        $ds = DIRECTORY_SEPARATOR;
        return $this->getMageDir() . $ds . 'app' . $ds . 'Mage.php';
    }

    /**
     * Retrieve path for Varien_Profiler
     *
     * @return string
     */
    public function getVarFilename()
    {
        $ds = DIRECTORY_SEPARATOR;
        return $this->getMageDir() . $ds . 'lib' . $ds . 'Varien' . $ds . 'Profiler.php';
    }

    /**
     * Retrieve downloader file path
     *
     * @param string $name
     * @return string
     */
    public function filepath($name = '')
    {
        $ds = DIRECTORY_SEPARATOR;
        return rtrim($this->getRootDir() . $ds . str_replace('/', $ds, $name), $ds);
    }

    /**
     * Retrieve object of view
     *
     * @return Maged_View
     */
    public function view()
    {
        if (!$this->_view) {
            $this->_view = new Maged_View;
        }
        return $this->_view;
    }

    /**
     * Retrieve object of model
     *
     * @param string $model
     * @param boolean $singleton
     * @return Maged_Model
     */
    public function model($model = null, $singleton = false)
    {
        if ($singleton && isset($this->_singletons[$model])) {
            return $this->_singletons[$model];
        }

        if (is_null($model)) {
            $class = 'Maged_Model';
        } else {
            $class = 'Maged_Model_'.str_replace(' ', '_', ucwords(str_replace('_', ' ', $model)));
            if (!class_exists($class, false)) {
                include_once str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
            }
        }

        $object = new $class();

        if ($singleton) {
            $this->_singletons[$model] = $object;
        }

        return $object;
    }

    /**
     * Retrieve object of config
     *
     * @return Maged_Model_Config
     */
    public function config()
    {
        if (!$this->_config) {
            $this->_config = $this->model('config')->load();
        }
        return $this->_config;
    }

    /**
     * Retrieve object of session
     *
     * @return Maged_Model_Session
     */
    public function session()
    {
        if (!$this->_session) {
            $this->_session = $this->model('session')->start();
        }
        return $this->_session;
    }

    /**
     * Set Controller action
     *
     * @param string $action
     * @return Maged_Controller
     */
    public function setAction($action=null)
    {
        if (is_null($action)) {
            if (!empty($this->_action)) {
                return $this;
            }
            $action = !empty($_GET[self::ACTION_KEY]) ? $_GET[self::ACTION_KEY] : 'index';
        }
        if (empty($action) || !is_string($action) || !method_exists($this, $this->getActionMethod($action))) {
            $action = 'noroute';
        }
        $this->_action = $action;
        return $this;
    }

    /**
     * Retrieve Controller action name
     *
     * @return string
     */
    public function getAction()
    {
        return $this->_action;
    }

    /**
     * Set Redirect to URL
     *
     * @param string $url
     * @param bool $force
     * @return Maged_Controller
     */
    public function redirect($url, $force = false)
    {
        $this->_redirectUrl = $url;
        if ($force) {
            $this->processRedirect();
        }
        return $this;
    }

    /**
     * Precess redirect
     *
     * @return Maged_Controller
     */
    public function processRedirect()
    {
        if ($this->_redirectUrl) {
            if (headers_sent()) {
                echo '<script type="text/javascript">location.href="'.$this->_redirectUrl.'"</script>';
                exit;
            } else {
                header("Location: ".$this->_redirectUrl);
                exit;
            }
        }
        return $this;
    }

    /**
     * Forward to action
     *
     * @param string $action
     * @return Maged_Controller
     */
    public function forward($action)
    {
        $this->setAction($action);
        $this->_isDispatched = false;
        return $this;
    }

    /**
     * Retrieve action method by action name
     *
     * @param string $action
     * @return string
     */
    public function getActionMethod($action = null)
    {
        $method = (!is_null($action) ? $action : $this->_action).'Action';
        return $method;
    }

    /**
     * Generate URL for action
     *
     * @param string $action
     * @param array $params
     */
    public function url($action = '', $params = array())
    {
        $args = array();
        foreach ($params as $k => $v) {
            $args[] = sprintf('%s=%s', rawurlencode($k), rawurlencode($v));
        }
        $args = $args ? join('&', $args) : '';

        return sprintf('%s?%s=%s%s', $_SERVER['SCRIPT_NAME'], self::ACTION_KEY, rawurlencode($action), $args);
    }

    /**
     * Dispatch process
     *
     */
    public function dispatch()
    {
        header('Content-type: text/html; charset=UTF-8');

        $this->setAction();

        if (!$this->isInstalled()) {
            if (!in_array($this->getAction(), array('index', 'connectInstallAll', 'empty'))) {
                $this->setAction('index');
            }
        } else {
            $this->session()->authenticate();
        }

        while (!$this->_isDispatched) {
            $this->_isDispatched = true;

            $method = $this->getActionMethod();
            $this->$method();
        }

        $this->processRedirect();
    }

    /**
     * Check root dir is writable
     *
     * @return bool
     */
    public function isWritable()
    {
        if (is_null($this->_writable)) {
            $this->_writable = is_writable($this->getMageDir() . DIRECTORY_SEPARATOR)
                && is_writable($this->filepath())
                && (!file_exists($this->filepath('config.ini') || is_writable($this->filepath('config.ini'))));

        }
        return $this->_writable;
    }

    /**
     * Check is Magento files downloaded
     *
     * @return bool
     */
    public function isDownloaded()
    {
        return file_exists($this->getMageFilename()) && file_exists($this->getVarFilename());
    }

    /**
     * Check is Magento installed
     *
     * @return bool
     */
    public function isInstalled()
    {
        if (!$this->isDownloaded()) {
            return false;
        }
        if (!class_exists('Mage', false)) {
            if (!file_exists($this->getMageFilename())) {
                return false;
            }
            include_once $this->getMageFilename();
            Mage::setIsDownloader();
        }
        return Mage::isInstalled();
    }

    /**
     * Retrieve Maintenance flag
     *
     * @return bool
     */
    protected function _getMaintenanceFlag()
    {
        if (is_null($this->_maintenance)) {
            $this->_maintenance = !empty($_REQUEST['maintenance']) && $_REQUEST['maintenance'] == '1' ? true : false;
        }
        return $this->_maintenance;
    }

    /**
     * Retrieve Maintenance Flag file path
     *
     * @return string
     */
    protected function _getMaintenanceFilePath()
    {
        if (is_null($this->_maintenanceFile)) {
            $path = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR;
            $this->_maintenanceFile = $path . 'maintenance.flag';
        }
        return $this->_maintenanceFile;
    }

    /**
     * Begin install package(s)
     *
     */
    public function startInstall()
    {
        if ($this->_getMaintenanceFlag()) {
            $maintenance_filename='maintenance.flag';
            $connectConfig = $this->model('connect', true)->connect()->getConfig();
            if(!$this->isWritable()||strlen($connectConfig->__get('remote_config'))>0){
                $ftpObj = new Mage_Connect_Ftp();
                $ftpObj->connect($connectConfig->__get('remote_config'));
                $tempFile = tempnam(sys_get_temp_dir(),'maintenance');
                @file_put_contents($tempFile, 'maintenance');
                $ret=$ftpObj->upload($maintenance_filename, $tempFile);
                $ftpObj->close();
            }else{
                @file_put_contents($this->_getMaintenanceFilePath(), 'maintenance');
            }
        }
    }

    /**
     * End install package(s)
     *
     */
    public function endInstall()
    {
        try {
            if (!empty($_GET['clean_sessions'])) {
                Mage::app()->cleanAllSessions();
            }
            Mage::app()->cleanCache();
        } catch (Exception $e) {
            $this->session()->addMessage('error', "Exception during cache and session cleaning: ".$e->getMessage());
        }

        // reinit config and apply all updates
        Mage::app()->getConfig()->reinit();
        Mage_Core_Model_Resource_Setup::applyAllUpdates();
        Mage_Core_Model_Resource_Setup::applyAllDataUpdates();

        if ($this->_getMaintenanceFlag()) {
            $maintenance_filename='maintenance.flag';
            $connectConfig = $this->model('connect', true)->connect()->getConfig();
            if(!$this->isWritable()&&strlen($connectConfig->__get('remote_config'))>0){
                $ftpObj = new Mage_Connect_Ftp();
                $ftpObj->connect($connectConfig->__get('remote_config'));
                $ftpObj->delete($maintenance_filename);
                $ftpObj->close();
            }else{
                @unlink($this->_getMaintenanceFilePath());
            }
        }
    }
}