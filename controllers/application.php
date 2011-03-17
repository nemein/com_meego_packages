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
     * Generates the content for the index page showing the icons of variants
     */
    public function get_index(array $args)
    {
        $this->data['repositories'] = array();
        $this->data['oses'] = array();

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

        $repositories = $q->list_objects();

        foreach ($repositories as $repository)
        {
            if (   $repository->repoos == 'meego'
                && $repository->repoosux)
            {
                $repository->os = 'MeeGo';
                $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['title'] = $repository->repoos . ' ' . $repository->repoosversion;
                $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['uxes'][$repository->repoosux]['title'] = ucfirst($repository->repoosux);
                $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['uxes'][$repository->repoosux]['css'] = $repository->repoosgroup . ' ' . $repository->repoosux;
                $localurl = $this->mvc->dispatcher->generate_url
                (
                    'apps_ux_index',
                    array
                    (
                        'ux' => $repository->repoosux
                    ),
                    $this->request
                );
                $this->data['oses'][$repository->repoos . ' ' . $repository->repoosversion]['uxes'][$repository->repoosux]['url'] = $localurl;
            }
        }
    }
}