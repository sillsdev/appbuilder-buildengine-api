# appbuilder-buildengine-api #
This is the Web Service interface for the SIL App Publishing Service. BuildEngine creates entries in a database
and transforms the data into Jenkins Job DSL configuration shared in a git Repository shared with
the Jenkins build infrastructure (see [docker-appbuilder-jenkins](https://github.com/sillsdev/docker-appbuilder-jenkins)).

## Build Status ##
Develop: [ ![Codeship Status for sillsdev/appbuilder-buildengine-api](https://codeship.com/projects/4fe24ee0-0999-0134-1439-2adbeb910e90/status?branch=develop)](https://codeship.com/projects/155333)

Master: [ ![Codeship Status for sillsdev/appbuilder-buildengine-api](https://codeship.com/projects/4fe24ee0-0999-0134-1439-2adbeb910e90/status?branch=master)](https://codeship.com/projects/155333)


## API Specs ##
https://docs.google.com/a/sil.org/document/d/1FtHLCEOvOuSnBC1ryeEUH3Vx2Y5XqF_WhpkPpjWteqs/edit?usp=sharing

## Doorman: Service Provisioning ##

[Doorman](https://doorman.sil.org) is a web application developed by [GTIS](http://gtis.sil.org) that automates the
workflow of approval and provisioning of services for SIL and affiliate organizations (requires Insite authentication).
Examples of the services are: Smartsheet, Trello, HipChat, Jira, and CrashPlan.

For the SIL App Publishing Service, Doorman will provide the following services:

* Readiness Check
* Collect Publishing Project Definition
    * Project Name
    * Language Code
    * App Publishing Key
    * App Store (Currently: Wycliffe USA)
    * Future: Account for expenses and charges (if we cannot cover them)
* Notifies Service Admin for Project Creation Approval
* Create Git Repo in AWS CodeCommit & assigns access rights to user using App Publishing Key
* Requests New Project in BuildEngine (includes App Publishing URL)
* Notifies user of App Publishing URL and request them to Send/Receive project
* Requests New Build in BuildEngine -> Build Artifact in S3
* Builds App for Initial Store Entry
* Notifies User for Review
* Notifies Service Admin for Review, Approval, and creation of initial App Store Entry (must be done by human; Play Store limites number/day)
* Request BuildEnging to Publish App to Google Play Store
* Manages Service/Update of Project

### Dependencies ###
In production, there is a dependency on Amazon Web Services for the following services:

* [CodeCommit](https://aws.amazon.com/codecommit/)[^1]
    * create repository for storing project configuration
* [Identity and Access Management (IAM)](https://aws.amazon.com/iam/)
    * create users based on project provisioning requests
    * create group based on user's Entity of Assignment and add user to group
    * give permission to user to access CodeCommit Repo

### Deployment ###
The staging version is available at https://doorman-sab.gtis.guru.

## App Publishing Service ##
The App Publishing Service is a collection of applications that are deployed as Docker containers.  Docker containers
are based on Linux Containers which is a virtualization strategy that reuses Linux Kernel of the host and isolates
applications in a mini Linux system.  Therefore, the host for these containers must be a Linux system (which can be run
in a Virtual Machine on non-Linux systems for development).

There are two sets of containers:

* BuildEngine - RESTful Web Service used by Doorman
    * web - REST API implementation that takes requests and updates database
    * cron - processes the updates to the database and interfaces with Jenkins; updates database with status from Jenkins
* AppBuilder - Executes the building and publishing of Apps
    * master - Jenkins instance
    * slave - build agent that executes SAB to build apps and Google Play Developer APIs to publish apps

BuildEngine manages the state of the Jenkins configuration using Jenkins [Job DSL Plugin](https://github.com/jenkinsci/job-dsl-plugin/wiki).
BuildEngine writes the Job DSL scripts into a Git repository.  If it notices changes, it commits the changes, pushes to the remote repository,
and attempts to re-run the Job-Wrapper-Seed job on the Jenkins master (via Jenkins REST API).  This job processes the Job DSL scripts in the
Git repository which reconfigures Jenkins.

### Deployment ###

In production, the containers are deployed to a docker container service, like [Amazon ECS](https://aws.amazon.com/ecs/).
For local development, these containers can be deployed to:

 * Native Docker: Linux
 * Vagrant: Windows or Linux
 * Docker Toolbox: Mac

Note: Docker Toolbox exists for Windows, however it an [issue with interactive terminal](https://github.com/docker/docker/issues/12469) (docker -it container command).
This limitation makes it difficult to debug issues during development and is not recommended.

### Dependencies ###
In production or development, there is a dependency on Amazon Web Services for the following services:

* [Simple Storage Service (S3)](https://aws.amazon.com/s3/)
    * store sensitive information including private keys and credentials
    * store build artifacts accessible to users
* [CodeCommit](https://aws.amazon.com/codecommit/)[^1]
    * synchronizing data between the BuildEngine Web Service and Jenkins build infrastructure
    * Note: In development, it might be possible to use another git system.  Need to test.
* [Relational Database Services (RDS)](https://aws.amazon.com/rds/)
    * store project, build, and release information for BuildEngine Web Service
    * Note: In development, we use a MariaDB database to simulate RDS database

For SIL development or staging, please contact @chris_hubbard or @rickmaclean for access to the SIL LSDev account.

# Development setup instructions #

APP_ENV is a variable that differentiates development, staging, and production environments.
For development environments, you should use the form "development-USERNAME-MACHINE" (e.g. development-chrish-win).
Whenever you use see APP_ENV in the directions, please substitute with your chosen environment string.  APP_ENV should
be limited to alphanumeric characters, '.', '_', and '-' (CodeCommit limit).

* On Windows, you will need to install [Git for Windows](https://git-scm.com/download/win) and execute commands from a Git Bash shell.
* [Clone Source Repositories](#clone-source-repositories) for  ```appbuilder-buildengine-api``` and ```docker-appbuilder-jenkins```
* [Create BuildEngine SSH Key](#create-buildengine-ssh-key) to be used to authenticate git access to jenkins config data
* [Associate BuildEngine SSH Key](#associate-buildengine-ssh-key) to a user in IAM
* [Create AppBuilder SSH Key](#create-appbuilder-ssh-key) to be used to authenticate git access to projects
* [Associate AppBuilder SSH Key](#associate-buildengine-ssh-key) to a user in IAM
* [Create BuildEngine CodeCommit Repository](#create-buildengine-codecommit-repository)
* [Create S3 Folders](#create-s3-folders) to store credentials
* [Give permissions to IAM users](#give-permissions)
* [Jenkins Configuration](#jenkins-configuration)
* [Deploy containers](#deploy-containers) for AppBuilder
* [Build Engine Configuration](#build-engine-configuration)
* [Deploy containers](#deploy-containers) for BuildEngine

#### Clone Source Repositories ####
You will need to authenticate to GitHub to be able to clone the repositories. You can either use username/password with the HTTPS urls:

```bash
git clone https://github.com/sillsdev/appbuilder-buildengine-api
git clone https://github.com/sillsdev/docker-appbuilder-jenkins
```

Or you can create an SSH Key, store the private and public key in ~/.ssh, and associate the public key with your GitHub account.

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
ssh-keygen -t rsa -b 4096 -f ~/.ssh/id_rsa
# login to https://github.com/settings/keys/ and "New SSH Key" using ~/.ssh/id_rsa.pub
git clone git@github.com:sillsdev/appbuilder-buildengine-api
git clone git@github.com:sillsdev/docker-appbuilder-jenkins
```

If you want to create a separate SSH Key just for GitHub, you can create the SSH Key in a subdirectory of ~/.ssh and then add an entry to
~/.ssh/config (which normally doesn't exist--create an empty file) to specify the location of the key.

```
Host github.com
	IdentityFile ~/.ssh/github/id_rsa
```

[Back](#development-setup-instructions)

#### Create BuildEngine SSH Key ####
BuildEngine uses git to synchronize the git Repository with ssh keys for authentication.

1. Create ~/.ssh/buildengine_api directory
2. Create ssh key in ~/.ssh/buildengine_api directory (use an empty key phrase)
```bash
mkdir -p ~/.ssh/buildengine_api
chmod 700 ~/.ssh/buildengine_api
ssh-keygen -t rsa -b 4096 -f ~/.ssh/buildengine_api/id_rsa
openssl rsa -in ~/.ssh/buildengine_api/id_rsa -pubout > ~/.ssh/buildengine_api/id_rsa_pub.pem
```

This will also save the public key to ~/.ssh/buildengine_api/id_rsa.pub

[Back](#development-setup-instructions)

#### Associate BuildEngine SSH Key ###
BuildEngine uses SSH authentication to access the Git repository which store Jenkins job configuration.
You need to associate the SSH Public Key with a user in IAM.

1. In AWS, go to IAM service and create or select a user which will be the "Build Engine" user for your deployment
    + Note: You will need the ```Access Key Id``` and ```Secret Access Key``` for this user later.
2. Select the Security Credentials tab
3. Click on "Upload SSH public key" button
4. Open ~/.ssh/buildengine_api/id_rsa.pub with a text editor and copy all of the text
5. Paste the text into the "Upload SSH public key" page.
6. Click on the "Upload SSH public Key" button
7. You should see a new entry under "SSH keys for AWS CodeCommit". Save the value of "SSH Key ID" for the new uploaded key to ~/.ssh/buildengine_api/ssh_key_id.txt

[Back](#development-setup-instructions)

#### Create AppBuilder SSH Key ####
BuildEngine uses git to synchronize the git Repository with ssh keys for authentication.

1. Create ~/.ssh/appbuilder directory
2. Create ssh key in ~/.ssh/appbuilder directory (use an empty key phrase)
```bash
mkdir -p ~/.ssh/appbuilder
chmod 700 ~/.ssh/appbuilder
ssh-keygen -t rsa -b 4096 -f ~/.ssh/appbuilder/id_rsa
openssl rsa -in ~/.ssh/appbuilder/id_rsa -pubout > ~/.ssh/appbuilder/id_rsa_pub.pem
```

This will also save the public key to ~/.ssh/appbuilder/id_rsa.pub

[Back](#development-setup-instructions)

#### Associate AppBuilder SSH Key ###
AppBuilder uses SSH authentication to access the CodeCommit Repo for projects.  You need to associate the SSH Public Key
with a user in IAM.

1. In AWS, go to IAM service and create or select a user which will be the "App Builder" user for your deployment
    + Note: You will need the ```Access Key Id``` and ```Secret Access Key``` for this user later.
2. Select the Security Credentials tab
3. Click on "Upload SSH public key" button
4. Open ~/.ssh/appbuilder/id_rsa.pub with a text editor and copy all of the text
5. Paste the text into the "Upload SSH public key" page.
6. Click on the "Upload SSH public Key" button
7. You should see a new entry under "SSH keys for AWS CodeCommit". Save the value of "SSH Key ID" for the new uploaded key to ~/.ssh/appbuilder/ssh_key_id.txt

[Back](#development-setup-instructions)

#### Create BuildEngine CodeCommit Repository ####
We use a Git repository to store Jenkins Job DSL configuration.  BuildEngine takes information from the Database and generates
Job DSL files for each of the jobs in the database, commits any changes to the configuration, and notifies Jenkins to reconfigure
the jobs (i.e. execute Job-Wrapper-Seed job which processes the Job DSL files from the Git repository.

To use a CodeCommit Repository for BuildEngine:
1. Go to  [AWS CodeCommit](https://console.aws.amazon.com/codecommit/home)
2. Click "Create new repository"
3. Set the repository name to ci-scripts-APP_ENV
4. Click on the "Clone URL" dropdown and select SSH.  Copy the URL and keep for later.

The format of the URL should be ssh://git-commit.us-east-1.amazonaws.com/v1/repos/REPO where REPO is the repository name you entered.

[Back](#development-setup-instructions)

#### Create S3 Folders ####
BuildEngine and AppBuilder use [s3-expand](https://github.com/silinternational/s3-expand) to extract credentials needed to
access resources. 

* Create the following folder in [AWS S3](https://console.aws.amazon.com/s3/home)
```
sil-appbuilder-secrets/APP_ENV/buildengine_api/ssh
```
* Save buildengine_api private and public key in S3
```
sil-appbuilder-secrets/APP_ENV/buildengine_api/ssh/id_rsa
sil-appbuilder-secrets/APP_ENV/buildengine_api/ssh/id_rsa.pub
```

Jenkins is used to initiate jobs that can build the application and/or publish them to the Google Play Store.
The system can be set up so that one instance of Jenkins performs both of these tasks or it can be configured to perform only one
of the two functions, allowing another instance of Jenkins to perform the other.  For a typical development
environment, the same Jenkins instance will be used for both, so both sets of keys described below should be
included.

If the credentials being saved here are to support a system that will build the application using AppBuilder:

* Create the following folders in [AWS S3](https://console.aws.amazon.com/s3/home)
```
sil-appbuilder-secrets/APP_ENV/jenkins/build/appbuilder_ssh
sil-appbuilder-secrets/APP_ENV/jenkins/build/google_play_store
```
 

* Save appbuilder private and public key in S3
```
sil-appbuilder-secrets/APP_ENV/jenkins/build/appbuilder_ssh/id_rsa
sil-appbuilder-secrets/APP_ENV/jenkins/build/appbuilder_ssh/id_rsa.pub
```

AppBuilder needs additional credentials for signing APK files.  These

* Create the following folder in S3
```
sil-appbuilder-secrets/APP_ENV/jenkins/build/google_play_store/wycliffeusa
```

* Create a wycliffeusa.keystore (using Scripture App Builder from Tools->Create New KeyStore menu)
* When creating a keystore, there are 3 other pieces of information needed to access the keystore later.
    * Key Store Password
    * Key Alias Name
    * Key Alias Password
* Create text files (that will be uploaded to S3) with the values entered for these 3 pieces of information.
    * ksp.txt - Key Store Password
    * ka.txt - Key Alias
    * kap.txt - Key Alias Password
* Upload wycliffeusa.keystore, ksp.txt, ka.txt, and kap.txt to ```sil-appbuilder-secrets/APP_ENV/jenkins/build/google_play_store/wycliffeusa```

If the credentials being saved here are to support a system that will publish the application to Google Play Store:
* Create the following folder in S3
```
sil-appbuilder-secrets/APP_ENV/jenkins/publish/google_play_store/wycliffeusa
```
* Upload playstore_api_issuer.txt and playstore_api_key.p12 to ```sil-appbuilder-secrets/APP_ENV/jenkins/publish/google_play_store/wycliffeusa```

 
[Back](#development-setup-instructions)

#### Give Permissions ####
There are 3 different users involved in accessing S3 resources

* Build Engine
    * CodeCommit: Write Job DSL configuration
    * S3: Access secrets
    * S3: Put build artifacts
    * IAM: Create User and Groups, Manage SSH Key
    * CodeCommit: Create Repository
* App Builder
    * CodeCommit: Read Job DSL configuration
    * CodeCommit: Read/Write Project data
    * S3: Access secrets
* End User
    * CodeCommit: Read/Write Project data

Create the following policies:

* S3 App Builder Secrets - extract ssh keys to access Git repository for Job DSL configuration
    + In [AWS IAM Policies](https://console.aws.amazon.com/iam/home#polices), Create Policy
    + Select "Create Your Own Policy"
    + Set the Policy Name to "s3-appbuilder-secrets-APPENV"
    + Paste in this text and then click on "Create Policy"

```javascript
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetBucketLocation",
                "s3:ListBucket"
            ],
            "Condition": {
                "StringLike": {
                    "s3:prefix": [
                        "APP_ENV/*"
                    ]
                }
            },
            "Resource": [
                "arn:aws:s3:::sil-appbuilder-secrets"
            ]
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject"
            ],
            "Resource": [
                "arn:aws:s3:::sil-appbuilder-secrets/APP_ENV/*"
            ]
        }
    ]
}
```

* S3 App Builder Artifacts - write and delete build artifiacts accessed by end user via Doorman
    + Note: These are a too permissive.  TODO: Work on minimal set.
    + In [AWS IAM Policies](https://console.aws.amazon.com/iam/home#polices), Create Policy
    + Select "Create Your Own Policy"
    + Set the Policy Name to "s3-appbuilder-artifacts-APPENV"
    + Paste in this text and then click on "Create Policy"

```javascript
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "*"
            ],
            "Resource": [
                "arn:aws:s3:::sil-appbuilder-artifacts"
            ]
        },
        {
            "Effect": "Allow",
            "Action": [
                "*"
            ],
            "Resource": [
                "arn:aws:s3:::sil-appbuilder-artifacts/*"
            ]
        }
    ]
}
```

* CodeCommit Repository for Job DSL configuration
    + In [AWS IAM Policies](https://console.aws.amazon.com/iam/home#polices), Create Policy
    + Select "Create Your Own Policy"
    + Set the Policy Name to "codecommit-ci-scripts-APPENV"
    + Paste in this text and then click on "Create Policy"

```javascript
{
    "Version": "2012-10-17",
    "Statement": [
        {
           "Effect": "Allow",
            "Action": [
                "codecommit:GetBranch",
                "codecommit:GitPull",
                "codecommit:GitPush",
                "codecommit:ListBranches"
            ],
            "Resource": [
                "arn:aws:codecommit:us-east-1:117995318043:ci-scripts-APP_ENV"
            ]
        }
    ]
}
```

* CodeCommit Repository for project data
    + In [AWS IAM Policies](https://console.aws.amazon.com/iam/home#polices), Create Policy
    + Select "Create Your Own Policy"
    + Set the Policy Name to "codecommit-projects-APPENV"
    + Paste in this text and then click on "Create Policy"

```javascript
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "codecommit:GetBranch",
                "codecommit:GitPull",
                "codecommit:GitPush",
                "codecommit:ListBranches"
            ],
            "Resource": [
                "arn:aws:codecommit:us-east-1:117995318043:*"
            ]
        }
    ]
}
```

* Project creations (Create Users, Groups, and Project Repository)
    + In [AWS IAM Policies](https://console.aws.amazon.com/iam/home#polices), Create Policy
    + Select "Create Your Own Policy"
    + Set the Policy Name to "projects-creation-APPENV"
    + Paste in this text and then click on "Create Policy"

```javascript
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "VisualEditor0",
            "Effect": "Allow",
            "Action": [
                "iam:CreateGroup",
                "iam:AddUserToGroup",
                "iam:ListSSHPublicKeys",
                "iam:GetSSHPublicKey",
                "iam:UploadSSHPublicKey",
                "iam:GetUser",
                "iam:CreateUser",
                "iam:GetGroup",
                "iam:PutGroupPolicy"
            ],
            "Resource": ""
        },
        {
            "Sid": "VisualEditor1",
            "Effect": "Allow",
            "Action": [
                "codecommit:CreateRepository",
                "codecommit:DeleteRepository"
            ],   
            "Resource": "arn:aws:codecommit::*:*"
        }
    ]
}
````
    
Attach the following policies to the "Build Engine" user:

* s3-appbuilder-secrets-APP_ENV
* s3-appbuilder-artifacts-APP_ENV
* codecommit-ci-scripts-APP_ENV
* projects-creation-APP_ENV

Attach the following policies to the "App Builder" user:

* s3-appbuilder-secrets-APP_ENV
* s3-appbuilder-artifacts-APP_ENV
* codecommit-projects-APP_ENV

[Back](#development-setup-instructions)

#### Jenkins Configuration ####
In the directory where you cloned ```docker-appbuilder-jenkins```, do the following:

* copy local.env.dist to local.env and update the variables. Replace SOME_KEY with your chosen environment string.
* set ```AWS_USER_ID``` to the account number of the AWS account being used (see https://console.aws.amazon.com/support/home)
* set ```EXPAND_S3_KEY``` and ```EXPAND_S3_SECRET``` to the ```Access Key Id``` and ```Secret Access Key``` of the App Builder User.
* set ```BUILD_ENGINE_REPO_URL``` to the ssh url saved from creating the Build Engine repository.
* set ```BUILD_ENGINE_REPO_BRANCH``` to master
* set ```BUILD_ENGINE_GIT_SSH_USER``` to the value stored in ~/.ssh/buildengine_api/ssh_key_id.txt
* set ```BUILD_ENGINE_JENKINS_MASTER_URL``` to the url of Jenkins.  This depends how you will deploy this app:
    + If you will be using vagrant (Windows or Linux): ```http://192.168.70.241:8080```
    + If you will be using native docker (Linux): ```http://localhost:8080```
    + If you will be using docker-machine (Mac): run ```docker-engine ip default``` to determine IP, port 8080
        + most likely will be: http://192.168.99.100:8080

in ```docker-appbuilder-jenkins``` will match ```APPBUILDER_JENKINS_URL``` in ```appbuilder-buildengine-api```

[Back](#development-setup-instructions)

#### Build Engine Configuration ####
In the directory where you cloned ```appbuilder-buildengine-api```, do the following:

* copy local.env.dist to local.env and update the variables. Replace SOME_KEY with your chosen environment string.
* set ```EXPAND_S3_KEY``` and ```EXPAND_S3_SECRET``` to the ```Access Key Id``` and ```Secret Access Key``` of the Build Engine User.
* set ```AWS_ACCESS_KEY_ID``` and ```AWS_SECRET_ACCESS_KEY``` to the ```Access Key Id``` and ```Secret Access Key``` of the Build Engine User.
* set ```BUILD_ENGINE_GIT_SSH_USER``` to the value of ~/.ssh/buildengine_api/ssh_key_id.txt
* set ```APPBUILDER_GIT_SSH_USER``` to the value stored in ~/.ssh/appbuilder/ssh_key_id.txt
* set ```BUILD_ENGINE_REPO_URL``` to the ssh url saved from creating the Build Engine repository.
* set ```BUILD_ENGINE_JENKINS_MASTER_URL``` to value used in ```docker-appbuilder-jenkins```
* set ```PUBLISH_JENKINS_MASTER_URL``` to value used in ```docker-appbuilder-jenkins```
* set ```API_ACCESS_TOKEN``` to some unique string to your environment.
    + This will be used during testing for HTTP Bearer Token Authentication.
    + See [RFC6750](https://tools.ietf.org/html/rfc6750) for more details.

[Back](#development-setup-instructions)

### Deploy Containers ###

* If deploying to native Docker on Linux or Docker Toolbox on Mac, set environment variable DOCKER_UIDGID
```bash
echo 'export DOCKER_UIDGID="$(id -u):$(id -g)"' >> ~/.profile
source ~/.profile
```

#### Native: Linux ####
* [Install Docker](https://docs.docker.com/engine/installation/linux/ubuntulinux/)
* [Install Docker-Compose](https://docs.docker.com/compose/install/)
* NOTE: DOCKER_UIDGID needs to be set in environment
* Start services
```bash
docker-compose up -d
```

#### Vagrant: Windows or Linux ####
* [Install VirtualBox](https://virtualbox.org)
* [Install Vagrant 1.7.4](https://vagrantup.com)
* Start vagrant & services
```bash
vagrant up
vagrant ssh
```

#### Docker Toolbox: Mac ####
* [Install Docker Toolbox](https://docs.docker.com/engine/installation/mac/)
* NOTE: DOCKER_UIDGID needs to be set in environment
* Start docker and services
```bash
docker-machine start default
eval $(docker-machine env default)
docker-compose up -d
docker-machine ip default # to know what the IP Address to use for docker host
```

[Back](#development-setup-instructions)

# Testing API #
Doorman interacts with BuildEngine using a RESTful Web Interface.  This can be simlated with a Google Chrome Extension called Advanded REST Client.

* Install [Advanced REST Client](https://chrome.google.com/webstore/detail/advanced-rest-client/hgmloofddffdnphfgcellkdfbfbjeloo?utm_source=chrome-app-launcher-info-dialog) to test Web Service
* [Give User Write Permissions to CodeCommit](#give-user-write-permissions-to-codecommit)
* [Create AppBuilder CodeCommit Repository](#create-appbuilder-codecommit-repository)
* [Commit Project](#commit-project) to AppBuilder CodeCommit repository
* [Add Job to BuildEngine]
TODO:
* Get job
* Request a build for a job
* Get build status
* Publish a build


#### Give User Write Permissions to CodeCommit ####
For development deployment, we will need a project to test with.  Earlier we created a codecommit-project-APP_ENV policy
that gives the appbuilder user read/write permissions to any repository with the name prefix of projects-APP_ENV-.

* Add the following text to ~/.ssh/config to use the appbuilder ssh key to commit the project.

```
Host git-codecommit.*.amazonaws.com
    User USERNAME
    IdentityFile ~/.ssh/appbuilder/id_rsa
```

* Change USERNAME to be the value of ~/.ssh/appbuilder/ssh_key_id.txt

#### Create AppBuilder CodeCommit Repository ####
We use CodeCommit Git repositories for storing the data for the project.  The Doorman service provisioning application
automates the creation of the repository and setting the security.  We need to simulate that here.

1. Go to  [AWS CodeCommit](https://console.aws.amazon.com/codecommit/home)
2. Click "Create new repository"
3. Set the repository name to projects-APP_ENV-PROJECT_NAME
4. Click on the "Clone URL" dropdown and select SSH.  Copy the URL for the next step.

#### Commit Project ####
* Copy a project to use for testing
    + In a ~/App\ Builder/Scripture\ Apps/App\ Projects/, copy some project to projects-APP_ENV-PROJECT_NAME
* In a command window, do the following:
    + cd ~/App\ Builder/Scripture\ Apps/App\ Projects/projects-APP_ENV-PROJECT_NAME
    + git init
    + git add .
    + git commit -m "Initial Revision"
    + git remote add origin URL
        * URL is the "Clone URL" from [Create AppBuilder CodeCommit Repository](#create-appbuilder-codecommit-repository)
    + git push origin master

#### Add Job to BuildEngine ####
The base URL of BuildEngine depends on how you deployed BuildEngine

* Start Google Chrome and Switch to "Apps" in Google Chrome and select ARC
* set URL (first field) to http://BUILDENGINE_HOST/job (BUILDENGINE_HOST depends on how BuildEngine was deployed)
    + vagrant (Windows or Linux): 192.168.70.121
    + native docker (Linux): localhost
    + docker-machine (Mac): run ```docker-engine ip default``` to determine IP
        * most likely: 192.168.99.100
* set VERB to POST
* set Headers to (replace YOUR_API_TOKEN)
```
Accept: application/json
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```
* set Payload to (replace YOUR_REQUEST_ID with a unique value like the return value of ```date +%s```)
```javascript
{
    "request_id" : "YOUR_REQUEST_ID",
    "git_url" : "ssh://git-codecommit.us-east-1.amazonaws.com/v1/repos/projects-APP_ENV-PROJECT_NAME",
    "app_id" : "scriptureappbuilder",
    "publisher_id" : "wycliffeusa"
}
```
* Click Send


[^1]: We use for the Git Repository since AWS only charges for $1/month/active-user.  For many projects, the user
requesting the project will be active for a short period of time and then not modify the project repository for a long time.
It would be possible to use another Git service (like [GitHub](https://github.com)) to host the project repository, but will require to make a
code change in application/common/model/Job.php to allow other repository urls).

