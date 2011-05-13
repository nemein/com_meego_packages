<?php
class com_meego_packages_workflow_review implements midgardmvc_helper_workflow_definition
{
    public function can_handle(midgard_object $object)
    {
        if (!$object instanceof com_meego_package)
        {
            return false;
        }

        if (!midgardmvc_core::get_instance()->authentication->is_user())
        {
            return false;
        }

        // Check that the package's repository has a form
        $repository = new com_meego_repository($object->repository);
        return midgardmvc_ui_forms_generator::has_object_forms($repository);
    }

    public function get()
    {
        $workflow = new ezcWorkflow('review');

        $get_package_instance = new ezcWorkflowNodeInput
        (
            array
            (
                'package_instance' => new midgardmvc_helper_workflow_condition_guid()
            )
        );
        $workflow->startNode->addOutNode($get_package_instance);

        $find_form_action = new ezcWorkflowNodeAction
        (
            array
            (
                'class' => 'com_meego_packages_workflow_action_getform'
            )
        );
        $get_package_instance->addOutNode($find_form_action);

        $get_form = new ezcWorkflowNodeInput
        (
            array
            (
                'review_form' => new midgardmvc_ui_forms_workflow_condition_form()
            )
        );
        $find_form_action->addOutNode($get_form);

        $get_review = new ezcWorkflowNodeInput
        (
            array
            (
                'review' => new midgardmvc_ui_forms_workflow_condition_instance()
            )
        );
        $get_form->addOutNode($get_review);
        $get_review->addoutNode($workflow->endNode);

        return $workflow;
    }

    public function start(midgard_object $object, array $args = null)
    {
        $workflow = $this->get();

        $execution = new midgardmvc_helper_workflow_execution_interactive($workflow);
        $execution->setVariable('package_instance', $object->guid);
        $execution->start();

        $values = array();
        if (!$execution->hasEnded())
        {
            $values['execution'] = $execution->guid;
            return $values;
        }
        return $values;
    }

    public function resume($execution_guid, array $args = null)
    {
        $workflow = $this->get();

        $execution = new midgardmvc_helper_workflow_execution_interactive($workflow, $execution_guid);

        $execution->resume($args);

        $values = array();
        if (!$execution->hasEnded())
        {
            $values['execution'] = $execution->guid;
            return $values;
        }
        return $values;
    }
}
