# -*- mode: ruby -*-
# vi: set ft=ruby :
# Copyright (c) 2015 SIL International
#
#   Permission is hereby granted, free of charge, to any person obtaining a copy
#   of this software and associated documentation files (the "Software"), to deal
#   in the Software without restriction, including without limitation the rights
#   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
#   copies of the Software, and to permit persons to whom the Software is
#   furnished to do so, subject to the following conditions:
#
#   The above copyright notice and this permission notice shall be included in
#   all copies or substantial portions of the Software.
#
#   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
#   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
#   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
#   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
#   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
#   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
#   THE SOFTWARE.


# All Vagrant configuration is done below. The "2" in Vagrant.configure
# configures the configuration version (we support older styles for
# backwards compatibility). Please don't change it unless you know what
# you're doing.
Vagrant.configure(2) do |config|
  # The most common configuration options are documented and commented below.
  # For a complete reference, please see the online documentation at
  # https://docs.vagrantup.com.

  # Every Vagrant development environment requires a box. You can search for
  # boxes at https://atlas.hashicorp.com/search.
  # config.vm.box = "ubuntu/trust64"
  config.vm.box = "AlbanMontaigu/boot2docker"
  config.vm.box_version = "= 1.8.1"

  # The AlbanMontaigu/boot2docker box has not been set up as a Vagrant
  # 'base box', so it is necessary to specify how to SSH in.
  config.ssh.username = "docker"
  config.ssh.password = "tcuser"
  config.ssh.insert_key = true

  # Create a private network, which allows host-only access to the machine
  # using a specific IP.
  # config.vm.network "private_network", ip: "192.168.33.10"
  #config.vm.network "private_network", ip: "192.168.70.21"

  # These lines override a virtual NIC that the AlbanMontaigu/boot2docker box
  # creates by default. If you need to change the the box's IP address (which
  # is necessary to run separate, simulataneous instances of this Vagrantfile),
  # do it here.
  config.vm.provider "virtualbox" do |v, override|
    # Enable gui for troubleshooting with boot
    # v.gui = true
    # Create a private network for accessing VM without NAT
    override.vm.network "private_network", ip: "192.168.70.21", id: "default-network", nic_type: "virtio"
  end

  # Share an additional folder to the guest VM. The first argument is
  # the path on the host to the actual folder. The second argument is
  # the path on the guest to mount the folder. And the optional third
  # argument is a set of non-required options.

  # Synced folders for container data.

  config.vm.synced_folder "./application", "/data",
   # 33 is the www-data user/group in the ubuntu container
   mount_options: ["uid=33","gid=33"] 

  # Note:
  #   By default Vagrant syncs the project directory to /vagrant. This
  #   Vagrantfile assumes this behavior, and also assumes that the
  #   docker-compose.yml will then be located at /vagrant/docker-compose.yml
  if ENV['DOCKER_IMAGEDIR_PATH']
    config.vm.synced_folder ENV['DOCKER_IMAGEDIR_PATH'], "/preload-images"
  else
    # The escape sequences are for colored output
    sred="\033[31m"
    sgreen="\033[32m"
    snorm="\033[0m"

    puts sred + "Set " + sgreen + "DOCKER_IMAGEDIR_PATH " + sred +
         "in your environment to import local tar archives\ncontaining " +
         "images into docker's image-store" + snorm
  end
  
  # A fix for speed issues with DNS resolution:
  #   http://serverfault.com/questions/453185/vagrant-virtualbox-dns-10-0-2-3-not-working?rq=1
  config.vm.provider "virtualbox" do |vb|
    vb.customize ["modifyvm", :id, "--natdnshostresolver1", "on"]
  end


  # This provisioner runs on the first `vagrant up`.
  config.vm.provision "install", type: "shell", inline: <<-SHELL
     # Copy the home directory to persistent storage
     mkdir /mnt/sda2/home
     cp -r /home/docker /mnt/sda2/home
     chown -R docker.staff /mnt/sda2/home/docker

     #Switcheroo 
     mount --bind /mnt/sda2/home/docker /home/docker
     cd /home/docker

     # Download Python and Pip
     mkdir /mnt/sda2/tce-persist
     chown docker.staff /mnt/sda2/tce-persist
     chmod 775 /mnt/sda2/tce-persist

     mount --bind /mnt/sda2/tce-persist /mnt/sda2/tmp/tce/optional
     sudo -u tc tce-load -w python
     umount /mnt/sda2/tmp/tce/optional

     mkdir /mnt/sda2/pip
     cd /mnt/sda2/pip

     curl https://bootstrap.pypa.io/get-pip.py > get-pip.py
     chmod u+x get-pip.py

     # Configure boot2docker's running of docker
     # (Mixing of tabs and spaces is intentional, used for the <<- operator)
     cat <<-EOF >> /var/lib/boot2docker/profile
	EXTRA_ARGS="-icc=false"
	DOCKER_TLS="no"

	EOF

     /etc/init.d/docker stop
     iptables -F
     /etc/init.d/docker start

     # Convenience
     if mount | grep /vagrant 1>/dev/null; then
       ln -s /vagrant /home/docker/vagrant
     fi
   SHELL


  # This provisioner runs on every `vagrant reload' (as well as the first
  # `vagrant up`), reinstalling from local directories
  config.vm.provision "recompose", type: "shell",
   run: "always", inline: <<-SHELL
     #Switcheroo 
     mount --bind /mnt/sda2/home/docker /home/docker
     cd /home/docker

     # Install Docker-Compose
     curl -L https://github.com/docker/compose/releases/download/1.4.0/docker-compose-`uname -s`-`uname -m` > /usr/local/bin/docker-compose
     chmod 755 /usr/local/bin/docker-compose

     # Preload docker with images (but only if the synced folder is mounted)
     if mount | grep /preload-images 1>/dev/null; then
       echo "Preloading the docker-daemon with images"
       cd /preload-images

       allready_images=$(docker images -q)

       for file in $(ls | egrep '.tar$' ); do
         name=$( basename $file .tar )

         # Scan through the images we have and check if they are already loaded
         for i in $allready_images; do 
           if [[ $i == $name ]]; then
             # Skip this file
             echo "--> Skipping $name.tar (already loaded)"
             continue 2
           fi
         done

         echo "--> Loading $name.tar"
         docker load < $file
       done
     fi

     # Run docker-compose (which will update preloaded images, and
     # pulls any images not preloaded)
     cd /vagrant
     docker-compose up -d

     # Update the preload image directory with any new (or changed) images
     if mount | grep /preload-images 1>/dev/null; then
       echo "Adding images in /preload-images."
       cd /preload-images

       # First, construct a space-separated list of the images IDs that are
       # running as a result of docker-compose
       running=$(docker ps -q )
       runids=""

       for r in $running; do
         newid=$(docker inspect --format='{{.Image}}' $r)

         # The id must be truncated to the standard 12 characters
         runids="$runids "$(echo $newid | egrep -o '^[0-9a-f]{12}')
       done
       
       for i in $(docker images -q); do
         if ! [[ -f $i.tar ]]; then
           echo "--> Saving $i.tar"
           docker save -o $i.tar $i
         else
           # Update the timestamps on the ones which have already been
           # exported but which still used by the docker-compose.yml
           for j in $runids; do
             if [[ $j == $i ]]; then
               echo "--> skipping $i.tar " \
                 "(already exists: in-use, timestamp updated)"
               touch $i.tar
               continue 2
             fi
           done

           echo "--> skipping $i.tar (already exists: unused)"
         fi
       done
     fi

     # Propose possible ENV variable exports
     cat <<-EOF > /docker-connect.sh
  #!/bin/sh
  echo " "
  echo "To connect to docker running on the Vagrant Box,"
  echo "set your DOCKER_HOST ENV variable, one of:"
  echo " "

  regex='[0-9]\\{,3\\}\\.[0-9]\\{,3\\}\\.[0-9]\\{,3\\}\\.[0-9]\\{,3\\}'

  ip -4 -o a | grep eth | sed -e "
    /\\$regex/ {
      s/\\$regex/\\n&/
      s/.*\\n//
      s/\\$regex/&\\n/
      s/\\n.*//

      s%.*%  export DOCKER_HOST=tcp://&:2375%
      p
    }

    # If no match, delete the line
          d
  "
  EOF
     chmod u+x /docker-connect.sh

     /docker-connect.sh

     echo " "
     echo "Or, just use 'vagrant ssh' to connect, and run commands"
     echo "from inside the box, e.g.:"
     echo " "
     echo "  vagrant ssh"
     echo "  cd /vagrant"
     echo "  docker-compose ps"
     echo "  docker images"

     # Finally, and importantly, stop all running dhcp clients; this box is
     # statically configured by Vagrant, and asking for dhcp will just
     # override the network settings in this Vagrantfile.
     killall udhcpc
  SHELL
end
