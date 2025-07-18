#!/bin/sh

# Vérifiez que trois arguments sont passés au script
if [ "$#" -ne 3 ]; then
    echo "Usage: $0 <tag> <filename> <repo>"
    echo 100 > ${PROGRESS_FILE}
    exit 1
fi

echo "*************************************"
echo "*    Install notification module    *"
echo "*************************************"

# Récupérer les arguments
TAG=$1
echo "TAG=$1"
FILENAME=$2
echo "FILENAME=$2"
DESTINATION_DIR=$3
echo "DESTINATION_DIR=$3"

URL="https://github.com/JeedomConnect-Team/JeedomConnect/releases/download/$TAG/$FILENAME"

wget --spider $URL 2>/dev/null

# Vérifier le code de retour de wget
if [ $? -eq 0 ]; then
    echo ""
    echo ""
    # Si le fichier existe, le télécharger et le mettre dans le répertoire de destination
    wget -O $DESTINATION_DIR $URL
    echo "Le fichier a été téléchargé dans $DESTINATION_DIR"
    chmod +x $DESTINATION_DIR
else
    echo ""
    echo ""
    # Si le fichier n'existe pas, afficher un message d'erreur
    echo "Le fichier demandé n'existe pas."
    echo "  --->> $URL"
fi

echo "***************************"
echo "*      Install ended      *"
echo "***************************"