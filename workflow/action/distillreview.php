<?php
class com_meego_packages_workflow_action_distillreview implements ezcWorkflowServiceObject
{
    public function execute(ezcWorkflowExecution $execution)
    {
        $form = midgardmvc_ui_forms_generator::get_by_guid($execution->getVariable('review_form'));
        $review = midgardmvc_ui_forms_store::load_form($form, new midgardmvc_ui_forms_form_instance($execution->getVariable('review')));

        $items = $form->items;
        $boolean_count = 0;
        $positive = 0;
        foreach ($items as $key => $item)
        {
            if (!$item instanceof midgardmvc_helper_forms_field_boolean)
            {
                continue;
            }

            $boolean_count++;

            if ($item->get_value())
            {
                $positive++;
            }
        }

        if (!$boolean_count)
        {
            // No booleans in form, distill always true
            $execution->setVariable('distilled_review', true);
            return;
        }

        if ($positive >= $boolean_count / 2)
        {
            $execution->setVariable('distilled_review', true);
            return;
        }
        $execution->setVariable('distilled_review', false);
    }

    public function __toString()
    {
        return 'distillreview';
    }
}
