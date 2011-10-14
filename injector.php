<?php
class com_meego_packages_injector
{
    var $mvc = null;
    var $part = 'applications';

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

        if ($route->id == "apps_index")
        {
            $route->template_aliases['topbar'] = 'cmp-welcome-text';
        }
        else
        {
            $route->template_aliases['topbar'] = 'cmp-menubar';
        }

        // login link with redirct specified
        $request->set_data_item('redirect_link', $this->mvc->context->get_request(0)->get_path());
        // placeholder for a link to list staging apps
        $request->set_data_item('staging_link', false);
        // set if user is admin
        $request->set_data_item('admin', false);
        // admins get a link to category management UI
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
                $os = $this->mvc->configuration->default['os'];
            }

            // if the matched UX is not configured then we shout out loud
            if (! array_key_exists($matched['ux'], $this->mvc->configuration->os_ux[$os]))
            {
                $redirect = true;
                $ux = $this->mvc->configuration->latest[$os]['ux'];
            }

            if ($redirect)
            {
                //throw new midgardmvc_exception_notfound("Please pick a valid UX, " . $matched['ux'] . " does not exist.", 404);
                com_meego_packages_controllers_basecategory::redirect($os, $matched['version'], $ux);
            }

            // gather available UXes for the popups
            // @todo: this piece of code is only needed for some of the routes
            // so we should not run it when not needed
            $uxes = array();
            $versions = array();

            $repositories = com_meego_packages_controllers_application::get_top_project_repos();

            $latest = $this->mvc->configuration->latest;

            foreach ($repositories as $repository)
            {
                if (   array_key_exists($repository->repoos, $latest)
                    && $repository->repoosversion == $latest[$repository->repoos]['version'])
                {
                    if (! strlen($repository->repoosux))
                    {
                        // No UX means a core or universal repo, so we populate all UXes
                        foreach ($this->mvc->configuration->os_ux[$repository->repoos] as $configured_ux => $configured_ux_title)
                        {
                            $uxes[$repository->repoos . $configured_ux] = com_meego_packages_controllers_application::populate_repo_ux($repository, $configured_ux);
                        }
                    }
                    else
                    {
                        $uxes[$repository->repoos . $repository->repoosux] = com_meego_packages_controllers_application::populate_repo_ux($repository);
                    }
                }

                if ($matched['os'] == $repository->repoos)
                {
                    // all versions of the matched, current UX
                    if (   $repository->repoosux == ''
                        || $repository->repoosux == 'universal'
                        || $repository->repoosux == $matched['ux']
                        && ! array_key_exists($repository->repoosversion, $versions))
                    {
                        $_repo = com_meego_packages_controllers_application::populate_repo_ux($repository, $matched['ux']);
                        $versions[$repository->repoosversion] = array (
                            'version' => $repository->repoosversion,
                            'url' => $_repo['url']
                        );
                    }
                }

                // if we are not serving a staging_ route then
                // check if the repo's project has a staging project configured
                if (   substr($route->id, 0, 8) != 'staging_'
                    && $repository->repoos == $os
                    && $this->mvc->configuration->top_projects[$repository->projectname]['staging'])
                {
                    $workflows = com_meego_packages_controllers_workflow::get_open_workflows_for_osux($repository->repoos, $repository->repoosversion, $repository->repoosux);

                    if (count($workflows))
                    {
                        // if there is at least 1 workflow then we set and show the link in the templates
                        $link = $this->mvc->dispatcher->generate_url
                        (
                            'staging_basecategories_os_version_ux',
                            array
                            (
                                'os' => $os,
                                'version' => (string) $repository->repoosversion,
                                'ux' => $repository->repoosux
                            ),
                            'com_meego_packages'
                        );
                        $request->set_data_item('staging_link', $link);
                    }
                }
            }

            krsort($uxes);
            ksort($versions);

            $request->set_data_item('uxes', $uxes);
            $request->set_data_item('versions', $versions);
        }

        // in case there is no matched stuff from the request we will use the defaults configured
        if (! is_array($matched))
        {
            $matched = array();
        }

        if (   is_array($matched)
            && (   ! array_key_exists('os', $matched)
                || ! array_key_exists('version', $matched)
                || ! array_key_exists('ux', $matched)))
        {
            $matched = array_merge($matched, $this->mvc->configuration->latest[$this->mvc->configuration->default['os']]);
            $matched['os'] = $this->mvc->configuration->default['os'];
            $this->part = 'packages';
        }

        // Add the CSS and JS files needed by Packages
        $this->add_head_elements();

        $matched['translated_ux'] = ucwords($this->mvc->i18n->get('title_' . $matched['ux'] . '_ux'));

        $request->set_data_item('matched', $matched);
        $request->set_data_item('submit_app_url', $this->mvc->configuration->submit_app_url);

        $request->set_data_item('staging_area', false);
        if (substr($route->id, 0, 8) == 'staging_')
        {
            $request->set_data_item('staging_area', true);
        }
    }

    /**
     * Adds js and css files to head
     */
    private function add_head_elements()
    {
        $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/eu_urho_widgets/js/jquery.rating/jquery.rating.js');

        if ($this->part == 'applications')
        {
            $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/com_meego_packages/js/init_rating_widget_big.js');
        }
        else
        {
            $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/com_meego_packages/js/init_rating_widget_small.js');
        }
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
