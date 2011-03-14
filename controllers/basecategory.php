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
                $this->object->localurl = $this->get_url_read();
                $this->object->mappings = $this->get_relations_of_basecategory($this->object->id);
            }
        }

        if (   ! array_key_exists('basecategory', $args)
            || ! is_object($this->object))
        {
            $this->prepare_new_object($args);
        }
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
                $basecategory->mappings = $this->get_relations_of_basecategory($basecategory->id);
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
        try
        {
            $this->load_object($args);
            $this->data['category'] = $this->object;
            $this->data['feedback_objectname'] = $this->object->name;
            $this->data['undelete_error'] = false;
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
     * Updateing a basecategory
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

            $this->object->name = $new_name;
            $this->object->description = $new_description;
            $this->object->update();

            $this->data['feedback_objectname'] = $this->object->name;
            $this->data['feedback'] = 'feedback_basecategory_update_ok';
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
     * Looks up and returns all the relations of a particular base category
     */
    private function get_relations_of_basecategory($id)
    {
        return null;
    }
}