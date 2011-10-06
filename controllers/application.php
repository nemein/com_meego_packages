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
     * Prepares an array with important data of the repository
     *
     * @param object repository object
     * @param string UX name, in case the repository has none
     * @return array with data
     */
    public function populate_repo_ux($repository = null, $default_ux = 'universal')
    {
        $retval = array();

        if (strlen($repository->repoosux))
        {
            $default_ux = $repository->repoosux;
        }

        //$repository->os = strtolower($repository->repoos);
        $retval['translated_title'] = ucwords($this->mvc->i18n->get('title_' . $default_ux . '_ux'));
        $retval['title'] = ucwords($default_ux);
        $retval['css'] = $repository->repoosgroup . ' ' . $default_ux;

        $localurl = $this->mvc->dispatcher->generate_url
        (
            'basecategories_os_version_ux',
            array
            (
                'os' => $repository->repoos,
                'version' => $repository->repoosversion,
                'ux' => $default_ux
            ),
            'com_meego_packages'
        );
        $retval['url'] = $localurl;

        return $retval;
    }

    /**
     * Returns an array with all repos that belong to top projects
     *
     * @return array
     */
    public function get_top_project_repos()
    {
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

        $list = $q->list_objects();

        return $list;
    }

    /**
     * Generates the content for the index page showing the icons of variants
     * Here we gather the uxes from the latest version of the OS that is
     * configurable
     *
     * @param array args
     */
    public function get_index(array $args)
    {
        $this->data['oses'] = array();
        $this->data['latest'] = false;
        $this->data['repositories'] = array();

        $latest_os = $this->mvc->configuration->latest['os'];
        $latest_os_version = $this->mvc->configuration->latest['version'];

        $repositories = $this->get_top_project_repos();

        foreach ($repositories as $repository)
        {
            if (   $repository->repoos == strtolower($latest_os)
                && $repository->repoosversion == $latest_os_version)
            {
                $this->data['latest']['title'] = $this->mvc->configuration->os_map[$repository->repoos] . ' ' . $repository->repoosversion;

                if (! strlen($repository->repoosux))
                {
                    // No UX means a core or universal repo, so we populate all UXes
                    foreach($this->mvc->configuration->os_ux as $configured_ux => $configured_ux_title)
                    {
                        #echo "add configured latest: " . $repository->repoos . ' ' . $repository->repoosversion . ': ' . $configured_ux . "\n";
                        $this->data['latest']['uxes'][$configured_ux] = $this->populate_repo_ux($repository, $configured_ux);
                    }
                }
                else
                {
                    $this->data['latest']['uxes'][$repository->repoosux] = $this->populate_repo_ux($repository);
                    #echo "add latest: " . $repository->repoos . ' ' . $repository->repoosversion . ': ' . $repository->repoosux . "\n";
                }
            }
            else
            {
                $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['title'] = $this->mvc->configuration->os_map[$repository->repoos] . ' ' . $repository->repoosversion;

                if (! strlen($repository->repoosux))
                {
                    // No UX means a core repo, so we populate all UXes
                    foreach($this->mvc->configuration->os_ux as $configured_ux => $configured_ux_title)
                    {
                        $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['uxes'][$configured_ux] = $this->populate_repo_ux($repository, $configured_ux);
                        #echo "add configured normal: " . $repository->repoos . ' ' . $repository->repoosversion . ': ' . $configured_ux . "\n";
                    }
                }
                else
                {
                    $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['uxes'][$repository->repoosux] = $this->populate_repo_ux($repository);
                    #echo "add normal: " . $repository->repoos . ' ' . $repository->repoosversion . ': ' . $repository->repoosux . "\n";
                }
            }
        }
        #ob_flush();
    }

    /**
     * Get applications (ie top packages) for a base category but
     * only if they belong to a certain ux and to a top project that is configurable
     *
     * This is eventually filters the results of
     * get_packages_by_basecategory()
     *
     * @param array of args (os, version, ux, basecategory, packagetitle)
     * @param boolean true indicates the need of the counter only
     *
     * @return array of packagedetails objects
     *
     * The code will get simpler as soon as
     * https://github.com/midgardproject/midgard-core/issues#issue/86
     * is fixed and we can define joined views
     */
    public function get_applications(array $args, $counter = false)
    {
        $cnt = 0;

        $retval = array();
        $packages = array();

        $this->data['os'] = false;
        $this->data['version'] = false;
        $this->data['ux'] = false;
        $this->data['base'] = true;
        $this->data['packages'] = array();
        $this->data['basecategory'] = false;
        $this->data['categorytree'] = false;
        $this->data['packagetitle'] = false;

        if (   array_key_exists('os', $args)
            && $args['os'])
        {
            $this->data['os'] = strtolower($args['os']);
        }
        else
        {
            $args['os'] = $this->mvc->configuration->latest['os'];
        }

        if (   array_key_exists('version', $args)
            && $args['version'])
        {
            $this->data['version'] = strtolower($args['version']);
        }
        else
        {
            $args['version'] = $this->mvc->configuration->latest['version'];;
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
            $packages = self::get_applications_by_criteria($args);
        }
        else
        {
            // this sets data['packages'] and we just need to filter that
            $packages = self::get_filtered_applications($this->data['os'], $this->data['version'], 0, $this->data['ux']);
        }

        $this->data['rows'] = false;
        $this->data['pages'] = false;
        $this->data['previous_page'] = false;
        $this->data['next_page'] = false;
        $this->data['items_shown'] = '';
        $this->data['submit_app_url'] = $this->mvc->configuration->submit_app_url;

        if (! count($packages))
        {
            // say something
            $this->data['packages'] = array();
            return 0;
        }

        // enable for testing, if you need large packages array for paging
        //$_package = array_pop($packages);
        //$packages = array_fill(0, 90, $_package);
        //$localpackages = $packages;
        //$apps_counter = count($localpackages);

        // real:
        $localpackages = $packages;

        $apps_counter = self::count_unique_apps($packages);
        $this->data['total_apps'] = $apps_counter;

        // do the paging
        // unfortunately we can't implement this using SQL constraint
        // because we have a title filter feature that uses regexps
        // and Midgard does not support REGEXP or RLIKE operators
        // so it is easier to do the paging on the ready result set
        // total number of packages (well apps in this context)
        $current_page = 1;

        if (   array_key_exists('page', $_GET)
            && is_numeric($_GET['page'])
            && $_GET['page'] > 0)
        {
            $current_page = $_GET['page'];
        }

        $limit = $this->mvc->configuration->rows_per_page * $this->mvc->configuration->items_per_row;

        $this->data['highest_page_number'] = ceil($apps_counter / $limit);

        if ($apps_counter > 4 * $limit)
        {
            if (($current_page - 1) * $limit > $apps_counter)
            {
                $current_page = $this->data['highest_page_number'];
            }

            if ($current_page > 4)
            {
                for ($i = $current_page - 3; $i <= $current_page; $i++)
                {
                    $this->data['pages'][] =$i;
                }
            }
            else
            {
                for ($i = 1; $i <= 4; $i++)
                {
                    $this->data['pages'][] = $i;
                }
            }
        }

        if (   $current_page > 0
            && $apps_counter > $limit)
        {
            // we cut the result set according to paging request
            $offset = ($current_page - 1) * $limit;

            if ($offset > $apps_counter)
            {
                $offset = $apps_counter - $limit;
            }

            if (($current_page - 1) > 0)
            {
                $this->data['previous_page'] = '?page=' . ($current_page - 1);
            }

            if ($current_page * $limit < $apps_counter)
            {
                $this->data['next_page'] = '?page=' . ($current_page + 1);
            }

            // make sure we have enough unique apps for set_data()
            $cnt = $limit;
            do
            {
                $localpackages = array_slice($packages, $offset, $cnt, true);
            }
            while (   self::count_unique_apps($localpackages) < $limit
                   && ($offset + $cnt++) <= count($packages));

            //
            if ($current_page == 1)
            {
                if ($limit > $apps_counter)
                {
                    $this->data['items_shown'] = '1 - ' . $apps_counter;
                }
                else
                {
                    $this->data['items_shown'] = '1 - ' . $limit;
                }
            }
            elseif ($current_page * $limit <= $apps_counter)
            {
                $this->data['items_shown'] = (($current_page - 1) * $limit) + 1 . ' - ' .  $current_page * $limit;
            }
            else
            {
                $this->data['items_shown'] = (($current_page - 1) * $limit) + 1 . ' - ' .  ((($current_page - 1) * $limit) + count($localpackages));
            }
        }

        // this will fill in providers, variants and statistics for each package
        // by filtering out those projects that are not top_projects (see configuration)
        // and variant names that don't fit the package filter criteria (see configuration)
        self::set_data($localpackages);

        // enable for testing, if you need large package array for the template
        // $this->data['packages'] = array_fill(0, 12, array_pop($this->data['packages']));

        // let's prepare the rows for the template
        $per_row = $this->mvc->configuration->items_per_row;
        for ($i = 1; $i <= ceil(count($this->data['packages']) / $per_row); $i++)
        {
            $this->data['rows'][] = array_slice($this->data['packages'], ($i - 1) * $per_row, $per_row, true);
        }

        // if an exact application is shown
        if (   $this->data['packagetitle']
            && count($this->data['packages']))
        {
            // enable commenting
            if ($this->mvc->authentication->is_user())
            {
                $this->data['can_post'] = true;
                self::enable_commenting();
            }
            else
            {
                $this->data['can_post'] = false;
            }
        }

        // if have no apps then return a 404
        // may not even get here since we return already well above
        if (   ! count($this->data['packages'])
            && ! $counter)
        {
            if ($this->data['packagetitle'])
            {
                $error_msg = $this->mvc->i18n->get('no_such_package');
            }
            else
            {
                $error_msg = $this->mvc->i18n->get('no_available_packages');
            }

            // oops, there are no packages for this base category..
            throw new midgardmvc_exception_notfound($error_msg);
        }

        // we return this counter as it is used by count_number_of_apps()
        return $apps_counter;
    }

    /**
     * Count number of apps (ie. packages group by package->title)
     * for the given basecategory and UX
     *
     * @param string name os the os
     * @param string version number of the os
     * @param string name of the basecategory
     * @param string name of the ux
     *
     * @return integer total amount of apps
     *
     */
    public function count_number_of_apps($os = '', $os_version = '', $basecategory = '', $ux = '')
    {
        $counter = self::get_applications(
            array(
                'os' => $os,
                'version' => $os_version,
                'basecategory' => $basecategory,
                'ux' => $ux
            ),
            true
        );

        return $counter;
    }

    /**
     * Count number of unique apps in an array
     *
     * @param array of com_meego_package_details objects
     *
     * @return integer total amount of apps
     *
     */
    public function count_unique_apps($packages)
    {
        $apps = array();

        foreach ($packages as $package)
        {
            if ($package)
            {
                $apps[$package->packagetitle] = $package->packagetitle;
            }
        }

        return count($apps);
    }

    /**
     * Returns all apps that match the criteria specified by the args
     *
     * @param array args
     * @return array of com_meego_package_details objects
     */
    public function get_applications_by_criteria(array $args)
    {
        $ux = null;
        if (array_key_exists('ux', $args))
        {
            $ux = $args['ux'];
        }

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
                if ($args['packagetitle'])
                {
                    $filtered = self::get_filtered_applications($args['os'], null, $relation->packagecategory, $ux, $args['packagetitle']);
                }
                else
                {
                    $filtered = self::get_filtered_applications($args['os'], $args['version'], $relation->packagecategory, $ux, $args['packagetitle']);
                }

                if (is_array($filtered))
                {
                    $packages = array_merge($filtered, $packages);
                }
            }

            // sort the packages by title
            if (count($packages))
            {
                uasort(
                    $packages,
                    function($a, $b)
                    {
                        if ($a->packagetitle == $b->packagetitle)
                        {
                            return 0;
                        }
                        return ($a->packagetitle < $b->packagetitle) ? -1 : 1;
                    }
                );
            }
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
     * @param string os name of the OS
     * @param string version of the OS
     * @param integer package category id
     * @param string ux name
     * @return array of com_meego_package_details objects
     */
    public function get_filtered_applications($os = null, $os_version = null, $packagecategory_id = 0, $ux_name = false, $package_title = false)
    {
        $apps = null;
        $packages = array();
        $os_constraint = null;
        $os_version_constraint = null;
        $repo_constraint = null;
        $packagecategory_constraint = null;
        $ux_constraint = null;
        $packagetitle_constraint = null;

        $repo_constraint = new midgard_query_constraint(
            new midgard_query_property('repodisabledownload'),
            '=',
            new midgard_query_value(false)
        );

        if ($os)
        {
            $os_constraint = new midgard_query_constraint(
                new midgard_query_property('repoos'),
                '=',
                new midgard_query_value($os)
            );
        }

        if ($os_version)
        {
            $os_version_constraint = new midgard_query_constraint(
                new midgard_query_property('repoosversion'),
                '=',
                new midgard_query_value($os_version)
            );
        }

        if ($packagecategory_id)
        {
            $packagecategory_constraint = new midgard_query_constraint(
                new midgard_query_property('packagecategory'),
                '=',
                new midgard_query_value($packagecategory_id)
            );
        }

        if (   is_string($ux_name)
            && strlen($ux_name)
            && $ux_name != 'universal')
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

        if ($os_constraint)
        {
            $qc->add_constraint($os_constraint);
        }
        if ($os_version_constraint)
        {
            $qc->add_constraint($os_version_constraint);
        }
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

        // sort by title
        $q->add_order(new midgard_query_property('packagetitle', $storage), SORT_ASC);

        $q->execute();

        $all_objects = $q->list_objects();

        // filter apps so that only the ones remain that are allowed by package filter configuration
        if ($packagetitle_constraint)
        {
            // no unique array in case an exact package was requested
            // otherwise we will loose the available architectures
            $apps = self::filter_titles($all_objects, $this->mvc->configuration->package_filters, false);
        }
        else
        {
            $apps = self::filter_titles($all_objects, $this->mvc->configuration->package_filters, false);
        }

        //echo 'all packages: ' . count($all_objects) . ', apps after filtering: ' . count($apps) . "\n";
        //ob_flush();

        return $apps;
    }

    /**
     * Filters out packages from an array using the package filter configuration
     * Also filters out those packages that do not belong to any basecatgory yet
     *
     * @param array with com_meego_package_details objects
     * @param array of filters as per configured
     * @param boolean make the return array contain unique items only
     * @return array associative array of com_meego_package_details objects
     */
    public function filter_titles(array $packages, array $filters, $unique = false)
    {
echo "filter titles; unique " . $unique . "\n";
ob_flush();

        $apps = array();
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

            $basecategory = com_meego_packages_controllers_package::determine_base_category($package);

            // if could not determine the basecategory then skip this package; it should not be listed
            if (! strlen($basecategory))
            {
                $filtered = true;
            }

            if ($filtered)
            {
                continue;
            }

            if ($unique)
            {
                $localpackages[$package->packagetitle] = $package;
            }
            else
            {
                $localpackages[] = $package;
            }
        }

        return $localpackages;
    }

    /**
     * Sets $this->data['packages'] for the template
     *
     * This function groups packages into applications, so e.g. 5 variants of
     * the same package will mean 1 item in the final array.
     *
     * @param array of com_meeg_package_details objects
     *
     */
    public function set_data(array $packages)
    {
        $older = array();
        $latest = array('os' => '', 'version' => '', 'variants' => array());

        $matched = $this->request->get_route()->get_matched();

        // this index will be used to grab the download URL of the appropriate arch variant of the package
        $index = 0;
        if (array_key_exists($matched['ux'], $this->mvc->configuration->ux_arch_map))
        {
            $index = $this->mvc->configuration->ux_arch_map[$matched['ux']];
        }

        foreach ($packages as $package)
        {
            if (! isset($this->data['packages'][$package->packagetitle]['name']))
            {
                // set the name
                $this->data['packages'][$package->packagetitle]['name'] = $package->packagetitle;

                // gather some basic stats
                $stats = com_meego_packages_controllers_package::get_statistics($package->packagetitle);

                // set the total number of comments
                $this->data['packages'][$package->packagetitle]['number_of_comments'] = $stats['number_of_comments'];

                // the stars as html snippet for the average rating; should be used as-is in the template
                $this->data['packages'][$package->packagetitle]['average_rating'] = $stats['average_rating'];
                $this->data['packages'][$package->packagetitle]['stars'] = com_meego_ratings_controllers_rating::draw_stars($stats['average_rating']);

                // collect ratings and comments (used in application detailed view)
                if (! array_key_exists('ratings', $this->data['packages'][$package->packagetitle]))
                {
                    $this->data['packages'][$package->packagetitle]['ratings'] = array();
                }

                $this->data['packages'][$package->packagetitle]['ratings'] = self::prepare_ratings($package->packagetitle);

                // set a summary
                $this->data['packages'][$package->packagetitle]['summary'] = $package->packagesummary;
                // set a longer description
                $this->data['packages'][$package->packagetitle]['description'] = $package->packagedescription;
                $this->data['packages'][$package->packagetitle]['basecategoryname'] = $package->basecategoryname;
                // base category name

                $this->data['packages'][$package->packagetitle]['iconurl'] = false;
                $this->data['packages'][$package->packagetitle]['screenshoturl'] = false;

                // a package may have multiple arches
                // but we should nominate one to be the default in order to support the new design
                $this->data['packages'][$package->packagetitle]['defaultdownloadurl'] = false;

                $_package = new com_meego_package($package->packageid);

                $attachments = $_package->list_attachments();

                $_icon_marker = 'icon.png';
                $_screenshot_marker = 'screenshot.png';

                foreach ($attachments as $attachment)
                {
                    if ($attachment->mimetype == 'image/png')
                    {
                        if (    strrpos($attachment->name, $_screenshot_marker) !== false
                             && ! $this->data['packages'][$package->packagetitle]['screenshoturl'])
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
                        }

                        if (    strrpos($attachment->name, $_icon_marker) !== false
                             && ! $this->data['packages'][$package->packagetitle]['iconurl'])
                        {
                            $this->data['packages'][$package->packagetitle]['iconurl'] = $this->mvc->dispatcher->generate_url
                            (
                                'attachmentserver_variant',
                                array
                                (
                                    'guid' => $attachment->guid,
                                    'variant' => '',
                                    'filename' => $attachment->name,
                                ),
                                '/'
                            );
                        }
                    }
                }
            }

            // if the UX is empty then we consider the package to be good for all UXes
            // this value is used in the template to show a proper icon
            $package->ux = $package->repoosux;

            if ( ! strlen($package->ux) )
            {
                $package->ux = 'universal';
            }

            $this->data['ux'] = $package->ux;

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

            // set the latest version of the package
            // and also maintain an array with older versions (could be used in some templates)
            if (! array_key_exists($package->packageversion, $older))
            {
                $older[$package->packageversion] = array('version' => '', 'providers' => array());
            }

            if (! array_key_exists($package->repoprojectname, $older[$package->packageversion]['providers']))
            {
                $older[$package->packageversion]['providers'][$package->repoprojectname] = array('projectname' => '', 'variants' => array());
            }

            if ($latest['version'] < $package->packageversion)
            {
                if (count($latest['variants']))
                {
                    // merge current latest to older
                    $older[$package->packageversion]['providers'][$package->repoprojectname]['variants'] = array_merge($older[$package->packageversion]['providers'][$package->repoprojectname]['variants'], $latest['variants']);
                    $latest['variants'] = array();
                }
                // add the variant that has the same UX as requested
                $latest['variants'][$package->repoarch] = $package;
                $latest['version'] = $package->packageversion;
                $latest['ux'] = $package->ux;
            }
            elseif ($latest['version'] == $package->packageversion)
            {
                // same version, but probably different arch
                $latest['variants'][$package->repoarch] = $package;
            }
            elseif ($latest['version'] > $package->packageversion)
            {
                // this package clearly goes to older
                $older[$package->packageversion]['version'] = $package->packageversion;
                $older[$package->packageversion]['providers'][$package->repoprojectname]['projectname'] = $package->repoprojectname;
                $older[$package->packageversion]['providers'][$package->repoprojectname]['variants'][] = $package;
            }

            // set the default download url for the package
            if (     array_key_exists($index, $latest['variants'])
                && ! $this->data['packages'][$package->packagetitle]['defaultdownloadurl'])
            {
                // index was set at the beginning of this method
                $this->data['packages'][$package->packagetitle]['defaultdownloadurl'] = $latest['variants'][$index]->packageinstallfileurl;
            }

            // set the latest of this package
            if (! array_key_exists('latest', $this->data['packages'][$package->packagetitle]))
            {
                $this->data['packages'][$package->packagetitle]['latest'] = array('version' => '', 'variants' => array());
            }

            if ($this->data['packages'][$package->packagetitle]['latest']['version'] <= $latest['version'])
            {
                $this->data['packages'][$package->packagetitle]['latest'] = $latest;
            }

            if (array_key_exists($latest['version'], $older))
            {
                // always remove the latest from the older array
                unset($older[$latest['version']]);
            }

            // always keep it up-to-date
            $this->data['packages'][$package->packagetitle]['older'] = $older;

            $this->data['packages'][$package->packagetitle]['localurl'] = $this->mvc->dispatcher->generate_url
            (
                'apps_by_title',
                array
                (
                    'os' => $package->repoos,
                    'version' => $package->repoosversion,
                    'ux' => (array_key_exists('ux', $matched)) ? $matched['ux'] : $package->ux,//$latest['ux'],
                    'basecategory' => com_meego_packages_controllers_package::determine_base_category($package), //$this->data['basecategory'],
                    'packagetitle' => $package->packagetitle
                ),
                $this->request
            );

            // get the workflows for this package
            // todo: this will get workflows for older versions too!
            if (! array_key_exists('workflows', $this->data['packages'][$package->packagetitle]))
            {
                $this->data['packages'][$package->packagetitle]['workflows'] = array();
            }

            $object = new com_meego_package($package->packageguid);
            $list_of_workflows = midgardmvc_helper_workflow_utils::get_workflows_for_object($object);

            foreach ($list_of_workflows as $workflow => $workflow_data)
            {
                $this->data['packages'][$package->packagetitle]['workflows'][] = array
                (
                    'label' => $workflow_data['label'],
                    'url' => $this->mvc->dispatcher->generate_url
                    (
                        'package_instance_workflow_start',
                        array
                        (
                            'package' => $package->packagename,
                            'version' => $package->packageversion,
                            'project' => $package->repoprojectname,
                            'repository' => $package->reponame,
                            'arch' => $package->repoarch,
                            'workflow' => $workflow,
                        ),
                        'com_meego_packages'
                    )
                );
            }
        } //foreach

        // let's unset the default variant, so we don't list it among the "other downloads"
        unset($this->data['packages'][$package->packagetitle]['latest']['variants'][$index]);

        // and we don't need this either
        unset($latest);
    }

    /**
     * Adds a form to page if commenting is enabled
     *
     * Uses the data array that is set earlier for the templates
     */
    public function enable_commenting()
    {
        $matched = $this->request->get_route()->get_matched();

        $this->data['relocate'] = $this->mvc->dispatcher->generate_url(
            'apps_by_title',
            array
            (
                'os' => $matched['os'],
                'version' => $matched['version'],
                'ux' => $matched['ux'],
                'basecategory' => $matched['basecategory'],
                'packagetitle' => $matched['packagetitle']
            ),
            $this->request
        );

        if (count($this->data['packages'][$this->data['packagetitle']]['latest']['variants']))
        {
            // set all variants so user can choose
            foreach ($this->data['packages'][$this->data['packagetitle']]['latest']['variants'] as $variant)
            {
                $this->data['architectures'][$variant->repoarch] = array
                (
                    'name' => $variant->repoarch,
                    'packageguid' => $variant->packageguid
                );
            }

            // get the 1st variant and set packageguid variable, in case we don't offer choosing a variant
            $variant = reset($this->data['packages'][$this->data['packagetitle']]['latest']['variants']);
            $this->data['packageguid'] = $variant->packageguid;
        }

        $this->data['postaction'] = $this->mvc->dispatcher->generate_url
        (
            'rating_create', array
            (
                'to' => $this->data['packageguid']
            ),
            'com_meego_ratings_caching'
        );
    }

    /**
     * Process application comments and ratings
     *
     * It basically adds the comment and rating to each architecture variant of
     * the latest version of the app, because these are the versions downloadable
     * on the detailed apps view page
     *
     */
    public function post_comment_application(array $args)
    {
        if (   ! is_array($_POST)
            || ! isset($_POST['packageguid']))
        {
            $this->mvc->head->relocate($_POST['relocate']);
        }

        $this->data['feedback'] = false;

        $guid = $_POST['packageguid'];

        $comment = null;

        // if comment is also given then create a new comment entry
        if (isset($_POST['comment']))
        {
            $content = $_POST['comment'];
            if (strlen($content))
            {
                // save comment only if it is not empty
                $comment = new com_meego_comments_comment();

                $comment->to = $guid;
                $comment->content = $content;

                if (! $comment->create())
                {
                    die ("can't create comment");
                }
            }
        }

        if (isset($_POST['rating']))
        {
            $rate = $_POST['rating'];

            if ($rate > $this->mvc->configuration->maxrate)
            {
                $rate = $this->mvc->configuration->maxrate;
            }

            $rating = new com_meego_ratings_rating();

            $rating->to = $guid;
            $rating->rating = $rate;

            if (is_object($comment))
            {
                $rating->comment = $comment->id;
            }

            if ($rating->create())
            {
                $this->data['feedback'] = 'ok';
            }
            else
            {
                $this->data['feedback'] = 'nok';
            }
        }

        if (isset($_POST['relocate']))
        {
            midgardmvc_core::get_instance()->head->relocate($_POST['relocate']);
        }
    }

    /**
     * Gathers ratings and appends them to data
     *
     * @param string title of the application
     * @return array of ratings together with their comments
     */
    public function prepare_ratings($application_title = null)
    {
        $retval = array();

        $storage = new midgard_query_storage('com_meego_package_ratings');
        $q = new midgard_query_select($storage);

        $q->set_constraint
        (
            new midgard_query_constraint
            (
                new midgard_query_property('title'),
                '=',
                new midgard_query_value($application_title)
            )
        );

        $q->add_order(new midgard_query_property('posted', $storage), SORT_DESC);
        $q->execute();

        $ratings = $q->list_objects();

        if (count($ratings))
        {
            foreach ($ratings as $rating)
            {
                $rating->stars = '';

                if (   $rating->rating
                    || $rating->commentid)
                {
                    // add a new property containing the stars to the rating object
                    $rating->stars = com_meego_ratings_controllers_rating::draw_stars($rating->rating);
                    // pimp the posted date
                    $rating->date = gmdate('Y-m-d H:i e', strtotime($rating->posted));
                    // avatar part
                    $rating->avatar = false;

                    if ($rating->authorguid)
                    {
                        $username = null;

                        // get the midgard user name from rating->authorguid
                        $user = com_meego_packages_utils::get_user_by_person_guid($rating->authorguid);

                        if (   $user
                            && $user->login != 'admin')
                        {
                            // get avatar and url to user profile page only if the user is not the midgard admin
                            try
                            {
                                $rating->avatar = $this->mvc->dispatcher->generate_url('meego_avatar', array('username' => $user->login), '/');
                                $rating->avatarurl = $this->mvc->configuration->user_profile_prefix . $user->login;
                            }
                            catch (Exception $e)
                            {
                                // no avatar
                            }
                        }
                    }
                }

                array_push($retval, $rating);
            }
            unset ($ratings);
        }

        return $retval;
    }
}