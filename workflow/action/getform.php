<?php
class com_meego_packages_workflow_action_getform implements ezcWorkflowServiceObject
{
    public function execute(ezcWorkflowExecution $execution)
    {
        $package_instance = new com_meego_package($execution->getVariable('package_instance'));

        // We load the form from the package's repository
        $repository = new com_meego_repository($package_instance->repository);
        $list_of_forms = midgardmvc_ui_forms_generator::list_for_object($repository);
        if (empty($list_of_forms))
        {
            return;
        }

        $execution->setVariable('review_form', $list_of_forms[0]->name);
    }

    public function __toString()
    {
        return 'getform';
    }
}
