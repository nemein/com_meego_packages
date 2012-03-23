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
     * Retrieves the user specified by it person property
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
     * Retrieves the user specified by guid
     *
     * @param guid user's guid
     * @return object midgard_user object
     */
    public static function get_user_by_guid($guid = '')
    {
        $user = null;

        if (mgd_is_guid($guid))
        {
            $storage = new midgard_query_storage('midgard_user');
            $q = new midgard_query_select($storage);

            $q->set_constraint(new midgard_query_constraint(
               new midgard_query_property('guid'),
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

    /**
     * Returns the translated i18n string
     *
     * @param string id of the string
     * @return string
     */
    public function get_i18n_string(array $args)
    {
        if (array_key_exists('id', $args))
        {
            $this->data['i18n'] = midgardmvc_core::get_instance()->i18n->get($args['id']);
        }
    }

    /**
     * Returns the stripped down form on a workflow
     *
     * @param object com_meego_package_details object
     */
    public function get_stripped_forms_for_package($package = null)
    {
        $retval = array();
        $object = new com_meego_package($package->packageguid);
        $workflows = midgardmvc_helper_workflow_utils::get_workflows_for_object($object);

        if (is_array($workflows))
        {
            $this->mvc->component->load_library('Workflow');

            foreach ($workflows as $name => $workflow_data)
            {
                $args = array(
                    'package' => $package->packagename,
                    'version' => $package->packageversion,
                    'project' => $package->repoprojectname,
                    'repository' => $package->reponame,
                    'arch' => $package->repoarch,
                    'workflow' => $name
                );

                $workflow_definition = new $workflow_data['provider'];

                $values = $workflow_definition->start($object);
                $workflow = $workflow_definition->get();

                if (isset($values['review_form']))
                {
                    $form = new midgardmvc_ui_forms_form($values['review_form']);
                    $fields = midgardmvc_ui_forms_generator::list_fields($form);
                    foreach ($fields as $field)
                    {
                        $retval[$name][$field->title]['widget'] = $field->widget;
                        $retval[$name][$field->title]['options'] = $field->options;
                    }
                }
                else if (isset($values['execution']))
                {
                    $args['execution'] = $values['execution'];

                    $execution = new midgardmvc_helper_workflow_execution_interactive($workflow, $args['execution']);
                    $variables = $execution->getVariables();

                    if (isset($variables['review_form']))
                    {
                        $form = new midgardmvc_ui_forms_form($variables['review_form']);
                        $fields = midgardmvc_ui_forms_generator::list_fields($form);
                        foreach ($fields as $field)
                        {
                            $retval[$name][$field->title]['widget'] = $field->widget;
                            $retval[$name][$field->title]['options'] = $field->options;
                        }
                    }
                }
            }
        }

        return $retval;
    }
}
?>