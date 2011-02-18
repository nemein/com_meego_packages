<?php
class com_meego_packages_controllers_package
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    public function get_package(array $args)
    {
        $qb = com_meego_package::new_query_builder();
        $qb->add_constraint('title', '=', $args['package']);
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

            if (!isset($repositories[$package->repository]))
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
                    'repository' => $repositories[$package->repository]->name,
                ),
                $this->request
            );

            $package->repositoryobject->localurl = midgardmvc_core::get_instance()->dispatcher->generate_url
            (
                'repository',
                array
                (
                    'repository' => $repositories[$package->repository]->name,
                ),
                $this->request
            );
            $this->data['packages'][] = $package;
        }
    }

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
                    'repository' => $args['repository'],
                ),
                $this->request
            );
            $this->data['packages'][] = $package;
        }
    }

    public function get_instance(array $args)
    {
        $qb = com_meego_package::new_query_builder();
        $qb->add_constraint('title', '=', $args['package']);
        $qb->add_constraint('version', '=', $args['version']);
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
        $this->data['package']->description = str_replace("\n\n","<br /><br />",($this->data['package']->description));

        // if user is not logged in then don't show the installfileurl link
        if ( ! midgardmvc_core::get_instance()->authentication->is_user() )
        {
            $this->data['package']->installfileurl = false;
        }

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
                throw new midgardmvc_exception_notfound("Package category not found");
            }

            $this->data['package']->category_name = $categories[0]->name . "/" . $this->data['package']->category_name;
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
                'repository' => $this->data['package']->repositoryobject->name,
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
            if ( ! array_key_exists($relation->relation, $this->data['relations']))
            {
                $_title = $relation->relation;

                if (array_key_exists($relation->relation, $typemap))
                {
                    $_title = $typemap[$relation->relation] . ':';
                }

                $this->data['relations'][$relation->relation] = array('title' => $_title);
                $this->data['relations'][$relation->relation]['packages'] = array();
            }
            array_push($this->data['relations'][$relation->relation]['packages'], $relation);
        }

        unset($relations, $_title, $typemap);

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
                        'repository' => $args['repository'],
                        'workflow' => $workflow,
                    ),
                    $this->request
                )
            );
        }
    }

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