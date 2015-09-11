<?php
    /* @var $jobName string */
    /* @var $gitUrl string */
    /* @var $publisherName string */
?>
import scriptureappbuilder.jobs;

def jobName = '<?= $jobName ?>'
def gitUrl = '<?= $gitUrl ?>'
def publisherName = '<?= $publisherName ?>'
job(jobName) {
    jobs.codecommitBuildJob(delegate, gitUrl, publisherName)
}
