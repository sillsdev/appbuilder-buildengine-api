# appbuilder-buildengine-api #
This is the Web Service interface for App Publishing Service to provide build
services.  Doorman will request jobs to be created. BuildEngine will create 
entries in its database and synchronize the data to appbuilder-ci-scripts.

## API Specs ##
https://docs.google.com/a/sil.org/document/d/1FtHLCEOvOuSnBC1ryeEUH3Vx2Y5XqF_WhpkPpjWteqs/edit?usp=sharing 

## Development Environment ##
1. Install requirements 
2. Fork git@bitbucket.org:silintl/appbuilder-ci-scripts
3. Create ssh-key 
4. Configuration

### Install Requirements ###
1. VirtualBox
2. Vagrant

### Create ssh-key ###
BuildEngine uses git to synchronize the appbuilder-ci-scripts.  It uses ssh keys for authentication.

1. Create application/.ssh directory
2. Create ssh key in application/.ssh directory

    ssh-keygen -t rsa -b 4096 -f application/.ssh/id_rsa

3. Add ssh public to your user account

    https://bitbucket.org/account/user/your_username_goes_here/ssh-keys  

### Configuration ###
Copy local.env.dist to local.env and set the following variables:

    APP_ENV=development_your_username_goes_here
    BUILD_ENGINE_REPO_URL=git@bitbucket.org:your_username_goes_here/appbuilder-ci-scripts
    BUILD_ENGINE_JENKINS_MASTER_URL=http://url_to_jenkins
    API_ACCESS_TOKEN=some_string_used_to_authenticate_requests

If you are using vagrant/docker development environment of appbuilder-docker, then the url will be:

    http://192.168.70.241