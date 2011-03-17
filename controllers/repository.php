<?php
class com_meego_packages_controllers_repository
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
    }

    /**
     * Redirect user to correct repository
     */
    public function get_redirect(array $args)
    {
        // Fallback, didn't recognize browser's MeeGo version, redirect to list of repositories
        $this->mvc->head->relocate
        (
            $this->mvc->dispatcher->generate_url
            (
                'repositories', array(),
                $this->request
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

        $qb = com_meego_repository::new_query_builder();
        $qb->add_constraint('disabledownload', '=', false);
        $repositories = $qb->execute();

        $prefix = $this->mvc->dispatcher->generate_url(
            'repositories',
            array(),
            $this->request
        );

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

    /**
     * Fetches all repositories that are under a certain OS and version
     * @param array args
     */
    public function get_repositories_list(array $args)
    {
        $this->data['title'] = 'Available repositories for ' . $args['os'] . '-' . $args['version'] . '-' . $args['ux'];

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
        $q->add_order(new midgard_query_property('title', $storage), SORT_ASC);
        $q->execute();

        $repositories = $q->list_objects();

        $cnt = 0;
        foreach ($repositories as $repository)
        {
            (++$cnt % 2 == 0) ? $repository->rawclass = 'even' : $repository->rawclass = 'odd';

            // get the name of the project the repository belongs to
            $project = new com_meego_project($repository->project);
            $repository->projectname = $project->name;

            $repository->localurl = $this->mvc->dispatcher->generate_url
            (
                'repository',
                array
                (
                    'project' => $project->name,
                    'repository' => $repository->name,
                    'arch' => $repository->arch
                ),
                $this->request
            );

            $this->data['repositories'][] = $repository;
        }
    }

    public function get_repository(array $args)
    {
        $qb = com_meego_repository::new_query_builder();
        $qb->add_constraint('disabledownload', '=', false);
        if (isset($args['project']))
        {
            $qbproject = com_meego_project::new_query_builder();
            $qbproject->add_constraint('name', '=', $args['project']);

            $projects = $qbproject->execute();

            if (count($projects))
            {
                $qb->add_constraint('project', '=', $projects[0]->id);
            }

            $this->data['projectname'] = $args['project'];
        }

        if (isset($args['repository']))
        {
            $qb->add_constraint('name', '=', $args['repository']);
        }

        if (isset($args['arch']))
        {
            $qb->add_constraint('arch', '=', $args['arch']);
        }

        $repositories = $qb->execute();

        if (count($repositories) == 0)
        {
            throw new midgardmvc_exception_notfound("Repository not found");
        }

        $this->data['repository'] = $repositories[0];

        $this->data['packages'] = array();


        $storage = new midgard_query_storage('com_meego_package');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('repository', $storage),
            '=',
            new midgard_query_value($this->data['repository']->id)
        );

        $q->set_constraint($qc);
        $q->add_order(new midgard_query_property('name', $storage), SORT_ASC);
        $q->execute();

        $packages = $q->list_objects();

        $cnt = 0;
        foreach ($packages as $package)
        {
            (++$cnt % 2 == 0) ? $package->rawclass = 'even' : $package->rawclass = 'odd';

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
                    'repository' => $this->data['repository']->name,
                    'arch' => $this->data['repository']->arch
                ),
                $this->request
            );
            $this->data['packages'][] = $package;
        }
    }

    /**
     * Show latest apps in repository
     */
    public function get_repository_latest(array $args)
    {
        $this->data['projectname'] = $args['project'];

        $this->data['repository'] = array();

        $storage = new midgard_query_storage('com_meego_repository');

        $qc = new midgard_query_constraint_group('AND');

        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('name', $storage),
            '=',
            new midgard_query_value($args['repository'])
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('arch', $storage),
            '=',
            new midgard_query_value($args['arch'])
        ));

        $q = new midgard_query_select($storage);

        $q->set_constraint($qc);
        $q->execute();

        $repositories = $q->list_objects();

        if (count($repositories) > 0)
        {
                $this->data['repository'] = $repositories[0];
        }
        else
        {
            throw new midgardmvc_exception_notfound("Repository not found");
        }
        $storage = new midgard_query_storage('com_meego_package');

        $q = new midgard_query_select($storage);
        $q->set_constraint(new midgard_query_constraint(
            new midgard_query_property('repository', $storage),
            '=',
            new midgard_query_value($this->data['repository']->id)
        ));

        $q->add_order(new midgard_query_property('metadata.created', $storage), SORT_DESC);

        if (isset($args['amount']))
        {
            $q->set_limit((int)$args['amount']);
        }

        $q->execute();

        $packages = $q->list_objects();

        $cnt = 0;
        foreach ($packages as $package)
        {
            (++$cnt % 2 == 0) ? $package->rawclass = 'even' : $package->rawclass = 'odd';

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
                    'repository' => $this->data['repository']->name,
                    'arch' => $this->data['repository']->arch
                ),
                $this->request
            );
            $this->data['packages'][] = $package;
        }
    }

}