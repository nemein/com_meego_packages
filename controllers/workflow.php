<?php
class com_meego_packages_controllers_workflow
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    public function post_start_package_instance(array $args)
    {
        $this->package = $this->load_package_instance($args);
        $workflow_class = $this->load_workflow($args);

        midgardmvc_core::get_instance()->component->load_library('Workflow');

        $workflow = new $workflow_class();
        $values = $workflow->start($this->package);
        if (isset($values['execution']))
        {
            // Workflow suspended and needs input, redirect to workflow page
            midgardmvc_core::get_instance()->head->relocate
            (
                midgardmvc_core::get_instance()->dispatcher->generate_url
                (
                    'package_instance_workflow_resume',
                    array
                    (
                        'package' => $this->package->name,
                        'version' => $this->package->version,
                        'repository' => $args['repository'],
                        'workflow' => $args['workflow'],
                        'execution' => $values['execution'],
                    ),
                    $this->request
                )
            );
        }

        // Workflow completed, redirect to package instance
        midgardmvc_core::get_instance()->head->relocate
        (
            midgardmvc_core::get_instance()->dispatcher->generate_url
            (
                'package_instance',
                array
                (
                    'package' => $this->package->name,
                    'version' => $this->package->version,
                    'repository' => $args['repository'],
                ),
                $this->request
            )
        );
    }

    public function get_resume_package_instance(array $args)
    {
        $this->package = $this->load_package_instance($args);
        $workflow_class = $this->load_workflow($args);

        midgardmvc_core::get_instance()->component->load_library('Workflow');

        $workflow_definition = new $workflow_class();
        $workflow = $workflow_definition->get();
        try
        {
            $execution = new midgardmvc_helper_workflow_execution_interactive($workflow, $args['execution']);
        
        }
        catch (ezcWorkflowExecutionException $e)
        {
            throw new midgardmvc_exception_notfound("Workflow {$args['workflow']} {$args['execution']} not found: " . $e->getMessage());
        }

        $waiting_for = $execution->getWaitingFor();
        foreach ($waiting_for as $variable => $variable_information)
        {
            switch (get_class($variable_information['condition']))
            {
                case 'midgardmvc_ui_forms_workflow_condition_instance':
                    $this->get_form($execution);
                    break;
                // TODO: Handle other typical workflow input types
            }
        }
    }

    private function get_form(ezcWorkflowExecution $execution)
    {
        $list_of_variables = $execution->getVariables();
        foreach ($list_of_variables as $name => $value)
        {
            if (!mgd_is_guid($value))
            {
                continue;
            }
            $form = midgardmvc_ui_forms_generator::get_by_guid($value);
            if (!$form)
            {
                continue;
            }
            $this->data['form'] = $form;
        }
    }

    private function load_package_instance(array $args)
    {
        $qb = com_meego_package::new_query_builder();
        $qb->add_constraint('name', '=', $args['package']);
        $qb->add_constraint('version', '=', $args['version']);
        $qb->add_constraint('repository.name', '=', $args['repository']);
        $packages = $qb->execute();
        if (count($packages) == 0)
        {
            throw new midgardmvc_exception_notfound("Package not found");
        }
        return $packages[0];
    }

    private function load_workflow(array $args)
    {
        $list_of_workflows = midgardmvc_helper_workflow_utils::get_workflows_for_object($this->package);
        if (!isset($list_of_workflows[$args['workflow']]))
        {
            throw new midgardmvc_exception_notfound("Unable to run workflow {$args['workflow']} for this package instance");
        }
        return $list_of_workflows[$args['workflow']]['provider'];
    }
}
