# appbuilder-buildengine-api #
This is the Web Service interface for the SIL App Publishing Service. BuildEngine creates entries in a database
and transforms the data into Jenkins Job DSL configuration shared in a git Repository shared with
the Jenkins build infrastructure (see [docker-appbuilder-jenkins](https://bitbucket.org/silintl/docker-appbuilder-jenkins)).

## API Specs ##
https://docs.google.com/a/sil.org/document/d/1FtHLCEOvOuSnBC1ryeEUH3Vx2Y5XqF_WhpkPpjWteqs/edit?usp=sharing

## Doorman: Service Provisioning ##

[Doorman](https://doorman.sil.org) is a web application developed by [GTIS](http://gtis.sil.org) that automates the
workflow of approval and provisioning of services for SIL and affiliate organizations (requires Insite authentication).
Examples of the services are: Smartsheet, Trello, HipChat, Jira, an CrashPlan.

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
* [Clone Source Repositories](#markdown-header-clone-source-repositories) for  ```appbuilder-buildengine-api``` and ```docker-appbuilder-jenkins```
* [Create BuildEngine SSH Key](#markdown-header-create-buildengine-ssh-key) to be used to authenticate git access to jenkins config data
* [Associate BuildEngine SSH Key](#markdown-header-associate-buildengine-ssh-key) to a user in IAM
* [Create AppBuilder SSH Key](#markdown-header-create-appbuilder-ssh-key) to be used to authenticate git access to projects
* [Associate AppBuilder SSH Key](#markdown-header-associate-buildengine-ssh-key) to a user in IAM
* [Create BuildEngine CodeCommit Repository](#markdown-header-create-buildengine-codecommit-repository)
* [Create AppBuilder CodeCommit Respository](#markdown-header-create-appbuilder-codecommit-repository)
* Create S3 Folders
```
sil-appbuilder-secrets/APP_ENV/buildengine_api/ssh
sil-appbuilder-secrets/APP_ENV/jenkins/appbuilder_ssh
```
* Save buildengine_api private and public key in S3
```
sil-appbuilder-secrets/APP_ENV/buildengine_api/ssh/id_rsa
sil-appbuilder-secrets/APP_ENV/buildengine_api/ssh/id_rsa.pub
```
* Save appbuilder private and public key in S3
```
sil-appbuilder-secrets/APP_ENV/jenkins/appbuilder_ssh/id_rsa
sil-appbuilder-secrets/APP_ENV/jenkins/appbuilder_ssh/id_rsa.pub
```
* [Give permissions to IAM user for CodeCommit and S3 Access](#markdown-header-give-permissions)
* If deploying to native Docker on Linux or Docker Toolbox on Mac, set environment variable DOCKER_UIDGID
```bash
echo 'export DOCKER_UIDGID="$(id -u):$(id -g)"' >> ~/.profile
source ~/.profile
```
* In ```appbbuilder-buildengine-api```, do [Build Engine Configuration](#markdown-header-build-engine-configuration)
* [Deploy containers](#deploy-containers) for ```appbuilder-buildengine-api```
* In ```docker-appbuilder-jenkins```, do [Jenkins Configuration](#markdown-header-jenkins-configuration)
* [Deploy containers](#deploy-containers) for ```docker-appbuilder-jenkins```

# Testing API #
* Install [Advanced REST Client](https://chrome.google.com/webstore/detail/advanced-rest-client/hgmloofddffdnphfgcellkdfbfbjeloo?utm_source=chrome-app-launcher-info-dialog) to test Web Service
* Commit a Scripture App Builder project to a CodeCommit repo
* Switch to "Apps" in Google Chrome and select ARC
* Enter
    + URL: http://192.168.70.121/job
    + VERB: POST
    + Headers:
```
Accept: application/json
Authorization: Bearer your_api_token
Content-Type: application/json
```
    + Payload
```javascript
{
    "request_id" : "your_request_id",
    "git_url" : "ssh://SSH_KEY_ID@git-codecommit.REGION.amazonaws.com/v1/repos/REPO",
    "app_id" : "scriptureappbuilder",
    "publisher_id" : "wycliffeusa"
}
```
* Click Send

### Deploy Containers ###
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

#### Clone Source Repositories ####
You will need to authenticate to BitBucket to be able to clone the repositories. You can either use username/password with the HTTPS urls:

```bash
git clone https://bitbucket.org/silintl/appbuilder-buildengine-api
git clone https://bitbucket.org/silintl/docker-appbuilder-jenkins
```

Or you can create an SSH Key, store the private and public key in ~/.ssh, and associate the public key with your BitBucket account.

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
ssh-keygen -t rsa -b 4096 -f ~/.ssh/id_rsa
# login to https://bitbucket.org/account/user/USERNAME/ssh-keys/ and "Add Key" using ~/.ssh/id_rsa.pub
git clone git@bitbucket.org:silintl/appbuilder-buildengine-api
git clone git@bitbucket.org:silintl/docker-appbuilder-jenkins
```

#### Create BuildEngine CodeCommit Repository ####
We use a Git repository to store Jenkins Job DSL configuration.  BuildEngine takes information from the Database and generates
Job DSL files for each of the jobs in the database, commits any changes to the configuration, and notifies Jenkins to reconfigure
the jobs (i.e. execute Job-Wrapper-Seed job which processes the Job DSL files from the Git repository.

To use a CodeCommit Repository for BuildEngine:
1. In AWS, go to the CodeCommit service (under Development Tools)
2. Click "Create new repository"
3. Set the repository name to ci-scripts-APP_ENV
4. Click on the "Clone URL" dropdown and select SSH.  Copy the URL and keep for later.

The format of the URL should be ssh://git-commit.us-east-1.amazonaws.com/v1/repos/REPO where REPO is the repository name you entered.

#### Create AppBuilder CodeCommit Repository ####
We use CodeCommit Git repositories for storing the data for the project.  The Doorman service provisioning application
automates the creation of the repository and setting the security.  We need to simulate that here.

1. In AWS, go to the CodeCommit service (under Development Tools)
2. Click "Create new repository"
3. Set the repository name to projects-APP_ENV-PROJECT_NAME
4. Click on the "Clone URL" dropdown and select SSH.  Copy the URL and keep for later.


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
7. Take note of the "SSH Key ID" for the new uploaded key (suggestion: save to ~/.ssh/buildengine_api/ssh_key_id.txt)

The SSH Key ID will need to set ```BUILD_ENGINE_GIT_SSH_USER```

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
7. Take note of the "SSH Key ID" for the new uploaded key (suggestion: save to ~/.ssh/appbuilder/ssh_key_id.txt)

The SSH Key ID will need to set ```APPBUILDER_GIT_SSH_USER```

#### Give Permissions ####
There are 3 different users involved in accessing S3 resources
* Build Engine
    + CodeCommit: Write Job DSL configuration
    * S3: Access secrets
    * S3: Put build artifacts
* App Builder
    * CodeCommit: Read Job DSL configuration
    * CodeCommit: Read/Write Project data
    * S3: Access secrets
* End User
    * CodeCommit: Read/Write Project data

The "Build Engine" user needs access to the following resources:
* S3 App Builder Secrets - extract ssh keys to access Git repository for Job DSL configuration
    + Create this under Policies in IAM, so that you can attach it to the "Build Engine" user and the "App Builder" user.

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

The "App Builder" user needs access to the following resources:
* S3 App Builder Secrets - extract ssh keys to access Git repository for Job DSL configuration
    + Attach the policy created for Build Engine
 
* CodeCommit Repository for Job DSL configuration
    + Create this under Policies in IAM, so that you can attach it to the "Build Engine" user and the "App Builder" user.

* CodeCommit Repository for project data
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
                "arn:aws:codecommit:us-east-1:117995318043:projects-APP_ENV-*"
            ]
        }
    ]
}
```

#### Build Engine Configuration ####
In the directory where you cloned ```appbuilder-buildengine-api```, copy local.env.dist to local.env and update the variables.
Replace SOME_KEY with your chosen environment string.

```EXPAND_S3_KEY``` and ```EXPAND_S3_SECRET``` should be set to the ```Access Key Id``` and ```Secret Access Key``` of the Build Engine User.
```AWS_ACCESS_KEY_ID``` and ```AWS_SECRET_ACCESS_KEY``` should be set to the ```Access Key Id``` and ```Secret Access Key``` of the Build Engine User.

```BUILD_ENGINE_GIT_SSH_USER``` should be set to the value of ~/.ssh/buildengine_api/ssh_key_id.txt
```APPBUILDER_GIT_SSH_USER``` should be set to the value of ~/.ssh/appbuilder/ssh_key_id.txt

```BUILD_ENGINE_REPO_URL``` should be set to the ssh url saved from creating the Build Engine repository.

If you are using vagrant deployment environment for ```docker-appbuilder-jenkins```, then use:

    BUILD_ENGINE_JENKINS_MASTER_URL=http://192.168.70.241:8080
 
If you are using docker-machine deployment environment for ```docker-appbuilder-jenkins```, then run ```docker-engine ip default```
to find out what IP address to use (most likely 192.168.99.100).

API_ACCESS_TOKEN should be some unique string to your environment.  When making HTTP requests to the API, set the following header:

    Authorization: Bearer your_api_token

#### Jenkins Configuration ####
In the directory where you cloned ```docker-appbuilder-jenkins```, copy local.env.dist to local.env and update the variables.
Replace SOME_KEY with your chosen environment string.

```EXPAND_S3_KEY``` and ```EXPAND_S3_SECRET``` should be set to the ```Access Key Id``` and ```Secret Access Key``` of the App Builder User.

```BUILD_ENGINE_REPO_URL```, ```BUILD_ENGINE_REPO_BRANCH```, and ```BUILD_ENGINE_GIT_SSH_USER``` will be the same values in each file.

```BUILD_EHGINE_JENKINS_MASTER_URL``` in ```docker-appbuilder-jenkins``` will match ```APPBUILDER_JENKINS_URL``` in ```appbuilder-buildengine-api```


[^1]: We use for the Git Repository since AWS only charges for $1/month/active-user.  For many projects, the user
requesting the project will be active for a short period of time and then not modify the project repository for a long time.
It would be possible to use another Git service (like [BitBucket](https://bitbucket.org)) to host the project repository, but will require to make a
code change in application/common/model/Job.php to allow other repository urls).
