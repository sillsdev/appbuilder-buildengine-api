<?php
namespace console\components;

use JenkinsApi\Item\Build as JenkinsBuild;
use JenkinsApi\Item\Job as JenkinsJob;

class ActionCommon
{
     /**
     * We can only get the build_number until the build has actually started.  So, if there is currently
     * a build running, wait until the next cycle before trying.
     * @param JenkinsJob $job
     * @param array $params
     * @return JenkinsBuild|null
     */
    protected static function startBuildIfNotBuilding($job, $params = array(), $timeoutSeconds = 60, $checkIntervalSeconds = 2)
    {
        // Note: JenkinsJob::isCurrentlyBuilding doesn't check for getLastBuild return null :-(
        $startTime = time();
        if (!$job->getLastBuild())
        {
            echo "...not built at all, so launch a build". PHP_EOL;
            $job->launch($params);
            $lastBuild = null;
            while ((time() < $startTime + $timeoutSeconds)
                    && !$lastBuild)
            {
                sleep($checkIntervalSeconds);
                $job->refresh();
                $lastBuild = $job->getLastBuild();
            }
        }
        else if (!$job->isCurrentlyBuilding())
        {
            echo "...not building, so launch a build". PHP_EOL;

            $lastNumber = $job->getLastBuild()->getNumber();

            $job->launch($params);

            while ((time() < $startTime + $timeoutSeconds)
                && ($job->getLastBuild()->getNumber() == $lastNumber))
            {
                sleep($checkIntervalSeconds);
                $job->refresh();
            }
        }
        else
        {
            // Currently building so wait for next cycle
            return null;
        }

        $build = $job->getLastBuild();
        if (is_null($build))
        {
            echo '...There was no lastbuild for this job so $build is null {$job->getLastBuild()} '.  PHP_EOL;
        }
        else
        {
            echo "...is building now. Returning build ". $build->getNumber() . PHP_EOL;
        }
        return $build;
    }
}


