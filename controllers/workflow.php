<?php
class com_meego_packages_controllers_workflow
{
    var $mvc = null;
    var $request = null;
    var $package = null;

    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;

        $this->mvc = midgardmvc_core::get_instance();

        $this->mvc->i18n->set_translation_domain('com_meego_packages');

        $default_language = $this->mvc->configuration->default_language;

        if (! isset($default_language))
        {
            $default_language = 'en_US';
        }

        $this->mvc->i18n->set_language($default_language, false);
    }

    /**
     * Lists all open workflows
     */
    public function get_index(array $args)
    {
        $this->data['workflows'] = self::get_open_workflows();
    }

    /**
     *
     */
    public function post_start_package_instance(array $args)
    {
        $this->package = $this->load_package_instance($args);

        $workflow_class = $this->load_workflow($args);

        $this->mvc->component->load_library('Workflow');

        $workflow = new $workflow_class();
        $values = $workflow->start($this->package);

        if (isset($values['execution']))
        {
            // Workflow suspended and needs input, redirect to workflow page
            $this->mvc->head->relocate
            (
                $this->mvc->dispatcher->generate_url
                (
                    'package_instance_workflow_resume',
                    array
                    (
                        'package' => $this->package->name,
                        'version' => $this->package->version,
                        'project' => $args['project'],
                        'repository' => $args['repository'],
                        'arch' => $args['arch'],
                        'workflow' => $args['workflow'],
                        'execution' => $values['execution'],
                    ),
                    $this->request
                )
            );
        }

        // Workflow completed, redirect to package instance
        if ($this->request->isset_data_item('redirect_link'))
        {
            $redirect_link = $this->request->get_data_item('redirect_link');
        }
        elseif (isset($_POST['redirect_link']))
        {
            $redirect_link = $_POST['redirect_link'];
        }
        else
        {
            $redirect_link = midgardmvc_core::get_instance()->dispatcher->generate_url
            (
                'package_instance',
                array
                (
                    'package' => $this->package->name,
                    'version' => $this->package->version,
                    'project' => $args['project'],
                    'repository' => $args['repository'],
                    'arch' => $args['arch']
                ),
                $this->request
            );
        }

        // Workflow completed, redirect to package instance
        midgardmvc_core::get_instance()->head->relocate($redirect_link);
    }

    /**
     *
     */
    public function get_resume_package_instance(array $args)
    {
        $this->package = $this->load_package_instance($args);

        // populate the package for the template
        $this->data['package'] = $this->package;

        $workflow_class = $this->load_workflow($args);

        $this->mvc->component->load_library('Workflow');

        $this->workflow_definition = new $workflow_class();
        $workflow = $this->workflow_definition->get();
        try
        {
            $this->execution = new midgardmvc_helper_workflow_execution_interactive($workflow, $args['execution']);
        }
        catch (ezcWorkflowExecutionException $e)
        {
            throw new midgardmvc_exception_notfound("Workflow {$args['workflow']} {$args['execution']} not found: " . $e->getMessage());
        }

        $this->execution->resume();

        $waiting_for = $this->execution->getWaitingFor();
        $this->data['forms'] = array();
        foreach ($waiting_for as $variable => $variable_information)
        {
            switch (get_class($variable_information['condition']))
            {
                case 'midgardmvc_ui_forms_workflow_condition_instance':
                    $form_for_variable = $this->get_form($this->execution);

                    if (!is_null($form_for_variable))
                    {
                        $this->data['forms'][$variable] = $form_for_variable;
                    }
                    break;
                // TODO: Handle other typical workflow input types
            }
        }
    }

    /**
     *
     */
    public function post_resume_package_instance(array $args)
    {
        $this->get_resume_package_instance($args);

        if (empty($this->data['forms']))
        {
            throw new midgardmvc_exception_httperror('POST not allowed when there are no forms for the workflow', 405);
        }

        $list_of_variables = array();

        foreach ($this->data['forms'] as $variable => $formdata)
        {
            $formdata['form']->process_post();

            $instance = new midgardmvc_ui_forms_form_instance();
            $instance->form = $formdata['db_form']->id;
            $instance->relatedobject = $this->package->guid;
            $instance->create();

            if ( ! midgardmvc_ui_forms_store::store_form($formdata['form'], $instance) )
            {
                $instance->delete();
                continue;
            }

            $list_of_variables[$variable] = $instance->guid;
        }

        $values = $this->workflow_definition->resume($this->execution->guid, $list_of_variables);

        if (! isset($values['execution']))
        {
            if ($this->request->isset_data_item('redirect_link'))
            {
                $redirect_link = $this->request->get_data_item('redirect_link');
            }
            elseif (isset($_POST['redirect_link']))
            {
                $redirect_link = $_POST['redirect_link'];
            }
            else
            {
                $redirect_link = midgardmvc_core::get_instance()->dispatcher->generate_url
                (
                    'package_instance',
                    array
                    (
                        'package' => $this->package->name,
                        'version' => $this->package->version,
                        'project' => $args['project'],
                        'repository' => $args['repository'],
                        'arch' => $args['arch']
                    ),
                    $this->request
                );
            }

            // Workflow completed, redirect to package instance
            midgardmvc_core::get_instance()->head->relocate($redirect_link);
        }

        // populate the package
        $this->data['package'] = $this->package;
    }

    /**
     *
     */
    private function get_form(ezcWorkflowExecution $execution)
    {
        $list_of_variables = $execution->getVariables();

        foreach ($list_of_variables as $name => $value)
        {
            if (! mgd_is_guid($value))
            {
                continue;
            }

            try
            {
                $db_form = new midgardmvc_ui_forms_form($value);
            }
            catch (midgard_error_exception $e)
            {
                continue;
            }

            $form = midgardmvc_ui_forms_generator::get_by_form($db_form, false);

            if ($this->request->isset_data_item('redirect_link'))
            {
                $redirect_link = $this->request->get_data_item('redirect_link');
            }
            else
            {
                if(array_key_exists('HTTP_REFERER', $_SERVER))
                {
                    $redirect_link = $_SERVER['HTTP_REFERER'];
                }
                else
                {
                    $redirect_link = '';
                }
            }

            $form->set_cancel(null, $this->mvc->i18n->get('cancel', 'midgardmvc_helper_forms'), $redirect_link);

            // add a hidden input with the recirect link
            // where we go upon successful submit
            $field = $form->add_field('redirect_link', 'text');
            $field->set_value($redirect_link);
            $widget = $field->set_widget('hidden');

            return array
            (
                'db_form' => $db_form,
                'form' => $form
            );
        }

        return null;
    }

    /**
     *
     */
    private function load_package_instance(array $args)
    {
        $storage = new midgard_query_storage('com_meego_package_details');

        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint_group('AND');

        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('packagename'),
            '=',
            new midgard_query_value($args['package'])
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('packageversion'),
            '=',
            new midgard_query_value($args['version'])
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('repoprojectname'),
            '=',
            new midgard_query_value($args['project'])
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('reponame'),
            '=',
            new midgard_query_value($args['repository'])
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('repoarch'),
            '=',
            new midgard_query_value($args['arch'])
        ));

        $q->set_constraint($qc);
        $q->execute();

        $packages = $q->list_objects();

        if (count($packages) == 0)
        {
            throw new midgardmvc_exception_notfound("Package not found");
        }

        $package = new com_meego_package($packages[0]->packageguid);

        return $package;
    }

    /**
     *
     */
    private function load_workflow(array $args)
    {
        if ($this->package->metadata->hidden)
        {
            return null;
        }
        $list_of_workflows = midgardmvc_helper_workflow_utils::get_workflows_for_object($this->package);

        if (! isset($list_of_workflows[$args['workflow']]))
        {
            throw new midgardmvc_exception_notfound("Unable to run workflow {$args['workflow']} for this package instance");
        }

        return $list_of_workflows[$args['workflow']]['provider'];
    }

    /**
     * Adds some useful data to the forms
     * @param array of forms
     * @return array
     */
    private function append_forms(array $forms)
    {
        $retval = null;
        if (count($forms))
        {
            foreach ($forms as $form)
            {
                $browseurl = $this->mvc->dispatcher->generate_url
                (
                    'repository',
                    array
                    (
                        'project' => $form->projectname,
                        'repository' => $form->reponame,
                        'arch' => $form->repoarch
                    ),
                    'com_meego_packages'
                );

                $editurl = $this->mvc->dispatcher->generate_url
                (
                    'form_read',
                    array
                    (
                        'form' => $form->formguid,
                    ),
                    'midgardmvc_ui_forms'
                );

                $retval[] = array(
                    'form_title' => $form->formtitle,
                    'repository_title' => $form->repotitle,
                    'browse_url' => $browseurl,
                    'edit_url' => $editurl
                );
            }
        }
        return $retval;
    }

    /**
     * Returns open workflows
     */
    public function get_open_workflows()
    {
        return $this->get_workflows('open');
    }

    /**
     * Returns open workflows
     */
    public function get_closed_workflows()
    {
        return $this->get_workflows('closed');
    }

    /**
     * Retrieves all workflows with the corresponding
     * repository's title and URL to browse that repository
     *
     * @param string can be open, closed, or both. default is both
     * @return array
     */
    public function get_workflows($type = 'both')
    {
        $retval = null;

        $storage = new midgard_query_storage('com_meego_package_repository_form');
        $q = new midgard_query_select($storage);

        $dt = new midgard_datetime();
        $now = $dt->__toString();

        switch ($type)
        {
            case 'closed':
                $qc = new midgard_query_constraint(
                    new midgard_query_property('formend'),
                    '<',
                    new midgard_query_value($now));
                break;
            case 'both':
                break;
            case 'open':
            default:
                $qc = new midgard_query_constraint_group('AND');
                $qc->add_constraint(
                    new midgard_query_constraint(
                        new midgard_query_property('formstart'),
                        '<=',
                        new midgard_query_value($now)
                ));
                $qc->add_constraint(
                    new midgard_query_constraint(
                        new midgard_query_property('formend'),
                        '>',
                        new midgard_query_value($now)
                ));
        }

        if ($qc)
        {

            $q->set_constraint($qc);
        }
        $q->execute();

        $forms = $q->list_objects();

        $retval = self::append_forms($forms);

        return $retval;
    }

    /**
     * Retrieves all open workflows for the specified OS / UX combo
     * Returns repository titles, form titles and URLs to browse the repos
     *
     * @return array
     */
    public function get_open_workflows_for_osux($os = null, $version = null, $ux = null)
    {
        $retval = null;

        if (   $os
            && $version
            && $ux)
        {
            $dt = new midgard_datetime();
            $now = $dt->__toString();

            $storage = new midgard_query_storage('com_meego_package_repository_form');

            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint_group('AND');

            $qc->add_constraint(
                new midgard_query_constraint(
                    new midgard_query_property('formstart'),
                    '<=',
                    new midgard_query_value($now)
            ));
            $qc->add_constraint(
                new midgard_query_constraint(
                    new midgard_query_property('formend'),
                    '>',
                    new midgard_query_value($now)
            ));
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('repoos'),
                '=',
                new midgard_query_value($os)
            ));
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('repoosversion'),
                '=',
                new midgard_query_value($version)
            ));

            $qc1 = new midgard_query_constraint_group('OR');
            $qc1->add_constraint(new midgard_query_constraint(
                new midgard_query_property('repoux'),
                '=',
                new midgard_query_value($ux)
            ));
            $qc1->add_constraint(new midgard_query_constraint(
                new midgard_query_property('repoux'),
                '=',
                new midgard_query_value('')
            ));

            $qc->add_constraint($qc1);
            $q->set_constraint($qc);

            $q->execute();

            $forms = $q->list_objects();

            $retval = self::append_forms($forms);
        }

        return $retval;
    }

    /**
     * Admin UI for workflow management
     */
    public function get_admin_index(array $args)
    {
        $this->mvc->authorization->require_admin();

        $this->data['workflows']['open'] = $this->get_open_workflows();
        $this->data['workflows']['closed'] = $this->get_closed_workflows();

        $this->data['repositories'] = com_meego_packages_controllers_repository::get_all_repositories();
    }
}
