<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Hosting\Hosting\Code\Classes;

/**
 * Description of Member
 *
 * @author sbc
 */

class Cpanel {

    public $message = '';
    public $installed_call_count = 0;
    public $user_id = '';
    public $has_ftp_connection = false;

    public function getThemeName($account) {

        $account_cls = new Account();

        return $account_cls->getThemeName($account);
    }

    public function setupLoginScript($account) {

        $factory = new KazistFactory();

        $extract_path = JPATH_ROOT . 'uploads/updates/' . $account->username;

        $factory->makeDir($extract_path, 0777);

        $this->recursiveDelete($extract_path);

        $installer = file_get_contents(JPATH_ROOT . 'templates/wp-installer.twig');
        $autologin = file_get_contents(JPATH_ROOT . 'templates/wp-autologin.twig');
        $autologin_str = $factory->renderString($autologin, (array) $account);
        $installer_str = $factory->renderString($installer, (array) $account);
        file_put_contents($extract_path . '/wp-autologin.php', $autologin_str);
        file_put_contents($extract_path . '/wp-installer.php', $installer_str);

        $this->uploadFiles($extract_path, $account);

        $installer_url = $account->domain . '/wp-installer.php';

        $this->curlCall($installer_url, true);
    }

    public function setupWebsite($account) {

        $email = new Email();
        $factory = new KazistFactory();

        $extract_path = JPATH_ROOT . 'uploads/updates/' . $account->username;
        $template_path = JPATH_ROOT . 'templates/' . $this->getThemeName($account);

        if ($this->isReadyForInstall($account, $extract_path) || $account->is_update) {

            $factory->makeDir($extract_path, 0777);

            $this->backupSite($account);
            $this->recursiveDelete($extract_path);
            $this->zipExtract($template_path, $extract_path);
            $this->editConfigFile($template_path, $extract_path, $account);

            $this->uploadFiles($extract_path, $account);

            $installer_url = $account->domain . '/wp-installer.php';

            $this->curlCall($installer_url, true);

            if ($account->is_update) {
                $email->sendDefinedLayoutEmail('hosting.hosting.whm.backupaccount', $account->email, $account);
            } else {
                $email->sendDefinedLayoutEmail('hosting.hosting.whm.createaccount', $account->email, $account);
            }
        }
    }

    public function saveChangeDomain($account) {

        $email = new Email();
        $factory = new KazistFactory();

        $extract_path = JPATH_ROOT . 'uploads/updates/' . $account->username;
        $template_path = JPATH_ROOT . 'templates/';

        if ($this->isReadyForInstall($account, $extract_path)) {

            $factory->makeDir($extract_path, 0777);

            $this->backupSite($account);
            $this->recursiveDelete($extract_path);
            $this->editChangeDomainFile($template_path, $extract_path, $account);

            $this->uploadFiles($extract_path, $account);

            $installer_url = $account->domain . '/wp-changedomain.php';

            $this->curlCall($installer_url, true);
        } else {
            $factory->enqueueMessage('Change the domain has failed', 'error');
        }
    }

    public function searchAccount($account) {

        $code_arr = array(200, 301, 302, 403);
        $domain_url = $account->domain;
        $analyser_url = $account->domain . '/wp-analyser.php';

        $analysis = $this->curlCall($analyser_url, true);
        $domain = $this->curlCall($domain_url, true);

        if (in_array($domain['httpcode'], $code_arr) && $analysis['httpcode'] == 200) {
            return true;
        }

        return false;
    }

    public function backupSite($account) {

        $folder = 'archive/';
        $backup_url = $account->domain . '/wp-backup.php';
        $extract_path = JPATH_ROOT . 'uploads/updates/' . $account->username;

        $factory = new KazistFactory();

        $factory->makeDir($extract_path . '/' . $folder, '0777');

        if ($factory->getSetting('hosting_hosting_backup_mirror')) {
            $mirror = new Mirror();
            $mirror->download_folder = JPATH_ROOT . 'uploads/hosting/backup/' . $account->username . '/';
            $mirror->processMirror($account);
            $this->zipData($mirror->download_folder, $extract_path . '/' . $folder . '/mirror.zip');
        }

        $this->uploadBackupScript($extract_path, $account);
        $this->curlCall($backup_url, true);

        $this->recursiveDelete($mirror->download_folder);
    }

    public function editChangeDomainFile($template_path, $extract_path, $account) {

        $factory = new KazistFactory();

        $sql = file_get_contents($template_path . '/changedomain.sql');
        $changedomain = file_get_contents(JPATH_ROOT . 'templates/wp-changedomain.twig');

        $account->database_name = substr($account->username, 0, 8) . '_' . 'main';
        $account->database_user = $account->database_name;
        $account->database_prefix = 'wp';


        $sql_str = $factory->renderString($sql, (array) $account);
        $changedomain_str = $factory->renderString($changedomain, (array) $account);

        file_put_contents($extract_path . '/changedomain.sql', $sql_str);
        file_put_contents($extract_path . '/wp-changedomain.php', $changedomain_str);
    }

    public function editConfigFile($template_path, $extract_path, $account) {

        $factory = new KazistFactory();

        $sql = file_get_contents($template_path . '/database.sql');
        $htaccess = file_get_contents(JPATH_ROOT . 'templates/htaccess.twig');
        $config = file_get_contents(JPATH_ROOT . 'templates/wp-config.twig');
        $autologin = file_get_contents(JPATH_ROOT . 'templates/wp-autologin.twig');
        $installer = file_get_contents(JPATH_ROOT . 'templates/wp-installer.twig');
        $salt = file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/');

        $account->database_name = substr($account->username, 0, 8) . '_' . 'main';
        $account->database_user = $account->database_name;
        $account->database_prefix = 'wp';
        $account->salt = $salt;


        $htaccess_str = $factory->renderString($htaccess, (array) $account);
        $installer_str = $factory->renderString($installer, (array) $account);
        $config_str = $factory->renderString($config, (array) $account);
        $autologin_str = $factory->renderString($autologin, (array) $account);
        $sql_str = $factory->renderString($sql, (array) $account);

        file_put_contents($extract_path . '/.htaccess', $htaccess_str);
        file_put_contents($extract_path . '/wp-installer.php', $installer_str);
        file_put_contents($extract_path . '/wp-config.php', $config_str);
        file_put_contents($extract_path . '/wp-autologin.php', $autologin_str);
        file_put_contents($extract_path . '/database.sql', $sql_str);
    }

    function zipData($source, $destination, $ignore_arr = array()) {

        if (extension_loaded('zip')) {
            if (file_exists($source)) {
                $zip = new \ZipArchive();
                if ($zip->open($destination, \ZIPARCHIVE::CREATE)) {
                    $source = realpath($source);
                    if (is_dir($source)) {
                        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);
                        foreach ($files as $file) {
                            $file = realpath($file);
                            if (!in_array($file, $ignore_arr)) {

                                if (is_dir($file)) {
                                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                                } else if (is_file($file)) {
                                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                                }
                            }
                        }
                    } else if (is_file($source)) {
                        $zip->addFromString(basename($source), file_get_contents($source));
                    }
                }
                return $zip->close();
            }
        }

        return false;
    }

    public function zipExtract($template_path, $extract_path) {

        $zip = new \ZipArchive;
        $factory = new KazistFactory();

        if ($zip->open($template_path . '/files.zip') === TRUE) {

            $zip->extractTo($extract_path);
            $zip->close();

            $factory->enqueueMessage(' Zip File Extracted Successfully.');

            return true;
        } else {
            $factory->enqueueMessage('Extracting Zip File Failed.');

            return false;
        }
    }

    public function ftpConnection($account) {

        $ftp_account = 'ftp' . $account->username . '@' . $account->domain;

        $server = 'ftp.' . $account->domain;
        $ftp_user_name = $ftp_account;
        $ftp_user_pass = substr($account->password, 0, 10);

        // xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx FTP
        $ftp = new FtpNew($server);

        $this->has_ftp_connection = $ftp->login($ftp_user_name, $ftp_user_pass);

        return $ftp;
    }

    public function isReadyForInstall($account, $extract_path) {

        $domain_url = $account->domain;
        $analyser_url = $account->domain . '/wp-analyser.php';

        $whm = new Whm();

        $cpanel = $whm->searchAccount($account);

        $domain = $this->curlCall($domain_url, true);
        $analysis = $this->curlCall($analyser_url, true);

        if (is_array($cpanel) && $cpanel['uid']) {

            if ($analysis['httpcode'] == 200) {

                $data = json_decode($analysis['resp'], TRUE);

                return ((int) $data['installed']) ? false : true;
            } else {

                $this->installed_call_count = $this->installed_call_count + 1;

                if ($this->installed_call_count < 3) {
                    //upload analyzer and call isInstalled

                    $this->uploadAnalyser($extract_path, $account);
                    return $this->isReadyForInstall($account, $extract_path);
                }
            }
        }

        return false;
    }

    public function curlCall($tmp_url, $rewrite = false) {

        $result = array();

        $url = (!$rewrite) ? $tmp_url : $this->getClearUrl($tmp_url);

        // ob_start();
        //$out = fopen('php://output', 'w');


        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, array(
            CURLOPT_HEADER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_3_3 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5'
        ));

        //curl_setopt($curl, CURLOPT_VERBOSE, true);
        // curl_setopt($curl, CURLOPT_STDERR, $out);
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // Close request to clear up some resources
        curl_close($curl);

        // fclose($out);
        //$debug = ob_get_clean();
        // print_r($debug);


        $result['httpcode'] = $httpcode;
        $result['resp'] = $resp;

        return $result;
    }

    function url_valid(&$url) {
        $file_headers = @get_headers($url);
        if ($file_headers === false)
            return false; // when server not found
        foreach ($file_headers as $header) { // parse all headers:
            // corrects $url when 301/302 redirect(s) lead(s) to 200:
            if (preg_match("/^Location: (http.+)$/", $header, $m))
                $url = $m[1];
            // grabs the last $header $code, in case of redirect(s):
            if (preg_match("/^HTTP.+\s(\d\d\d)\s/", $header, $m))
                $code = $m[1];
        } // End foreach...
        if ($code == 200)
            return true; // $code 200 == all OK
        else
            return false; // All else has failed, so this must be a bad link
    }

// End function url_exists

    public function getClearUrl($url) {

        $bits = parse_url($url);

        $newHost = substr($bits["host"], 0, 4) !== "www." ? "www." . $bits["host"] : $bits["host"];

        $bits["scheme"] = ($bits["scheme"] <> '') ? $bits["scheme"] : 'http';

        $new_url = $bits["scheme"] . "://" . $newHost . (isset($bits["port"]) ? ":" . $bits["port"] : "") . $bits["path"] . (!empty($bits["query"]) ? "?" . $bits["query"] : "");

        return $new_url;
    }

    public function uploadBackupScript($extract_path, $account) {

        $factory = new KazistFactory();

        $this->recursiveDelete($extract_path);

        $factory->makeDir($extract_path, 0777);

        $backup = file_get_contents(JPATH_ROOT . 'templates/wp-backup.twig');
        $backup_str = $factory->renderString($backup, (array) $account);
        file_put_contents($extract_path . '/wp-backup.php', $backup_str);

        $this->sendViaFTP($extract_path, $account);
    }

    public function uploadFiles($extract_path, $account) {

        $ignore_arr = array(
            $extract_path . '/upload.zip',
            $extract_path . '/wp-installer.php',
            $extract_path . '/database.sql',
            $extract_path . '/index.html',
        );

        chmod($extract_path, 0777);

        $this->zipData($extract_path, $extract_path . '/upload.zip', $ignore_arr);

        $this->recursiveDelete($extract_path, $ignore_arr);
        $this->sendViaFTP($extract_path, $account);

        $this->recursiveDelete($extract_path);
    }

    function chmodRecursively($dir, $dirPermissions, $filePermissions) {
        $dp = opendir($dir);
        while ($file = readdir($dp)) {
            if (($file == ".") || ($file == ".."))
                continue;

            $fullPath = $dir . "/" . $file;

            if (is_dir($fullPath)) {
                echo('DIR:' . $fullPath . "\n");
                chmod($fullPath, $dirPermissions);
                chmod_r($fullPath, $dirPermissions, $filePermissions);
            } else {
                echo('FILE:' . $fullPath . "\n");
                chmod($fullPath, $filePermissions);
            }
        }
        closedir($dp);
    }

    public function uploadAnalyser($extract_path, $account) {

        $factory = new KazistFactory();

        $this->recursiveDelete($extract_path);

        $factory->makeDir($extract_path, 0777);
        chmod($extract_path, 0777);

        $analyser = file_get_contents(JPATH_ROOT . 'templates/wp-analyser.twig');
        $analyser_str = $factory->renderString($analyser, (array) $account);

        file_put_contents($extract_path . '/wp-analyser.php', $analyser_str);

        $errorList = $this->sendViaFTP($extract_path, $account);
    }

    public function sendViaFTP($extract_path, $account) {

        $ftp_directory = '/';

        $ftp = $this->ftpConnection($account);

        if ($this->has_ftp_connection) {
            $errorList = $ftp->send_recursive_directory($extract_path, $ftp_directory);
        }

        return $errorList;
    }

    function recursiveDelete($dir, $ignore_arr = array()) {


        if (is_dir($dir)) {
            $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($files as $file) {

                $file_path = $file->getRealPath();

                if (!in_array($file_path, $ignore_arr)) {
                    if ($file->isDir()) {
                        rmdir($file_path);
                    } else {
                        if (!unlink($file_path)) {
                            return false;
                        }
                    }
                } else {
                    
                }
            }


            if (!empty($ignore_arr)) {

                $iterator = new \FilesystemIterator($dir);
                $isDirEmpty = !$iterator->valid();

                if ($isDirEmpty) {
                    rmdir($dir);
                }
            }

            return true;
        }
    }

}
