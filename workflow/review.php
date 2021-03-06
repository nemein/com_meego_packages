<?php
class com_meego_packages_workflow_review implements midgardmvc_helper_workflow_definition
{
    public function can_handle(midgard_object $object)
    {
        if (! $object instanceof com_meego_package)
        {
            return false;
        }

        // Check that the package's repository has a form
        $repository = new com_meego_repository($object->repository);

        if (! midgardmvc_ui_forms_generator::has_object_forms($repository))
        {
            return false;
        }

        // Check if the form is assigned to this repository
        $storage = new midgard_query_storage('com_meego_package_repository_form');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint_group('AND');

        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('formtitle'),
            '=',
            new midgard_query_value($this->workflow['label'])
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('repoguid'),
            '=',
            new midgard_query_value($repository->guid)
        ));

        $q->set_constraint($qc);

        $res = $q->execute();

        if (! $q->get_results_count())
        {
            return false;
        }

        if (! midgardmvc_core::get_instance()->authentication->is_user())
        {
            return true;
        }

        // safety net
        try
        {
            $user = midgardmvc_core::get_instance()->authentication->get_person();
        }
        catch (midgard_error_exception $e)
        {
            // if the person object is gone we will have an exception here
            return false;
        }

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
            return false;
        }

        return true;
    }

    /**
     * @todo: docs
     */
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

        $distill_review = new ezcWorkflowNodeAction
        (
            array
            (
                'class' => 'com_meego_packages_workflow_action_distillreview'
            )
        );
        $get_review->addoutNode($distill_review);

        $check_integer = new ezcWorkflowNodeInput
        (
            array
            (
                'distilled_review' => new ezcWorkflowConditionIsInteger()
            )
        );
        $distill_review->addoutNode($check_integer);

        $notify_boss = new ezcWorkflowNodeAction
        (
            array
            (
                'class' => 'com_meego_packages_workflow_action_notifyboss',
            )
        );
        $check_integer->addoutNode($notify_boss);

        $notify_boss->addoutNode($workflow->endNode);

        return $workflow;
    }

    /**
     * @todo: docs
     */
    public function start(midgard_object $object, array $args = null)
    {
        if (! midgardmvc_core::get_instance()->authentication->is_user())
        {
            return false;
        }

        $user = midgardmvc_core::get_instance()->authentication->get_user();

        $workflow = $this->get();

        $storage = new midgard_query_storage('midgardmvc_helper_workflow_execution');
        $q = new midgard_query_select($storage);

        $q->set_constraint(new midgard_query_constraint(
            new midgard_query_property('metadata.creator'),
            '=',
            new midgard_query_value($user->person)
        ));

        $q->execute();

        $execs = $q->list_objects();

        foreach ($execs as $exec)
        {
            $variables = unserialize($exec->variables);
            if ($variables['package_instance'] == $object->guid)
            {
                midgardmvc_core::get_instance()->log(__CLASS__, 'Re-use unfinished workflow execution (' . $exec->id . ')', 'info');
                return self::resume($exec->guid, $variables);
            }
        }

        $execution = new midgardmvc_helper_workflow_execution_interactive($workflow);
        $execution->setVariable('package_instance', $object->guid);
        $execution->start();

        $values = array();

        if (! $execution->hasEnded())
        {
            $values['execution'] = $execution->guid;
            return $values;
        }
        return $values;
    }

    /**
     * @todo: docs
     */
    public function resume($execution_guid, array $args = null)
    {
        $workflow = $this->get();

        $execution = new midgardmvc_helper_workflow_execution_interactive($workflow, $execution_guid);
        $execution->resume($args);

        $values = array();

        if (! $execution->hasEnded())
        {
            $values = $args;
            $values['execution'] = $execution->guid;
            return $values;
        }

        return $values;
    }
}
