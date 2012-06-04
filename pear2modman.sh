MAGE_PATH="./mage"
CONVERT_PATH="convert/$1"

# Check for command line arguments.
if test -z "$1"
then
    echo "Usage: $0 <connect key>"
    exit 0
fi

# Check for existence of mage file.
if [ ! -f $MAGE_PATH ]
then
    echo "Mage file was not found."
    exit 0
fi

# Delete temporary files.
rm -rf $CONVERT_PATH && mkdir -p $CONVERT_PATH

# Download and extract extension. 
echo "Downloading $1..."
PACKAGE_PATH=$(echo $($MAGE_PATH download community $1) | awk '{ print $3 }')

# Check for existence of downloaded package.
if [ "$PACKAGE_PATH" = "Package" ]
then
    echo "Could not find package - invalid key specified?"
    rm -rf $CONVERT_PATH
    exit 0
fi
echo "Extracting package to $CONVERT_PATH."
tar -xzf $PACKAGE_PATH -C $CONVERT_PATH

# Convert to modman. 
echo "Converting..."
php -f pear2modman.php $CONVERT_PATH

