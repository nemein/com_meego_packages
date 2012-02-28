<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Provides a session based authentication method that validates logins with an LDAP service
 *
 * @package midgardmvc_core
 */

class com_meego_packages_services_authentication_ldap extends midgardmvc_core_services_authentication_ldap
{
    private $server = '';
    private $dn = '';

    public function __construct()
    {
        $ldap_settings = midgardmvc_core::get_instance()->configuration->services_authentication_ldap;
        $this->server = $ldap_settings['server'];
        $this->dn = $ldap_settings['dn'];
        parent::__construct();
    }

    /**
     * Validate user against LDAP and then generate a session
     */
    public function create_login_session(array $tokens, $clientip = null)
    {
        // Validate user against LDAP
        $ldapuser = $this->ldap_authenticate($tokens);

        if (! $ldapuser)
        {
            // we could also return an error message here
            return false;
        }

        // LDAP authentication handled, we don't need the password any longer
        unset($tokens['password']);
        $tokens['authtype'] = 'LDAP';

        // If user is already in DB we can just log in
        // catch: this will create a person object
        $session = midgardmvc_core_services_authentication_sessionauth::create_login_session($tokens, $clientip);

        if ($session)
        {
            // check if the logged in user has a person object
            // if not, then create it and assign the new person to the user object
            $user = midgardmvc_core::get_instance()->authentication->get_user();

            if ($user)
            {
                $person = new midgard_person($user->person);

                if ($person)
                {
                    return true;
                }
            }

            // @todo: verify if we ever get here actually because we should not
            $persons = $this->get_persons($ldapuser, $user->person);

            if (count($persons) == 0)
            {
                $person = $this->create_person($ldapuser, $tokens);
                if ($person)
                {
                    $user->set_person($person);
                    $user->update();
                }
            }
            return true;
        }

        // Otherwise we need to create the necessary Midgard account
        if (! $this->create_account($ldapuser, $tokens))
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item
            (
                'midgardmvc_core_services_authentication_message',
                midgardmvc_core::get_instance()->i18n->get('midgard account creation failed', 'midgardmvc_core')
            );
            return false;
        }
        // ..and log in
        return midgardmvc_core_services_authentication_sessionauth::create_login_session($tokens, $clientip);
    }

    /**
     * Creates an account
     */
    private function create_account(array $ldapuser, array $tokens)
    {
        $user = null;
        $person = null;
        midgardmvc_core::get_instance()->authorization->enter_sudo('midgardmvc_core');
        $transaction = new midgard_transaction();
        $transaction->begin();

        $persons = $this->get_persons($ldapuser);

        if (count($persons) == 0)
        {
            $person = $this->create_person($ldapuser, $tokens);
        }
        else
        {
            // we have multiple persons with the same firstname and lastname
            // let's see the corresponding midgard_user object and its login field

            foreach ($persons as $person)
            {
                $user = com_meego_packages_utils::get_user_by_person_guid($person->guid);
                if ($user->login == $tokens['login'])
                {
                    break;
                }
                else
                {
                    $user = null;
                    $person = null;
                }
            }
        }

        if (! $user)
        {
            if (! $person)
            {
                $person = $this->create_person($ldapuser, $tokens);
            }

            if ($person)
            {
                $user = new midgard_user();
                $user->login = $tokens['login'];
                $user->password = '';
                $user->usertype = 1;
                $user->authtype = 'LDAP';
                $user->active = true;
                $user->set_person($person);

                if (! $user->create())
                {
                    midgardmvc_core::get_instance()->log
                    (
                        __CLASS__,
                        "Creating midgard_user for LDAP user failed: " . midgard_connection::get_instance()->get_error_string(),
                        'warning'
                    );

                    $transaction->rollback();
                    midgardmvc_core::get_instance()->authorization->leave_sudo();
                    return false;
                }
            }
        }

        midgardmvc_core::get_instance()->authorization->leave_sudo();

        if (! $transaction->commit())
        {
            return false;
        }

        return true;
    }

    /**
     * Creates and returns a person object
     */
    private function create_person($ldapuser = null, $tokens = null)
    {
        if (! $ldapuser)
        {
            return false;
        }

        $person = new midgard_person();

        $firstname = $ldapuser['firstname'];
        $lastname = $ldapuser['lastname'];

        if (   $firstname == ''
            || $firstname == '--')
        {
            $firstname = $tokens['login'];
        }

        if (   $lastname == ''
            || $lastname == '--')
        {
            $lastname = '';
        }

        $person->firstname = $firstname;
        $person->lastname = $lastname;

        if (! $person->create())
        {
            midgardmvc_core::get_instance()->log
            (
                __CLASS__,
                "Creating midgard_person for LDAP user failed: " . midgard_connection::get_instance()->get_error_string(),
                'warning'
            );

            $transaction->rollback();
            midgardmvc_core::get_instance()->authorization->leave_sudo();
            return false;
        }

        $person->set_parameter('midgardmvc_core_services_authentication_ldap', 'employeenumber', $ldapuser['employeenumber']);

        return $person;
    }

    /**
     * Checks if an account is avaialable
     *
     * @return array with user information (username, firstname, lastname, email, emloyeenumber)
     *               or null if the account does not exist
     */
    public function ldap_check($tokens = null)
    {
        $userinfo = null;

        if (! array_key_exists('login', $tokens))
        {
            return $userinfo;
        }

        $ds = ldap_connect($this->server);

        if (! $ds)
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message',
                midgardmvc_core::get_instance()->i18n->get('ldap authentication failed: no connection to server', 'midgardmvc_core')
            );

            return null;
        }

        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        $userinfo = parent::ldap_search($ds, $tokens['login']);

        if (   $userinfo
            && array_key_exists('password', $tokens))
        {
            // the user exists in LDAP, let's try the password too (by binding)
            if (! @ldap_bind($ds, "cn={$tokens['login']},{$this->dn}", $tokens['password']))
            {
                // auth failed, let's null the userinfo array
                $userinfo = null;
            }
        }

        ldap_close($ds);

        return $userinfo;
    }

    /**
     * Performs an LDAP bind; ie. authenticates
     *
     * @return Array with username (uid), firstname (cn) and email (mail) coming from LDAP
     */
    public function ldap_authenticate(array $tokens)
    {
        if (   ! isset($tokens['login'])
            || ! isset($tokens['password']))
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message',
                midgardmvc_core::get_instance()->i18n->get('ldap authentication requires login and password', 'midgardmvc_core')
            );

            return null;
        }

        $ds = ldap_connect($this->server);
        if (! $ds)
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message',
                midgardmvc_core::get_instance()->i18n->get('ldap authentication failed: no connection to server', 'midgardmvc_core')
            );

            return null;
        }

        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

        try
        {
            $userinfo = @ldap_bind($ds, "cn={$tokens['login']},{$this->dn}", $tokens['password']);
            if ($userinfo)
            {
                // Valid account
                $userinfo = parent::ldap_search($ds, $tokens['login']);
                ldap_close($ds);
                return $userinfo;
            }
            else
            {
                // we probably have invalid credentials, so no user info must be supplied
                // at the end of the code we will return null anyway.
            }
        }
        catch(Exception $e)
        {
            die('How on earth do we get here:' . $e->getMessage());
        }

        ldap_close($ds);

        midgardmvc_core::get_instance()->context->get_request()->set_data_item(
            'midgardmvc_core_services_authentication_message',
            midgardmvc_core::get_instance()->i18n->get('ldap authentication failed: login and password don\'t match', 'midgardmvc_core')
        );

        return null;
    }

    /**
     * Returns all person object that match the given ldapuser details
     */
    private function get_persons($ldapuser = null, $person_guid = null)
    {
        $retval = false;

        if (is_array($ldapuser)
            && array_key_exists('firstname', $ldapuser)
            && array_key_exists('lastname', $ldapuser))
        {
            $storage = new midgard_query_storage('midgard_person');
            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint_group('AND');

            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('firstname'),
                '=',
                new midgard_query_value($ldapuser['firstname'])
            ));
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('lastname'),
                '=',
                new midgard_query_value($ldapuser['lastname'])
            ));

            if ($person_guid)
            {
                $qc->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('guid'),
                    '=',
                    new midgard_query_value($person_guid)
                ));
            }

            $q->set_constraint($qc);
            $q->execute();

            //$q->toggle_readonly(false);
            $retval = $q->list_objects();
        }

        return $retval;
    }
}
