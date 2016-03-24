package scriptureappbuilder;
import utilities.common;
import scriptureappbuilder.keystore;
import scriptureappbuilder.google;

class jobs {
	static gitBranch = '*/master'
	static buildJobScript = '''
rm -rf output/*
{ set +x; } 2>/dev/null
PROJNAME=$(basename *.appDef .appDef)
rename "s/$PROJNAME/build/" *
/usr/share/scripture-app-builder/sab.sh -load build.appDef -no-save -build -ta 22 -ks $KS -ksp $KSP -ka $KA -kap $KAP -fp apk.output=$WORKSPACE/output -vc $VERSION_CODE
set -x
echo $(awk -F '[<>]' '/package/{print $3}' build.appDef) > output/package_name.txt
echo $VERSION_CODE > output/version_code.txt
rename "s/build/$PROJNAME/" build*
if [ -d "metadata" ]; then
  tar -cvzf "output/metadata.tar.gz" "metadata"
fi
git add *.appDef
git commit -m "Update Version Code"
'''
	static artifactFiles = 'output/*'

	static void codecommitBuildJob(jobContext, gitUrl, publisherName, artifactUrlBase) {
		jobContext.with {
			description "Create App for ${gitUrl}"

			configure common.XvfbBuildWrapper()
			configure keystore."${publisherName}_credentialsBindingWrapper"()

			label('android-sdk && app-builders')

			scm {
				git {
					remote {
						url(gitUrl)
						credentials('appbuilder-buildagent')
					}
					branch(gitBranch)
                                        localBranch("master")
                                        configure { node ->
                                            node / 'extensions' / 'hudson.plugins.git.extensions.impl.UserIdentity' << {
                                                delegate.name('AppBuilder_BuildAgent')
                                                email('appbuilder_buildagent@sil.org')
                                            }
                                        }
				}
			}
                        parameters {
                            stringParam('VERSION_CODE', '', '' )
                        }

			steps {
				shell(buildJobScript)
			}

			publishers {
				archiveArtifacts(artifactFiles)
                                git {
                                    pushMerge(true)
                                    pushOnlyIfSuccess(true)
                                    forcePush(false)
                                    branch('origin', 'master')
                                }
			}

			logRotator {
				numToKeep(5)
				artifactNumToKeep(2)
			}
		}
	}

    static publishJobScript = '''
if [ ! -f "$WORKSPACE/metadata.tar.gz" ]; then
  echo "Missing metadata.tar.gaz"
  exit 1
fi
tar xvf "$WORKSPACE/metadata.tar.gz"

set +x
supply -k $PAK -i $PAI -b *.apk -p $(cat package_name.txt) --track $CHANNEL
'''
    static void googleplayPublishJob(jobContext, gitUrl, publisherName, buildJobName) {
        jobContext.with {
            description "Publish App for ${gitUrl}"

			configure google."${publisherName}_credentialsBindingWrapper"()

            label('fastlane-supply')
            parameters {
                choiceParam('CHANNEL', ['production', 'alpha', 'beta'])
            }
            steps {
                shell("rm -rf *")
                copyArtifacts(buildJobName) {
                    flatten()
                    buildSelector {
                        latestSuccessful(true)
                    }
                }
                shell(publishJobScript)
            }

			logRotator {
				numToKeep(5)
			}
        }
    }
}

