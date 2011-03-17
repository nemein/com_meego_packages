<?php
class com_meego_packages_controllers_application
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

        // there must be at least 1 top project configured
        if (! count($this->mvc->configuration->top_projects))
        {
            throw new midgardmvc_exception_httperror("There is no top project configured.", 500);
        }
    }

    /**
     * Redirect user to correct UX, if possible to detect
     */
    public function get_redirect(array $args)
    {
        // Fallback, didn't recognize browser's MeeGo version, redirect to list of repositories
        $this->mvc->head->relocate
        (
            $this->mvc->dispatcher->generate_url
            (
                'apps_index', array(), $this->request
            )
        );
    }

    /**
     * Generates the content for the index page showing the icons of variants
     */
    public function get_index(array $args)
    {
        $this->data['repositories'] = array();
        $this->data['oses'] = array();

        // use a view to determine which repositories can be shown
        $storage = new midgard_query_storage('com_meego_package_repository_project');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint_group('AND');

        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('repodisabledownload'),
            '=',
            new midgard_query_value(false)
        ));

        // filter the top projects only
        if (count($this->mvc->configuration->top_projects) == 1)
        {
            $top_project = array_keys($this->mvc->configuration->top_projects);
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('projectname'),
                '=',
                new midgard_query_value($top_project[0])
            ));
        }
        else
        {
            $qc2 = new midgard_query_constraint_group('OR');
            foreach ($this->mvc->configuration->top_projects as $top_project => $description)
            {
                $qc2->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('projectname'),
                    '=',
                    new midgard_query_value($top_project)
                ));
            }
            $qc->add_constraint($qc2);

        }

        $q->set_constraint($qc);
        $q->execute();

        $repositories = $q->list_objects();

        foreach ($repositories as $repository)
        {
            if (   $repository->repoos == 'meego'
                && $repository->repoosux)
            {
                $repository->os = 'MeeGo';
                $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['title'] = $repository->repoos . ' ' . $repository->repoosversion;
                $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['uxes'][$repository->repoosux]['title'] = ucfirst($repository->repoosux);
                $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['uxes'][$repository->repoosux]['css'] = $repository->repoosgroup . ' ' . $repository->repoosux;
                $localurl = $this->mvc->dispatcher->generate_url
                (
                    'apps_ux_index',
                    array
                    (
                        'ux' => $repository->repoosux
                    ),
                    $this->request
                );
                $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['uxes'][$repository->repoosux]['url'] = $localurl;
            }
        }
    }

    /**
     * Get applicarions (ie top packages) for a base category but
     * only if they belong to a certain ux and to a top project that is configurable
     *
     * This is eventually filters the results of
     * get_packages_by_basecategory()
     *
     * @param string name of base category
     * @param string name of ux
     *
     * @return array of packagedetails objects
     *
     * The code will get simples as soon as
     * https://github.com/midgardproject/midgard-core/issues#issue/86
     * is fixed and we can define joined views
     */
    public function get_applications(array $args)
    {
        $cnt = 0;

        $localpackages = array();

        $this->data['ux'] = false;
        $this->data['base'] = true;
        $this->data['packages'] = array();
        $this->data['basecategory'] = false;
        $this->data['categorytree'] = false;
        $this->data['packagetitle'] = false;

        if (array_key_exists('something', $args))
        {
            $args['ux'] = false;
            $args['basecategory'] = false;
            if (com_meego_packages_controllers_repository::ux_exists($args['something']))
            {
                // let's see if something is a ux
                $args['ux'] = $args['something'];
            }
            elseif (com_meego_packages_controllers_basecategory::basecategory_exists($args['something']))
            {
                // see if this is a basecategory
                $args['basecategory'] = $args['something'];
            }
        }

        if (   array_key_exists('ux', $args)
            && $args['ux'])
        {
            $this->data['ux'] = strtolower($args['ux']);
        }
        else
        {
            $args['ux'] = false;
        }

        if (   array_key_exists('packagetitle', $args)
            && $args['packagetitle'])
        {
            $this->data['packagetitle'] = strtolower($args['packagetitle']);
        }
        else
        {
            $args['packagetitle'] = false;
        }

        if (   array_key_exists('basecategory', $args)
            && $args['basecategory'])
        {
            // this sets data['packages'] and we just need to filter that
            $localpackages = self::get_applications_by_basecategory($args);
            $this->data['basecategory'] = strtolower($args['basecategory']);
        }
        else
        {
            // this sets data['packages'] and we just need to filter that
            $localpackages = self::get_filtered_applications(0, $this->data['ux']);
        }

        // prepare the data for the templates
        com_meego_packages_controllers_package::set_data($localpackages);

        // this is the placeholder for the needed apps
        $localpackages = array();

        foreach ($this->data['packages'] as $packagename => $package)
        {
            // create a copy of the package so that we can work on it
            $localpackages[$packagename] = $package;
            $localpackages[$packagename]['providers'] = array();

            // providers are the individual projects
            foreach($package['providers'] as $projectname => $project)
            {
                if (array_key_exists($projectname, $this->mvc->configuration->top_projects))
                {
                    $localpackages[$packagename]['providers'][$projectname] = $project;

                    if (isset($data['ux']))
                    {
                        // filter variants base on ux only if it is set in the request
                        $localpackages[$packagename]['providers'][$projectname]['variants'] = array();
                        // variants are the individual packages
                        // filter out the ones that have wrong repoosux and projectcategoryname
                        foreach ($project['variants'] as $variant)
                        {
                            if (   strtolower($variant->repoosux) == $this->data['ux']
                                || strtolower($variant->repoosux) == 'allux'
                                || strtolower($variant->repoosux) == '')
                            {
                                // cut this variant
                                $localpackages[$packagename]['providers'][$projectname]['variants'][] = $variant;
                            }
                        }

                        // if there is no suitable variant then remove the provider
                        if (! count($localpackages[$packagename]['providers'][$projectname]['variants']))
                        {
                            unset($localpackages[$packagename]['providers'][$projectname]);
                        }
                    }
                }
            }

            // if there is no provider for a package then let's remove it
            if (! count($localpackages[$packagename]['providers']))
            {
                unset($localpackages[$packagename]);
            }
        }

        $this->data['packages'] = $localpackages;

        unset($localpackages);

        return $this->data['packages'];
    }

    /**
     * Count number of apps (ie. packages group by package->title)
     * for the given basecategory and UX
     *
     * @param string name of the basecategory
     * @param string name of the ux
     *
     * @return integer total amount of apps
     *
     */
    public function count_number_of_apps($basecategory = '', $ux = '')
    {
        $packages = array();

        // gather packages from top projects that belong to this basecategory and ux
        $packages = array_merge(
            $packages,
            self::get_applications(
                array(
                    'basecategory' => $basecategory,
                    'ux' => $ux
                )
            )
        );

        return count($packages);
    }

    /**
     * Returns all apps that belong to a certain base category
     *
     * @param array args; 'basecategory' argument can be like: Games
     * @return array of com_meego_package_details objects
     */
    public function get_applications_by_basecategory(array $args)
    {
        // get the base category object
        $basecategory = com_meego_packages_controllers_basecategory::load_object($args);

        if (is_object($basecategory))
        {
            $packages = array();

            // get relations
            $relations = com_meego_packages_controllers_basecategory::load_relations_for_basecategory($basecategory->id);

            // gather all packages from each relation
            foreach ($relations as $relation)
            {
                $filtered = self::get_filtered_applications($relation->packagecategory, $args['ux'], $args['packagetitle']);
                $packages = array_merge($filtered, $packages);
            }

            // sort the packages by title
            uasort(
                $packages,
                function($a, $b)
                {
                    if ($a->packagetitle == $b->packagetitle) {
                        return 0;
                    }
                    return ($a->packagetitle < $b->packagetitle) ? -1 : 1;
                }
            );
        }
        else
        {
            // oops, there are no packages for this base category..
            throw new midgardmvc_exception_notfound("There is no category called: " . $args['basecategory']);
        }

        return $packages;
    }

    /**
     * Returns all packages that are filtered using
     *  - the package filter configuration
     *  - the package category id (if given)
     *  - the ux name (if given)
     *
     * @param integer package category id
     * @param string ux name
     * @return array of com_meego_package_details objects
     */
    public function get_filtered_applications($packagecategory_id = 0, $ux_name = false, $package_title = false)
    {
        $packages = array();
        $repo_constraint = null;
        $packagecategory_constraint = null;
        $ux_constraint = null;
        $packagetitle_constraint = null;

        $repo_constraint = new midgard_query_constraint(
            new midgard_query_property('repodisabledownload'),
            '=',
            new midgard_query_value(false)
        );

        if ($packagecategory_id)
        {
            $packagecategory_constraint = new midgard_query_constraint(
                new midgard_query_property('packagecategory'),
                '=',
                new midgard_query_value($packagecategory_id)
            );
        }

        if (is_string($ux_name)
            && strlen($ux_name))
        {
            $ux_constraint = new midgard_query_constraint(
                new midgard_query_property('repoosux'),
                '=',
                new midgard_query_value($ux_name)
            );
        }

        if (is_string($package_title)
            && strlen($package_title))
        {
            $packagetitle_constraint = new midgard_query_constraint(
                new midgard_query_property('packagetitle'),
                '=',
                new midgard_query_value($package_title)
            );
        }

        $storage = new midgard_query_storage('com_meego_package_details');
        $q = new midgard_query_select($storage);
        $qc = new midgard_query_constraint_group('AND');

        // filter the top projects only
        if (count($this->mvc->configuration->top_projects))
        {
            // filter the top projects only
            if (count($this->mvc->configuration->top_projects) == 1)
            {
                $top_project = array_keys($this->mvc->configuration->top_projects);
                $qc->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('repoprojectname'),
                    '=',
                    new midgard_query_value($top_project[0])
                ));
            }
            else
            {
                $qc2 = new midgard_query_constraint_group('OR');
                foreach ($this->mvc->configuration->top_projects as $top_project => $description)
                {
                    $qc2->add_constraint(new midgard_query_constraint(
                        new midgard_query_property('repoprojectname'),
                        '=',
                        new midgard_query_value($top_project)
                    ));
                }
                $qc->add_constraint($qc2);
            }
        }

        $qc->add_constraint($repo_constraint);

        if ($packagecategory_constraint)
        {
            $qc->add_constraint($packagecategory_constraint);
        }
        if ($ux_constraint)
        {
            $qc->add_constraint($ux_constraint);
        }
        if ($packagetitle_constraint)
        {
            $qc->add_constraint($packagetitle_constraint);
        }

        $q->set_constraint($qc);
        $q->execute();

        #$packages = $q->list_objects();
        #print_r($packages);
        #ob_flush();
        #die;

        $packages = self::filter_applications($q->list_objects(), $this->mvc->configuration->package_filters);

        // sort the packages by title
        uasort(
            $packages,
            function($a, $b)
            {
                if ($a->packagetitle == $b->packagetitle) {
                    return 0;
                }
                return ($a->packagetitle < $b->packagetitle) ? -1 : 1;
            }
        );

        return $packages;
    }

    /**
     * Filters out packages from an array using thepackage filter configuration
     *
     * @param array with com_meego_package_details objects
     * @param array of filters as per configured
     * @return array the filtered associative array
     */
    private static function filter_applications(array $packages, array $filters)
    {
        $localpackages = array();

        foreach ($packages as $package)
        {
            $filtered = false;

            // filter packages by their titles
            // useful to filter -src packages for example
            // the pattern of projects to be filtered is configurable
            foreach ($filters as $filter)
            {
                if (preg_match($filter, $package->packagetitle))
                {
                    $filtered = true;
                    break;
                }
            }

            if ($filtered)
            {
                continue;
            }

            // create a copy of the package so that we can work on it
            $localpackages[$package->packagetitle] = $package;
        }

        return $localpackages;
    }
}