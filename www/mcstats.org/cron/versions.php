<?php

define('ROOT', '../public_html/');
define('MAX_CHILDREN', 30);

require_once ROOT . 'config.php';
require_once ROOT . 'includes/database.php';
require_once ROOT . 'includes/func.php';

// the current number of running forks
$running_processes = 0;

$baseEpoch = normalizeTime();
$minimum = strtotime('-30 minutes', $baseEpoch);

// iterate through all of the plugins
foreach (loadPlugins(PLUGIN_ORDER_ALPHABETICAL) as $plugin)
{
    // are we at the process limit ?
    if ($running_processes >= MAX_CHILDREN)
    {
        // wait for some children to be allocated
        pcntl_wait($status);
        $running_processes --;
    }

    $running_processes ++;
    $pid = pcntl_fork();

    if ($pid == 0)
    {
        $master_db_handle = try_connect_database();

        foreach($plugin->getVersions() as $versionID => $version)
        {
            // Count the amount of servers that upgraded to this version
            $count = $plugin->countVersionChanges($versionID, $minimum);

            // Insert it into the database
            $statement = $master_db_handle->prepare('INSERT INTO VersionTimeline (Plugin, Version, Count, Epoch) VALUES (:Plugin, :Version, :Count, :Epoch)');
            $statement->execute(array(
                ':Plugin' => $plugin->getID(),
                ':Version' => $versionID,
                ':Count' => $count,
                ':Epoch' => $baseEpoch
            ));
        }

        exit(0);
    }
}

// wait for all of the processes to finish
while ($running_processes > 0)
{
    pcntl_wait($status);
    $running_processes --;
}