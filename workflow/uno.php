<?php
class com_meego_packages_workflow_uno implements midgardmvc_helper_workflow_definition
{
    public function can_handle(midgard_object $object)
    {
        if (! $object instanceof com_meego_package)
        {
            return false;
        }

        if (! midgardmvc_core::get_instance()->authentication->is_user())
        {
            return false;
        }

        // Check that the package's repository has a form
        $repository = new com_meego_repository($object->repository);

        if (! midgardmvc_ui_forms_generator::has_object_forms($repository))
        {
            return false;
        }

        //TODO: Check that object is reviewable

        $user = midgardmvc_core::get_instance()->authentication->get_person();

        //Hasn't reviewed yet
        $storage = new midgard_query_storage('midgardmvc_ui_forms_form_instance');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint_group('AND');

        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('relatedobject'),
            '=',
            new midgard_query_value($object->guid)
        ));

        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('metadata.creator'),
            '=',
            new midgard_query_value($user->guid)
        ));
        $q->set_constraint($qc);

        $res = $q->execute();

        if ($q->get_results_count() != 0)
        {
            //return false;
        }

        return true;
    }

    public function get()
    {
        $workflow = new ezcWorkflow('uno');

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
        $distill_review = new ezcWorkflowNodeAction
        (
            array
            (
                'class' => 'com_meego_packages_workflow_action_distillreview'
            )
        );
        $get_review->addoutNode($distill_review);

        $check_boolean = new ezcWorkflowNodeInput
        (
            array
            (
                'distilled_review' => new ezcWorkflowConditionIsBool()
            )
        );
        $distill_review->addoutNode($check_boolean);

        $notify_boss = new ezcWorkflowNodeAction
        (
            array
            (
                'class' => 'com_meego_packages_workflow_action_notifyboss',
            )
        );
        $check_boolean->addoutNode($notify_boss);

        $notify_boss->addoutNode($workflow->endNode);

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
