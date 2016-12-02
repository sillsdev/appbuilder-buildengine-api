package scriptureappbuilder;
import scriptureappbuilder.keystore;
import scriptureappbuilder.google;

class jobs {
    static gitBranch = '*/master'
    static buildJobScript = '''
PROJNAME=$(basename *.appDef .appDef)
mv "${PROJNAME}.appDef" build.appDef
mv "${PROJNAME}_data" build_data
APPDEF_VERSION=$(grep "version code=" build.appDef|awk -F"\\"" '{print $2}')
if [ "$APPDEF_VERSION" -ge "$VERSION_CODE" ]; then
    VERSION_CODE=$((APPDEF_VERSION+1))
fi
VERSION_NUMBER=$(dpkg -s scripture-app-builder | grep 'Version' | awk -F '[ +]' '{print $2}')
{ set +x; } 2>/dev/null
/usr/share/scripture-app-builder/sab.sh -load build.appDef -no-save -build -ta 22 -ks $KS -ksp $KSP -ka $KA -kap $KAP -fp apk.output=$WORKSPACE/output -vc $VERSION_CODE -vn $VERSION_NUMBER -ft share-app-link=true
set -x
echo $(awk -F '[<>]' '/package/{print $3}' build.appDef) > output/package_name.txt
echo $VERSION_CODE > output/version_code.txt
if [ -f "build_data/about/about.txt" ]; then
  cp build_data/about/about.txt output/
fi
PUBLISH_DIR="build_data/publish/play-listing"
if [ -d "$PUBLISH_DIR" ]; then
  cp -r "$PUBLISH_DIR" output
fi
mv build_data "${PROJNAME}_data"
mv build.appDef "${PROJNAME}.appDef"

# Work-around for https://issues.jenkins-ci.org/browse/JENKINS-35102
killall Xvfb
'''
    static artifactFiles = 'output/**'

    static void codecommitBuildJob(jobContext, gitUrl, publisherName) {
        jobContext.with {
            description "Create App for ${gitUrl}"

            wrappers {
                xvfb('default') {
                    screen('1024x768x24')
                    autoDisplayName(true)
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

            configure keystore."credentialsBindingWrapper"(publisherName)

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
wget -r -i "${ARTIFACT_URL}play-listing.html"
PLAY_LISTING_DIR=$(find -name play-listing -print)
cd $PLAY_LISTING_DIR
cd ..
wget "$APK_URL"
wget "${ARTIFACT_URL}package_name.txt"
wget "${ARTIFACT_URL}version_code.txt"

set +x
if [ -z "$PROMOTE_FROM" ]; then
	supply -j $PAJ -b *.apk -p $(cat package_name.txt) --track $CHANNEL -m play-listing
else
	supply -j $PAJ -b *.apk -p $(cat package_name.txt) --track $PROMOTE_FROM --track_promote_to $CHANNEL -m play-listing
fi
'''
    static void googleplayPublishJob(jobContext, gitUrl, publisherName, buildJobName) {
        jobContext.with {
            description "Publish App for ${gitUrl}"

            configure google."credentialsBindingWrapper"(publisherName)

            label('fastlane-supply')
            parameters {
                choiceParam('CHANNEL', ['production', 'alpha', 'beta'])
                stringParam('ARTIFACT_URL', '', '' )
                stringParam('APK_URL', '', '' )
                stringParam('PUBLIC_URL', '', '')
                stringParam('PROMOTE_FROM', '', '')
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

