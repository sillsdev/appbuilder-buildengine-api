<?php
    /* @var $jobName string */
    /* @var $gitUrl string */
    /* @var $publisherName string */
    /* @var $artifactUrlBase string */
?>
import scriptureappbuilder.jobs
def jobName = '<?= $jobName ?>'
def gitUrl = '<?= $gitUrl ?>'
def publisherName = '<?= $publisherName ?>'
def artifactUrlBase = '<?= $artifactUrlBase ?>'
job(jobName) {
    jobs.codecommitBuildJob(delegate, gitUrl, publisherName, artifactUrlBase)
}
job("publish_${jobName}") {
    jobs.googleplayPublishJob(delegate, gitUrl, publisherName, jobName)
}
