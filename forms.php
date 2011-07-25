<?php

class com_meego_packages_forms
{
    /**
     * Returns an array of posted forms that belong to the given package.
     * Each element of the array is appended with submitter info as well
     * as some css styling helper (odd or even row), and a local url where
     * the form can be viewed by admins.
     *
     * @param guid GUID of the package
     * @param integer number of the page
     *                Items shown on one page is configurable;
     *                see: defaults.yml::items_per_page
     *
     * @return array of com_meego_package_forms_posted objects
     */
    public function get_posted_forms($package_guid = null, $page = 1)
    {
        $retval = array();
        $retval['posts'] = null;
        $retval['previous_page'] = 1;
        $retval['next_page'] = 1;

        if (! $package_guid)
        {
            return $retval;
        }

        // @todo: maybe do a GUID check here?
        $cnt = 0;

        $storage = new midgard_query_storage('com_meego_package_forms_posted');

        $q = new midgard_query_select($storage);

        $q->set_constraint(new midgard_query_constraint(
            new midgard_query_property('packageguid'),
            '=',
            new midgard_query_value($package_guid)
        ));

        $limit = $this->mvc->configuration->items_per_page;

        // using set_limit and set_offset would work here, but let's try our universal pager

        //$q->set_limit($limit);
        //$q->set_offset($page * $limit);

        $q->execute();

        $posts = $q->list_objects();
        $total = count($posts);

        $paged_posts = com_meego_packages_pager::page($limit, $page, $posts);

        $retval['total'] = $total;
        $retval['items_shown'] = $paged_posts['items_shown'];
        $retval['previous_page'] = $paged_posts['previous_page'];
        $retval['next_page'] = $paged_posts['next_page'];

        foreach ($paged_posts['content'] as $post)
        {
            (++$cnt % 2 == 0) ? $post->rawclass = 'even' : $post->rawclass = 'odd';

            $post->localurl = $this->mvc->dispatcher->generate_url
            (
                'package_posted_form_instance',
                array
                (
                    'forminstance' => $post->forminstanceguid,
                ),
                $this->request
            );

            // get the login name for the submitter
            $qb = new midgard_query_builder('midgard_user');
            $qb->add_constraint('person', '=', $post->submitterguid);

            $users = $qb->execute();

            if (count($users))
            {
                $post->submitter = $users[0]->login;
            }

            unset($qb);

            if (! isset($retval['forms'][$post->formtitle]['title']))
            {
                $retval['forms'][$post->formtitle]['title'] = $post->formtitle;
            }
            $retval['forms'][$post->formtitle]['posts'][] = $post;
        }

        return $retval;
    }
}

?>