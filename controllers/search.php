<?php
class com_meego_packages_controllers_search
{
    var $mvc = null;
    var $request = null;

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
    public function get_search()
    {
        $query = $this->request->get_query();

        if (! isset($query['search']))
        {
            $this->mvc->head->relocate('/');
        }

        $this->request->set_data_item('search', $query['search');

        if (   ! isset($query['os'])
            || ! isset($query['version'])
            || ! isset($query['ux']))
        {
            $os = $this->mvc->configuration->default['os'];
            $version = $this->mvc->configuration->latest[$os]['version'];
            $ux = $this->mvc->configuration->latest[$os]['ux'];

            $search_url = rtrim($this->mvc->dispatcher->generate_url('search', array(), $this->request), '/');
            $search_url .= '?os=' . $os . '&version=' . $version . '&ux=' . $ux;
            $search_url .= '&search=' . $query['search'];

            $this->mvc->head->relocate($search_url);
        }

        $this->data['packages'] = array();

        $apps = com_meego_packages_controllers_application::get_applications($query, false, 'top');

        if ($apps == 1)
        {
            $package = array_pop($this->data['packages']);

            // Relocate to package directly
            $this->mvc->head->relocate
            (
                $this->mvc->dispatcher->generate_url
                (
                    'apps_by_title',
                    array
                    (
                        'os' => $query['os'],
                        'version' => $query['version'],
                        'ux' => $query['ux'],
                        'basecategory' => $package['basecategoryname'],
                        'packagetitle' => $package['name']
                    ),
                    $this->request
                )
            );
        }
    }
}
