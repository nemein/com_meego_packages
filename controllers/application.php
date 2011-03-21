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
                    'basecategories_for_ux_index',
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
                // something identifies a ux
                $args['ux'] = $args['something'];
            }
            elseif (com_meego_packages_controllers_basecategory::basecategory_exists($args['something']))
            {
                // something is a basecategory actually
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
            $this->data['packagetitle'] = $args['packagetitle'];
        }
        else
        {
            $args['packagetitle'] = false;
        }

        if (   array_key_exists('basecategory', $args)
            && $args['basecategory'])
        {
            $this->data['basecategory'] = strtolower($args['basecategory']);
            // this sets data['packages'] and we just need to filter that
            $localpackages = self::get_applications_by_basecategory($args);
        }
        else
        {
            // this sets data['packages'] and we just need to filter that
            $localpackages = self::get_filtered_applications(0, $this->data['ux']);
        }

        // this will fill in providers, variants and statistics for each package
        // by filtering out those projects that are not top_projects (see configuration)
        // and variant names that don't fit the package filter criteria (see configuration)
        self::set_data($localpackages);

        // we return this counter as it is used by count_number_of_apps()
        return count($this->data['packages']);
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
        $counter = self::get_applications(
            array(
                'basecategory' => $basecategory,
                'ux' => $ux
            )
        );

        return $counter;
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
            $ux_constraint = new midgard_query_constraint_group('OR');

            $ux_constraint->add_constraint(new midgard_query_constraint(
                new midgard_query_property('repoosux'),
                '=',
                new midgard_query_value($ux_name)
            ));
            $ux_constraint->add_constraint(new midgard_query_constraint(
                new midgard_query_property('repoosux'),
                '=',
                new midgard_query_value('')
            ));
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

        if ($packagetitle_constraint)
        {
            // if we look for an exact title then make sure we sort packages by versions
            // this way when we do the filter_applications the associative array returned
            // will only have the latest version of a certain application
            $q->add_order(new midgard_query_property('packageversion', $storage), SORT_ASC);
        }

        $q->execute();

        #$packages = $q->list_objects();
        #print_r($packages);

        // filter apps so that only the ones remain that are allowed by package filter configuration
        $packages = self::filter_titles($q->list_objects(), $this->mvc->configuration->package_filters);

        #print_r($packages);
        #ob_flush();
        #die;

        return $packages;
    }

    /**
     * Filters out packages from an array using thepackage filter configuration
     *
     * @param array with com_meego_package_details objects
     * @param array of filters as per configured
     * @return array the filtered associative array
     */
    private static function filter_titles(array $packages, array $filters)
    {
        $localpackages = array();

        foreach ($packages as $package)
        {
            $filtered = false;

            // filter packages by their titles (see configuration: package_filters)
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

            $localpackages[] = $package;
        }

        return $localpackages;
    }

    /**
     * Sets data for the template
     * It is used in two routes so that is why we have it as a separate function
     * @param array of packages
     */
    public function set_data(array $packages)
    {
        $localpackages = array();

        // let's do a smart grouping by package_title
        $variant_counter = 0;

        foreach ($packages as $package)
        {
            // check if the project that supplies this package is a top_project (see configuration)
            if (array_key_exists($package->repoprojectname, $this->mvc->configuration->top_projects))
            {
                // include only the necassary variants
                if (   $this->data['ux'] == false
                    || ($this->data['ux'] != ''
                        && (   strtolower($package->repoosux) == $this->data['ux'])
                            || strtolower($package->repoosux) == 'allux'
                            || strtolower($package->repoosux) == ''))
                {
                    if (! isset($this->data['packages'][$package->packagetitle]['name']))
                    {
                        // set the name
                        $this->data['packages'][$package->packagetitle]['name'] = $package->packagetitle;

                        if (! strlen($this->data['basecategory']))
                        {
                            $this->data['basecategory'] = com_meego_packages_controllers_package::determine_base_category($package);
                        }


                        // a hack to get a ux for linking to detailed package view
                        if (   ! isset($this->data['packages'][$package->packagetitle]['localurl'])
                            && $package->repoosux != '')
                        {
                            $ux = $package->repoosux;

                            if (strlen($this->data['ux']))
                            {
                                $ux = $this->data['ux'];
                            }

                            $this->data['packages'][$package->packagetitle]['localurl'] = $this->mvc->dispatcher->generate_url
                            (
                                'apps_title_basecategory_ux',
                                array
                                (
                                    'ux' => $ux,
                                    'basecategory' => $this->data['basecategory'],
                                    'packagetitle' => $package->packagetitle
                                ),
                                $this->request
                            );
                        }

                        // check if we have ux
                        if ( ! strlen($this->data['ux']))
                        {
                            $this->data['ux'] = strtolower($package->repoosux);
                        }

                        $this->data['ux'] = '';
                        $this->data['basecategory'] = '';

                        // gather some basic stats
                        $stats = com_meego_packages_controllers_package::get_statistics($package->packagetitle);

                        // set the total number of comments
                        $this->data['packages'][$package->packagetitle]['number_of_comments'] = $stats['number_of_comments'];

                        // the stars as html snippet for the average rating; should be used as-is in the template
                        $this->data['packages'][$package->packagetitle]['stars'] = com_meego_ratings_controllers_rating::draw_stars($stats['average_rating']);

                        // set a longer description
                        $this->data['packages'][$package->packagetitle]['description'] = $package->packagedescription;

                        // set a screenshoturl if the package object has any
                        $this->data['packages'][$package->packagetitle]['screenshoturl'] = false;

                        $_package = new com_meego_package($package->packageid);
                        $attachments = $_package->list_attachments();

                        foreach ($attachments as $attachment)
                        {
                            $this->data['packages'][$package->packagetitle]['screenshoturl'] = $this->mvc->dispatcher->generate_url
                            (
                                'attachmentserver_variant',
                                array
                                (
                                    'guid' => $attachment->guid,
                                    'variant' => 'sidesquare',
                                    'filename' => $attachment->name,
                                ),
                                '/'
                            );
                            break;
                        }
                    }

                    // if the UX is empty then we consider the package to be good for all UXes
                    // this value is used in the template to show a proper icon
                    $package->ux = $package->repoosux;
                    if ( ! strlen($package->ux) )
                    {
                        $package->ux = 'allux';
                    }

                    // provide a link to visit the page of a certain package variant
                    $package->localurl = $this->mvc->dispatcher->generate_url
                    (
                        'package_instance',
                        array
                        (
                            'package' => $package->packagetitle,
                            'version' => $package->packageversion,
                            'project' => $package->repoprojectname,
                            'repository' => $package->reponame,
                            'arch' => $package->repoarch
                        ),
                        $this->request
                    );

                    // we group the variants into providers. a provider is basically a project repository, e.g. home:fal
                    $this->data['packages'][$package->packagetitle]['providers'][$package->repoprojectname]['projectname'] = $package->repoprojectname;

                    // the variants are basically the versions built for different hardware architectures (not UXes)
                    $this->data['packages'][$package->packagetitle]['providers'][$package->repoprojectname]['variants'][] = $package;

                    // @todo: need to filter out variants in case there are multiple versions available
                }
            }
        }
    }
}