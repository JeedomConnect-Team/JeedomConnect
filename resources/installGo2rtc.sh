#!/bin/sh

# Télécharge le binaire go2rtc (https://github.com/AlexxIT/go2rtc) depuis ses
# propres releases GitHub (pas celles de JeedomConnect) - même schéma que
# installNotifBin.sh.

if [ "$#" -ne 3 ]; then
    echo "Usage: $0 <tag> <filename> <destination>"
    exit 1
fi

TAG=$1
echo "TAG=$1"
FILENAME=$2
echo "FILENAME=$2"
DESTINATION=$3
echo "DESTINATION=$3"

URL="https://github.com/AlexxIT/go2rtc/releases/download/$TAG/$FILENAME"

echo "*************************************"
echo "*         Install go2rtc           *"
echo "*************************************"

wget --spider "$URL" 2>/dev/null

if [ $? -eq 0 ]; then
    wget -O "$DESTINATION" "$URL"
    chmod +x "$DESTINATION"
    echo "Le fichier a été téléchargé dans $DESTINATION"
else
    echo "Le fichier demandé n'existe pas."
    echo "  --->> $URL"
    exit 1
fi

echo "***************************"
echo "*      Install ended     *"
echo "***************************"
