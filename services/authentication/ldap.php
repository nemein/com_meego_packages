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
            return false;
        }

        // LDAP authentication handled, we don't need the password any longer
        unset($tokens['password']);
        $tokens['authtype'] = 'LDAP';

        // If user is already in DB we can just log in
        if (midgardmvc_core_services_authentication_sessionauth::create_login_session($tokens, $clientip))
        {
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

    private function create_account(array $ldapuser, array $tokens)
    {
        $user = null;
        $person = null;
        midgardmvc_core::get_instance()->authorization->enter_sudo('midgardmvc_core');
        $transaction = new midgard_transaction();
        $transaction->begin();

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

        $q->set_constraint($qc);
        $q->execute();
        //$q->toggle_readonly(false);
        $persons = $q->list_objects();

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
     * Create and returns a user object
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
     * Performs an LDAP bind; ie. authenticates
     *
     * @return Array with username (uid), firstname (cn) and email (mail) coming from LDAP
     */
    public function ldap_authenticate(array $tokens)
    {
        if (   !isset($tokens['login'])
            || !isset($tokens['password']))
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message',
                midgardmvc_core::get_instance()->i18n->get('ldap authentication requires login and password', 'midgardmvc_core')
            );

            return null;
        }

        $ds = ldap_connect($this->server);
        if (!$ds)
        {
            midgardmvc_core::get_instance()->context->get_request()->set_data_item(
                'midgardmvc_core_services_authentication_message',
                midgardmvc_core::get_instance()->i18n->get('ldap authentication failed: no connection to server', 'midgardmvc_core')
            );

            return null;
        }

        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

        if (@ldap_bind($ds, "cn={$tokens['login']},{$this->dn}", $tokens['password']))
        {
            // Valid account
            $userinfo = parent::ldap_search($ds, $tokens['login']);
            ldap_close($ds);
            return $userinfo;
        }

        ldap_close($ds);
        midgardmvc_core::get_instance()->context->get_request()->set_data_item(
            'midgardmvc_core_services_authentication_message',
            midgardmvc_core::get_instance()->i18n->get('ldap authentication failed: login and password don\'t match', 'midgardmvc_core')
        );

        return null;
    }
}
