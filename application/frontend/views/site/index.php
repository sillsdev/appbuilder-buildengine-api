<?php
/* @var $this yii\web\View */
$this->title = 'SIL App Builder Administration';
?>
<div class="site-index">

    <div class="jumbotron">
        <h1>App Publishing Service</h1>
        <h1>Administration</h1>
    </div>

    <div class="body-content">

        <div class="row">
            <div class="col-lg-2">
                <h2>Job</h2>

                <p>View, edit or remove entries from the job table.  
                    Jobs point to the AWS S3 repository that contains the source for 
                    the builds and publishes associated with this job.
                    Deleting job entries also deletes any associated
                builds and releases associated with the job. </p>

                <p><a class="btn btn-default" href="/job-admin">Job Administration &raquo;</a></p>
            </div>
            <div class="col-lg-2">
                <h2>Build</h2>

                <p>View, edit or remove entries from the build table.  Each entry in this table contains a url link 
                    to the instance of the associated AWS Codebuild build attempt.  Deleting the build also
                deletes any releases associated with this build.</p>

                <p><a class="btn btn-default" href="/build-admin">Build Administration &raquo;</a></p>
            </div>
            <div class="col-lg-2">
                <h2>Release</h2>

                <p>View, edit or remove entries from the release table.  Entries in this table relate to attempts to publish 
                    builds in Google Play store or other customized locations.  Each entry in this table contains a url link 
                    to the instance of the associated AWS Codebuild publish attempt.</p>

                <p><a class="btn btn-default" href="/release-admin">Release Administration &raquo;</a></p>
            </div>
            <div class="col-lg-2">
                <h2>Client</h2>

                <p>View edit or remove entries from the client table.  Used if multiple Scriptoria sites are sending requests to 
                    the build engine.  Access tokens, which are used for the Authentication: Bearer fields of requests are entered
                    along with a prefix that is used in naming jobs associated with this client.

                <p><a class="btn btn-default" href="/client-admin">Client Administration &raquo;</a></p>
            </div>
            <div class="col-lg-2">
                <h2>Operation Queue</h2>

                <p>View, edit or remove entries from the operation queue table.  Entries in this table relate to internal operations
                    that are either queued to be performed, waiting to be retried, or have failed the maximum number of times and 
                    are present for reporting purposes only.

                <p><a class="btn btn-default" href="/operation-queue-admin">Operation Queue Administration &raquo;</a></p>
            </div>
            <div class="col-lg-2">
                <h2>Project</h2>

                <p>View edit or remove entries from the project table.
                     Each entry contains a link to be used by Scripture App Builder to create or update a source repository in AWS S3.

                <p><a class="btn btn-default" href="/project-admin">Project Administration &raquo;</a></p>
            </div>
        </div>

    </div>
</div>
