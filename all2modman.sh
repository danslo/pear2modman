for line in $(./mage list-available | tail -n +2 | grep -viE '^(lib|mage|interface|locale|test)' | sed -e 's/:[^:]*$//');
do
    if [ -n "$line" ];
    then
        ./pear2modman.sh $line
    fi
done
