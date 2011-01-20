<?php
class com_meego_packages_controllers_repository
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    /**
     * Redirect user to correct repository
     */
    public function get_redirect(array $args)
    {
        // Fallback, didn't recognize browser's MeeGo version, redirect to list of repositories
        midgardmvc_core::get_instance()->head->relocate
        (
            midgardmvc_core::get_instance()->dispatcher->generate_url
            (
                'repositories', array(),
                $this->request
            )
        );
    }

    public function get_index(array $args)
    {
        $this->data['repositories'] = array();
        $this->data['oses'] = array();

        $qb = com_meego_repository::new_query_builder();
        $qb->add_constraint('disabledownload', '=', false);
        $repositories = $qb->execute();

        $prefix = midgardmvc_core::get_instance()->dispatcher->generate_url('repositories', array(), $this->request);

        foreach ($repositories as $repository)
        {
            if (   $repository->os == 'meego'
                && $repository->osux)
            {
                $repository->os = 'MeeGo';
                $this->data['oses'][$repository->os . ' ' . $repository->osversion]['title'] = $repository->os . ' ' . $repository->osversion;
                $this->data['oses'][$repository->os . ' ' . $repository->osversion]['uxes'][$repository->osux]['title'] = ucfirst($repository->osux);
                $this->data['oses'][$repository->os . ' ' . $repository->osversion]['uxes'][$repository->osux]['css'] = $repository->osgroup . ' ' . $repository->osux;
                $this->data['oses'][$repository->os . ' ' . $repository->osversion]['uxes'][$repository->osux]['url'] = $prefix . mb_strtolower($repository->os) . '/' . $repository->osversion . '/' . $repository->osux;
                //$this->data['oses'][$repository->os . ' ' . $repository->osversion]['uxes'][$repository->osux]['groups'][$repository->osgroup]['title'] = ucfirst($repository->osgroup);
                //$this->data['oses'][$repository->os . ' ' . $repository->osversion]['uxes'][$repository->osux]['groups'][$repository->osgroup]['repositories'][] = $repository;
            }
        }
    }

    public function get_repository(array $args)
    {
        $qb = com_meego_repository::new_query_builder();
        $qb->add_constraint('disabledownload', '=', false);
        $qb->add_constraint('name', '=', $args['repository']);
        $repositories = $qb->execute();
        if (count($repositories) == 0)
        {
            throw new midgardmvc_exception_notfound("Repository not found");
        }
        $this->data['repository'] = $repositories[0];

        $this->data['packages'] = array();
        $qb = com_meego_package::new_query_builder();
        $qb->add_constraint('repository', '=', $this->data['repository']->id);
        $packages = $qb->execute();
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
                    'repository' => $this->data['repository']->name,
                ),
                $this->request
            );
            $this->data['packages'][] = $package;
        }
    }

    /**
     * Fetches all repositories that are under a certain OS and version
     * @param array args
     */
    public function get_repository_os_version(array $args)
    {
        $this->data['repositories'] = array();

        $storage = new midgard_query_storage('com_meego_repository');

        $qc = new midgard_query_constraint_group('AND');
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('disabledownload', $storage),
            '=',
            new midgard_query_value(false)
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('os', $storage),
            '=',
            new midgard_query_value($args['os'])
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('osversion', $storage),
            '=',
            new midgard_query_value($args['version'])
        ));

        $qc2 = new midgard_query_constraint_group('OR');
        $qc2->add_constraint(new midgard_query_constraint(
            new midgard_query_property('osux', $storage),
            '=',
            new midgard_query_value($args['ux'])
        ));

        $qc3 = new midgard_query_constraint_group('AND');
        $qc3->add_constraint(new midgard_query_constraint(
            new midgard_query_property('osux', $storage),
            '=',
            new midgard_query_value('')
        ));

        $qc4 = new midgard_query_constraint_group('OR');
        $qc4->add_constraint(new midgard_query_constraint(
            new midgard_query_property('osgroup', $storage),
            '=',
            new midgard_query_value('core')
        ));
        $qc4->add_constraint(new midgard_query_constraint(
            new midgard_query_property('osgroup', $storage),
            '=',
            new midgard_query_value('extras')
        ));
        $qc4->add_constraint(new midgard_query_constraint(
            new midgard_query_property('osgroup', $storage),
            '=',
            new midgard_query_value('surrounds')
        ));

        $qc3->add_constraint($qc4);

        $qc2->add_constraint($qc3);

        $qc->add_constraint($qc2);

        $q = new midgard_query_select($storage);
        $q->set_constraint($qc);
        $q->execute();

        $repositories = $q->list_objects();

        foreach ($repositories as $repository)
        {
            $repository->localurl = midgardmvc_core::get_instance()->dispatcher->generate_url
            (
                'repository',
                array
                (
                    'repository' => $repository->name,
                ),
                $this->request
            );
            $this->data['repositories'][] = $repository;
        }
    }
}