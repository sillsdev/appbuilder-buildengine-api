package scriptureappbuilder;
import scriptureappbuilder.keystore;
import scriptureappbuilder.google;

class jobs {
    static gitBranch = '*/master'
    static buildJobScript = '''
{ set +x; } 2>/dev/null
PROJNAME=$(basename *.appDef .appDef)
rename "s/$PROJNAME/build/" *
/usr/share/scripture-app-builder/sab.sh -load build.appDef -no-save -build -ta 22 -ks $KS -ksp $KSP -ka $KA -kap $KAP -fp apk.output=$WORKSPACE/output -vc $VERSION_CODE
set -x
echo $(awk -F '[<>]' '/package/{print $3}' build.appDef) > output/package_name.txt
echo $VERSION_CODE > output/version_code.txt
if [ -f "build_data/about/about.txt" ]; then
  cp build_data/about/about.txt output/
fi
rename "s/build/$PROJNAME/" build*
PUBLISH_DIR=$(find "${PROJNAME}_data" -name publish -print)
if [ -d "$PUBLISH_DIR" ]; then
  (cd "$PUBLISH_DIR" && tar cf - .) | gzip > output/publish.tar.gz
fi
'''
    static artifactFiles = 'output/*'

    static void codecommitBuildJob(jobContext, gitUrl, publisherName, artifactUrlBase) {
        jobContext.with {
            description "Create App for ${gitUrl}"

            wrappers {
                xvfb('default') {
                    screen('1024x768x24')
                }

                timeout {
                    noActivity(180)
                    failBuild()
                    writeDescription('Build failed due to timeout: {0} seconds')
                }
            }

            properties {
                zenTimestamp('yyyy_MM_dd_HH_mm_Z')
            }

            configure keystore."${publisherName}_credentialsBindingWrapper"()

            label('android-sdk && app-builders')

            scm {
                git {
                    remote {
                        url(gitUrl)
                        credentials('appbuilder-buildagent')
                    }
                    branch(gitBranch)
                        configure { node ->
                        node / 'extensions' / 'hudson.plugins.git.extensions.impl.UserIdentity' << {
                            delegate.name('AppBuilder_BuildAgent')
                            email('appbuilder_buildagent@sil.org')
                        }
                    }
                    extensions {
                        cleanBeforeCheckout()
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
                        tag('origin', '$BUILD_TAG-$VERSION_CODE-$BUILD_TIMESTAMP') {
                            create(true)
                        }
                    }
            }

            logRotator {
                numToKeep(5)
                artifactNumToKeep(2)
            }
        }
    }

    static publishJobScript = '''
rm -rf *
wget "$APK_URL"
wget "$ARTIFACT_URL/package_name.txt"
wget "$ARTIFACT_URL/publish.tar.gz"
wget "$ARTIFACT_URL/version_code.txt"

tar xvf "$WORKSPACE/publish.tar.gz"

set +x
supply -j $PAJ -b *.apk -p $(cat package_name.txt) --track $CHANNEL -m play-listing
'''
    static void googleplayPublishJob(jobContext, gitUrl, publisherName, buildJobName) {
        jobContext.with {
            description "Publish App for ${gitUrl}"

            configure google."${publisherName}_credentialsBindingWrapper"()

            label('fastlane-supply')
            parameters {
                choiceParam('CHANNEL', ['production', 'alpha', 'beta'])
                stringParam('ARTIFACT_URL', '', '' )
                stringParam('APK_URL', '', '' )
            }
            steps {
                shell(publishJobScript)
            }

            logRotator {
                numToKeep(5)
            }
        }
    }
}

