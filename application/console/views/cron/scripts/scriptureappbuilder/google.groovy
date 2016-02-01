
package scriptureappbuilder;

class google {
    static Closure wycliffeusa_credentialsBindingWrapper() {
        return { project ->
            project / 'buildWrappers' << 'org.jenkinsci.plugins.credentialsbinding.impl.SecretBuildWrapper' {
                bindings {
                    'org.jenkinsci.plugins.credentialsbinding.impl.FileBinding' {
                        variable 'PAK'
                        credentialsId 'wycliffeusa-playstore-api-key'
                    }
                    'org.jenkinsci.plugins.credentialsbinding.impl.StringBinding' {
                        variable 'PAI'
                        credentialsId 'wycliffeusa-playstore-api-issuer'
                    }
                }
            }
        }
    }
}