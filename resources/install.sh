#!/bin/bash
echo 0 > /tmp/hdmiCec_dep
# https://www.raspberrypi.org/forums/viewtopic.php?f=29&t=117019
COLOUR='\033[0;31m' # Red
NC='\033[0m' # No Color

#echo
#echo -e ${COLOUR}"Running libcec installation script${NC}"
#echo
echo "********************************************************"
echo "*             Installation des dépendances             *"
echo "********************************************************"
sleep 1
#sudo apt-get update -y -q
#sudo apt-get install libraspberrypi-dev 
#sudo apt-get install cmake liblockdev1-dev libudev-dev libxrandr-dev python-dev swig
#python-regex is reqired because internal regular expression do no not work (trap)
#sudo apt-get install python-regex
touch /tmp/hdmiCec_dep
echo "Début de l'installation"
echo 10 > /tmp/hdmiCec_dep
sleep 2
echo 20 > /tmp/hdmiCec_dep
sleep 2
echo 30 > /tmp/hdmiCec_dep
sleep 2
echo 40 > /tmp/hdmiCec_dep
sleep 2
#${1} = répertoire courant en principe resources
echo 50 > /tmp/hdmiCec_dep
sleep 2
echo 60 > /tmp/hdmiCec_dep
sleep 2
echo 70 > /tmp/hdmiCec_dep
sleep 2
echo 80 > /tmp/hdmiCec_dep
sleep 2

echo 90 > /tmp/hdmiCec_dep
sleep 2
cd $1
mkdir cecHdmi
chmod a+wx cecHdmi
echo 100 > /tmp/hdmiCec_dep
sleep 1
rm /tmp/hdmiCec_dep
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"