<?php
class com_meego_packages_controllers_application
{
    var $mvc = null;
    var $isuser = false;
    var $request = null;

    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;

        $this->mvc = midgardmvc_core::get_instance();
        $this->isuser = $this->mvc->authentication->is_user();

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

        $retval['configured_title'] = ucwords($this->mvc->configuration->os_ux[$repository->repoos][$default_ux]);

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
        $this->data['latest'] = false;
        $this->data['repositories'] = array();

        // must be an array
        $latest = $this->mvc->configuration->latest;

        // top repos that we show to "end users"
        $repositories = $this->get_top_project_repos();

        foreach ($repositories as $repository)
        {
            if (   array_key_exists($repository->repoos, $latest)
                && $repository->repoosversion == $latest[$repository->repoos]['version'])
            {
                // a nicer title for the OS
                //$this->data['latest'][$repository->repoos]['title'] = $this->mvc->configuration->os_map[$repository->repoos]['name'] . ' ' . $repository->repoosversion;

                if (! strlen($repository->repoosux))
                {
                    // No UX means a core or universal repo, so we populate all UXes
                    foreach($this->mvc->configuration->os_ux[$repository->repoos] as $configured_ux => $configured_ux_title)
                    {
                        $this->data['latest']['uxes'][$repository->repoos . $configured_ux] = $this->populate_repo_ux($repository, $configured_ux);
                    }
                }
                else
                {
                    $this->data['latest']['uxes'][$repository->repoos . $repository->repoosux] = $this->populate_repo_ux($repository);
                }
            }
        }

        // ugly hack to get N9 to the beginning
        if (   array_key_exists('latest', $this->data)
            && is_array($this->data['latest'])
            && array_key_exists('uxes', $this->data['latest']))
        {
            krsort($this->data['latest']['uxes']);
        }
    }

    /**
     * Get staging applications that are in repos of staging projects for a base category but
     * only if they belong to a certain ux and to a top project that is configurable
     *
     * @param array of args (os, version, ux, basecategory, packagename)
     */
    public function get_staging_applications(array $args)
    {
        self::get_applications($args, false, 'staging');
    }

    /**
     * Get staging applications that are in repos of staging projects for a base category but
     * only if they belong to a certain ux and to a top project that is configurable
     *
     * @param array of args (os, version, ux, basecategory, packagename)
     */
    public function get_newest_applications(array $args)
    {
        self::get_applications($args, false, 'newest');
    }

    /**
     * Get the 3 hottest application
     *
     * @param array of args (os, version, ux, basecategory, packagename)
     */
    public function get_hottest_applications(array $args)
    {
        self::get_applications($args, false, 'hottest');
    }

    /**
     * Get applications (ie top packages) for a base category but
     * only if they belong to a certain ux and to a top project that is configurable
     *
     * This is eventually filters the results of
     * get_packages_by_basecategory()
     *
     * @param array of args (os, version, ux, basecategory, packagename, search)
     * @param boolean true indicates the need of the counter only
     * @param string filter type 'top' deal with apps in top projects; 'staging' work on apps in staging projects only
     *
     * @return array of packagedetails objects
     *
     * The code will get simpler as soon as
     * https://github.com/midgardproject/midgard-core/issues#issue/86
     * is fixed and we can define joined views
     */
    public function get_applications(array $args, $counter = false, $filter_type = 'top')
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
        $this->data['packagename'] = false;

        $default_os = $this->mvc->configuration->default['os'];

        // this method is called from other classes too
        $this->isuser = $this->mvc->authentication->is_user();

        if (   array_key_exists('os', $args)
            && $args['os'])
        {
            $this->data['os'] = strtolower($args['os']);
        }
        else
        {
            $args['os'] = $default_os;
        }

        if (   array_key_exists('version', $args)
            && $args['version'])
        {
            $this->data['version'] = strtolower($args['version']);
        }
        else
        {
            if (array_key_exists($args['os'], $this->mvc->configuration->latest))
            {
                $args['version'] = $this->mvc->configuration->latest[$args['os']]['version'];
            }
            else
            {
                $args['version'] = $this->mvc->configuration->latest[$default_os]['version'];
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

        if (   array_key_exists('packagename', $args)
            && $args['packagename'])
        {
            $this->data['packagename'] = $args['packagename'];
        }
        else
        {
            $args['packagename'] = false;
        }

        $freetext_search = null;
        if (   array_key_exists('search', $args)
            && $args['search'])
        {
            $freetext_search = $args['search'];
        }


        if (   array_key_exists('basecategory', $args)
            && $args['basecategory'])
        {
            $args['basecategory'] = urldecode($args['basecategory']);
            $this->data['basecategory'] = strtolower($args['basecategory']);
            // this sets data['packages'] and we just need to filter that
            $packages = self::get_applications_by_criteria($args, $filter_type, $freetext_search);
        }
        else
        {
            // this sets data['packages'] and we just need to filter that
            $packages = self::get_filtered_applications($this->data['os'], $this->data['version'], 0, 0, $this->data['ux'], $args['packagename'], $filter_type, $freetext_search);
        }

        $this->data['rows'] = false;
        $this->data['pages'] = false;
        $this->data['previous_page'] = false;
        $this->data['next_page'] = false;
        $this->data['items_shown'] = '';
        $this->data['submit_app_url'] = $this->mvc->configuration->submit_app_url;

        if (! count($packages))
        {
            $this->data['packages'] = array();

            if ($this->data['packagename'])
            {
                // what if we just redirect?
                $url = '..';
                $this->mvc->head->relocate($url);
            }
            return false;
        }

        $localpackages = $packages;

        $apps_counter = self::count_unique_apps($packages);
        $this->data['total_apps'] = $apps_counter;

        if (! $counter)
        {
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

            if ($apps_counter > $limit)
            {
                if (($current_page - 1) * $limit > $apps_counter)
                {
                    $current_page = $this->data['highest_page_number'];
                }

                for ($i = 1; $i <= ceil($apps_counter / $limit); $i++)
                {
                    $this->data['pages'][] =$i;
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

                if (($current_page * $limit) < $apps_counter)
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

            // populate package details
            self::set_data($localpackages, $filter_type);

            // let's prepare the rows for the template
            $per_row = $this->mvc->configuration->items_per_row;
            for ($i = 1; $i <= ceil(count($this->data['packages']) / $per_row); $i++)
            {
                $this->data['rows'][] = array_slice($this->data['packages'], ($i - 1) * $per_row, $per_row, true);
            }

            $this->data['can_post'] = false;

            // if an exact application is shown
            if (   $this->data['packagename']
                && count($this->data['packages']))
            {
                // enable commenting and check if user has rated yet
                if ($this->isuser)
                {
                    $this->data['can_post'] = true;
                    $this->data['can_rate'] = self::can_rate($this->data['packages'][$this->data['packagename']]['packageguid']);

                    self::enable_commenting($filter_type);
                }
            }

            $this->data['profile_prefix'] = $this->mvc->configuration->user_profile_prefix;

            // if have no apps then return a 404
            // may not even get here since we return already well above
            if (! count($this->data['packages']))
            {
                // what if we just redirect?
                $url = '..';
                $this->mvc->head->relocate($url);

                if ($this->data['packagename'])
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
     * @param string filter type: 'top' for top projects only, 'staging' for staging projects only
     *
     * @return integer total amount of apps
     *
     */
    public function count_number_of_apps($os = '', $os_version = '', $basecategory = '', $ux = '', $filter_type = 'top')
    {
        //echo "called count_num_of_apps\n"; ob_flush();
        $counter = self::get_applications(
            array(
                'os' => $os,
                'version' => $os_version,
                'basecategory' => $basecategory,
                'ux' => $ux
            ),
            true,
            $filter_type
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
                $apps[$package->packagename] = $package->packagename;
            }
        }

        return count($apps);
    }

    /**
     * Returns all apps that match the criteria specified by the args
     *
     * @param array GET args
     * @param string filter type: 'top' for top projects only, 'staging' for staging projects only
     * @param string for free text search: title, name, summary, filename will be searched.
     *
     * @return array of com_meego_package_details objects
     */
    public function get_applications_by_criteria(array $args, $filter_type = 'top', $freetext_search = null)
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
                if ($args['packagename'])
                {
                    $filtered = self::get_filtered_applications($args['os'], null, $relation->packagecategory, $basecategory->id, $ux, $args['packagename'], $filter_type, $freetext_search);
                }
                else
                {
                    $filtered = self::get_filtered_applications($args['os'], $args['version'], $relation->packagecategory, $basecategory->id, $ux, $args['packagename'], $filter_type, $freetext_search);
                }

                if (is_array($filtered))
                {
                    $packages = array_merge($filtered, $packages);
                }
            }

            // sort the packages by name
            if (count($packages))
            {
                uasort(
                    $packages,
                    function($a, $b)
                    {
                        if ($a->packagename == $b->packagename)
                        {
                            return 0;
                        }
                        return ($a->packagename < $b->packagename) ? -1 : 1;
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
     * @param string package name
     * @param string filter type "top" or "staging" or "mix" (means both top and staging)
     *               default filter type: top
     * @param string for free text search: title, name, summary, filename will be searched.
     *
     * @return array of com_meego_package_details objects
     */
    public function get_filtered_applications($os = null, $os_version = null, $packagecategory_id = 0, $basecategory_id = 0, $ux_name = false, $package_name = false, $filter_type = 'top', $freetext_search = null)
    {
        $this->mvc->log("Get filtered apps: " . $os . ', ' . $os_version . ', ' . $packagecategory_id . ', ' . $ux_name . ', ' . $package_name . ', ' . $filter_type, 'info');

        $apps = null;
        $packages = array();
        $os_constraint = null;
        $os_version_constraint = null;
        $repo_constraint = null;
        $packagecategory_constraint = null;
        $basecategory_constraint = null;
        $ux_constraint = null;
        $packagename_constraint = null;
        $freetext_search_constraint = null;

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

        if ($basecategory_id)
        {
            $basecategory_constraint = new midgard_query_constraint(
                new midgard_query_property('basecategory'),
                '=',
                new midgard_query_value($basecategory_id)
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

        if (   is_string($package_name)
            && strlen($package_name))
        {
            $packagename_constraint = new midgard_query_constraint(
                new midgard_query_property('packagename'),
                '=',
                new midgard_query_value($package_name)
            );
        }
        elseif (isset($freetext_search))
        {
            //Make the SQL providers happy
            $freetext_search = '%' . $freetext_search . '%';

            $freetext_search_constraint = new midgard_query_constraint_group('OR');

            $freetext_search_constraint->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagename'),
                'LIKE',
                new midgard_query_value($freetext_search)
            ));
            $freetext_search_constraint->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagetitle'),
                'LIKE',
                new midgard_query_value($freetext_search)
            ));
            $freetext_search_constraint->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagesummary'),
                'LIKE',
                new midgard_query_value($freetext_search)
            ));
            $freetext_search_constraint->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagedescription'),
                'LIKE',
                new midgard_query_value($freetext_search)
            ));
            $freetext_search_constraint->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagehomepageurl'),
                'LIKE',
                new midgard_query_value($freetext_search)
            ));
            $freetext_search_constraint->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagelicense'),
                'LIKE',
                new midgard_query_value($freetext_search)
            ));
            $freetext_search_constraint->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagefilename'),
                'LIKE',
                new midgard_query_value($freetext_search)
            ));
            if (! $packagecategory_constraint)
            {
                $freetext_search_constraint->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('packagecategoryname'),
                    'LIKE',
                    new midgard_query_value($freetext_search)
                ));
                $freetext_search_constraint->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('basecategoryname'),
                    'LIKE',
                    new midgard_query_value($freetext_search)
                ));
            }
        }

        $storage = new midgard_query_storage('com_meego_package_details');

        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint_group('OR');

        if ($filter_type != 'mix')
        {
            // filter all hidden packages unless we are in mix filtering mode
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagehidden'),
                '=',
                new midgard_query_value(1)
            ));
        }

        // filter packages by their names (we always do this)
        foreach ($this->mvc->configuration->sql_package_filters as $filter)
        {
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagename'),
                'LIKE',
                new midgard_query_value($filter)
            ));
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagetitle'),
                'LIKE',
                new midgard_query_value($filter)
            ));
        }
        $q->set_constraint($qc);
        $q->execute();

        $filtered = array();

        foreach ($q->list_objects() as $package)
        {
            $filtered[] = $package->packageid;
        }

        // reset $qc and do a new query including all package IDs that must be filtered
        $qc = new midgard_query_constraint_group('AND');

        if (count($filtered))
        {
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packageid'),
                'NOT IN',
                new midgard_query_value($filtered)
            ));
        }

        //filter for top, staging or both types of projects
        if (count($this->mvc->configuration->top_projects))
        {
            $repoproject_filter = null;

            switch ($filter_type)
            {
                case 'newest':
                    $q->add_order(new midgard_query_property('packagecreated'), SORT_DESC);
                    break;
                case 'hottest':
                    $q->add_order(new midgard_query_property('statscachedratingvalue'), SORT_DESC);
                    break;
            }

            switch ($filter_type)
            {
                case 'mix':
                    $repoproject_filter = self::get_repoproject_filter('mix');
                    $q->add_order(new midgard_query_property('packageversion'), SORT_ASC);
                    break;
                case 'staging':
                    $repoproject_filter = self::get_repoproject_filter('staging');
                    break;
                case 'newest':
                case 'hottest':
                case 'top':
                default:
                    $repoproject_filter = self::get_repoproject_filter('top');
            }

            if ($repoproject_filter)
            {
                $qc->add_constraint($repoproject_filter);
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
        if ($basecategory_constraint)
        {
            $qc->add_constraint($basecategory_constraint);
        }
        if ($ux_constraint)
        {
            $qc->add_constraint($ux_constraint);
        }
        if ($packagename_constraint)
        {
            $qc->add_constraint($packagename_constraint);
        }
        elseif ($freetext_search_constraint)
        {
            $qc->add_constraint($freetext_search_constraint);
        }

        $q->set_constraint($qc);

        if ($packagename_constraint)
        {
            // if we look for an exact title then make sure we sort packages by versions
            // this way when we do the filter_applications the associative array returned
            // will only have the latest version of a certain application
            $q->add_order(new midgard_query_property('packageversion', $storage), SORT_ASC);
        }

        // sort by title
        //$q->add_order(new midgard_query_property('packagename', $storage), SORT_ASC);

        $q->execute();

        $all_objects = $q->list_objects();

        // filter apps so that only the ones remain that are allowed by package filter configuration
        if ($packagename_constraint)
        {
            // no unique array in case an exact package was requested
            // otherwise we will loose the available architectures
            $apps = self::filter_titles($all_objects, false);
        }
        else
        {
            $apps = self::filter_titles($all_objects, true);
        }

        // cut the array to 3 members only
        $scope = 3;

        if (array_key_exists('scope', $_GET))
        {
            $scope = $_GET['scope'];
        }

        if (   is_numeric($scope)
            && $scope != 'all')
        {
            switch ($filter_type)
            {
                case 'newest':
                case 'hottest':
                    $apps = array_slice($apps, 0, $scope);
            }
        }

        return $apps;
    }

    /**
     * Filters out packages from an array using the package filter configuration
     * Also filters out those packages that do not belong to any basecatgory yet
     *
     * @param array with com_meego_package_details objects
     * @param boolean make the return array contain unique items only
     *
     * @return array associative array of com_meego_package_details objects
     */
    public function filter_titles(array $packages, $unique = false)
    {
        $apps = array();
        $localpackages = array();

        foreach ($packages as $package)
        {
            $filtered = false;

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
                $localpackages[$package->packagename] = $package;
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
     * @param string filter type: 'top' or 'staging': settings URL will depend on it
     * @param boolean if true then return info about older packages too
     *
     * @return array
     *
     */
    public function set_data(array $packages, $filter_type = 'top', $history = false)
    {
        $all = array();

        // a flag for tricky situations caused by additional hand imports
        $remember = false;
        // store guid for promoted apps (ie. apps that passed qa)
        $promotedtoguid = '';
        $promotedfromguid = '';

        $default_latest = array('packagescore' => '', 'packagename' => '', 'packageguid' => '', 'promotedfromguid' => '', 'promotedtoguid' => '', 'os' => '', 'version' => '', 'size' => 'n/a', 'promoted' => 'n/a', 'released' => 'n/a', 'lastupdate' => 'n/a', 'variants' => array(), 'iconurl' => '');
        $latest = $default_latest;

        $matched = $this->request->get_route()->get_matched();

        // this index will be used to grab the download URL of the appropriate arch variant of the package
        $index = 0;
        if (   isset($matched['ux'])
            && array_key_exists($matched['ux'], $this->mvc->configuration->ux_arch_map))
        {
            $index = $this->mvc->configuration->ux_arch_map[$matched['ux']];
        }

        foreach ($packages as $package)
        {
            if ($package->packagename != $latest['packagename'])
            {
                // processing a different package, so reset some internal arrays
                $all = array();
                $latest = $default_latest;
            }

            if (! isset($this->data['packages'][$package->packagename]['name']))
            {
                // set the guid
                $this->data['packages'][$package->packagename]['packageguid'] = $package->packageguid;

                // set the name
                $this->data['packages'][$package->packagename]['name'] = $package->packagename;

                // set the title
                $this->data['packages'][$package->packagename]['title'] = $package->packagetitle;

                // get roles
                $this->data['packages'][$package->packagename]['roles'] = self::get_roles($package->packageguid);

                // gather some basic stats
                $stats = com_meego_packages_controllers_package::get_statistics($package->packagename);

                // set the total number of comments
                $this->data['packages'][$package->packagename]['number_of_comments'] = $stats['number_of_comments'];

                // figure out if there are posted forms for this app
                $this->data['packages'][$package->packagename]['posted_forms'] = count(com_meego_packages_forms::get_all_forms($package->packageguid));

                if (array_key_exists('number_of_rates', $stats))
                {
                    // total number of rates
                    $this->data['packages'][$package->packagename]['number_of_rates'] = $stats['number_of_rates'];
                }

                // the stars as html snippet for the average rating; should be used as-is in the template
                $this->data['packages'][$package->packagename]['average_rating'] = $stats['average_rating'];
                $this->data['packages'][$package->packagename]['stars'] = com_meego_ratings_controllers_rating::draw_stars($stats['average_rating']);

                // collect ratings and comments (used in application detailed view)
                if (! array_key_exists('ratings', $this->data['packages'][$package->packagename]))
                {
                    $this->data['packages'][$package->packagename]['ratings'] = array();
                }

                $ratings = self::prepare_ratings($package->packagename, $history);
                $this->data['packages'][$package->packagename]['ratings'] = $ratings['ratings'];
                $this->data['packages'][$package->packagename]['is_there_comment'] = ($history) ? $history : $ratings['comment'];

                // set a summary
                $this->data['packages'][$package->packagename]['summary'] = $package->packagesummary;
                // set a longer description
                $this->data['packages'][$package->packagename]['description'] = $package->packagedescription;
                $this->data['packages'][$package->packagename]['basecategoryname'] = $package->basecategoryname;
                // base category name

                $this->data['packages'][$package->packagename]['iconurl'] = false;
                $this->data['packages'][$package->packagename]['screenshoturl'] = false;

                // a package may have multiple arches
                // but we should nominate one to be the default in order to support the new design
                $this->data['packages'][$package->packagename]['defaultdownloadurl'] = false;

                $_package = new com_meego_package($package->packageid);

                $attachments = $_package->list_attachments();

                $_icon_marker = 'icon.png';
                $_screenshot_marker = 'screenshot.png';

                $this->data['packages'][$package->packagename]['screenshots'] = false;

                foreach ($attachments as $attachment)
                {
                    if ($attachment->mimetype == 'image/png')
                    {
                        if (strrpos($attachment->name, $_screenshot_marker) !== false)
                        {
                            $this->data['packages'][$package->packagename]['screenshots'][] = $this->mvc->dispatcher->generate_url
                            (
                                'attachmentserver_variant',
                                array
                                (
                                    'guid' => $attachment->guid,
                                    'variant' => 'prop480x300',
                                    'filename' => $attachment->name,
                                ),
                                '/'
                            );
                        }

                        if (    strrpos($attachment->name, $_icon_marker) !== false
                             && ! $this->data['packages'][$package->packagename]['iconurl'])
                        {
                            $this->data['packages'][$package->packagename]['iconurl'] = $this->mvc->dispatcher->generate_url
                            (
                                'attachmentserver_variant',
                                array
                                (
                                    'guid' => $attachment->guid,
                                    'variant' => 'icon',
                                    'filename' => $attachment->name,
                                ),
                                '/'
                            );
                        }
                    }
                }
            }

            if (count($this->data['packages'][$package->packagename]['screenshots']))
            {
                $this->data['packages'][$package->packagename]['screenshoturl'] = $this->data['packages'][$package->packagename]['screenshots'][0];
            }

            // if the UX is empty then we consider the package to be good for all UXes
            // this value is used in the template to show a proper icon
            $package->ux = $package->repoosux;

            if ( ! strlen($package->ux) )
            {
                $package->ux = 'universal';
            }

            $this->data['ux'] = $package->ux;

            // provide a link to visit the app page
            $package->localurl = $this->mvc->dispatcher->generate_url
            (
                'apps_by_name',
                array
                (
                    'os' => $package->repoos,
                    'version' => $package->repoosversion,
                    'ux' => $package->repoosux,
                    'basecategory' => $package->basecategoryname,
                    'packagename' => $package->packagename
                ),
                $this->request
            );

            // provide a link to visit the app page
            $this->data['packages'][$package->packagename]['historyurl'] = $this->mvc->dispatcher->generate_url
            (
                'history',
                array
                (
                    'os' => $package->repoos,
                    'version' => $package->repoosversion,
                    'ux' => $package->repoosux,
                    'packagename' => $package->packagename
                ),
                $this->request
            );

            $variant_info = array(
                'ux' => $package->repoosux,
                'repoos' => $package->repoos,
                'repoarch' => $package->repoarch,
                'packageid' => $package->packageid,
                'packageguid' => $package->packageguid,
                'packageinstallfileurl' => $package->packageinstallfileurl
            );

            //echo "current latest: " . $latest['packagename'] . ':' . $latest['version'] . ' vs package: ' . $package->packagename . ':' . $package->packageversion . "\n";

            if ($latest['version'] <= $package->packageversion)
            {
                $qarelease = $this->mvc->i18n->get('label_app_type_qarelease');
                $promoted = $this->mvc->i18n->get('label_app_type_promoted');
                $staging = $this->mvc->i18n->get('label_app_type_staging');

                //echo $package->packagename . ':' . $package->packageversion . ' from ' . $package->repoprojectname . " will be set as latest\n";
                //echo "latest score: " . $latest['packagescore'] . ', curtent package score: ' . $package->packagescore . "\n";

                if ($latest['packageguid'])
                {
                    if ($latest['version'] == $package->packageversion)
                    {
                        if (   $latest['repoid'] == $package->repoid
                            && (int) $latest['packagescore'] > $package->packagescore)
                        {
                            $remember = $latest;
                            //echo $package->packageversion . ' (score: ' . $remember['packagescore'] . ') from ' . $package->reponame .' repo id: ' . $package->repoid . " is already the latest, maybe it was reimported by hand for some reason\n";
                        }
                        else
                        {
                            //echo $package->packageversion . " is already the latest, this is probably from an other project; check if it was ever promoted\n";
                            $predecessors = self::get_predecessors($package->packageguid, 3);

                            if (count($predecessors))
                            {
                                foreach($predecessors as $key => $guid)
                                {
                                    //echo "compare: " . $guid . ' vs ' . $latest['packageguid'] . "\n";
                                    if (   $guid == $latest['packageguid']
                                        || (   isset($remember)
                                            && $guid == $remember['packageguid']
                                            && $remember['packagescore'] > 0
                                            && $remember['packagescore'] > $package->packagescore))
                                    {
                                        //echo "this version: " . $package->packageversion . ' was promoted on ' . $package->packagecreated->format('Y-m-d h:i e') . "\n";
                                        $promotedfromguid = $guid;
                                        if ($remember)
                                        {
                                            //echo "update history backwards";
                                            $all[$remember['released']]['promotedtoguid'] = $remember['packageguid'];
                                            $all[$remember['released']]['type'] = $promoted;
                                            $remember = false;
                                        }
                                        else
                                        {
                                            $promotedtoguid = $package->packageguid;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $all[$latest['released']] = array(
                        'size' => $latest['size'],
                        'repoid' => $latest['repoid'],
                        'version' => $latest['version'],
                        'variants' => $latest['variants'],
                        'released' => $latest['released'],
                        'packageid' => $latest['packageid'],
                        'packageguid' => $latest['packageguid'],
                        'packagescore' => $latest['packagescore'],
                        'hidden' => $latest['hidden'],
                        'publishers' => $latest['publishers'],
                        'localurl' => (isset($latest['localurl'])) ? $latest['localurl'] : false
                    );

                    if ($latest['promotedfromguid'])
                    {
                        $latest['type'] = $qarelease;
                        $all[$latest['released']]['promotedfromguid'] = $latest['promotedfromguid'];
                    }
                    else if ($promotedtoguid)
                    {
                        $latest['type'] = $promoted;
                        $all[$latest['released']]['promotedtoguid'] = $promotedtoguid;
                        $promotedtoguid = '';
                    }
                    else
                    {
                        $latest['type'] = $staging;
                    }

                    $all[$latest['released']]['type'] = $latest['type'];

                    $latest = $default_latest;
                }

                $latest['ux'] = $package->ux;
                $latest['size'] = ($package->packagesize) ?  (int) ($package->packagesize / 1024) . ' kb' : 'n/a';
                $latest['publishers'] = self::get_roles($package->packageguid, 'downloader');
                $latest['repoid'] = $package->repoid;
                $latest['hidden'] = $package->packagehidden;
                $latest['version'] = $package->packageversion;
                $latest['released'] = $package->packagecreated->format('Y-m-d H:i');
                // @todo: this might not be needed
                $latest['variants'][$package->repoarch] = $variant_info;
                $latest['packageid'] = $package->packageid;
                $latest['lastupdate'] = $package->packagerevised->format('Y-m-d H:i');
                $latest['packageguid'] = $package->packageguid;
                $latest['packagename'] = $package->packagename;
                $latest['packagescore'] = $package->packagescore;

                if ($promotedfromguid)
                {
                    $latest['promotedfromguid'] = $promotedfromguid;
                    if (! $latest['hidden'])
                    {
                        $latest['localurl'] = $package->localurl;
                    }
                    $promotedfromguid = '';
                }
                else if ($promotedtoguid)
                {
                    $latest['promotedtoguid'] = $promotedtoguid;
                    $promotedtoguid = '';
                }
                else
                {
                    if (! $latest['hidden'])
                    {
                        $latest['localurl'] = '/staging' . $package->localurl;
                    }
                }
            }
            elseif ($latest['version'] > $package->packageversion)
            {
                // this should not happen if the packages are ordered by version
                // anyway this package should go to all
                //echo $package->packageversion . ' from ' . $package->repoprojectname . ' goes to all' . "\n";
                $all[$package->packagecreated->format('Y-m-d H:i')] = $package->packageguid;
            }

            // add the latest to the all array
            $all[$latest['released']] = array(
                'size' => $latest['size'],
                'repoid' => $latest['repoid'],
                'version' => $latest['version'],
                'variants' => $latest['variants'],
                'released' => $latest['released'],
                'packageid' => $latest['packageid'],
                'packageguid' => $latest['packageguid'],
                'packagescore' => $latest['packagescore'],
                'hidden' => $latest['hidden'],
                'publishers' => $latest['publishers'],
                'localurl' => (isset($latest['localurl'])) ? $latest['localurl'] : false
            );
            if ($latest['promotedfromguid'])
            {
                $all[$latest['released']]['type'] = $qarelease;
                $all[$latest['released']]['promotedfromguid'] = $latest['promotedfromguid'];
            }
            else
            {
                $all[$latest['released']]['type'] = $staging;
            }

            //echo "\n\n";
            //ob_flush();

            // set the default download url for the package
            if (     array_key_exists($index, $latest['variants'])
                && ! $this->data['packages'][$package->packagename]['defaultdownloadurl'])
            {
                // index was set at the beginning of this method
                $this->data['packages'][$package->packagename]['defaultdownloadurl'] = $latest['variants'][$index]['packageinstallfileurl'];

                // set a different downloadurl in case the configured download schema for this OS is 'apps'
                if (   array_key_exists('download', $this->mvc->configuration->os_ux[$latest['variants'][$index]['repoos']])
                    && $this->mvc->configuration->os_ux[$latest['variants'][$index]['repoos']]['download'] == 'apps')
                {
                    $this->data['packages'][$package->packagename]['defaultdownloadurl'] = 'apps://' . $latest['variants'][$index]['packageid'];
                }
            }

            // set the latest of this package
            if (! array_key_exists('latest', $this->data['packages'][$package->packagename]))
            {
                $this->data['packages'][$package->packagename]['latest'] = array('version' => '', 'variants' => array());
            }

            if ($this->data['packages'][$package->packagename]['latest']['version'] <= $latest['version'])
            {
                $this->data['packages'][$package->packagename]['latest'] = $latest;
            }

            // always keep it up-to-date
            $this->data['packages'][$package->packagename]['all'] = $all;

            switch($filter_type)
            {
                case 'staging':
                    $route_id = 'staging_apps_by_name';
                    break;
                case 'top':
                default:
                    $route_id = 'apps_by_name';
            }

            $this->data['packages'][$package->packagename]['localurl'] = $this->mvc->dispatcher->generate_url
            (
                $route_id,
                array
                (
                    'os' => $package->repoos,
                    'version' => $package->repoosversion,
                    'ux' => (is_array($matched) && array_key_exists('ux', $matched)) ? $matched['ux'] : $package->ux,//$latest['ux'],
                    'basecategory' => com_meego_packages_controllers_package::determine_base_category($package), //$this->data['basecategory'],
                    'packagename' => $package->packagename
                ),
                $this->request
            );

            // do not bother any further for hidden instances
            if ($package->packagehidden)
            {
                continue;
            }

            // get the workflows for this package
            // todo: this will get workflows for all versions
            if (! array_key_exists('workflows', $this->data['packages'][$package->packagename]))
            {
                $this->data['packages'][$package->packagename]['workflows'] = array();
            }

            $object = new com_meego_package($package->packageguid);

            if (! $object->metadata->hidden)
            {
                $list_of_workflows = midgardmvc_helper_workflow_utils::get_workflows_for_object($object);

                foreach ($list_of_workflows as $workflow => $workflow_data)
                {
                    if (! $this->isuser)
                    {
                        $workflow_data['css'] .= ' login';
                    }

                    $this->data['packages'][$package->packagename]['workflows'][] = array
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
                        ),
                        'css' => $workflow_data['css']
                    );
                }
            }
        } //foreach

        if (isset($package))
        {
            // let's unset the default variant, so we don't list it among the "other downloads"
            unset($this->data['packages'][$package->packagename]['latest']['variants'][$index]);
        }

/*
        if (   isset($package)
            && array_key_exists($package->packagename, $this->data['packages']))
        {
            $latestversion = $this->data['packages'][$package->packagename]['latest']['version'];
            if (array_key_exists($latestversion, $this->data['packages'][$package->packagename]['all']))
            {
                // always remove the latest from the all array
                unset($this->data['packages'][$package->packagename]['all'][$latestversion]);
            }
        }
*/
        // and we don't need this either
        unset($latest);

        if (isset($this->data['packages']))
        {
            return $this->data['packages'];
        }
   }

    /**
     * Adds a form to page if commenting is enabled
     * Uses the data array that is set earlier for the templates
     *
     * @param string filter type: 'top' or 'staging'; setting an URL depends on this
     */
    public function enable_commenting($filter_type = 'top')
    {
        $matched = $this->request->get_route()->get_matched();

        switch($filter_type)
        {
            case 'staging':
                $route_id = 'staging_apps_by_name';
                break;
            case 'top':
            default:
                $route_id = 'apps_by_name';
        }

        $this->data['relocate'] = $this->mvc->dispatcher->generate_url(
            $route_id,
            array
            (
                'os' => $matched['os'],
                'version' => $matched['version'],
                'ux' => $matched['ux'],
                'basecategory' => $matched['basecategory'],
                'packagename' => $matched['packagename']
            ),
            $this->request
        );

        if (count($this->data['packages'][$this->data['packagename']]['latest']['variants']))
        {
            // set all variants so user can choose
            foreach ($this->data['packages'][$this->data['packagename']]['latest']['variants'] as $variant)
            {
                $this->data['architectures'][$variant['repoarch']] = array
                (
                    'name' => $variant['repoarch'],
                    'packageguid' => $variant['packageguid']
                );
            }

            // get the 1st variant and set packageguid variable, in case we don't offer choosing a variant
            $variant = reset($this->data['packages'][$this->data['packagename']]['latest']['variants']);
            $this->data['packageguid'] = $variant['packageguid'];
        }

        if (! array_key_exists('packageguid', $this->data))
        {
            $this->data['packageguid'] = $this->data['packages'][$this->data['packagename']]['packageguid'];
        }

        if (   array_key_exists('packageguid', $this->data)
            && $this->data['packageguid'])
        {
            $this->data['postaction'] = $this->mvc->dispatcher->generate_url
            (
                'apps_rating_create', array
                (
                    'to' => $this->data['packageguid']
                ),
                'com_meego_packages'
            );
        }
    }

    /**
     * Process application comments and ratings
     *
     * It intercepts the POST and checks if multiple rating is allowed
     * and the user can rate the object
     * If the user has already rated an object and multiple rating is not allowed
     * then it takes away rating from the POST and passes on the request
     * to the proper controller that will process it.
     *
     */
    public function post_comment_application(array $args)
    {
        if (! self::can_rate($args['to']))
        {
            unset($_POST['rating']);
        }

        $route_id = 'rating_create';

        $request = midgardmvc_core_request::get_for_intent('com_meego_ratings_caching', false);
        $request->add_component_to_chain($this->mvc->component->get('com_meego_ratings_caching'));

        $routes = $this->mvc->component->get_routes($request);

        $request->set_arguments($routes[$route_id]->set_variables($args));
        $request->set_route($routes[$route_id]);
        $request->set_method('post');

        $this->mvc->dispatcher->dispatch($request);
    }

    /**
     * Gathers ratings and appends them to data
     *
     * @param string title of the application
     * @param boolean flag to override show_ratings_without_comments configuration
     *
     * @return array of ratings together with their comments
     */
    public function prepare_ratings($application_name = null, $flag = false)
    {
        // the array to be returned
        // the comment flag is set to tru when the 1st comment found
        // this will help the template to display some headinhs only if needed
        $retval = array('ratings' => array(), 'comment' => false);

        $storage = new midgard_query_storage('com_meego_package_ratings');
        $q = new midgard_query_select($storage);

        $q->set_constraint
        (
            new midgard_query_constraint
            (
                new midgard_query_property('name'),
                '=',
                new midgard_query_value($application_name)
            )
        );

        $q->add_order(new midgard_query_property('posted', $storage), SORT_DESC);

        $q->execute();

        $ratings = $q->list_objects();

        if (count($ratings))
        {
            foreach ($ratings as $rating)
            {
                $rating->show = true;

                if (   ! $rating->commentid
                    && ! $this->mvc->configuration->show_ratings_without_comments
                    && ! $flag)
                {
                    $rating->show = false;
                    array_push($retval, $rating);
                    continue;
                }

                $rating->stars = '';

                if (   $rating->rating
                    || $rating->commentid)
                {
                    if ($rating->commentid)
                    {
                        $retval['comment'] = true;
                    }

                    if ($rating->rating)
                    {
                        // add a new property containing the stars to the rating object
                        $rating->stars = com_meego_ratings_controllers_rating::draw_stars($rating->rating);
                    }

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
                                $rating->avatar = com_meego_packages_utils::get_avatar($user->login);
                                $rating->avatarurl = $this->mvc->configuration->user_profile_prefix . $user->login;
                            }
                            catch (Exception $e)
                            {
                                // no avatar
                            }
                        }
                    }
                }

                array_push($retval['ratings'], $rating);
            }
            unset ($ratings);
        }

        return $retval;
    }

    /**
     * Checks is the currently logged in user has rated an object or not
     * Returns true if he / she did not rate yet.
     * @param GUID of the package
     * @return boolean
     */
    public function can_rate($guid)
    {
        $retval = true;

        $user = $this->mvc->authentication->get_user();

        $storage = new midgard_query_storage('com_meego_package_ratings');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint_group('AND');
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('authorguid'),
            '=',
            new midgard_query_value($user->person)
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('guid'),
            '=',
            new midgard_query_value($guid)
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('rating'),
            '<>',
            new midgard_query_value(0)
        ));

        $q->set_constraint($qc);
        $q->execute();

        $ratings = $q->list_objects();

        if (   count($ratings)
            && ! $this->mvc->configuration->allow_multiple_rating)
        {
            $retval = false;
        }

        return $retval;
    }

    /**
     * Gathers roles that belong to a package
     * @param guid of a package
     * @return array
     */
    public function get_roles($package_guid, $filter = null)
    {
        $retval = array();

        $storage = new midgard_query_storage('com_meego_package_role');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint_group('AND');

        $qc->add_constraint(new midgard_query_constraint (
            new midgard_query_property('package'),
            '=',
            new midgard_query_value($package_guid)
        ));

        if ($filter)
        {
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('role'),
                '=',
                new midgard_query_value($filter)
            ));
        }
        else
        {
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('id'),
                '>',
                new midgard_query_value(0)
            ));
        }

        $q->set_constraint($qc);
        $q->execute();

        $roles = $q->list_objects();

        if ($roles)
        {
            foreach ($roles as $role)
            {
                if (! array_key_exists($role->role, $retval))
                {
                    $retval[$role->role] = array();
                    $retval[$role->role]['users'] = array();
                }
                if (isset($role->user))
                {
                    $user = com_meego_packages_utils::get_user_by_guid($role->user);

                    if (! array_key_exists($user->login, $retval[$role->role]['users']))
                    {
                        $retval[$role->role]['users'][$user->login] = array();
                    }

                    $retval[$role->role]['users'][$user->login]['login'] = $user->login;
                    $retval[$role->role]['users'][$user->login]['profile'] = $this->mvc->configuration->user_profile_prefix . $user->login;
                }

                if (count($retval[$role->role]['users']) > 1)
                {
                    $retval[$role->role]['title'] = $this->mvc->i18n->get('label_roles_' . $role->role);
                }
                else
                {
                    $retval[$role->role]['title'] = $this->mvc->i18n->get('label_role_' . $role->role);
                }
            }
        }

        return $retval;
    }

    /**
     * returns a query contraint
     * @param string filter type:    top: top projects only
     *                           staging: staging projects only
     *                               mix: top and staging projects both
     *
     * @return query constraint or query contstraint group
     */
    public function get_repoproject_filter($type = 'top')
    {
        $retval = null;
        $queryvalue = array();

        if (count($this->mvc->configuration->top_projects) == 1)
        {
            $top_project = array_keys($this->mvc->configuration->top_projects);

            if (   $type == 'top'
                || $type == 'mix')
            {
                $queryvalue['top'] = new midgard_query_value($top_project[0]);
            }
            if (    $type == 'staging'
                ||  $type == 'mix')
            {
                $queryvalue['staging'] = new midgard_query_value($top_project[0]['staging']);
            }

            if ($type == 'mix')
            {
                $retval = new midgard_query_constraint_group('OR');
                $retval->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('repoprojectname'),
                    '=',
                    $queryvalue['top']
                ));
                $retval->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('repoprojectname'),
                    '=',
                    $queryvalue['staging']
                ));
            }
            else
            {
                if (array_key_exists($type, $queryvalue))
                {
                    $retval = new midgard_query_constraint(
                        new midgard_query_property('repoprojectname'),
                        '=',
                        $queryvalue[$type]
                    );
                }
            }
        }
        else
        {
            $retval = new midgard_query_constraint_group('OR');
            foreach ($this->mvc->configuration->top_projects as $top_project => $details)
            {
                if (   $type == 'top'
                    || $type == 'mix')
                {
                    $queryvalue['top'] = new midgard_query_value($top_project);
                }
                if (    $type == 'staging'
                    ||  $type == 'mix')
                {
                    $queryvalue['staging'] = new midgard_query_value($details['staging']);
                }
                if ($type == 'mix')
                {
                    $retval->add_constraint(new midgard_query_constraint(
                        new midgard_query_property('repoprojectname'),
                        '=',
                        $queryvalue['top']
                    ));
                    $retval->add_constraint(new midgard_query_constraint(
                        new midgard_query_property('repoprojectname'),
                        '=',
                        $queryvalue['staging']
                    ));
                }
                else
                {
                    if (array_key_exists($type, $queryvalue))
                    {
                        $retval->add_constraint(new midgard_query_constraint(
                            new midgard_query_property('repoprojectname'),
                            '=',
                            $queryvalue[$type]
                        ));
                    }
                }
            }
        }

        return $retval;
    }


    /**
     * Tries to find a package that was the "predecessor" of the given package
     * in terms of QA. In other words:
     * if an has already been promoted, then which package instance got the
     * QA votes. This is needed for showing a proper app history.
     *
     * @param guid com_meego_package guid
     * @param integer a metadata_score (packagescore) threshold above which we
     *                look for a package instance
     * @return string comma separated list of guids that may have been the
     *                "predecessor" of the given package
     */
    public function get_predecessors($packageguid, $qa_threshold)
    {
        $retval = null;
        $staging = null;

        $storage = new midgard_query_storage('com_meego_package_details');
        $q = new midgard_query_select($storage);

        $q->set_constraint(new midgard_query_constraint (
            new midgard_query_property('packageguid'),
            '=',
            new midgard_query_value($packageguid)
        ));

        $q->execute();
        $packages = $q->list_objects();

        if (! count($packages)) {
            return $retval;
        }
        $package = $packages[0];

        $project = $package->repoprojectname;
        // check if the project of this package has a staging repo
        if (array_key_exists($project, $this->mvc->configuration->top_projects))
        {
            if (! array_key_exists('staging', $this->mvc->configuration->top_projects[$project]))
            {
                return null;
            }
            $staging = $this->mvc->configuration->top_projects[$project]['staging'];
        }

        $qc = new midgard_query_constraint_group('AND');
        $qc->add_constraint(new midgard_query_constraint (
            new midgard_query_property('packagename'),
            '=',
            new midgard_query_value($package->packagename)
        ));
        $qc->add_constraint(new midgard_query_constraint (
            new midgard_query_property('packageversion'),
            '=',
            new midgard_query_value($package->packageversion)
        ));
        $qc->add_constraint(new midgard_query_constraint (
            new midgard_query_property('repoprojectname'),
            '=',
            new midgard_query_value($staging)
        ));
        $qc->add_constraint(new midgard_query_constraint (
            new midgard_query_property('packagescore'),
            '>',
            new midgard_query_value($qa_threshold)
        ));

        $q->set_constraint($qc);
        $q->execute();

        $packages = $q->list_objects();

        if (count($packages))
        {
            foreach ($packages as $package)
            {
                $retval[] = $package->packageguid;
            }
        }

        return $retval;
    }

    /**
     * Get staging applications that are in repos of staging projects for a base category but
     * only if they belong to a certain ux and to a top project that is configurable
     *
     * @param array of args (os, version, ux, basecategory, packagename)
     */
    public function get_history(array $args)
    {
        $this->data['packagename'] = $args['packagename'];
        $mix = self::get_filtered_applications($args['os'], $args['version'], 0, 0, $args['ux'], $args['packagename'], 'mix', null);
        $apps = self::set_data($mix, 'mix', true);
        $latest = $apps[$args['packagename']]['latest'];

        $apps[$args['packagename']]['passedqa'] = array();
        $apps[$args['packagename']]['promoted'] = array();
        $apps[$args['packagename']]['staging'] = array();

        if ($latest['promotedfromguid'])
        {
            // the latest version is actually promoted
            $apps[$args['packagename']]['passedqa'] = $latest;
        }
        else
        {
            $apps[$args['packagename']]['staging'] = $latest;
        }

        foreach ($apps[$args['packagename']]['all'] as $key => $instance)
        {
            if (! $instance['hidden'])
            {
                if (isset($instance['promotedfromguid']))
                {
                    if (   ! $apps[$args['packagename']]['passedqa']
                        || $apps[$args['packagename']]['passedqa']['version'] < $instance['version'])
                    {
                        $instance['localurl'] = $apps[$args['packagename']]['localurl'];
                        $apps[$args['packagename']]['passedqa'] = $instance;
                    }
                }
                else if (isset($instance['promotedtoguid']))
                {
                    if (   ! $apps[$args['packagename']]['promoted']
                        || $apps[$args['packagename']]['promoted']['version'] < $instance['version'])
                    {
                        $apps[$args['packagename']]['promoted'] = $instance;
                    }
                }
                else
                {
                    if (   ! $apps[$args['packagename']]['staging']
                        || $apps[$args['packagename']]['staging']['version'] < $instance['version'])
                    {
                        $instance['localurl'] = 'staging/' . $apps[$args['packagename']]['localurl'];
                        $apps[$args['packagename']]['staging'] = $instance;
                    }
                }
            }
        }

        krsort($apps[$args['packagename']]['all']);

        $i = 0;
        foreach($apps[$args['packagename']]['all'] as $key => $item)
        {
            (++$i % 2 == 0) ? $apps[$args['packagename']]['all'][$key]['rowclass'] = 'even' : $apps[$args['packagename']]['all'][$key]['rowclass'] = 'odd';
        }

        $this->data['packages'] = $apps;
    }

}