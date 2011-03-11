<?php
class com_meego_packages_controllers_package
{

    var $request = null;
    var $mvc = null;

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
        $qb->add_constraint('title', '=', $args['package']);
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
        $qb->add_constraint('title', '=', $args['package']);
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
    }

    /**
     * @todo: docs
     */
    private function search_packages($query)
    {
        if (isset($query['q'])
            && !empty($query['q']))
        {
            $qb = com_meego_package::new_query_builder();
            if (isset($query['q']))
            {
                // Text search
                $qb->begin_group('OR');
                $qb->add_constraint('name', 'LIKE', '%' . $query['q'] . '%');
                $qb->add_constraint('title', 'LIKE', '%' . $query['q'] . '%');
                $qb->add_constraint('summary', 'LIKE', '%' . $query['q'] . '%');
                $qb->end_group();
            }

            //if repository is specified for search
            if (   isset($query['repository'])
                && !empty($query['repository']))
            {
                $qb2 = com_meego_repository::new_query_builder();
                $qb2->add_constraint('name', '=', $query['repository']);
                $repository = $qb2->execute();
                if (count($repository) == 0)
                {
                    throw new midgardmvc_exception_notfound("Repository not found");
                }
                $qb->add_constraint('repository', '=', $repository[0]->id);

            }

            return $qb->execute();
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
            && !empty($query['q']))
        {
            $this->data['search'] = $query['q'];
            $this->data['packages'] = array();
            $packages = $this->search_packages($query);
            if (count($packages) == 1)
            {
                // Relocate to package directly
                $this->mvc->head->relocate
                (
                    $this->mvc->dispatcher->generate_url
                    (
                        'package',
                        array
                        (
                            'package' => $packages[0]->name,
                        ),
                        $this->request
                    )
                );
            }
            else if (count($packages) > 1)
            {
                foreach ($packages as $package)
                {
                    if (isset($this->data['packages'][$package->name]))
                    {
                        continue;
                    }

                    if (empty($package->title))
                    {
                        $package->title = $package->name;
                    }

                    $package->localurl = $this->mvc->dispatcher->generate_url
                    (
                        'package',
                        array
                        (
                            'package' => $package->name,
                        ),
                        $this->request
                    );
                    $this->data['packages'][$package->name] = $package;
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
        $this->data['categorytree'] = false;
        $this->data['packages'] = false;

        $categorytree = rawurldecode($args['categorytree']);

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
            return;
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
                $ids = array();

                while ($current->up != 0)
                {
                    $done = false;

                    #echo end($tree) . " vs " . $current->name . "(" . $current->id . ")\n<br/>";

                    if ($current->name == end($tree))
                    {
                        array_pop($tree);

                        #echo "add " . $current->id . "\n<br/>";

                        $ids[] = $current->id;
                        $current = new com_meego_package_category($current->up);

                        #echo "new current: " . $current->name . "(" . $current->id . ")<br/>\n";
                        #echo "new end tree: " . end($tree) . "<br/>\n";
                        #echo "count: " . count($tree) . "<br/>\n";

                        if (count($tree) == 1)
                        {
                            $done = true;
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

        #print_r($ids);
        #ob_flush();

        if (   isset($ids[0])
            && $ids[0] != 0)
        {
            $this->data['categorytree'] = $categorytree;
        }

        $storage = new midgard_query_storage('com_meego_package_details');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('packagecategory'),
            '=',
            new midgard_query_value($ids[0])
        );

        $q->set_constraint($qc);
        $q->add_order(new midgard_query_property('packagetitle', $storage), SORT_ASC);
        $q->execute();

        $packages = $q->list_objects();

        // let's do a smart grouping by package_title (ie. short names)
        $variant_counter = 0;
        foreach ($packages as $package)
        {
            // certain things must not be recorded in evert iteration of this loop
            // if we recorded the name, then we are pretty sure we recorded everything
            if (! isset($this->data['packages'][$package->packagetitle]['name']))
            {
                // set the name
                $this->data['packages'][$package->packagetitle]['name'] = $package->packagetitle;

                // gather some basic stats
                $stats = $this->get_statistics($package->packagetitle);

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
                $package->ux = 'allux';
            }

            // the variants are basically the versions built for different hardware architectures (not UXes)
            $this->data['packages'][$package->packagetitle]['providers'][$package->repoprojectname]['variants'][] = $package;
        }
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
                // now search for comments
                // unfortunately this can not be done in the com_meego_package_ratings view
                // because if a rating has a comment with ID 0 then SQL JOINs will skip that
                $storage = new midgard_query_storage('com_meego_comments_comment_author');
                $q = new midgard_query_select($storage);

                $qc = new midgard_query_constraint(
                    new midgard_query_property('commentid'),
                    '=',
                    new midgard_query_value($package->commentid)
                );

                $q->set_constraint($qc);
                $q->execute();

                $comments = $q->list_objects();

                if (count($comments))
                {
                    // do not count empty comments
                    if (strlen($comments[0]->content))
                    {
                        $retval['number_of_comments']++;
                    }
                }
            }
        }

        if (count($packages))
        {
            $retval['average_rating'] = round($sum / count($packages), 1);
        }

        return $retval;
    }

}