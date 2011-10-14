<?php
class com_meego_packages_workflow_action_notifyboss implements ezcWorkflowServiceObject
{
    public function execute(ezcWorkflowExecution $execution)
    {
        $qa_results_dir = "/var/qa";

        // value will be the latest score of the package
        $value = $execution->getVariable('distilled_review'); // an integer

        // dump this value to a file in the filesystem
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

        if (   count($package)
            && $value)
        {
            $project_name = $package[0]->repoprojectname;
            $package_name = $package[0]->packagename;

            $qa_results_dir = $qa_results_dir. '/' . $project_name;

            if (! is_dir($qa_results_dir))
            {
                $ret = mkdir($qa_results_dir, 0755, true);
                if (! $ret)
                {
                    // do something
                    return;
                }
            }

            $handle = fopen($qa_results_dir . '/' . $package_name . '.txt', "wb");
            if ($handle)
            {
                $ret = fwrite($handle, $value);
                if (! $ret)
                {
                    // do something
                }
            }
            fclose($handle);
        }
        return;
    }

    public function __toString()
    {
        return 'notifyboss';
    }
}
