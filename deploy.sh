#! /bin/bash
# Original by Dean Clatworthy: https://github.com/deanc/wordpress-plugin-git-svn
# Modified by Brent Shepherd: https://github.com/thenbrent/multisite-user-management
# Final by Mike Green <myatus@gmail.com>

## MAIN VARS
PLUGINSLUG="background-manager"
MAINFILE="background-manager.php"


## FUNCS
function showHelp()
{
cat <<EOF
Usage: `basename $0` [options]

Options:
    -h          Usage message
    -g          Commit changes to GIT and tag version
    -u ARG      SVN Username
    -p ARG      SVN Password
    -c ARG      Commit message
    -i          Ignore errors
EOF
}

function isDone()
{
    RESULT=$?

    if [ $RESULT == 0 ]
    then
        echo "DONE"
    elif [ $IGNERR == 0 ]
    then
        popd > /dev/null 2>&1
        exit $RESULT
    fi
}


## VARS INIT
SVNPATH="/tmp/${PLUGINSLUG}"
SVNURL="https://plugins.svn.wordpress.org/${PLUGINSLUG}/"
CURRENTDIR=`pwd`
GITPATH="${CURRENTDIR}/"
SVNUSER=""
SVNPWD=""
COMMITMSG=""
IGNERR=0
COMMITGIT=0

## CLI PARAMS
while getopts ":hu:p:c:ig" option
do
    case ${option} in
    h)  showHelp
        exit
        ;;
    g)  # Commit to GIT
        COMMITGIT=1
        ;;
    c)  # Commit Message
        COMMITMSG=${OPTARG}
        ;;
    u)  # SVN Username
        SVNUSER=${OPTARG}
        ;;
    p)	# SVN Password
        SVNPWD=${OPTARG}
        ;;
    i)  # Ignore Errors
        IGNERR=1
        ;;
    *)	;;
    esac
done

## MAIN ##

# Check for SVN username
if [ -z "${SVNUSER}" ]
then
	echo "ERROR: No SVN username specified."
	exit 2
fi

# Check version in readme.txt is the same as plugin file
NEWVERSION1=`grep "^Stable tag" ${GITPATH}/readme.txt | awk '{sub(/\r$/,""); print $NF}'`
NEWVERSION2=`grep "^Version" ${GITPATH}/${MAINFILE} | awk '{sub(/\r$/,""); print $NF}'`

if [ "$NEWVERSION1" != "$NEWVERSION2" ]
then
    echo "ERROR: Stable version '${NEWVERSION1}' specified, but plugin has version '${NEWVERSION2}'"
    exit 1
fi

echo -e "Preparing '${PLUGINSLUG}' version ${NEWVERSION1}\n"

pushd $GITPATH > /dev/null

while [ -z "${COMMITMSG}" ]
do
    echo -e "Commit message: \c"
    read COMMITMSG

    echo
done

if [ $COMMITGIT == 1 ]
then
    echo -e "Commiting to GIT... \c"
    git commit -am "$COMMITMSG"
    git tag -a "v${NEWVERSION1}" -m "Tagging version ${NEWVERSION1}"
    isDone

    echo -e "Pushing to GIT master branch... \c"
    git push origin master
    git push origin master --tags
    isDone
fi

echo -e "Creating local copy of SVN... \c"
#svn co -q ${SVNURL} ${SVNPATH}
isDone

echo -e "Exporting GIT to SVN... \c"
git checkout master --quiet
git checkout-index -a -f --prefix=${SVNPATH}/trunk/ --quiet
isDone

if [ -f "${SVNPATH}/trunk/.gitmodules" ]
then
    echo -e "Exporting Submodules... \c"

    GITSUBCMD='git checkout-index -a -f --prefix='${SVNPATH}'/trunk/$path/'
    git submodule foreach --quiet --recursive ${GITSUBCMD}
    isDone
fi

echo -e "Setting SVN Ignore properties... \c"
svn propset -q svn:ignore "deploy.sh
README.md
.gitmodules
.git
.git*
.gitignore" "$SVNPATH/trunk/"
isDone

# Go to SVN directory
popd > /dev/null
pushd $SVNPATH/ > /dev/null

echo -e "Adding new files to SVN... \c"
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{ printf "\"%s\"\n", substr($0,match($0,$2)) }' | xargs svn add 2> /dev/null
echo "DONE"

echo -e "Commiting changes to SVN... \c"
if [ ! -z "${SVNPWD}" ]; then SVNPWDOPT="--password '${SVNPWD}'"; fi;
svn commit --username '${SVNUSER}' ${SVNPWDOPT} -m '${COMMITMSG}'
isDone

echo -e "Creating new SVN tag... \c"
svn cp -q trunk/ tags/$NEWVERSION1/
isDone

svn commit --username '${SVNUSER}' ${SVNPWDOPT} -m 'Tagging version ${NEWVERSION1}'

popd > /dev/null

rm -fr $SVNPATH/

echo "Done!"


