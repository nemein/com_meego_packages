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
        $qb = com_meego_repository::new_query_builder();
        $qb->add_constraint('disabledownload', '=', false);
        $repositories = $qb->execute();
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
}
