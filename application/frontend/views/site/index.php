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
            <div class="col-lg-3">
                <h2>Job</h2>

                <p>View, edit or remove entries from the job table.  Each entry in the table corresponds to
                    a application build and an application publish job in Jenkins instances attached to this database. 
                    Deleting job entries also deletes any associated
                builds and releases associated with the job.  The corresponding jobs in Jenkins will also be deleted
                shortly after the entry is removed from the database. </p>

                <p><a class="btn btn-default" href="/job-admin">Job Administration &raquo;</a></p>
            </div>
            <div class="col-lg-3">
                <h2>Build</h2>

                <p>View, edit or remove entries from the build table.  Each entry in this table corresponds to a build
                    of the application job in Jenkins instances attached to this database.  Deleting the build also
                deletes any releases associated with this build.</p>

                <p><a class="btn btn-default" href="/build-admin">Build Administration &raquo;</a></p>
            </div>
            <div class="col-lg-3">
                <h2>Release</h2>

                <p>View, edit or remove entries from the release table.  Entries in this table relates to attempts to
                    publish builds to the Google Play Store and corresponds to a build of the Jenkins publish job.</p>

                <p><a class="btn btn-default" href="/release-admin">Release Administration &raquo;</a></p>
            </div>
            <div class="col-lg-3">
                <h2>Operation Queue</h2>

                <p>View, edit or remove entries from the operation queue table.  Entries in this table relate to internal operations
                    that are either queued to be performed, waiting to be retried, or have failed the maximum number of times and 
                    are present for reporting purposes only.

                <p><a class="btn btn-default" href="/operation-queue-admin">Operation Queue Administration &raquo;</a></p>
            </div>
        </div>

    </div>
</div>
