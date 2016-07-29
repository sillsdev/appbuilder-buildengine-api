<?php
namespace console\components;

use JenkinsApi\Item\Build as JenkinsBuild;
use JenkinsApi\Item\Job as JenkinsJob;

class ActionCommon
{
    /**
     * If there isn't a build running, start a new build and return the last build number so that we can check when
     * the new build is active (i.e. current build > last build).  If currently building, return null (and let caller
     * try again later).
     * @param JenkinsJob $job
     * @param array $params
     * @return last_build_number|null
     */
    protected function startBuildIfNotBuilding($job, $params = array())
    {
        // Note: JenkinsJob::isCurrentlyBuilding doesn't check for getLastBuild return null :-(
        $lastBuildNumber = null;
        if (!$job->getLastBuild())
        {
            echo "...not built at all, so launch a build". PHP_EOL;
            $job->launch($params);
            $lastBuildNumber = 0; // all real builds start > 0
        }
        else if (!$job->isCurrentlyBuilding())
        {
            echo "...not building, so launch a build". PHP_EOL;

            $lastBuild = $job->getLastBuild();
            $lastBuildNumber = $lastBuild->getNumber();

            $job->launch($params);
        }
        if (is_null($lastBuildNumber))
        {
            echo '...is currentlyBuilding so wait'.  PHP_EOL;
        }
        else
        {
            echo "...is launched. Returning last_build=". $lastBuildNumber . PHP_EOL;
        }
        return $lastBuildNumber;
    }
    /**
     * If a new build has started since $lastBuildNumber, then return the value, else return null.
     * @param JenkinsJob $job
     * @param integer $lastBuildNumber
     * @return integer build_number|null
     */
    protected function getStartedBuildNumber($job, $lastBuildNumber)
    {
        $build = $job->getLastBuild();
        if (!is_null($build)) {
            $buildNumber = $build->getNumber();
            if ($buildNumber > $lastBuildNumber) {
                return $buildNumber;
            }
        }

        return null;
    }
    function try_lock($tokenSemaphore, $tokenValue) {
        sem_acquire($tokenSemaphore);
        if (!shm_has_var($tokenValue, 6)) {
            shm_put_var($tokenValue, 6, 0);
        }
        $tmp = shm_get_var($tokenValue, 6);
        // This is so that if a crash occurs, it won't hang up for more than
        // 20 minutes.  A single instance should never take more than 20 minutes
        // to run
        if ($tmp > 20) {
            $tmp = 0;
        }
        $exit = ($tmp > 0);
        $tmp = $tmp + 1;
        $tmp = shm_put_var($tokenValue, 6, $tmp);
        $tmp = shm_get_var($tokenValue, 6);
        sem_release($tokenSemaphore);
        if ($exit) return false;
        return true;
    }
    function release($tokenSemaphore, $tokenValue) {
        sem_acquire($tokenSemaphore);
        $tmp = shm_get_var($tokenValue, 6);
        $tmp = shm_put_var($tokenValue, 6, 0);
        $tmp = shm_get_var($tokenValue, 6);
        sem_release($tokenSemaphore);
    }
}


