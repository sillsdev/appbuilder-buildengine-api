package scriptureappbuilder;
import utilities.common;
import scriptureappbuilder.keystore;
import scriptureappbuilder.google;

class jobs {
	static gitBranch = '*/master'
	static buildJobScript = '''
rm -rf output/*

{ set +x; } 2>/dev/null
/usr/share/scripture-app-builder/sab.sh -load *.appDef -build -ta 22 -ks $KS -ksp $KSP -ka $KA -kap $KAP -fp apk.output=$WORKSPACE/output
set -x
echo $(awk -F '[<>]' '/package/{print $3}' *.appDef) > output/package_name.txt
echo $(grep "version code=" *.appDef|awk -F"\\"" '{print $2}') > output/version_code.txt

if [ -d "metadata" ]; then
  tar -cvzf "output/metadata.tar.gz" "metadata"
fi
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
				}
			}

			steps {
				shell(buildJobScript)
			}

			publishers {
				archiveArtifacts(artifactFiles)
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

