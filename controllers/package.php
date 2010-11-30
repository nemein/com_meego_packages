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
        $qb->add_constraint('name', '=', $args['package']);
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
                    'package' => $package->name,
                    'version' => $package->version,
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
        $qb->add_constraint('name', '=', $args['package']);
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
                    'package' => $package->name,
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
        $qb->add_constraint('name', '=', $args['package']);
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
        $this->data['package']->description = nl2br($this->data['package']->description);

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
    }

    private function search_packages($query)
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
        if (isset($query['repository']))
        {
            $qb2 = com_meego_repository::new_query_builder();
            $qb2->add_constraint('name', '=', $query['repository']);
            $repository = $qb2->execute();
            if (count($repository) == 0)
            {
                throw new midgardmvc_exception_notfound("Package category not found");
            }
            $qb->add_constraint('repository', '=', $repository[0]->id);

        }

        return $qb->execute();
    }

    public function get_search(array $args)
    {
        $this->data['search'] = '';

        $query = $this->request->get_query();
        if (isset($query['q']))
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
                            'package' => $package->name,
                        ),
                        $this->request
                    )
                );
            }

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

        $qb = com_meego_repository::new_query_builder();
        //TODO: add constraints for arch or release.
        $repositories = $qb->execute();

        if (count($repositories) == 0)
        {
            $this->data['repositories'] = $repositories;
        }
    }
}
