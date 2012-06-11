<?php

require __DIR__ . '/../../midgardmvc_core/framework.php';
require __DIR__ . '/../../midgardmvc_account/controllers/activity.php';

$cmd = basename($argv[0]);
$inipath = php_ini_loaded_file();
$filepath = ini_get("midgard.configuration_file");

if (   ! $inipath
    || ! $filepath)
{
    echo "Please specify a valid php.ini with Midgard specific settings.\n";
    echo "Example: php -c <path-to-midgard-php-ini> $cmd ...\n";
    exit(1);
}


function help($cmd)
{
    echo "Help\n";
    echo "----\n";
    echo "$cmd [-u user_name]\n\n";
    echo "  Creates activity objects from past comments, rates, QA votes.";
    echo "  If user_name is given then it only checks for the given user\n";
    echo "\n\n";

    exit;
}

function usage($cmd)
{
    echo "Usage\n";
    echo "-----\n";
    echo "  $cmd -h\n";
    echo "  $cmd [-u user_name]\n";
    echo "\n";

    exit;
}

function debug($message)
{
    $message = date('Y-m-d H:i:s') . ' ' . $message . "\n";
    echo $message;
}

/**
 * Find a user based on login name and returns its guid
 * @param string login name
 */
function retrieve_user_guid($login = null)
{
    $retval = null;

    if ($login)
    {
        $storage = new midgard_query_storage('midgard_user');
        $q = new midgard_query_select($storage);

        $q->set_constraint(new midgard_query_constraint(
           new midgard_query_property('login'),
           '=',
           new midgard_query_value($login)
        ));

        $q->execute();
        $q->toggle_readonly(false);

        $users = $q->list_objects();

        if (count($users))
        {
            $retval = $users[0]->person;
        }

        unset($storage, $q, $users);
    }

    return $retval;
}

/**
 * Collect comments and ratings
 * @param guid of a specfic user
 */
function get_comments($user = null)
{
    $storage = new midgard_query_storage('com_meego_ratings_rating');
    $q = new midgard_query_select($storage);

    if ($user)
    {
        $qc = new midgard_query_constraint(
            new midgard_query_property('metadata.creator'),
            '=',
            new midgard_query_value($user)
        );
        $q->set_constraint($qc);
    }
    $q->execute();
    $results = $q->list_objects();
    return $results;
}

/**
 * Collect QA posts
 * @param guid of a specfic user
 */
function get_qas($user = null)
{
    $storage = new midgard_query_storage('midgardmvc_ui_forms_form_instance');
    $q = new midgard_query_select($storage);

    if ($user)
    {
        $qc = new midgard_query_constraint(
            new midgard_query_property('metadata.creator'),
            '=',
            new midgard_query_value($user)
        );
        $q->set_constraint($qc);
    }
    $q->execute();
    $results = $q->list_objects();
    return $results;
}


$config = new midgard_config();
$config->read_file_at_path($filepath);

$mgd = midgard_connection::get_instance();
$mgd->open_config($config);
$mgd->set_loglevel('debug');

$pos = 0;
$user = null;
$guid = null;

foreach($argv as $arg)
{
    switch ($arg)
    {
        case '-h':
            help($cmd);
            break;
        case '-u':
            if (isset($argv[$pos + 1]))
            {
                $user = $argv[$pos + 1];
            }
            break;
    }
    ++$pos;
}

if ($user)
{
    // retrieve the GUId of the user
    $guid = retrieve_user_guid($user);
    debug('GUID of ' . $user . ' is ' . $guid);
}

$comments = get_comments($guid);
$qas = get_qas($guid);
$all = array_merge($comments, $qas);

debug('Found ' . count($comments) . ' comments and ratings objects.');
debug('Found ' . count($qas) . ' QA posts.');

foreach($all as $act)
{
    $res = null;
    $verb = null;
    $summary = null;
    $target = null;

    switch(get_class($act))
    {
        case 'com_meego_ratings_rating':
            $target = $act->to;
            if ($act->rating > 0)
            {
                $verb = 'rate';
                $summary = 'The user rated';

                if ($act->comment > 0)
                {
                    $verb .= ', comment';
                    $summary .= ' and commented';
                }
            }
            else if ($act->comment > 0)
            {
                $verb = 'comment';
                $summary = 'The user commented';
            }
            if ($summary)
            {
                $summary .= ' an application.';
            }
            break;
        case 'midgardmvc_ui_forms_form_instance':
            $verb = 'review';
            $summary = 'The user has reviewed an application.';
            $target = $act->relatedobject;
            break;
        default:
    }

    if ($target && $verb && $summary)
    {
        $mvc = midgardmvc_core::get_instance('/home/sopi/.midgard2/meego/application.yml');
        $res = midgardmvc_account_controllers_activity::create_activity($act->metadata->creator, $verb, $target, $summary, 'Apps', $act->metadata->created, $mvc);

        if (! $res)
        {
            debug ('failed to create activity: ' . $verb . ' for target: ' . $target);
        }
    }
}

?>
