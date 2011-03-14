<?php
class com_meego_packages_controllers_category
{
    var $request = null;
    var $mvc = null;

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
     * @todo: docs
     */
    public function get_categories_list(array $args = null)
    {
        $this->data['categories'] = array();

        $storage = new midgard_query_storage('com_meego_package_category');
        $qc = new midgard_query_constraint(
            new midgard_query_property('name'),
            '<>',
            new midgard_query_value('')
        );
        $q = new midgard_query_select($storage);

        $q->set_constraint($qc);

        $q->execute();

        $categories = $q->list_objects();

        foreach ($categories as $category)
        {
            $category->available_packages = self::number_of_packages($category->id);

            if ($category->available_packages == 0)
            {
                //skip categories that have no pacages
                continue;
            }

            $up = $category->up;

            $category->tree = $category->name;

            while ($up != 0)
            {
                $current = new com_meego_package_category($up);
                $category->tree = $current->name . ':' . $category->tree;
                $up = $current->up;
            }

            $category->localurl = $this->mvc->dispatcher->generate_url
            (
                'packages_by_categorytree',
                array
                (
                    'categorytree' => $category->tree
                ),
                $this->request
            );

            $category->css = '';

            $this->data['categories'][$category->tree] = $category;
        }

        if (count($this->data['categories']))
        {
            reset($this->data['categories'])->css = 'first';
            end($this->data['categories'])->css = 'last';
        }

        uasort(
            $this->data['categories'],
            function($a, $b)
            {
                if ($a->tree == $b->tree) {
                    return 0;
                }
                return ($a->tree < $b->tree) ? -1 : 1;
            }
        );

        // @todo: set the active leaf somehow; class="active"
    }

    /**
     * Returns the number of packages available within the given category
     * @param integer id of the category
     * @return integer number of packages
     */
    public function number_of_packages($category_id)
    {
        $retval = 0;

        $storage = new midgard_query_storage('com_meego_package');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('category'),
            '=',
            new midgard_query_value($category_id)
        );

        $q->set_constraint($qc);
        $q->execute();

        $retval = $q->get_results_count();

        return $retval;
    }
}