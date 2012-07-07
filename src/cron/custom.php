<?php

define('ROOT', '../');
define('MAX_COLUMNS', 50); // soft limit of max amount of columns to loop through per plugin
define('MAX_CHILDREN', 30); // the maximum amount of children that can be started

require_once ROOT . 'config.php';
require_once ROOT . 'includes/database.php';
require_once ROOT . 'includes/func.php';

// the current number of running forks
$running_processes = 0;

$baseEpoch = normalizeTime();
$minimum = strtotime('-30 minutes', $baseEpoch);

// iterate through all of the plugins
foreach (loadPlugins(true) as $plugin)
{
    if ($plugin->getID() == GLOBAL_PLUGIN_ID) continue;

    // we want the data for the last hour
    $count = 0;

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

        // Loop through all of the possible columns
        foreach ($plugin->getCustomColumns() as $id => $name)
        {
            if ($count > MAX_COLUMNS) {
                // break;
            }

            // Sum the data for the current graphing period
            $sum = $plugin->sumCustomData($id, $minimum);

            $statement = $master_db_handle->prepare('INSERT INTO CustomDataTimeline (Plugin, ColumnID, DataPoint, Epoch) VALUES (:Plugin, :ColumnID, :DataPoint, :Epoch)');
            $statement->execute(array(
                ':Plugin' => $plugin->getID(),
                ':ColumnID' => $id,
                ':DataPoint' => $sum,
                ':Epoch' => $baseEpoch
            ));

            $count ++;
        }

        // exit the child
        exit(0);
    }
}

// wait for all of the processes to finish
while ($running_processes > 0)
{
    pcntl_wait($status);
    $running_processes --;
}