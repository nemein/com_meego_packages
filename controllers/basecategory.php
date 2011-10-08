<?php
class com_meego_packages_controllers_basecategory extends midgardmvc_core_controllers_baseclasses_crud
{
    var $mvc = null;
    var $request = null;

    // these defaults can be used to populate the com_meego_package_basecategory table
    // @todo: maybe make this configurable
    var $default_base_categories = array(
        'Internet' => '',
        'Office' => '',
        'Graphics' => '',
        'Games' => '',
        'Multimedia' => '',
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

        $this->mvc->i18n->set_translation_domain('com_meego_packages');

        $default_language = $this->mvc->configuration->default_language;

        if (! isset($default_language))
        {
            $default_language = 'en_US';
        }

        $this->mvc->i18n->set_language($default_language, false);

        if (isset($this->mvc->configuration->base_categories))
        {
            $this->default_base_categories = $this->mvc->configuration->base_categories;
        }
    }

    /**
     * Check if a basecategory exist
     *
     * @param string name of basecategory
     * @return false if basecategory does not exist; basecategory object otherwise
     */
    public function basecategory_exists($basecategory = '')
    {
        $retval = false;

        // try to search by name
        $storage = new midgard_query_storage('com_meego_package_basecategory');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('name'),
            '=',
            new midgard_query_value($basecategory)
        );

        $q->set_constraint($qc);
        $q->set_limit(1);
        $q->execute();

        $categories = $q->list_objects();

        if (count($categories))
        {
            $retval = $categories[0];
        }

        return $retval;
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
                    $this->object = self::basecategory_exists($args['basecategory']);
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
    public function get_url_browse_basecategory($os = null, $os_version = null, $ux = null, $basecategory = null)
    {
        $url = $this->mvc->dispatcher->generate_url
        (
            'apps_by_basecategory',
            array
            (
                'os' => $os,
                'version' => (string) $os_version,
                'ux' => $ux,
                'basecategory' => $basecategory
            ),
            $this->request
        );

        return $url;
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
        com_meego_packages_utils::require_admin();

        $this->load_object($args);

        $basecategories = self::load_basecategories();

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
     * Cleans the given string
     * used for base category name cleanups
     * @param string the input string
     * @return string the cleaned up string
     */
    private static function tidy_up($string = '')
    {
        $pattern = '/^(\w*)\s.*$/';
        $replacement = '${1}';
        return mb_strtolower(preg_replace($pattern, $replacement, $string));
    }

    /**
     * Show a fancy list of basecategories
     *
     * @param array args where the key
     *                                  'os' will contain the name of the OS
     *                                  'version' will contain the versionof the UX
     *                                  'ux' will contain the name of the UX
     *
     */
    public function get_basecategories_by_ux(array $args)
    {
        $this->data['basecategories'] = array();

        // for now we will not use the UX at all
        $basecategories = self::load_basecategories();

        $os = $args['os'];
        $os_version = $args['version'];
        $ux = $args['ux'];

        // check if OS is valid
        // this would be cheaper if we check the configuration, not the DB..
        if (! com_meego_packages_controllers_repository::os_exists($args['os'], $args['version']))
        {
            $os = $this->mvc->configuration->default['os'];
            $os_version = $this->mvc->configuration->latest[$os]['version'];
            $ux = $this->mvc->configuration->latest[$os]['ux'];
            self::redirect($os, $os_version, $ux);
        }

        // check from the configuration if ux is valid
        if (! array_key_exists($args['ux'], $this->mvc->configuration->os_ux[$os]))
        {
            $found = false;
            // check for base category, perhaps the user wants that
            foreach($basecategories as $basecategory)
            {
                if (self::tidy_up($basecategory->name) == self::tidy_up($args['ux']))
                {
                    $found = true;
                }
            }

            if (! $found)
            {
                //if no such base category then complain
                //throw new midgardmvc_exception_notfound($this->mvc->i18n->get("title_no_ux_or_basecategory", null, array('item' => $args['ux'])), 404);

                // redirect to basecategory index using default OS,  UX and versions
                $ux = $this->mvc->configuration->latest[$os]['ux'];
                self::redirect($os, $os_version, $ux);
            }
            else
            {
                //if no such base category then complain
                throw new midgardmvc_exception_notfound('get_basecategories_by_ux todo', 404);
                #$this->mvc->head->relocate($this->get_url_browse_basecategory($args['ux'], false));

                // @todo: redirect to that particular category index
            }
        }

        if (count($basecategories))
        {
            foreach ($basecategories as $basecategory)
            {
                // set the url where to browse that category
                $basecategory->localurl = $this->get_url_browse_basecategory($args['os'], $args['version'], $args['ux'], $basecategory->name);
                // count all apps that are in this category for that UX
                $basecategory->apps_counter = com_meego_packages_controllers_application::count_number_of_apps($args['os'], $args['version'], $basecategory->name, $args['ux']);
                // set the css class to be used to display this base category
                $basecategory->css = self::tidy_up($basecategory->name);
                // populate data
                $this->data['basecategories'][] = $basecategory;
            }
        }
    }

    /**
     * Reading a basecategory
     */
    public function get_manage_basecategory(array $args)
    {
        com_meego_packages_utils::require_admin();

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
            // relocate
            $this->mvc->head->relocate($this->get_url_admin_index());
        }

        $this->data['undelete'] = false;
        $this->data['indexurl'] = $this->get_url_admin_index();
        $this->data['form_action'] = $this->get_url_read($args['basecategory']);
    }

    /**
     * Creating a basecategory
     * @todo: finalize
     */
    public function post_create_basecategory(array $args)
    {
        com_meego_packages_utils::require_admin();

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
            }
            else
            {
                // create
                $this->object->name = $category['name'];

                $transaction = new midgard_transaction();
                $transaction->begin();

                $this->object->create();

                $transaction->commit();
                // @todo: try to do the mapping now
                // $this->post_create_relations(array('basecategory' => $this->object->guid));
            }

            if (! $this->object->guid)
            {
                $saved = false;
            }
        }

        if ($saved)
        {
            try
            {
                // @todo: add an uimessage
                $this->data['relocate'] = $this->get_url_admin_index();
                $this->mvc->head->relocate($this->data['relocate']);
            }
            catch (Exception $e)
            {
                // workaround for an MVC bug; this try - catch should not be needed
            }
        }
        else
        {
            throw new midgardmvc_exception_httperror("Could not populate default base categories", 500);
        }
    }

    /**
     * Update, delete, undelete a basecategory
     */
    public function post_manage_basecategory(array $args)
    {
        com_meego_packages_utils::require_admin();

        $this->data['category'] = false;
        $this->data['undelete'] = false;
        $this->data['undelete_error'] = false;
        $this->data['form_action'] = $this->get_url_read($args['basecategory']);
        $this->data['indexurl'] = $this->get_url_admin_index();

        // some counters need to be reset
        $this->data['mapping_counter'] = 0;
        $this->data['package_counter'] = 0;

        if (array_key_exists('undelete', $_POST))
        {
            if (! midgard_object_class::undelete($args['basecategory']))
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

        if (array_key_exists('undelete', $_POST))
        {
            $relations = self::load_relations_for_basecategory($this->object->id, true);

            if (is_array($relations))
            {
                foreach ($relations as $relation)
                {
                    midgard_object_class::undelete($relation->guid);
                }
            }
            // refresh mapping table
            $this->prepare_mapping($this->object);
            // set feedback and let it go
            $this->data['feedback'] = 'feedback_basecategory_undelete_ok';
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
            $this->delete_relations($this->object->id, true);

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
            // delete the base category
            $this->object->delete();
            // delete all its relations
            self::delete_relations($this->object->id, false);
            // allow undelete
            $this->data['undelete'] = true;
            // set feedback
            $this->data['feedback'] = 'feedback_basecategory_delete_ok';
            // some counters need to be reset
            $this->data['mapping_counter'] = 0;
            $this->data['package_counter'] = 0;
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
    private function delete_relations($basecategory_id = null, $purge = false)
    {
        com_meego_packages_utils::require_admin();

        if ($basecategory_id)
        {
            $relations = $this->load_relations_for_basecategory($basecategory_id);

            foreach($relations as $relation)
            {
                if ($purge)
                {
                    $relation->purge();
                }
                else
                {
                    $relation->delete();
                }
            }
        }
    }

    /**
     * Prepares some arrays for the mapping
     * @param object the basecategory object
     */
    public function prepare_mapping($object)
    {
        com_meego_packages_utils::require_admin();

        // this will set the package categories to this->data['categories']
        $packagecategories = com_meego_packages_controllers_category::get_all_package_categories();
        com_meego_packages_controllers_category::prepare_category_list($packagecategories);

        // to be used when displaying the number of package categories mapped to this base category
        $this->data['mapping_counter'] = 0;
        $this->data['package_counter'] = 0;

        // set a flag if both arrays are valid
        foreach ($this->data['categories'] as $category)
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
     * Loads all available base categories
     *
     * We could use a view later and then could have different criteria
     * like load only those bases who have packages for a certain UX
     *
     * @return array of base category objects
     */
    private function load_basecategories()
    {
        $storage = new midgard_query_storage('com_meego_package_basecategory');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('name'),
            '<>',
            new midgard_query_value('')
        );

        $q->set_constraint($qc);
        $q->execute();

        return $q->list_objects();
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
    private function load_relation($basecategory = null, $packagecategory = null, $includedeleted = false)
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

        if ($includedeleted)
        {
            $q->include_deleted(true);
        }

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
    public function load_relations_for_basecategory($basecategory_id = null, $includedeleted = false)
    {
        $relations = array();

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

            if ($includedeleted)
            {
                $q->include_deleted(true);
            }

            $q->execute();

            $relations = $q->list_objects();
        }

        return $relations;
    }

    /**
     * Redirects
     *
     * @param string OS name
     * @param string OS version
     * @param string UX name
     */
    public function redirect($os = null, $version = null, $ux = null)
    {
        if (   $os
            && $version
            && $ux)
        {
            $this->mvc->head->relocate
            (
                $this->mvc->dispatcher->generate_url
                (
                    'basecategories_os_version_ux',
                    array
                    (
                        'os' => $os,
                        'version' => (string) $version,
                        'ux' => $ux
                    ),
                    'com_meego_packages'
                )
            );
        }
    }
}
