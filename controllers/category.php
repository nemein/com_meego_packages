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

        // check if we have base categories and whether those have some mappings already
        $storage = new midgard_query_storage('com_meego_package_category_relation');

        $qc = new midgard_query_constraint(
            new midgard_query_property('basecategory'),
            '<>',
            new midgard_query_value('')
        );

        $q = new midgard_query_select($storage);

        $q->set_constraint($qc);
        $q->execute();

        $relations = $q->list_objects();

        if (count($relations))
        {
            // gather the base categories that have
            foreach($relations as $relation)
            {
                // get the packagecategory
                $packagecategory = self::get_package_category($relation->packagecategory);

                // fetch the base category
                $basecategory = new com_meego_package_basecategory($relation->basecategory);

                if ($basecategory->guid)
                {
                    // set the basecategory prop of the packagecategory
                    $packagecategory->basecategory = $basecategory->name;
                    $packagecategory->basecategoryid = $basecategory->id;
                }
                $categories[] = $packagecategory;
            }
            unset($packagecategory);
        }
        else
        {
            // there are no mappings yet, so let's fetch all package categories
            // and use them in the category listing
            $categories = self::get_all_package_categories();
        }

        // prepares data['categories'] to be used by the templates
        $this->prepare_category_list($categories);
    }

    /**
     * Returns all package categories
     */
    public function get_all_package_categories()
    {
        $storage = new midgard_query_storage('com_meego_package_category');
        $qc = new midgard_query_constraint(
            new midgard_query_property('name'),
            '<>',
            new midgard_query_value('')
        );
        $q = new midgard_query_select($storage);
        $q->set_constraint($qc);

        $q->execute();

        return $q->list_objects();
    }

    /**
     * Loads a particular package category specified by its id
     * @param integer id of the category
     * @return object the package category object
     */
    public function get_package_category($category_id = null)
    {
        $category = null;

        if ($category_id)
        {
            // check if we have base categories and whether those have some mappings already
            $storage = new midgard_query_storage('com_meego_package_category');

            $qc = new midgard_query_constraint(
                new midgard_query_property('id'),
                '=',
                new midgard_query_value($category_id)
            );

            $q = new midgard_query_select($storage);

            $q->set_constraint($qc);
            $q->execute();

            $categories = $q->list_objects();

            if (   is_array($categories)
                && count($categories))
            {
                $category = $categories[0];
            }
        }

        return $category;
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

    /**
     * Prepares the data['categories'] array that is to be used by the templates
     * @param array of category objects
     */
    public function prepare_category_list($categories = null)
    {
        if (is_array($categories))
        {
            $counter = 0;
            $prev_base = '';
            foreach ($categories as $category)
            {
                $up = $category->up;

                $category->tree = $category->name;

                while ($up != 0)
                {
                    $current = new com_meego_package_category($up);
                    $category->tree = $current->name . ':' . $category->tree;
                    $up = $current->up;
                }

                // to satisfy the template we need to set some value if it is not set
                if (! isset($category->basecategoryid))
                {
                    $counter = self::number_of_packages($category->id);

                    $category->basecategoryid = false;

                    $category->localurl = $this->mvc->dispatcher->generate_url
                    (
                        'packages_by_categorytree',
                        array
                        (
                            'categorytree' => $category->tree
                        ),
                        $this->request
                    );
                }
                else
                {
                    if ($category->basecategory != $prev_base)
                    {
                        $counter = 0;
                    }

                    $counter += self::number_of_packages($category->id);

                    // tricky part here
                    // if we have valid base categories (ie those that have mappings)
                    // then istead of populating a package categories we populate the base ones
                    // but of course we keep the counters as property

                    //$category->tree = $category->basecategory;

                    $prev_base = $category->basecategory;

                    $category->localurl = $this->mvc->dispatcher->generate_url
                    (
                        'packages_by_basecategory',
                        array
                        (
                            'basecategory' => $category->basecategory
                        ),
                        $this->request
                    );
                }

                $category->css = '';

                $category->available_packages = $counter;

                if ($category->available_packages == 0)
                {
                    //skip listing categories that have no packages
                    continue;
                }

                if (   isset($category->basecategoryid)
                    && $category->basecategoryid)
                {
                    $this->data['categories'][$category->basecategory] = $category;
                }
                else
                {
                    $this->data['categories'][$category->tree] = $category;
                }
            }

            if (count($this->data['categories']))
            {
                reset($this->data['categories'])->css = 'first';
                end($this->data['categories'])->css = 'last';
            }

            ksort($this->data['categories']);
/*
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
*/
            // @todo: set the active leaf somehow; class="active"
        }
    }
}