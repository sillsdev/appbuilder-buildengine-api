package utilities;
import utilities.Helper;

class common {
    static Closure XvfbBuildWrapper() {
        return { project ->
            project / 'buildWrappers' << 'org.jenkinsci.plugins.xvfb.XvfbBuildWrapper' {
                installationName 'default'
                screen '1024x768x24'
                displayNameOffset 1
            }
        }
    }
}
