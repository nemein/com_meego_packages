<?php
class com_meego_packages_workflow_action_notifyboss implements ezcWorkflowServiceObject
{
    public function execute(ezcWorkflowExecution $execution)
    {
        // tell BOSS

        // value will be the latest score of the package
        $value = $execution->getVariable('distilled_review'); // boolean yes or no

        $storage = new midgard_query_storage('com_meego_package_details');

        // get the detailed package object
        $q = new midgard_query_select($storage);

        $q->set_constraint(new midgard_query_constraint(
            new midgard_query_property('packageguid'),
            '=',
            new midgard_query_value($execution->getVariable('package_instance'))
        ));

        $q->execute();
        $package = $q->list_objects();

        if (count($package))
        {
            // $package[0] could be used to inform BOSS
        }
        return;
    }

    public function __toString()
    {
        return 'notifyboss';
    }
}
