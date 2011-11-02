<?php
/**
 * @package com_meego_packages
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_packages_utils
{
    /**
     * Returns the curretnly logged in user's object
     *
     * @return object midgard_user object of the current user
     */
    public static function get_current_user()
    {
        $mvc = midgardmvc_core::get_instance();
        return $mvc->authentication->get_user();
    }

    /**
     * Requires a user to be logged in
     * If not logged then redirect to the login page otherwise return user
     * object
     *
     * @param string optional redirect page after succesful login
     * @return object midgard_user object
     */
    public static function require_login($redirect = '')
    {
        $mvc = midgardmvc_core::get_instance();

        if (! $mvc->authentication->is_user())
        {
            $login_url = '/mgd:login';

            if (strlen($redirect))
            {
                $login_url .= '?redirect=' . $redirect;
            }

            $mvc->head->relocate($login_url);
        }

        return $mvc->authentication->get_user();
    }

    /**
     * Retrieves the user specified by guid
     *
     * @param guid user's person_guid
     * @return object midgard_user object
     */
    public static function get_user_by_person_guid($guid = '')
    {
        $user = null;

        if (mgd_is_guid($guid))
        {
            $storage = new midgard_query_storage('midgard_user');
            $q = new midgard_query_select($storage);

            $q->set_constraint(new midgard_query_constraint(
               new midgard_query_property('person'),
               '=',
               new midgard_query_value($guid)
            ));

            $q->execute();
            $q->toggle_readonly(false);

            $users = $q->list_objects();

            if (count($users))
            {
                $user = $users[0];
            }

            unset($storage);
            unset($q);
            unset($users);
        }

        return $user;
    }

    /**
     * Returns the link of the avatar image that can be used in e.g. img src
     *
     * @param string user name
     * @return string url
     */
    public function get_avatar($username = null)
    {
        $retval = midgardmvc_core::get_instance()->configuration->default_avatar;

        if ($username)
        {
            midgardmvc_core::get_instance()->authorization->enter_sudo('midgardmvc_core');

            // determine the person guid
            $storage = new midgard_query_storage('midgard_user');
            $q = new midgard_query_select($storage);

            $q->set_constraint(new midgard_query_constraint(
               new midgard_query_property('login'),
               '=',
               new midgard_query_value($username)
            ));

            $q->execute();
            $q->toggle_readonly(false);

            $users = $q->list_objects();

            midgardmvc_core::get_instance()->authorization->leave_sudo();

            if (count($users))
            {
                $user = $users[0];
            }

            $account = midgardmvc_account_injector::get_account($user->person);

            if ($account->guid)
            {
                $retval = $account->avatarurl;
            }
        }

        return $retval;
    }
}
?>