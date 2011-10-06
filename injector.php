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
        //$route->template_aliases['content-sidebar'] = 'cmp-show-sidebar';
        //$route->template_aliases['main-menu'] = 'cmp-show-main_menu';

        if ($route->id == "apps_index")
        {
            $route->template_aliases['topbar'] = 'cmp-welcome-text';
        }
        else
        {
            $route->template_aliases['topbar'] = 'cmp-menubar';
        }

        // Add the CSS and JS files needed by Packages
        $this->add_head_elements();

        $request->set_data_item('admin', false);

        $request->set_data_item('category_admin_url', false);

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
        $request->set_data_item('workflows', false);

        $workflows = null;

        $matched = $route->get_matched();

        if (   is_array($matched)
            && array_key_exists('os', $matched)
            && array_key_exists('version', $matched)
            && array_key_exists('ux', $matched))
        {
            $os = $matched['os'];
            $ux = $matched['ux'];
            $redirect = false;

            $decoded = '';
            if (array_key_exists('basecategory', $matched))
            {
                $decoded = rawurldecode($matched['basecategory']);

                if (array_key_exists($decoded, $this->mvc->configuration->basecategory_css_map))
                {
                    $matched['basecategory_css'] = $this->mvc->configuration->basecategory_css_map[$decoded];
                }
                else
                {
                    $matched['basecategory_css'] = strtolower($decoded);
                }
            }
            else
            {
                $matched['basecategory'] = false;
                $matched['basecategory_css'] = '';
            }

            if (! array_key_exists($matched['os'], $this->mvc->configuration->os_map))
            {
                $redirect = true;
                $os = $this->mvc->configuration->latest['os'];
            }

            // if the matched UX is not configured then we shout out loud
            if (! array_key_exists($matched['ux'], $this->mvc->configuration->os_ux))
            {
                $redirect = true;
                $ux = $this->mvc->configuration->latest['ux'];
            }

            if ($redirect)
            {
                //throw new midgardmvc_exception_notfound("Please pick a valid UX, " . $matched['ux'] . " does not exist.", 404);
                com_meego_packages_controllers_basecategory::redirect($os, $matched['version'], $ux);
            }

            if (array_key_exists('packagetitle', $matched))
            {
                // when browsing an exact package then we rather provide a direct link to the QA page of the package
            }

            if (! $workflows)
            {
                // populate links if there any workflow that matches this OS, OS version and UX combo
                $workflows = com_meego_packages_controllers_workflow::get_open_workflows_for_osux(
                    $matched['os'],
                    $matched['version'],
                    $matched['ux']
                );
            }

            $request->set_data_item('workflows', $workflows);

            //gather available UXes
            $uxes = array();
            $versions = array();

            $repos = com_meego_packages_controllers_application::get_top_project_repos();
            foreach ($repos as $repo)
            {
                ($repo->repoosux == '') ? $ux = 'universal' : $ux = $repo->repoosux;

                // link to the latest UX / version combo
                $uxes[$ux]['versions'][$repo->repoosversion] = $repo;

                if (   ! array_key_exists('latest', $uxes[$ux])
                    || (float)$repo->repoosversion > $uxes[$ux]['latest'])
                {
                    $uxes[$ux]['latest'] = $repo->repoosversion;
                }


                // all versions of the matched, current UX
                if ((    $ux == 'universal'
                     ||  $matched['ux'] == $ux)
                    && ! array_key_exists($repo->repoosversion, $versions)
                    && (float) $repo->repoosversion > 0)
                {
                    $_repo = com_meego_packages_controllers_application::populate_repo_ux($repo, $matched['ux']);
                    $versions[$repo->repoosversion] = array(
                        'version' => $repo->repoosversion,
                        'url' => $_repo['url']
                    );
                }
            }

            if (array_key_exists('universal', $uxes))
            {
                $latest = $uxes['universal']['versions'][$uxes['universal']['latest']];
                foreach($this->mvc->configuration->os_ux as $configured_ux => $configured_ux_title)
                {
                    $uxes[$configured_ux] = com_meego_packages_controllers_application::populate_repo_ux($latest, $configured_ux);
                }
                // this won't be needed anymore as we set all UXes
                unset($uxes['universal'], $latest);
            }

            arsort($versions);

            $request->set_data_item('uxes', $uxes);
            $request->set_data_item('versions', $versions);
        }

        $request->set_data_item('matched', $matched);
        //self::set_breadcrumb($request);
    }

    /**
     * Adds js and css files to head
     */
    private function add_head_elements()
    {
        $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/eu_urho_widgets/js/jquery.rating/jquery.rating.js');
        $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/com_meego_packages/js/init_rating_widget.js');
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
