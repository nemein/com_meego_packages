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
     *                see: defaults.yml
     *
     * @return array of com_meego_package_forms_posted objects
     */
    public function get_posted_forms($package_guid = null, $page = 1)
    {
        $cnt = 0;
        $retval = array();

        $retval['forms'] = null;
        $retval['previous_page'] = 1;
        $retval['next_page'] = 1;

        // @todo: maybe do a GUID check here?
        if (! $package_guid)
        {
            return $retval;
        }

        $posts = self::get_all_forms($package_guid);

        if (   is_array($posts)
            && count($posts))
        {
            $total = count($posts);
            $limit = $this->mvc->configuration->rows_per_page * $this->mvc->configuration->items_per_row;
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

                $post->submitter = "n/a";
                // get the login name for the submitter
                $user = com_meego_packages_utils::get_user_by_person_guid($post->submitterguid);

                if ($user)
                {
                    $post->submitter = $user->login;
                }

                if (! isset($retval['forms'][$post->formtitle]['title']))
                {
                    $retval['forms'][$post->formtitle]['title'] = $post->formtitle;
                }
                $retval['forms'][$post->formtitle]['posts'][$post->forminstanceguid] = $post;
            }
        }
        return $retval;
    }


    /**
     * Just return all DB objects
     */
    public function get_all_forms($package_guid = null)
    {
        $retval = array();

        $storage = new midgard_query_storage('com_meego_package_forms_posted');

        $q = new midgard_query_select($storage);

        $q->set_constraint(new midgard_query_constraint(
            new midgard_query_property('packageguid'),
            '=',
            new midgard_query_value($package_guid)
        ));

        // using set_limit and set_offset would work here, but let's try our universal pager
        //$q->set_limit($limit);
        //$q->set_offset($page * $limit);

        $q->execute();

        $forms = $q->list_objects();

        foreach ($forms as $form)
        {
            // don't return duplicates
            $retval[$form->forminstanceguid] = $form;
        }

        return $retval;
    }

}

?>