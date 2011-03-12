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
    public function load_object(array $args)
    {
        if (array_key_exists('basecategory', $args))
        {
            $this->object = new com_meego_package_basecategory($args['basecategory']);
        }
        else
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
    public function get_url_read()
    {
        return $this->mvc->dispatcher->generate_url
        (
            'basecategory_read', array
            (
                'basecategory' => $this->object->guid
            ),
            $this->request
        );
    }

    /**
     * @todo: docs
     */
    public function get_url_update()
    {
        return $this->mvc->dispatcher->generate_url
        (
            'basecategory_update', array
            (
                'basecategory' => $this->object->guid
            ),
            $this->request
        );
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

        //$this->data['redirect_url'] = '/';

        // check sufficient access rights
var_dump($this->mvc->authentication->is_user());
echo "\n";
var_dump($this->mvc->authorization->can_do('midgard:create', $this->object));
ob_flush();



        if (   ! $this->mvc->authentication->is_user()
            || ! $this->mvc->authorization->can_do('midgard:create', $this->object))
        {
            midgardmvc_core::get_instance()->head->relocate('/');
        }

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
                $basecategory->mappings = $this->get_relations_of_basecategory($basecategory->id);
                $this->data['basecategories'][] = $basecategory;
            }
        }
    }

    /**
     * Creating a basecategory
     */
    public function post_create_basecategory(array $args)
    {
    }

    /**
     * Reading a basecategory
     */
    public function get_read_basecategory(array $args)
    {
    }

    /**
     * Updateing a basecategory
     */
    public function post_update_basecategory(array $args)
    {
    }

    /**
     * Deleting a basecategory
     */
    public function post_delete_basecategory(array $args)
    {
    }

    /**
     * Looks up and returns all the relations of a particular base category
     */
    private function get_relations_of_basecategory($id)
    {
        return null;
    }
}