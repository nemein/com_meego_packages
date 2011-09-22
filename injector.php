<?php
class com_meego_packages_injector
{
    var $mvc = null;

    public function __construct()
    {
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
    public function inject_process(midgardmvc_core_request $request)
    {
        // We inject the template to provide MeeGo styling
        $request->add_component_to_chain($this->mvc->component->get('com_meego_packages'), true);

        // Default title for Packages pages, override in controllers when possible
        $this->mvc->head->set_title($this->mvc->i18n->get('title_apps'));
    }

    /**
     * Some template hack
     */
    public function inject_template(midgardmvc_core_request $request)
    {
        $route = $request->get_route();

        // Replace the default MeeGo sidebar with our own
        $route->template_aliases['content-sidebar'] = 'cmp-show-sidebar';
        $route->template_aliases['main-menu'] = 'cmp-show-main_menu';

        // Add the CSS and JS files needed by Packages
        $this->add_head_elements();

        $request->set_data_item('admin', false);

        if ($this->mvc->authentication->is_user())
        {
            if ($this->mvc->authentication->get_user()->is_admin())
            {
                $request->set_data_item('admin', true);

                $category_admin_url = $this->mvc->dispatcher->generate_url
                (
                    'basecategories_admin_index', array(), $request
                );

                $request->set_data_item('category_admin_url', $category_admin_url);
            }
        }

        $repository_index_url = $this->mvc->dispatcher->generate_url
        (
            'repositories', array(), $request
        );

        $request->set_data_item('repository_index_url', $repository_index_url);

        // populate open workflows
        $request->set_data_item('repositories', com_meego_packages_controllers_workflow::get_open_workflows());

        self::set_breadcrumb($request);
    }

    /**
     * Adds js and css files to head
     */
    private function add_head_elements()
    {
        $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/eu_urho_widgets/js/jquery.rating/jquery.rating.pack.js');
        $this->mvc->head->add_link
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDGARDMVC_STATIC_URL . '/com_meego_packages/css/packages.css'
            )
        );
        midgardmvc_core::get_instance()->head->add_link
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDGARDMVC_STATIC_URL . '/eu_urho_widgets/js/jquery.rating/jquery.rating.css'
            )
        );
        midgardmvc_core::get_instance()->head->add_link
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDGARDMVC_STATIC_URL . '/com_meego_ratings/css/cmr-ratings.css'
            )
        );
    }


    /**
     * Sets the breadcrumb
     *
     * @param object midgardmvc_core_request  object to assign 'breadcrumb' for templates
     */
    public function set_breadcrumb(midgardmvc_core_request $request)
    {
        $nexturl = '';
        $breadcrumb = array();

        /*
        $nexturl = $this->mvc->dispatcher->generate_url('index', array(), $request);
        $firstitem = array(
            'title' => $this->mvc->i18n->get('title_apps'),
            'localurl' => $nexturl,
            'last' => false
        );

        $breadcrumb[] = $firstitem;
        */

        $cnt = 0;

        foreach ($request->argv as $arg)
        {
            $nexturl .= '/' . $arg;

            $item = array(
                'title' => ucfirst($arg),
                'localurl' => $nexturl,
                'last' => (count($request->argv) - 1 == $cnt) ? true : false
            );

            $breadcrumb[] = $item;

            ++$cnt;
        }

        $request->set_data_item('breadcrumb', $breadcrumb);
    }
}
?>
