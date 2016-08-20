package bloomappmaker;
import bloomappmaker.keystore;
import bloomappmaker.google;

class jobs {
    static gitBranch = '*/master'
    static artifactFiles = 'output/**'

    static void codecommitBuildJob(jobContext, gitUrl, publisherName, artifactUrlBase) {
        def buildJobScript = '''
cd groovy/bloomappmaker/build
bundle install
ruby ./build-bloom-app.rb --spec_id PROJECT_URL --ks $KS --ksp $KSP --ka $KA --kap $KAP --api_key 'PARSE_API_KEY' --app_id 'PARSE_APP_ID' --dest $WORKSPACE/output --vc $VERSION_CODE --font_dir $WORKSPACE/groovy/bloomappmaker/build/fonts 
# Work-around for https://issues.jenkins-ci.org/browse/JENKINS-35102
killall Xvfb
'''
        def gitScriptUrl = System.getenv('BUILD_ENGINE_REPO_URL');
        def gitUser = System.getenv('BUILD_ENGINE_GIT_SSH_USER');
        def parseApiKey = System.getenv('PARSE_API_KEY');
        if (parseApiKey?.trim()) {
                buildJobScript = buildJobScript.replaceAll('PARSE_API_KEY', parseApiKey?.trim());
        }
        def parseAppId = System.getenv('PARSE_APP_ID');
        if (parseAppId?.trim()) {
                buildJobScript = buildJobScript.replaceAll('PARSE_APP_ID', parseAppId?.trim());
        }

        if (gitUser) {
          gitScriptUrl = "ssh://" + gitUser + "@" + gitScriptUrl.substring(6);
        }
        println "GitURL: " + gitScriptUrl;
        println "ProjectID: " + gitUrl;
        if (gitUrl?.trim()) {
            buildJobScript = buildJobScript.replaceAll('PROJECT_URL', gitUrl);
        }
        gitScriptUrl = gitScriptUrl?.trim();
        jobContext.with {
            description "Create Bloombook App for ID ${gitUrl}"

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

            configure keystore."${publisherName}_credentialsBindingWrapper"()

            label('android-sdk && app-builders')

            scm {
                git {
                    remote {
                        url(gitScriptUrl)
                        credentials('buildengine')
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
                    configure { git ->
                        git / 'extensions' / 'hudson.plugins.git.extensions.impl.SparseCheckoutPaths' / 'sparseCheckoutPaths' {
                            ['groovy/bloomappmaker'].each { mypath ->
                             'hudson.plugins.git.extensions.impl.SparseCheckoutPath' {
                                path("${mypath}")
                        }
                    }
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
supply -j $PAJ -b *.apk -p $(cat package_name.txt) --track $CHANNEL -m play-listing
'''

    static void googleplayPublishJob(jobContext, gitUrl, publisherName, buildJobName) {
        jobContext.with {
            description "Publish Bloombook App for ID ${gitUrl}"

            configure google."${publisherName}_credentialsBindingWrapper"()

            label('fastlane-supply')
            parameters {
                choiceParam('CHANNEL', ['production', 'alpha', 'beta'])
                stringParam('ARTIFACT_URL', '', '' )
                stringParam('APK_URL', '', '' )
                stringParam('PUBLIC_URL', '', '')
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

