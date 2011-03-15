<?php
class com_meego_packages_controllers_basecategory extends midgardmvc_core_controllers_baseclasses_crud
{
    var $request = null;
    var $mvc = null;

    // these defaults can be used to populate the com_meego_package_basecategory table
    var $default_base_categories = array(
        'Internet' => '',
        'Office' => '',
        'Graphics' => '',
        'Games' => '',
        'Audio' => '',
        'Video' => '',
        'Education' => '',
        'Science' => '',
        'System' => '',
        'Development' => '',
        'Accessibility' => '',
        'Network' => '',
        'Location & Navigation' => '',
        'Utilities' => '',
        'Other' => ''
    );

    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;

        $this->mvc = midgardmvc_core::get_instance();

        // check sufficient access rights
        // we could do that in injector too...
        if (   ! $this->mvc->authentication->is_user()
            || ! $this->mvc->authentication->get_user()->is_admin())
        {
            midgardmvc_core::get_instance()->head->relocate('/');
        }

        $this->mvc->i18n->set_translation_domain('com_meego_packages');

        $default_language = $this->mvc->configuration->default_language;

        if (! isset($default_language))
        {
            $default_language = 'en_US';
        }

        $this->mvc->i18n->set_language($default_language, false);
    }

    /**
     * sets the current category object by its guid, id or name
     * sets the current object to a new com_meego_package_basecategory if
     * the no argument specified, of the given category is not in the database
     */
    public function load_object(array $args)
    {
        $this->object = null;

        if (array_key_exists('basecategory', $args))
        {
            try
            {
                $this->object = new com_meego_package_basecategory($args['basecategory']);
            }
            catch (InvalidArgumentException $e)
            {
                if (! is_object($this->object))
                {
                    // try to search by name
                    $storage = new midgard_query_storage('com_meego_package_basecategory');
                    $q = new midgard_query_select($storage);

                    $qc = new midgard_query_constraint(
                        new midgard_query_property('name'),
                        '=',
                        new midgard_query_value($args['basecategory'])
                    );

                    $q->set_constraint($qc);
                    $q->set_limit(1);
                    $q->execute();

                    $categories = $q->list_objects();

                    if (count($categories))
                    {
                        $this->object = $categories[0];
                    }
                }
            }

            if (is_object($this->object))
            {
                $this->object->localurl = self::get_url_read();
            }
        }

        if (   ! array_key_exists('basecategory', $args)
            || ! is_object($this->object))
        {
            self::prepare_new_object($args);
        }

        return $this->object;
    }

    /**
     * @todo: docs
     */
    public function prepare_new_object(array $args)
    {
        $this->object = new com_meego_package_basecategory();
    }

    /**
     * @todo: docs
     */
    public function get_url_admin_index()
    {
        return $this->mvc->dispatcher->generate_url
        (
            'basecategories_admin_index', array(), $this->request
        );
    }

    /**
     * @todo: docs
     */
    public function get_url_read($guid = null)
    {
        if (! $guid)
        {
            $guid = $this->object->guid;
        }
        return $this->mvc->dispatcher->generate_url
        (
            'basecategories_admin_manage', array
            (
                'basecategory' => $guid
            ),
            $this->request
        );
    }

    /**
     * @todo: docs
     */
    public function get_url_update($guid = null)
    {
        $this->get_url_read($guid);
    }

    /**
     * Administrative tasks for category management
     *
     * Mainly meant for defining new base categories
     * and manage mapping between real package categories and the base ones
     */
    public function get_admin_index(array $args)
    {
        $this->load_object($args);

        $storage = new midgard_query_storage('com_meego_package_basecategory');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('name'),
            '<>',
            new midgard_query_value('')
        );

        $q->set_constraint($qc);
        $q->execute();

        $basecategories = $q->list_objects();

        $this->data['basecategories'] = null;

        if (count($basecategories))
        {
            foreach ($basecategories as $basecategory)
            {
                $basecategory->localurl = $this->get_url_read($basecategory->guid);
                $basecategory->mapping_counter = $this->count_number_of_relations($basecategory->id);
                $basecategory->package_counter = $this->count_number_of_packages($basecategory->id);
                $this->data['basecategories'][] = $basecategory;
            }
        }
        else
        {
            // gather defaults so the admin has a chance to populate those
            foreach ($this->default_base_categories as $name => $description)
            {
                if ($description == '')
                {
                    $description = $this->mvc->i18n->get('no_description');
                }
                $category = array('name' => $name, 'description' => $description);

                $this->data['defaultbasecategories'][] = $category;
            }

            $this->data['form_action'] = $this->mvc->dispatcher->generate_url
            (
                'basecategories_admin_create', array(), $this->request
            );
        }
    }

    /**
     * Creating a basecategory
     */
    public function post_create_basecategory(array $args)
    {
        $saved = true;

        // save categories update existing ones
        foreach($_POST['categories'] as $category)
        {
            // look if basecategory with such name exists already
            $this->load_object(array('basecategory' => $category['name']));

            $this->object->description = $category['description'];

            if ($this->object->guid)
            {
                // update
                $this->object->update();
                echo "update " . $category['name'] . "\n";
            }
            else
            {
                // create
                $this->object->name = $category['name'];
                $this->object->create();
                echo "create " . $category['name'] . "\n";
                // try to do the mapping now
                // $this->post_create_relations(array('basecategory' => $this->object->guid));
            }

            ob_flush();

            if (! $this->object->guid)
            {
                $saved = false;
            }
        }

        if ($saved)
        {
            // @todo: add an uimessage
            $this->data['relocate'] = $this->get_url_admin_index();
            $this->mvc->head->relocate($this->data['relocate']);
        }
        else
        {
            throw new midgardmvc_exception_httperror("Could not populate default base categories", 500);
        }
    }

    /**
     * Reading a basecategory
     */
    public function get_manage_basecategory(array $args)
    {
        $this->data['map'] = false;

        try
        {
            $this->load_object($args);
            $this->data['category'] = $this->object;
            $this->data['feedback_objectname'] = $this->object->name;
            $this->data['undelete_error'] = false;

            $this->prepare_mapping($this->object);
        }
        catch (midgard_error_exception $e)
        {
            $this->data['status'] = 'error';
            $this->data['feedback_objectname'] = $args['basecategory'];
            $this->data['feedback'] = 'feedback_basecategory_does_not_exist';
            $this->data['undelete_error'] = true;
        }

        $this->data['undelete'] = false;
        $this->data['indexurl'] = $this->get_url_admin_index();
        $this->data['form_action'] = $this->get_url_read($args['basecategory']);
    }

    /**
     * Updating a basecategory
     */
    public function post_manage_basecategory(array $args)
    {
        $this->data['undelete'] = false;
        $this->data['undelete_error'] = false;
        $this->data['form_action'] = $this->get_url_read($args['basecategory']);
        $this->data['indexurl'] = $this->get_url_admin_index();

        if (array_key_exists('undelete', $_POST))
        {
            if (midgard_object_class::undelete($args['basecategory']))
            {
                $this->data['feedback'] = 'feedback_basecategory_undelete_ok';
            }
            else
            {
                $this->data['undelete_error'] = true;
                $this->data['status'] = 'error';
                $this->data['feedback_objectname'] = $args['basecategory'];
                $this->data['feedback'] = 'feedback_basecategory_undelete_failed';
                return;
            }
        }

        try
        {
            $this->load_object($args);
        }
        catch (midgard_error_exception $e)
        {
            $this->data['status'] = 'error';
            $this->data['feedback_objectname'] = $args['basecategory'];
            $this->data['feedback'] = 'feedback_basecategory_does_not_exist';
            $this->data['undelete_error'] = true;
            return;
        }

        $this->data['status'] = 'status';
        $this->data['category'] = $this->object;

        $this->data['feedback_objectname'] = $this->object->name;

        if (array_key_exists('update', $_POST))
        {
            $new_name = trim(htmlentities($_POST['categories'][$this->object->name]['name']));
            $new_description = trim(htmlentities($_POST['categories'][$this->object->name]['description']));

            if (strlen($new_name) == 0)
            {
                $this->data['status'] = 'error';
                $this->data['feedback'] = 'error_basecategory_name_empty';
                return;
            }

            if ($new_name == $this->object->name)
            {
                if ($new_description == $this->object->description)
                {
                    $this->data['feedback'] = 'feedback_basecategory_no_change';
                    return;
                }
            }

            $this->prepare_mapping($this->object);

            $this->object->name = $new_name;
            $this->object->description = $new_description;
            $this->object->update();

            $this->data['feedback_objectname'] = $this->object->name;
            $this->data['feedback'] = 'feedback_basecategory_update_ok';
        }
        elseif (array_key_exists('updatemapping', $_POST))
        {
            // delete all mappings of this base category
            $this->delete_relations($this->object->id);

            // set relations to db if they were posted
            if (array_key_exists('mapped', $_POST))
            {
                foreach ($_POST['mapped'] as $packagecategory)
                {
                    $newrelation = new com_meego_package_category_relation();
                    $newrelation->basecategory = $this->object->id;
                    $newrelation->packagecategory = $packagecategory;
                    if (   $newrelation->basecategory != 0
                        && $newrelation->packagecategory != 0)
                    {
                        $newrelation->create();
                    }
                }
                $this->data['feedback'] = 'feedback_category_mapping_updated_ok';
            }

            // set the list of all package categories, but set it so
            // that parents are also part of the category name, e.g. Application:Games, not just Games
            $this->prepare_mapping($this->object);
        }
        elseif (array_key_exists('delete', $_POST))
        {
            $this->object->delete();
            $this->data['undelete'] = true;
            $this->data['feedback'] = 'feedback_basecategory_delete_ok';
        }
        elseif (array_key_exists('index', $_POST))
        {
            $this->mvc->head->relocate($this->get_url_admin_index());
        }
    }

    /**
     * Deetes all existing relations of a base category
     * @param integer id of the base category
     */
    private function delete_relations($basecategory_id = null)
    {
        if ($basecategory_id)
        {
            $relations = $this->load_relations_for_basecategory($basecategory_id);

            foreach($relations as $relation)
            {
                $relation->purge();
            }
        }
    }

    /**
     * Prepares some arrays for the mapping
     * @param object the basecategory object
     */
    public function prepare_mapping($object)
    {
        // this will set the package categories to this->data['categories']
        $packagecategories = com_meego_packages_controllers_category::get_all_package_categories();
        com_meego_packages_controllers_category::prepare_category_list($packagecategories);

        // to be used when displaying the number of package categories mapped to this base category
        $this->data['mapping_counter'] = 0;
        $this->data['package_counter'] = 0;

        // set a flag if both arrays are valid
        foreach($this->data['categories'] as $category)
        {
            $relation = $this->load_relation($object->id, $category->id);

            $mapped = false;

            if (is_object($relation))
            {
                $mapped = true;

                ++$this->data['mapping_counter'];
                $this->data['package_counter'] += $category->available_packages;
            }

            $this->data['map'][] = array(
                'id' => $category->id,
                'tree' => $category->tree,
                'mapped' => $mapped
            );
        }
    }

    /**
     * Loads a relation object betwenn a base category and a package category
     *
     * @param integer id of the base category
     * @param integer id of the package category
     *
     * @return object relation object
     *
     */
    private function load_relation($basecategory = null, $packagecategory = null)
    {
        $relation = null;

        $storage = new midgard_query_storage('com_meego_package_category_relation');

        $qc = new midgard_query_constraint_group('AND');

        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('basecategory'),
            '=',
            new midgard_query_value($basecategory)
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('packagecategory'),
            '=',
            new midgard_query_value($packagecategory)
        ));

        $q = new midgard_query_select($storage);

        $q->set_constraint($qc);
        $q->set_limit(1);
        $q->execute();

        $relations = $q->list_objects();

        if (   is_array($relations)
            && count($relations))
        {
            $relation = $relations[0];
        }

        return $relation;
    }

    /**
     * Counts the amount of mapping a base category has
     * @param integer id of a basecategory
     * @return integer number of relations recorded
     */
    public function count_number_of_relations($basecategory_id)
    {
        $relations = $this->load_relations_for_basecategory($basecategory_id);

        return count($relations);
    }

    /**
     * Counts the amount of packages that are covered by a base category
     * @param integer id of a basecategory
     * @return integer number of packages
     */
    public function count_number_of_packages($basecategory_id)
    {
        $counter = 0;

        $relations = $this->load_relations_for_basecategory($basecategory_id);

        foreach($relations as $relation)
        {
            $counter += com_meego_packages_controllers_category::number_of_packages($relation->packagecategory);
        }

        return $counter;
    }

    /**
     * Loads all relations that belong to a base category
     * @param integer id of the base category
     * @return array of relation objects
     */
    public function load_relations_for_basecategory($basecategory_id = null)
    {
        $relations = null;

        if ($basecategory_id)
        {
            $storage = new midgard_query_storage('com_meego_package_category_relation');
            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint(
                new midgard_query_property('basecategory'),
                '=',
                new midgard_query_value($basecategory_id)
            );

            $q->set_constraint($qc);
            $q->execute();

            $relations = $q->list_objects();
        }

        return $relations;
    }
}