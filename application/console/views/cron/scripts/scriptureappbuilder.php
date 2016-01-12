<?php
    /* @var $buildJobName string */
    /* @var $publishJobName string */
    /* @var $gitUrl string */
    /* @var $publisherName string */
    /* @var $artifactUrlBase string */
?>
import scriptureappbuilder.jobs
def buildJobName = '<?= $buildJobName ?>'
def publishJobName = '<?= $publishJobName ?>'
def gitUrl = '<?= $gitUrl ?>'
def publisherName = '<?= $publisherName ?>'
def artifactUrlBase = '<?= $artifactUrlBase ?>'
job(buildJobName) {
    jobs.codecommitBuildJob(delegate, gitUrl, publisherName, artifactUrlBase)
}
job(publishJobName) {
    jobs.googleplayPublishJob(delegate, gitUrl, publisherName, buildJobName)
}
