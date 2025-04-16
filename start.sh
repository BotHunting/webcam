set -eu
if ! command -v php &> /dev/null
then
    echo "PHP could not be found, installing PHP..."
    sudo apt-get update
    sudo apt-get install php -y
fi

php -S 0.0.0.0:3000 -t public