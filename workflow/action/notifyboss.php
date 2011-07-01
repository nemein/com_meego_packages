<?php
class com_meego_packages_workflow_action_notifyboss implements ezcWorkflowServiceObject
{
    public function execute(ezcWorkflowExecution $execution)
    {
        $value = $execution->getVariable('distilled_review'); // boolean yes or no
        // TODO: Tell BOSS
        return;
    }

    public function __toString()
    {
        return 'notifyboss';
    }
}
