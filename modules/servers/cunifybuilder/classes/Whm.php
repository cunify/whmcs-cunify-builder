<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


/**
 * Description of Member
 *
 * @author sbc
 */

class Whm
{

    public $message = '';
    public $user_id = '';
    public $domain = '';
    public $server = '';
    public $cpanel = '';
    public $account_exist = 0;
    public $ignore_error_log = 0;
    public $whm_account_user = '';
    public $prefix = 'host';

    public function __construct()
    {

        define("CPANEL_API_1", 1);
        define("CPANEL_API_2", 2);
        define("UAPI", 3);
    }

    public function setupConnection($account)
    {

        $host_url = $this->server['hostname'] . ':2087';

        $cpanel = new \Gufy\CpanelPhp\Cpanel([
            'host' => $host_url,
            'username' => $this->server['username'],
            'auth_type' => 'hash', // there is also an option to use "hash"
            'password' => $this->server['accesshash'], // if you use hash, get the value from WHM's Remote Access Key if not use the root password here
        ]);

        $this->cpanel = $cpanel;

        return $cpanel;
    }

    public function searchAccount($account)
    {

        $cpanel = $this->setupConnection($account);

        $search_arr = array('searchtype' => 'domain', 'searchmethod' => 'exact', 'search' => $account['domain']);
        $whm_account_str = $cpanel->listaccts($search_arr);

        $whm_account = json_decode($whm_account_str, true);

        if (!empty($whm_account['acct'])) {
            return $whm_account['acct'][0];
        }

        return false;
    }

    public function createAccount($account)
    {

        $cpanel = $this->setupConnection($account);

        $new_cpanel = $cpanel->createAccount($account['domain'], $account['username'], $account['password'], $account['configoption1']);

        $this->setEnqueuedMessage($new_cpanel);
    }

    public function createLoginLink($account, $app = '')
    {

        $cpanel = $this->setupConnection($account);

        $options = [
            'user' => $account['username'],
            'service' => 'cpaneld',
            'api.version' => 1,
        ];

        if ($app != '') {
            $options['app'] = $app;
        }

        $result = $cpanel->create_user_session($options);

        if ($result == false) {
            return false;
        }
        if ($result) {
            $decoded_response = json_decode($result, true);
            if (isset($decoded_response['data']) && !empty($decoded_response['data'])) {
                $url = $decoded_response['data']['url'];
                return $url;
            }
        }
    }

    public function changePasswords($account)
    {

        $cpanel = $this->setupConnection($account);

        $email_account = $this->getEmailAccount($account);
        $ftp_account = $this->getFtpAccount($account);
        $database_account = $this->getDatabaseName($account);

        //Change cpanel password
        $result = $cpanel->passwd([
            'user' => $account['username'],
            'pass' => $account['password'],
        ]);

        //Change email password
        $result = $cpanel->execute_action(
            UAPI, 'Email', 'passwd_pop', $account['username'], array(
                'domain' => $account['domain'],
                'email' => $email_account,
                'password' => $account['password'],
            )
        );
        $this->setEnqueuedMessage($result);

        //Change ftp password
        $result = $cpanel->execute_action(
            UAPI, 'Ftp', 'passwd', $account['username'], array(
                'user' => $ftp_account,
                'pass' => $account['password'],
            )
        );
        $this->setEnqueuedMessage($result);

        //Change database table password
        $grant = $cpanel->execute_action(
            UAPI, 'Mysql', 'set_privileges_on_database', $account['username'], [
                'user' => $database_account,
                'database' => $database_account,
                'privileges' => 'ALL PRIVILEGES',
            ]
        );
        $this->setEnqueuedMessage($grant);

        $result = $cpanel->execute_action(
            UAPI, 'Mysql', 'set_password', $account['username'], array(
                'user' => $database_account,
                'password' => $account['password'],
            )
        );
        $this->setEnqueuedMessage($result);
    }

    public function unsuspendAccount($account)
    {

        $cpanel = $this->setupConnection($account);

        if (is_object($cpanel)) {
            $result = $cpanel->unsuspendacct([
                'user' => $account['username'],
            ]);

            $this->setEnqueuedMessage($result);
        }
    }

    public function suspendAccount($account)
    {

        $cpanel = $this->setupConnection($account);

        if (is_object($cpanel)) {
            $result = $cpanel->suspendacct([
                'user' => $account['username'],
                'reason' => 'Nonpayment',
            ]);

            $this->setEnqueuedMessage($result);
        }
    }

    public function removeAccount($account)
    {

        $cpanel = $this->setupConnection($account);

        if (is_object($cpanel)) {
            $result = $cpanel->removeacct([
                'user' => $account['username'],
            ]);

            $this->setEnqueuedMessage($result);
        }
    }
    public function changeDomainAccount($account, $whm_account)
    {

        $cpanel = $this->setupConnection($account);

        $result = $cpanel->modifyacct(array(
            'user' => $whm_account['user'],
            'DNS' => $account['old_domain'],
        ));

        $this->setEnqueuedMessage($result);
    }

    public function modifyUsernameAccount($account, $whm_account)
    {

        $cpanel = $this->setupConnection($account);

        $result = $cpanel->modifyacct(array(
            'user' => $whm_account['user'],
            'newuser' => $account['username'],
        ));

        $this->setEnqueuedMessage($result);
    }

    public function modifyEmailAccount($account)
    {

        $cpanel = $this->setupConnection($account);

        $result = $cpanel->modifyacct([
            'user' => $account['username'],
            'contactemail' => $account['email'],
        ]);

        $this->setEnqueuedMessage($result);
    }

    //xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx Ftp Functions

    public function getFtpAccount($account)
    {

        $ftp_account = 'ftp' . $account['username'] . '@' . $account['domain'];

        return $ftp_account;
    }

    public function createFtp($account)
    {

        $cpanel = $this->setupConnection($account);

        $ftp_account = $this->getFtpAccount($account);

        $new_ftp = $cpanel->execute_action(
            UAPI, 'Ftp', 'ftp_exists', $account['username'], array(
                'user' => $ftp_account,
            )
        );

        $new_ftp_obj = json_decode($new_ftp);

        if (!$new_ftp_obj->result->status) {

            $new_ftp = $cpanel->execute_action(
                UAPI, 'Ftp', 'add_ftp', $account['username'], array(
                    'user' => $ftp_account,
                    'pass' => $account['password'],
                    'homedir' => 'public_html',
                )
            );

            $this->setEnqueuedMessage($new_ftp);
        }
    }

    //xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx Email Functions

    public function getEmailAccount($account)
    {

        $email_account = $account['username'];

        return $email_account;
    }

    public function createEmail($account)
    {

        $cpanel = $this->setupConnection($account);

        $email_account = $this->getEmailAccount($account);

        $exist_email = $cpanel->execute_action(
            UAPI, 'Email', 'list_pops', $account['username'], array(
                'regex' => $email_account,
            )
        );

        $new_email_obj = json_decode($exist_email);

        if (!$new_email_obj->status) {

            $new_email = $cpanel->execute_action(
                UAPI, 'Email', 'add_pop', $account['username'], array(
                    'email' => $email_account,
                    'password' => $account['password'],
                    'quota' => '0',
                    'domain' => $account['domain'],
                    'skip_update_db' => '0',
                )
            );

            $this->setEnqueuedMessage($new_email);
        }
    }

    //xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx Database Functions
    public function getDatabaseName($account)
    {

        $str_username = substr($account['username'], 0, 8) . '_' . 'main';

        return $str_username;
    }

    public function createDatabase($account)
    {

        $cpanel = $this->setupConnection($account);

        $prefix_anewdb = $this->getDatabaseName($account);

        $check_db = $cpanel->execute_action(
            UAPI, 'Mysql', 'check_database', $account['username'], [
                'name' => $prefix_anewdb,
            ]
        );

        if (!$check_db->status) {

            $data = $cpanel->execute_action(UAPI, 'Mysql', 'create_database', $account['username'], ['name' => $prefix_anewdb]);

            //create the new user!
            $usr = $cpanel->execute_action(
                UAPI, 'Mysql', 'create_user', $account['username'], [
                    'name' => $prefix_anewdb,
                    'password' => $account['password'],
                ]
            );

            $this->setEnqueuedMessage($usr);

            //grant everything
            $grant = $cpanel->execute_action(
                UAPI, 'Mysql', 'set_privileges_on_database', $account['username'], [
                    'user' => $prefix_anewdb,
                    'database' => $prefix_anewdb,
                    'privileges' => 'ALL PRIVILEGES',
                ]
            );

            $this->setEnqueuedMessage($grant);
        }
    }

    //xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx Utility Functions

    public function setEnqueuedMessage($new_cpanel)
    {

        $factory = new CunifyFactory();

        if ($this->ignore_error_log) {
            return;
        }

        $new_cpanel_arr = json_decode($new_cpanel, true);

        if ($new_cpanel_arr['result'] && $new_cpanel_arr['result'][0]['status'] == 0 && $new_cpanel_arr['result'][0]['statusmsg'] != '') {
            $factory->enqueueMessage($new_cpanel_arr['result'][0]['statusmsg'], 'error');
        }

        if (is_array($new_cpanel_arr['result']['errors']) && !empty($new_cpanel_arr['result']['errors'])) {

            $error_msg = implode('', $new_cpanel_arr['result']['errors']);

            if ($error_msg != '') {
                $factory->enqueueMessage('', 'error');
                $factory->enqueueMessage($error_msg, 'error');
            }
        }
    }

}
