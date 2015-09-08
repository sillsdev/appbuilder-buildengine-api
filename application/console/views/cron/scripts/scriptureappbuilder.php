<?php
    /* @var $jobName string */
    /* @var $gitUrl string */
?>
import scriptureappbuilder.jobs;

def jobName = '<?= $jobName ?>'
def gitUrl = '<?= $gitUrl ?>'
job(jobName) {
    jobs.gitBuildJob(delegate, gitUrl);
}
