# yii2-template #
This is a template application to kickstart other applications

## Requirements ##
1. VirtualBox
2. Vagrant

## Setup ##
1. Clone this repo
2. Delete the .git/ folder
3. Edit Vagrantfile to specify whatever IP address you want, and adjust the sycned folder 
that gets mounted as /data if you need to
4. Copy ```local.env.dist``` to ```local.env```
5. Set an environment variable on your operating system named ```DOCKER_IMAGEDIR_PATH``` 
and point it to a folder where you want Docker to store images for reuse
6. Run ```vagrant up```