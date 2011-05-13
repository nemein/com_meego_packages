<?php
class com_meego_packages_controllers_package
{
    var $mvc = null;
    var $request = null;
    var $uxmap = array();

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
    public function get_package(array $args)
    {
        $qb = com_meego_package::new_query_builder();
        $qb->add_constraint('name', '=', $args['package']);
        $qb->add_order('repository.name', 'ASC');
        $packages = $qb->execute();
        if (count($packages) == 0)
        {
            throw new midgardmvc_exception_notfound("Package not found");
        }

        $this->data['package'] = $packages[0];
        if (empty($this->data['package']->title))
        {
            $this->data['package']->title = $this->data['package']->name;
        }

        $repositories = array();
        $this->data['packages'] = array();
        foreach ($packages as $package)
        {
            // if user is not logged in then don't show the installfileurl link
            if ( ! $this->mvc->authentication->is_user() )
            {
                $package->installfileurl = false;
            }

            if (empty($package->title))
            {
                $package->title = $package->name;
            }

            if ( ! isset($repositories[$package->repository]) )
            {
                $repository = new com_meego_repository();
                $repository->get_by_id($package->repository);
                $repositories[$package->repository] = $repository;
            }

            $package->repositoryobject = $repositories[$package->repository];

            $package->localurl = $this->mvc->dispatcher->generate_url
            (
                'package_instance',
                array
                (
                    'package' => $package->title,
                    'version' => $package->version,
                    'project' => $args['project'],
                    'repository' => $repositories[$package->repository]->name,
                    'arch' => $repositories[$package->repository]->arch
                ),
                $this->request
            );

            // get the name of the project the repository belongs to
            $project = new com_meego_project($repository->project);

            $package->repositoryobject->localurl = $this->mvc->dispatcher->generate_url
            (
                'repository',
                array
                (
                    'project' => $project->name,
                    'repository' => $repositories[$package->repository]->name,
                    'arch' => $repositories[$package->repository]->arch
                ),
                $this->request
            );
            $this->data['packages'][] = $package;
        }
    }

    /**
     * @todo: docs
     */
    public function get_repository(array $args)
    {
        $qb = com_meego_package::new_query_builder();
        $qb->add_constraint('title', '=', $args['package']);
        $qb->add_constraint('repository.name', '=', $args['repository']);
        $packages = $qb->execute();
        if (count($packages) == 0)
        {
            throw new midgardmvc_exception_notfound("Package not found");
        }

        $this->data['package'] = $packages[0];

        if (empty($this->data['package']->title))
        {
            $this->data['package']->title = $this->data['package']->name;
        }

        $this->data['packages'] = array();
        foreach ($packages as $package)
        {
            if (empty($package->title))
            {
                $package->title = $package->name;
            }

            $package->localurl = $this->mvc->dispatcher->generate_url
            (
                'package_instance',
                array
                (
                    'package' => $package->title,
                    'version' => $package->version,
                    'project' => $args['project'],
                    'repository' => $args['repository'],
                    'arch' => $args['arch']
                ),
                $this->request
            );
            $this->data['packages'][] = $package;
        }
    }

    /**
     * @todo: docs
     */
    public function get_instance(array $args)
    {
        if (isset($args['project']))
        {
            $qbproject = com_meego_project::new_query_builder();
            $qbproject->add_constraint('name', '=', $args['project']);

            $projects = $qbproject->execute();

            if (count($projects))
            {
                $project = $projects[0];
            }
        }

        $qb = com_meego_package::new_query_builder();
        $qb->add_constraint('name', '=', $args['package']);
        $qb->add_constraint('version', '=', $args['version']);
        $qb->add_constraint('repository.project', '=', $project->id);
        $qb->add_constraint('repository.name', '=', $args['repository']);
        $qb->add_constraint('repository.arch', '=', $args['arch']);

        $packages = $qb->execute();

        if (count($packages) == 0)
        {
            throw new midgardmvc_exception_notfound("Package not found");
        }

        $this->data['package'] = $packages[0];

        if (empty($this->data['package']->title))
        {
            $this->data['package']->title = $this->data['package']->name;
        }

        $this->data['package']->description = str_replace("\n\n","<br /><br />",($this->data['package']->description));

        // if user is not logged in then don't show the installfileurl link
        if ( ! $this->mvc->authentication->is_user() )
        {
            $this->data['package']->installfileurl = false;
        }

        if ($this->data['package']->category)
        {
            $qb = com_meego_package_category::new_query_builder();
            $qb->add_constraint('id', '=', $this->data['package']->category);
            $categories = $qb->execute();
            if (count($categories) == 0)
            {
              throw new midgardmvc_exception_notfound("Package category not found");
            }

            $this->data['package']->category_name = $categories[0]->name;

            while ($categories[0]->up != 0)
            {
                $qb = com_meego_package_category::new_query_builder();
                $qb->add_constraint('id', '=', $categories[0]->up);
                $categories = $qb->execute();
                if (count($categories) == 0)
                {
                    throw new midgardmvc_exception_notfound("Package parent category not found");
                }

                $this->data['package']->category_name = $categories[0]->name . "/" . $this->data['package']->category_name;
            }
        }
        else
        {
          $this->data['package']->category_name = "";
        }

        $this->data['package']->localurl = $this->mvc->dispatcher->generate_url
        (
            'package',
            array
            (
                'package' => $this->data['package']->title,
            ),
            $this->request
        );
        $this->data['package']->repositoryobject = new com_meego_repository($this->data['package']->repository);

        $this->data['package']->repositoryobject->localurl = $this->mvc->dispatcher->generate_url
        (
            'repository',
            array
            (
                'project' => $args['project'],
                'repository' => $this->data['package']->repositoryobject->name,
                'arch' => $this->data['package']->repositoryobject->arch
            ),
            $this->request
        );

        $this->data['package']->screenshoturl = false;

        $attachments = $this->data['package']->list_attachments();

        foreach ($attachments as $attachment)
        {
            $this->data['package']->screenshoturl = $this->mvc->dispatcher->generate_url
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

        $storage = new midgard_query_storage('com_meego_package_relation');
        $q = new midgard_query_select($storage);

        $q->set_constraint(new midgard_query_constraint(
            new midgard_query_property('from', $storage),
            '=',
            new midgard_query_value($this->data['package']->id)
        ));

        $res = $q->execute();
        if ($res != 'MGD_ERR_OK')
        {
            $_mc = midgard_connection::get_instance();
            echo "Error received from midgard_connection: " . $_mc->get_error_string() . "\n";
            return;
        }

        $relations = $q->list_objects();

        /* maps relation types to human parsable names */
        $typemap = array
        (
            'requires' => 'Requires',
            'buildrequires' => 'Build requires',
            'obsoletes' => 'Obsoletes',
            'conflicts' => 'Conflicts',
            'provides' => 'Provides'
        );

        $this->data['relations'] = array();

        foreach ($relations as $relation)
        {
            $_url = false;

            $_title = $relation->relation;

            if (! isset($this->data['relations'][$relation->relation]))
            {
                $this->data['relations'][$relation->relation] = array
                (
                    // can be requires, conflicts, obsoletes etc
                    'title' => $_title,
                    // array for holding individial relation objects
                    'packages' => array(),
                );
            }

            $_relpackage = null;

            $storage = new midgard_query_storage('com_meego_package_details');
            $q = new midgard_query_select($storage);
            $q->set_constraint
            (
                new midgard_query_constraint
                (
                    new midgard_query_property('packageid', $storage),
                    '=',
                    new midgard_query_value($relation->to)
                )
            );

            $q->execute();
            $_packages = $q->list_objects();

            if (count($_packages))
            {
                $_relpackage = $_packages[0];
            }

            if ($_relpackage)
            {
                $_url = $this->mvc->dispatcher->generate_url
                (
                    'package_instance',
                    array
                    (
                        'package' => $relation->toname,
                        'version' => $relation->version,
                        'project' => $args['project'],
                        'repository' => $_relpackage->reponame,
                        'arch' => $_relpackage->repoarch
                    ),
                    $this->request
                );
            }

            $_relation = $relation;

            if (array_key_exists($relation->relation, $typemap))
            {
                $this->data['relations'][$relation->relation]['title'] = $typemap[$relation->relation] . ':';
            }

            $_relation->localurl = $_url;

            array_push($this->data['relations'][$relation->relation]['packages'], $_relation);
        }

        unset($relations, $relation, $_relation, $_url, $typemap);

        $list_of_workflows = midgardmvc_helper_workflow_utils::get_workflows_for_object($this->data['package']);
        $this->data['workflows'] = array();
        foreach ($list_of_workflows as $workflow => $workflow_data)
        {
            $this->data['workflows'][] = array
            (
                'label' => $workflow_data['label'],
                'url' => $this->mvc->dispatcher->generate_url
                (
                    'package_instance_workflow_start',
                    array
                    (
                        'package' => $this->data['package']->name,
                        'version' => $this->data['package']->version,
                        'project' => $args['project'],
                        'repository' => $args['repository'],
                        'arch' => $args['arch'],
                        'workflow' => $workflow,
                    ),
                    $this->request
                )
            );
        }

        // get the ratings for this package
        // enable if the template does not use dynamic loading
        // $this->data['package']->ratings = self::prepare_ratings($this->data['package']->guid);
    }

    /**
     * @todo: docs
     */
    private function search_packages($query)
    {
        if (   isset($query['q'])
            && ! empty($query['q']))
        {
            $storage = new midgard_query_storage('com_meego_package_details');
            $q = new midgard_query_select($storage);
            $qc = new midgard_query_constraint_group('OR');

            if (isset($query['q']))
            {
                // Text search
                $qc->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('packagename'),
                    'LIKE',
                    new midgard_query_value('%' . $query['q'] . '%')
                ));
                $qc->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('packagetitle'),
                    'LIKE',
                    new midgard_query_value('%' . $query['q'] . '%')
                ));
                $qc->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('packagesummary'),
                    'LIKE',
                    new midgard_query_value('%' . $query['q'] . '%')
                ));
                $qc->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('packagefilename'),
                    'LIKE',
                    new midgard_query_value('%' . $query['q'] . '%')
                ));
            }

            //if repository is specified for search
            if (   isset($query['repository'])
                && ! empty($query['repository']))
            {
                $qb = com_meego_repository::new_query_builder();
                $qb->add_constraint('name', '=', $query['repository']);
                $repository = $qb->execute();
                if (count($repository) == 0)
                {
                    throw new midgardmvc_exception_notfound("Repository not found");
                }
                $qc->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('repository'),
                    '=',
                    new midgard_query_value($repository[0]->id)
                ));
            }

            $q->set_constraint($qc);
            $q->execute();

            // filter package names according to configuration
            $packages = com_meego_packages_controllers_application::filter_titles($q->list_objects(), $this->mvc->configuration->package_filters);

            return $packages;
        }
    }

    /**
     * @todo: docs
     */
    public function get_search(array $args)
    {
        $this->data['search'] = '';

        $query = $this->request->get_query();
        if (   isset($query['q'])
            && ! empty($query['q']))
        {
            $this->data['search'] = $query['q'];
            $this->data['packages'] = array();

            $packages = $this->search_packages($query);

            if (count($packages) == 1)
            {
                $package = $package[0];
                // Relocate to package directly
                $this->mvc->head->relocate
                (
                    $this->mvc->dispatcher->generate_url
                    (
                        'apps_by_title',
                        array
                        (
                            'os' => $package->repoos,
                            'version' => $package->repoosversion,
                            'ux' => $package->repoosux,
                            'basecategory' => self::determine_base_category($package),
                            'packagetitle' => $package->packagetitle
                        ),
                        $this->request
                    )
                );
            }
            else if (count($packages) > 1)
            {
                foreach ($packages as $package)
                {
                    if (isset($this->data['packages'][$package->packagename]))
                    {
                        continue;
                    }

                    if (empty($package->packagetitle))
                    {
                        $package->packagetitle = ucfirst($package->packagename);
                    }

                    $package->localurl = $this->mvc->dispatcher->generate_url
                    (
                        'apps_by_title',
                        array
                        (
                            'os' => $package->repoos,
                            'version' => $package->repoosversion,
                            'ux' => $package->repoosux,
                            'basecategory' => self::determine_base_category($package),
                            'packagetitle' => $package->packagetitle
                        ),
                        $this->request
                    );
                    $this->data['packages'][$package->packagename] = $package;
                }
            }
        }

        $qb = com_meego_repository::new_query_builder();

        //TODO: add constraints for arch or release.
        $this->data['repositories'] = $qb->execute();
    }

    /**
     * Returns all packages that belong to a certain category
     * @param array args; 'categorytree' argument can be like: System:Base
     */
    public function get_packages_by_categorytree(array $args)
    {
        $this->data['ux'] = false;
        $this->data['base'] = false;
        $this->data['packages'] = false;
        $this->data['basecategory'] = false;
        $this->data['categorytree'] = rawurldecode($args['categorytree']);


        $category = $this->determine_category_by_tree($this->data['categorytree']);

        if (   isset($category)
            && $category != 0)
        {
            $storage = new midgard_query_storage('com_meego_package_details');
            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint(
                new midgard_query_property('packagecategory'),
                '=',
                new midgard_query_value($category)
            );

            $q->set_constraint($qc);
            $q->add_order(new midgard_query_property('packagetitle', $storage), SORT_ASC);
            $q->execute();

            $packages = $q->list_objects();

            $this->set_data($packages);
        }
    }

    /**
     * Returns all packages that belong to a certain base category
     * @param array args; 'basecategory' argument can be like: Games
     */
    public function get_packages_by_basecategory(array $args)
    {
        // get the base category object
        $basecategory = com_meego_packages_controllers_basecategory::load_object($args);

        if (is_object($basecategory))
        {
            $this->data['base'] = true;
            $this->data['categorytree'] = false;
            $this->data['basecategory'] = strtolower($basecategory->name);

            // get relations
            $relations = com_meego_packages_controllers_basecategory::load_relations_for_basecategory($basecategory->id);

            $packages = array();

            // gather all packages from each relation
            foreach ($relations as $relation)
            {
                $storage = new midgard_query_storage('com_meego_package_details');
                $q = new midgard_query_select($storage);

                $qc = new midgard_query_constraint(
                    new midgard_query_property('packagecategory'),
                    '=',
                    new midgard_query_value($relation->packagecategory)
                );

                $q->set_constraint($qc);
                $q->execute();

                $packages = array_merge($q->list_objects(), $packages);
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

            // prepare the data for the template
            self::set_data($packages);
        }
        else
        {
            // oops, there are no packages for this base category..
            throw new midgardmvc_exception_notfound("There is no category called: " . $args['basecategory']);
        }
    }

    /**
     * Renders an overview of a package identified by its title
     * The page will display all ratings and comments of all variants of the given package
     *
     * It will also show the links to the detailed pages of each individual package variants
     *
     * No commenting or rating is enabled on this page. Those can be done on the variant pages.
     *
     * @param array args
     *
     */
    public function get_package_overview(array $args)
    {
        $this->data['packages'] = false;
        $this->data['categorytree'] = rawurldecode($args['categorytree']);
        $this->data['packagetitle'] = rawurldecode($args['packagetitle']);

        $category = $this->determine_category_by_tree($this->data['categorytree']);

        if (   isset($category)
            && $category != 0)
        {
            $storage = new midgard_query_storage('com_meego_package_details');
            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint_group('AND');

            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagecategory'),
                '=',
                new midgard_query_value($category)
            ));
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagetitle'),
                '=',
                new midgard_query_value($this->data['packagetitle'])
            ));

            $q->set_constraint($qc);
            $q->add_order(new midgard_query_property('packagetitle', $storage), SORT_ASC);
            $q->execute();

            $packages = $q->list_objects();

            $this->set_data($packages);

            // collect all ratings and comments
            $this->data['packages'][$this->data['packagetitle']]['ratings'] = array();

            // placeholder for variant ratings
            $local_ratings = array();

            foreach ($this->data['packages'][$this->data['packagetitle']]['providers'] as $provider)
            {
                foreach ($provider['variants'] as $variant)
                {
                    $local_ratings = array_merge(self::prepare_ratings($variant->packageguid), $local_ratings);
                }
            }

            // set ratings to the template
            $this->data['packages'][$this->data['packagetitle']]['ratings'] = $local_ratings;
        }
    }

    /**
     * Returns a category id based on a category tree, such as: Application:Games:Chess for example
     * @param string category tree (: separated list of strings)
     * @return integer id of the category
     */
    public function determine_category_by_tree($categorytree)
    {
        $retval = 0;

        if (strlen($categorytree))
        {
            if (strpos($categorytree, ':'))
            {
                $tree = explode(':', $categorytree);
            }
            else
            {
                $tree = array($categorytree);
            }
        }
        else
        {
            // no categorytree given
            return $retval;
        }

        $storage = new midgard_query_storage('com_meego_package_category');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('name'),
            '=',
            new midgard_query_value(end($tree))
        );

        $q->set_constraint($qc);
        $q->execute();

        $categories = $q->list_objects();

        $origtree = $tree;
        $done = false;
        $ids = array();

        foreach ($categories as $category)
        {
            $tree = $origtree;
            $current = $category;

            if (   $current->up == 0
                && count($tree) == 1)
            {
                $done = true;
                $ids[] = $current->id;
            }
            else
            {
                #echo "------------------------------------<br/>\n";
                #echo "start with: " . $current->name . "(" . $current->id . "), parent: " . $current->up . "<br/>\n";

                while (! $done)
                {
                    $done = false;

                    #echo end($tree) . " vs " . $current->name . "(" . $current->id . ")\n<br/>";

                    if ($current->name == end($tree))
                    {
                        array_pop($tree);

                        #echo "add " . $current->id . "\n<br/>";

                        $ids[] = $current->id;
                        $current = new com_meego_package_category($current->up);

                        #echo "new current up: " . $current->up . "<br/>\n";
                        #echo "new current: " . $current->name . "(" . $current->id . ")<br/>\n";
                        #echo "new end tree: " . end($tree) . "<br/>\n";
                        #echo "count tree: " . count($tree) . "<br/>\n";
                        #echo "count ids: " . count($ids) . ", count origtree: " . count($origtree) . "<br/>\n";

                        if (   count($tree) == 1
                            && $current->name == end($tree))
                        {
                            $ids[] = $current->id;
                            $done = true;
                        }

                        if (   ! $done
                            && $current->name != end($tree))
                        {
                            $ids = array();
                        }
                    }
                    else
                    {
                        #echo "reset and break<br/>\n";
                        break;
                    }
                }
            }

            if ($done)
            {
                break;
            }

        }

        if (count($ids))
        {
            $retval = $ids[0];
        }

        return $retval;
    }

    /**
     * Returns an array filled with some stats about a package identified by its title
     *
     * @param string title, e.g. anki
     * @return array
     *
     */
    public function get_statistics($title)
    {
        $retval = array
        (
            'average_rating' => 0,
            'number_of_comments' => 0
        );

        // get the packages that have this title
        $storage = new midgard_query_storage('com_meego_package_ratings');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('title'),
            '=',
            new midgard_query_value($title)
        );

        $q->set_constraint($qc);
        $q->execute();

        $packages = $q->list_objects();

        $sum = 0;
        foreach ($packages as $package)
        {
            // get the rating sum
            $sum += $package->rating;

            if ($package->commentid)
            {
                // do not count empty comments
                if (strlen($package->comment))
                {
                    $retval['number_of_comments']++;
                }
            }
        }

        if (count($packages))
        {
            $retval['average_rating'] = round($sum / count($packages), 1);
        }

        return $retval;
    }

    /**
     * Sets data for the template
     * It is used in two routes so that is why we have it as a separate function
     * @param array of packages
     */
    public function set_data(array $packages)
    {
        // let's do a smart grouping by package_title (ie. short names)
        $variant_counter = 0;

        foreach ($packages as $package)
        {
            $this->data['categorytree'] = self::determine_category_tree($package);

            $tree_url = $this->mvc->dispatcher->generate_url
            (
                'package_overview_tree',
                array
                (
                    'categorytree' => $this->data['categorytree'],
                    'packagetitle' => $package->packagetitle
                ),
                $this->request
            );

            // certain things must not be recorded in evert iteration of this loop
            // if we recorded the name, then we are pretty sure we recorded everything
            if (! isset($this->data['packages'][$package->packagetitle]['name']))
            {
                // set the name
                $this->data['packages'][$package->packagetitle]['name'] = $package->packagetitle;

                if (   isset($this->data['base'])
                    && $this->data['base'])
                {
                    // if browsing by base categories, then we have to figure out the
                    // the category tree of the package
                    //$this->data['categorytree'] = self::determine_category_tree($package);

                    if (! strlen($this->data['basecategory']))
                    {
                        $this->data['basecategory'] = self::determine_base_category($package);
                    }

                    // if base category is still empty
                    // then it means that this package is not yet covered by any base category
                    // so we have to provide a different URL
                    if (! strlen($this->data['basecategory']))
                    {
                        // local url to a package index page
                        $this->data['packages'][$package->packagetitle]['localurl'] = $tree_url;
                    }
                    else
                    {
                        // ok, so we got a base category, let's form the url

                        // check if we have ux
                        if ( ! strlen($this->data['ux']))
                        {
                            $this->data['ux'] = strtolower($package->repoosux);
                        }

                        // if could not figure out the ux then provide a tree url
                        if ( ! strlen($this->data['ux']))
                        {
                            $this->data['packages'][$package->packagetitle]['localurl'] = $tree_url;
                        }
                        else
                        {
                            $this->data['packages'][$package->packagetitle]['localurl'] = $this->mvc->dispatcher->generate_url
                            (
                                'apps_by_title',
                                array
                                (
                                    'os' => $package->repoos,
                                    'version' => $package->repoosversion,
                                    'ux' => $this->data['ux'],
                                    'basecategory' => $this->data['basecategory'],
                                    'packagetitle' => $package->packagetitle
                                ),
                                $this->request
                            );
                        }
                        $this->data['ux'] = '';
                        $this->data['basecategory'] = '';
                    }
                }
                else
                {
                    // local url to a package index page
                    $this->data['packages'][$package->packagetitle]['localurl'] = $tree_url;
                }
                // gather some basic stats
                $stats = self::get_statistics($package->packagetitle);

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

            // we group the variants into providers. a provider is basically a project repository, e.g. home:fal
            $this->data['packages'][$package->packagetitle]['providers'][$package->repoprojectname]['projectname'] = $package->repoprojectname;

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

            // if the UX is empty then we consider the package to be good for all UXes
            // this value is used in the template to show a proper icon
            $package->ux = $package->repoosux;
            if ( ! strlen($package->ux) )
            {
                $package->ux = 'universal';
            }

            // the variants are basically the versions built for different hardware architectures (not UXes)
            $this->data['packages'][$package->packagetitle]['providers'][$package->repoprojectname]['variants'][] = $package;
        }
    }

    /**
     * Determines a category tree starting from a certain category
     * @param object a packagedetails object
     * @return string the full category tree
     */
    public function determine_category_tree($packagedetails)
    {
        $category = new com_meego_package_category($packagedetails->packagecategory);

        $category->tree = null;

        if (is_object($category))
        {
            $up = $category->up;

            $category->tree = $category->name;

            while ($up != 0)
            {
                $current = new com_meego_package_category($up);
                $category->tree = $current->name . ':' . $category->tree;
                $up = $current->up;
            }
        }

        return $category->tree;
    }

    /**
     * Determines the base category of a given package
     * Since a package category can -in theory belong to multiple base categories
     * this call will return the 1st match only
     *
     * @param object a packagedetails object
     *
     * @return string name of the base category
     */
    public function determine_base_category($packagedetails)
    {
        $basecategory = '';

        $storage = new midgard_query_storage('com_meego_package_category_relation');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('packagecategory'),
            '=',
            new midgard_query_value($packagedetails->packagecategory)
        );

        $q->set_constraint($qc);
        $q->execute();

        $bases = $q->list_objects();

        if (count($bases))
        {
            $object = new com_meego_package_basecategory($bases[0]->basecategory);

            if (is_object($object))
            {
                $basecategory = $object->name;
            }
        }

        return $basecategory;
    }

    /**
     * Gathers ratings and appends them to data
     *
     * @param guid of the package
     * @return array of ratings together with their comments
     */
    public function prepare_ratings($package_guid)
    {
        $retval = array();

        $storage = new midgard_query_storage('com_meego_package_ratings');
        $q = new midgard_query_select($storage);

        $q->set_constraint
        (
            new midgard_query_constraint
            (
                new midgard_query_property('guid'),
                '=',
                new midgard_query_value($package_guid)
            )
        );

        $q->add_order(new midgard_query_property('posted', $storage), SORT_DESC);
        $q->execute();

        $ratings = $q->list_objects();

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
                    $qb = new midgard_query_builder('midgard_user');
                    $qb->add_constraint('person', '=', $rating->authorguid);

                    $users = $qb->execute();

                    if (count($users))
                    {
                        $username = $users[0]->login;
                    }

                    unset($qb);

                    if (count($users) > 0)
                    {
                        $username = $users[0]->login;
                    }

                    if (   $username
                        && $username != 'admin')
                    {
                        // get avatar and url to user profile page only if the user is not the midgard admin
                        try
                        {
                            $rating->avatar = $this->mvc->dispatcher->generate_url('meego_avatar', array('username' => $username), '/');
                            $rating->avatarurl = $this->mvc->configuration->user_profile_prefix . $username;
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

        return $retval;
    }
}
