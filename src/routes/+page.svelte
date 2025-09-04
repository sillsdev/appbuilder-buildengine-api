<div class="pb-2">
	<div class="py-12 text-center">
		<h1 class="pb-0 pl-0">App Publishing Service</h1>
		<h1 class="pb-0 pl-0">Administration</h1>
	</div>
	<div>
		<div class="flex flex-col space-x-6">
			<div class="flex flex-col space-x-6 lg:flex-row">
				<div>
					<h2 class="pl-0">Job</h2>
					<p>
						View, edit, or remove entries from the job table. Jobs point to the AWS S3 repository
						that contains the source for the builds and publishes associated with this job. Deleting
						job entries also deletes any associated builds and releases associated with the job.
					</p>
					<p><a class="btn btn-outline" href="/job-admin">Job Administration »</a></p>
				</div>
				<div>
					<h2 class="pl-0">Build</h2>
					<p>
						View, edit, or remove entries from the build table. Each entry in this table contains a
						url link to the instance of the associated AWS Codebuild build attempt. Deleting the
						build also deletes any releases associated with this build.
					</p>
					<p><a class="btn btn-outline" href="/build-admin">Build Administration »</a></p>
				</div>
				<div>
					<h2 class="pl-0">Release</h2>
					<p>
						View, edit, or remove entries from the release table. Entries in this table relate to
						attempts to publish builds in Google Play store or other customized locations. Each
						entry in this table contains a url link to the instance of the associated AWS Codebuild
						publish attempt.
					</p>
					<p><a class="btn btn-outline" href="/release-admin">Release Administration »</a></p>
				</div>
			</div>
			<div class="flex flex-col space-x-6 lg:flex-row">
				<div>
					<h2 class="pl-0">Client</h2>
					<p>
						View, edit, or remove entries from the client table. Used if multiple Scriptoria sites
						are sending requests to the build engine. Access tokens, which are used for the
						Authentication: Bearer fields of requests are entered along with a prefix that is used
						in naming jobs associated with this client.
					</p>
					<p><a class="btn btn-outline" href="/client-admin">Client Administration »</a></p>
				</div>
				<div>
					<h2 class="pl-0">Operation Queue</h2>
					<p>
						View, edit, or remove entries from the operation queue table. Entries in this table
						relate to internal operations that are either queued to be performed, waiting to be
						retried, or have failed the maximum number of times and are present for reporting
						purposes only.
					</p>
					<p>
						<a class="btn btn-outline" href="/operation-queue-admin">Operation Queue Administration »</a>
					</p>
				</div>
				<div>
					<h2 class="pl-0">Project</h2>
					<p>
						View, edit, or remove entries from the project table. Each entry contains a link to be
						used by Scripture App Builder to create or update a source repository in AWS S3.
					</p>
					<p><a class="btn btn-outline" href="/project-admin">Project Administration »</a></p>
				</div>
			</div>
		</div>
	</div>
</div>
