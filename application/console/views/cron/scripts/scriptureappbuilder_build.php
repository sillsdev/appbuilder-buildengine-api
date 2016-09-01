<?php
    /* @var $buildJobName string */
    /* @var $publishJobName string */
    /* @var $gitUrl string */
    /* @var $publisherName string */
?>
import scriptureappbuilder.jobs
def buildJobName = '<?= $buildJobName ?>'
def publishJobName = '<?= $publishJobName ?>'
def gitUrl = '<?= $gitUrl ?>'
def publisherName = '<?= $publisherName ?>'
job(buildJobName) {
    jobs.codecommitBuildJob(delegate, gitUrl, publisherName)
}
