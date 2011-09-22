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
     * Prepares an array with important data of the repository
     *
     * @param object com_meego_os object
     * @param object com_meego_ux object
     * @param string OS group the repo belongs to, e.g. extras
     * @param string UX name, in case the repository has none
     *
     * @return array with data
     */
    private function populate_repo_ux($os = null, $ux = null, $group = '', $default_ux = null)
    {
        $retval = array();

        if (strlen($ux->name))
        {
            $default_ux = $ux->name;
        }

        //$repository->os = strtolower($repository->repoos);
        $retval['translated_title'] = ucwords($this->mvc->i18n->get('title_' . $default_ux . '_ux'));
        $retval['title'] = ucwords($default_ux);
        $retval['css'] = $group . ' ' . $default_ux;

        $localurl = $this->mvc->dispatcher->generate_url(
            'repositories',
            array(),
            $this->request
        );

        $retval['url'] = $localurl . mb_strtolower($os->name) . '/' . $os->version . '/' . $default_ux;

        return $retval;
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
        $qb->add_order('osversion', 'DESC');

        $repositories = $qb->execute();

        foreach ($repositories as $repository)
        {
            if ($repository->os)
            {
                $repository->os = $this->mvc->configuration->os_map[$repository->os];

                $os = new com_meego_os($repository->osversion);

                $this->data['oses'][$repository->os . ' ' . $os->version]['title'] = $repository->os . ' ' . ucfirst($os->version);//$repository->osversion;

                $ux = new com_meego_ux($repository->osux);

                if (! strlen($ux->name))
                {
                    // No UX means a core repo, so we populate all UXes
                    foreach($this->mvc->configuration->os_ux as $configured_ux => $configured_ux_title)
                    {
                        $this->data['oses'][$repository->os . ' ' . $os->version]['uxes'][$configured_ux] = $this->populate_repo_ux($os, $ux,  $repository->osgroup, $configured_ux);
                    }
                }
                else
                {
                    $this->data['oses'][$repository->os . ' ' . $os->version]['uxes'][$ux->name] = $this->populate_repo_ux($os, $ux, $repository->osgroup);
                }
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

        $storage = new midgard_query_storage('com_meego_package_repository_project');

        $qc = new midgard_query_constraint_group('AND');
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('repodisabledownload', $storage),
            '=',
            new midgard_query_value(false)
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('repoos', $storage),
            '=',
            new midgard_query_value($args['os'])
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('repoosversion', $storage),
            '=',
            new midgard_query_value($args['version'])
        ));

        $qc2 = new midgard_query_constraint_group('OR');
        $qc2->add_constraint(new midgard_query_constraint(
            new midgard_query_property('repoosux', $storage),
            '=',
            new midgard_query_value($args['ux'])
        ));

        $qc3 = new midgard_query_constraint_group('AND');
        $qc3->add_constraint(new midgard_query_constraint(
            new midgard_query_property('repoosux', $storage),
            '=',
            new midgard_query_value('')
        ));

        $qc4 = new midgard_query_constraint_group('OR');

        foreach ($this->mvc->configuration->os_groups as $os_group => $os_group_title)
        {
            $qc4->add_constraint(new midgard_query_constraint(
                new midgard_query_property('repoosgroup', $storage),
                '=',
                new midgard_query_value($os_group)
            ));
        }

        $qc4->add_constraint(new midgard_query_constraint(
            new midgard_query_property('repoosgroup', $storage),
            '=',
            new midgard_query_value('')
        ));

        $qc3->add_constraint($qc4);
        $qc2->add_constraint($qc3);
        $qc->add_constraint($qc2);

        $q = new midgard_query_select($storage);

        $q->set_constraint($qc);
        $q->add_order(new midgard_query_property('repotitle', $storage), SORT_ASC);
        $q->execute();

        $repositories = $q->list_objects();

        $cnt = 0;
        foreach ($repositories as $repository)
        {
            (++$cnt % 2 == 0) ? $repository->rawclass = 'even' : $repository->rawclass = 'odd';

            $repository->localurl = $this->mvc->dispatcher->generate_url
            (
                'repository',
                array
                (
                    'project' => $repository->projectname,
                    'repository' => $repository->reponame,
                    'arch' => $repository->repoarch
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

        // by default noone can manage workflows
        $this->request->set_data_item('manage_workflows', false);

        $cnt = 0;
        foreach ($packages as $package)
        {
            // by default there are no posted forms
            $package->posts = false;

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
                    'package' => $package->name,
                    'version' => $package->version,
                    'project' => $args['project'],
                    'repository' => $this->data['repository']->name,
                    'arch' => $this->data['repository']->arch
                ),
                $this->request
            );

            // see if packages in this repo have forms submitted
            $storage = new midgard_query_storage('com_meego_package_forms_posted');

            $q = new midgard_query_select($storage);

            $q->set_constraint(new midgard_query_constraint(
                new midgard_query_property('packageguid'),
                '=',
                new midgard_query_value($package->guid)
            ));

            $q->execute();

            if ($q->get_results_count())
            {
                $_url = $this->mvc->dispatcher->generate_url
                (
                    'package_posted_forms',
                    array
                    (
                        'package' => $package->guid,
                    ),
                    'com_meego_packages'
                );
                $package->posts = $_url;
            }


            $this->data['packages'][] = $package;
        }

        $user = $this->mvc->authentication->get_user();

        if (   $user
            && $user->is_admin())
        {
            $this->request->set_data_item('manage_workflows', true);

            $this->request->set_data_item('create_form_url', $this->mvc->dispatcher->generate_url
            (
                'form_create',
                array
                (
                    'parent' => $this->data['repository']->guid
                ),
                'midgardmvc_ui_forms'
            ));

            // @todo: get a list of forms
            $forms = null;

            $storage = new midgard_query_storage('midgardmvc_ui_forms_form');

            $q = new midgard_query_select($storage);

            $q->set_constraint(new midgard_query_constraint(
                new midgard_query_property('parent'),
                '=',
                new midgard_query_value($this->data['repository']->guid)
            ));

            $q->execute();

            $items = $q->list_objects();

            foreach($items as $item)
            {
                $form = $item;

                $form->edit_url = $this->mvc->dispatcher->generate_url
                (
                    'form_update',
                    array
                    (
                        'form' => $item->guid,
                    ),
                    'midgardmvc_ui_forms'
                );

                $forms[] = $form;
            }

            $this->request->set_data_item('forms', $forms);
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
                $package->title = ucfirst($package->name);
            }

            $package->localurl = $this->mvc->dispatcher->generate_url
            (
                'package_instance',
                array
                (
                    'package' => $package->name,
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
     * Checks is the given ux is valid
     *
     * @param string name of the ux
     * @return boolean true if ux exists, false otherwise
     */
    public function ux_exists($ux = '')
    {
        $retval = false;
        $storage = new midgard_query_storage('com_meego_repository');

        $q = new midgard_query_select($storage);

        $q->set_constraint(new midgard_query_constraint(
            new midgard_query_property('osux'),
            '=',
            new midgard_query_value($ux)
        ));

        $q->execute();

        if ($q->get_results_count())
        {
            $retval = true;
        }

        if (! $retval)
        {
            if (array_key_exists($ux, $this->mvc->configuration->os_ux))
            {
                $retval = true;
            }
        }

        return $retval;
    }
}
