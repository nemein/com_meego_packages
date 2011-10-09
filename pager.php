<?php

class com_meego_packages_pager
{
    /**
     * Pages an array and returns the paged array
     *
     * @param int total number of items
     * @param int items per page limit
     * @param int current page
     * @param array the data array to be paged
     *
     * @return array paged array with 'previous_page', 'next_page',
     *               'items_shown' and 'content' members
     */
    public function page($limit = 1, $current_page = 1, array $data)
    {
        $retval = array();

        $retval['content'] = 0;
        $retval['items_shown'] = 0;
        $retval['previous_page'] = false;
        $retval['next_page'] = false;

        $total = count($data);

        $localdata = $data;

        if (   $current_page > 0
            && $total > $limit)
        {
            // we cut the result set according to paging request
            $offset = ($current_page - 1) * $limit;

            if ($offset > $total)
            {
                $offset = $total - $limit;
            }

            if (($current_page - 1) > 0)
            {
                $retval['previous_page'] = '?page=' . ($current_page - 1);
            }

            if ($current_page * $limit < $total)
            {
                $retval['next_page'] = '?page=' . ($current_page + 1);
            }

            // workaround for php bug?
            // if we don't do this then array_slice will not work properly
            // in case offset = 1, limit = 1 and count(data) = 2...
            //
            //ob_start();
            //var_dump($data);
            //ob_end_clean();

            // this is where paging really happens
            $localdata = array_slice($data, $offset, $limit, true);

            if ($current_page == 1)
            {
                if ($limit > $total)
                {
                    $retval['items_shown'] = '1 - ' . $total;
                }
                else
                {
                    $retval['items_shown'] = '1 - ' . $limit;
                }
            }
            elseif ($current_page * $limit <= $total)
            {
                $retval['items_shown'] = (($current_page - 1) * $limit) + 1 . ' - ' .  $current_page * $limit;
            }
            else
            {
                $retval['items_shown'] = (($current_page - 1) * $limit) + 1 . ' - ' .  ((($current_page - 1) * $limit) + count($localdata));
            }
        }

        $retval['content'] = $localdata;

        return $retval;
    }
}

?>