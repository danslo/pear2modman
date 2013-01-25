MAGE_PATH="./mage"

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

# Strip connect 1.0 prefix.
PACKAGE_NAME=$(echo $1 | sed -e 's/magento-community\///')
CONVERT_PATH="convert/$PACKAGE_NAME"

# Delete temporary files.
echo "Deleting temporary files..."
rm -rf $CONVERT_PATH && mkdir -p $CONVERT_PATH

# Download and extract extension. 
echo "Downloading $PACKAGE_NAME..."
PACKAGE_PATH=$(echo $($MAGE_PATH download community $PACKAGE_NAME) | awk '{ print $3 }')

# Check for existence of downloaded package.
if [ "$PACKAGE_PATH" = "Package" ]
then
    echo "Could not find package - invalid key specified?"
    rm -rf $CONVERT_PATH
    exit 0
# Check if we were even initialized.
elif [ "$PACKAGE_PATH" = "Channel" ]
then
    echo "Could not find community channel, initializing mage and restarting."
    $MAGE_PATH mage-setup
    ./$0 $PACKAGE_NAME
    exit 0
fi

# Extract package.
echo "Extracting $PACKAGE_PATH to $CONVERT_PATH."
tar -xzf $PACKAGE_PATH -C $CONVERT_PATH

# Convert to modman. 
echo "Converting..."
php -f pear2modman.php $CONVERT_PATH
echo ""
