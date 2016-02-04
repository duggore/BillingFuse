<?php
/*
*
* BillingFuse
*
* @copyright 2016 BillingFuse International Limited.
*
* @license Apache V2.0
*
* THIS SOURCE CODE FORM IS SUBJECT TO THE TERMS OF THE PUBLIC
* APACHE LICENSE V2.0. A COMPLETE COPY OF THE LICENSE TEXT IS
* INCLUDED IN THE LICENSE FILE. 
*
*/

class Fuse_Requirements implements \Fuse\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    private $_all_ok = true;
    private $_app_path = BF_PATH_ROOT;
    private $_options = array();

    public function __construct()
    {
        $this->_options = array(
            'php'   =>  array(
                'extensions' => array(
                    'pdo_mysql',
                    'curl',
                    'zlib',
                    'gettext',
                    'mcrypt',
                 ),
                'version'       =>  PHP_VERSION,
                'min_version'   =>  '5.3',
                'safe_mode'     =>  ini_get('safe_mode'),
            ),
            'writable_folders' => array(
                $this->_app_path . '/data/cache',
                $this->_app_path . '/data/log',
                $this->_app_path . '/data/uploads',
            ),
            'writable_files' => array(
                $this->_app_path . '/config.php',
            ),
        );
    }

    public function getOptions()
    {
        return $this->_options;
    }

    public function getInfo()
    {
        $data = array();
        $data['ip']             = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null;
        $data['PHP_OS']         = PHP_OS;
        $data['PHP_VERSION']    = PHP_VERSION;

        $data['bf']    = array(
            'BF_LOCALE'     =>  $this->di['config']['locale'],
            'BF_SEF_URLS'   =>  BF_SEF_URLS,
            'version'       =>  Fuse_Version::VERSION,
        );

        $data['ini']    = array(
            'allow_url_fopen'   =>  ini_get('allow_url_fopen'),
            'safe_mode'         =>  ini_get('safe_mode'),
            'memory_limit'      =>  ini_get('memory_limit'),
            'magic_quotes'      =>  (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()),
        );

        $data['permissions']    = array(
            BF_PATH_UPLOADS     =>  substr(sprintf('%o', fileperms(BF_PATH_UPLOADS)), -4),
            BF_PATH_DATA        =>  substr(sprintf('%o', fileperms(BF_PATH_DATA)), -4),
            BF_PATH_CACHE       =>  substr(sprintf('%o', fileperms(BF_PATH_CACHE)), -4),
            BF_PATH_LOG         =>  substr(sprintf('%o', fileperms(BF_PATH_LOG)), -4),
        );
        
        $data['extensions']    = array(
            'apc'           => extension_loaded('apc'),
            'curl'          => extension_loaded('curl'),
            'pdo_mysql'     => extension_loaded('pdo_mysql'),
            'zlib'          => extension_loaded('zlib'),
            'mbstring'      => extension_loaded('mbstring'),
            'mcrypt'        => extension_loaded('mcrypt'),
            'gettext'       => extension_loaded('gettext'),
        );
        
        //determine php username
        if(function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $data['posix_getpwuid'] = posix_getpwuid(posix_geteuid());
        }
        return $data;
    }
    
    public function isPhpVersionOk()
    {
        $current = $this->_options['php']['version'];
        $required = $this->_options['php']['min_version'];
        return version_compare($current, $required, '>=');
    }

    /**
     * What extensions must be loaded for BillingFuse o function correctly
     */
    public function extensions()
    {
        $exts = $this->_options['php']['extensions'];

        $result = array();
        foreach($exts as $ext) {
            if(extension_loaded($ext)) {
                $result[$ext] = true;
            } else {
                $result[$ext] = false;
                $this->_all_ok = false;
            }
        }

        return $result;
    }

    /**
     * Files that must be writable
     */
    public function files()
    {
        $files = $this->_options['writable_files'];
        $result = array();

        foreach($files as $file) {
            if ($this->checkPerms($file)) {
                $result[$file] = true;
            } else if (is_writable($file)) {
            	$result[$file] = true;
            } else {
                $result[$file] = false;
                $this->_all_ok = false;
            }
        }

        return $result;
    }

    /**
     * Folders that must be writable
     */
    public function folders()
    {
        $folders = $this->_options['writable_folders'];

        $result = array();
        foreach($folders as $folder) {
            if($this->checkPerms($folder)) {
                $result[$folder] = true;
            } else if (is_writable($folder)) {
            	$result[$folder] = true;
            } else {
                $result[$folder] = false;
                $this->_all_ok = false;
            }
        }

        return $result;
    }

    /**
     * Check if we can continue with installation
     * @return bool
     */
    public function canInstall()
    {
        $this->extensions();
        $this->folders();
        $this->files();
        return $this->_all_ok;
    }

    /**
     * Check permissions
     * @param string $path
     * @param string $perm
     * @return bool
     */
    public function checkPerms($path, $perm = '0777')
    {
        clearstatcache();
        $configmod = substr(sprintf('%o', @fileperms($path)), -4);
        return ($configmod == $perm);
    }

}