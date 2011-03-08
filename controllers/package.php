<?php
class com_meego_packages_controllers_package
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
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
            if ( ! midgardmvc_core::get_instance()->authentication->is_user() )
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

            $package->localurl = midgardmvc_core::get_instance()->dispatcher->generate_url
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

            $package->repositoryobject->localurl = midgardmvc_core::get_instance()->dispatcher->generate_url
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

            $package->localurl = midgardmvc_core::get_instance()->dispatcher->generate_url
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
        if ( ! midgardmvc_core::get_instance()->authentication->is_user() )
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

        $this->data['package']->localurl = midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'package',
            array
            (
                'package' => $this->data['package']->title,
            ),
            $this->request
        );
        $this->data['package']->repositoryobject = new com_meego_repository($this->data['package']->repository);

        $this->data['package']->repositoryobject->localurl = midgardmvc_core::get_instance()->dispatcher->generate_url
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
            $this->data['package']->screenshoturl = midgardmvc_core::get_instance()->dispatcher->generate_url
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
                $_url = midgardmvc_core::get_instance()->dispatcher->generate_url
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
                'url' => midgardmvc_core::get_instance()->dispatcher->generate_url
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
                midgardmvc_core::get_instance()->head->relocate
                (
                    midgardmvc_core::get_instance()->dispatcher->generate_url
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

                    $package->localurl = midgardmvc_core::get_instance()->dispatcher->generate_url
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

}