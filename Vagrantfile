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
  config.vm.box = "ubuntu/trusty64"
  #config.vm.box = "AlbanMontaigu/boot2docker"
  # config.vm.box_version = "= 1.10.1"

  # The AlbanMontaigu/boot2docker box has not been set up as a Vagrant
  # 'base box', so it is necessary to specify how to SSH in.
  # config.ssh.username = "docker"
  # config.ssh.password = "tcuser"
  # config.ssh.insert_key = true


  # Create a forwarded port mapping which allows access to a specific port
  # within the machine from a port on the host machine. In the example below,
  # accessing "localhost:8080" will access port 80 on the guest machine.
  # config.vm.network "forwarded_port", guest: 80, host: 8080


  # Create a private network, which allows host-only access to the machine
  # using a specific IP.
  # config.vm.network "private_network", ip: "192.168.33.10"
  #config.vm.network "private_network", ip: "192.168.70.249", nic_type: "virtio"

  # These lines override a virtual NIC that the AlbanMontaigu/boot2docker box
  # creates by default. If you need to change the the box's IP address (which
  # is necessary to run separate, simulataneous instances of this Vagrantfile),
  # do it here.
  config.vm.provider "virtualbox" do |v, override|
    # Enable gui for troubleshooting with boot
    # v.gui = true
    # Create a private network for accessing VM without NAT
    override.vm.network "private_network", ip: "192.168.70.121" #, id: "default-network", nic_type: "virtio"
  end

  # Set memory to VM to 512M. boot2docker default is 1.5G
  # Limit CPU usage to up to 50% of host CPU
  config.vm.provider "virtualbox" do |v|
    #v.memory = 768
    #v.customize ["modifyvm", :id, "--cpuexecutioncap", "50"]
  end

  # Create a public network, which generally matched to bridged network.
  # Bridged networks make the machine appear as another physical device on
  # your network.
  # config.vm.network "public_network"


  # Share an additional folder to the guest VM. The first argument is
  # the path on the host to the actual folder. The second argument is
  # the path on the guest to mount the folder. And the optional third
  # argument is a set of non-required options.

  # Synced folders for container data.

  config.vm.synced_folder ".", "/vagrant",
   # 33 is the www-data user/group in the ubuntu container
   mount_options: ["uid=33","gid=33","fmode=755","dmode=755"]

  config.vm.provider "virtualbox" do |vb|
    # Customize the amount of memory on the VM:
    vb.memory = "1024"

    # A fix for speed issues with DNS resolution:
    # http://serverfault.com/questions/453185/vagrant-virtualbox-dns-10-0-2-3-not-working?rq=1
    vb.customize ["modifyvm", :id, "--natdnshostresolver1", "on"]

    # Set the timesync threshold to 59 seconds, instead of the default 20 minutes.
    # 59 seconds chosen to ensure SimpleSAML never gets too far out of date.
    vb.customize ["guestproperty", "set", :id, "/VirtualBox/GuestAdd/VBoxService/--timesync-set-threshold", 59000]
  end


  # This provisioner runs on the first `vagrant up`.
  config.vm.provision "install", type: "shell", inline: <<-SHELL
    # Add Docker apt repository
    sudo apt-key adv --keyserver hkp://p80.pool.sks-keyservers.net:80 --recv-keys 58118E89F3A912897C070ADBF76221572C52609D
    sudo sh -c 'echo deb https://apt.dockerproject.org/repo ubuntu-trusty main > /etc/apt/sources.list.d/docker.list'
    sudo apt-get update -y
    # Uninstall old lxc-docker
    apt-get purge lxc-docker
    apt-cache policy docker-engine
    # Install docker and dependencies
    sudo apt-get install -y linux-image-extra-$(uname -r)
    sudo apt-get install -y docker-engine
    # Add user vagrant to docker group
    sudo groupadd docker
    sudo usermod -aG docker vagrant
    # Install Docker Compose
    curl -sS -L https://github.com/docker/compose/releases/download/1.5.2/docker-compose-`uname -s`-`uname -m` > /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose

    echo 'export DOCKER_UIDGID=$(id -u):$(id -g)' >> /home/vagrant/.profile
    echo 'cd /vagrant'
  SHELL


  # This provisioner runs on every `vagrant reload' (as well as the first
  # `vagrant up`), reinstalling from local directories
  config.vm.provision "recompose", type: "shell",
   run: "always", inline: <<-SHELL
     set -x
     # Run docker-compose (which will update preloaded images, and
     # pulls any images not preloaded)
     cd /vagrant

     # Start services
     GID=`id -g`

     DOCKER_UIDGID="${UID}:${GID}" docker-compose up -d

  SHELL
end
