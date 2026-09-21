#!/bin/sh

# Télécharge le binaire cloudflared (https://github.com/cloudflare/cloudflared)
# depuis ses propres releases GitHub - même schéma que installGo2rtc.sh.

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

URL="https://github.com/cloudflare/cloudflared/releases/download/$TAG/$FILENAME"

echo "*************************************"
echo "*       Install cloudflared         *"
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
