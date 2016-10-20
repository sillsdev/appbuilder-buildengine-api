
package scriptureappbuilder;

class google {
    static Closure wycliffeusa_credentialsBindingWrapper() {
        return { project ->
            project / 'buildWrappers' << 'org.jenkinsci.plugins.credentialsbinding.impl.SecretBuildWrapper' {
                bindings {
                    'org.jenkinsci.plugins.credentialsbinding.impl.FileBinding' {
                        variable 'PAJ'
                        credentialsId 'wycliffeusa-playstore-api-json'
                    }
                }
            }
        }
    }
    static Closure credentialsBindingWrapper(publisherName) { 
         return { project -> 
            project / 'buildWrappers' << 'org.jenkinsci.plugins.credentialsbinding.impl.SecretBuildWrapper' { 
                bindings { 
                    'org.jenkinsci.plugins.credentialsbinding.impl.FileBinding' { 
                        variable 'PAJ' 
                        credentialsId "${publisherName}-playstore-api-json" 
                    } 
                } 
            } 
        } 
    } 
}