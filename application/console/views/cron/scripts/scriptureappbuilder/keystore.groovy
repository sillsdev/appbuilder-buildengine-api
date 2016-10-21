package scriptureappbuilder;

class keystore {

    static Closure credentialsBindingWrapper(publisherName) { 
        return { project ->
            project / 'buildWrappers' << 'org.jenkinsci.plugins.credentialsbinding.impl.SecretBuildWrapper' {
		bindings {
			'org.jenkinsci.plugins.credentialsbinding.impl.FileBinding' {
				variable 'KS'
                                credentialsId "${publisherName}-ks" 
			}
			'org.jenkinsci.plugins.credentialsbinding.impl.StringBinding' {
				variable 'KSP'
                                credentialsId "${publisherName}-ksp" 
			}
			'org.jenkinsci.plugins.credentialsbinding.impl.StringBinding' {

				variable 'KA'
                                credentialsId "${publisherName}-ka" 
			}
			'org.jenkinsci.plugins.credentialsbinding.impl.StringBinding' {
				variable 'KAP'
                                credentialsId "${publisherName}-kap" 
			}
		}
            }
        }
    }
}
