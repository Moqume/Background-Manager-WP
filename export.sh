#! /bin/bash

## FUNCS
function showHelp()
{
cat <<EOF
Usage: `basename $0` [options]

Options:
    -h          Usage message
    -o ARG      Output directory
    -q          Quiet output
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
OUTDIR=""
DOQUIET=""

## CLI PARAMS
while getopts ":ho:q" option
do
    case ${option} in
    h)  showHelp
        exit
        ;;
    o)  # Output directory
        OUTDIR=${OPTARG}
        ;;
    q)  # Quiet?
        DOQUIET="--quiet"
        ;;
    *)	;;
    esac
done

# Make sure an output directoy was specified
if [ -z "${OUTDIR}" ]
then
	echo "ERROR: No output directory specified."
	exit 2
fi

# If the output directory does not exist, create it
if [ -d "${OUTDIR}" ]
then
    mkdir ${OUTDIR}
fi

echo "Exporting GIT to SVN..."
git checkout master ${DOQUIET}
git checkout-index -a -f --prefix=${OUTDIR}/ ${DOQUIET}

# If there are submodules, check them out too
if [ -f "${OUTDIR}/.gitmodules" ]
then
    echo "Exporting Submodules..."

    GITSUBCMD='git checkout-index -a -f --prefix='${OUTDIR}'/$path/'
    git submodule foreach ${DOQUIET} --recursive ${GITSUBCMD}
fi

echo "Cleaning output directory"
# Remove files that are not needed in the export
if [ -f "${OUTDIR}/export.sh" ]; then rm "${OUTDIR}/export.sh"; fi
if [ -f "${OUTDIR}/.gitignore" ]; then rm "${OUTDIR}/.gitignore"; fi
if [ -f "${OUTDIR}/.gitmodules" ]; then rm "${OUTDIR}/.gitmodules"; fi
if [ -f "${OUTDIR}/README.md" ]; then rm "${OUTDIR}/README.md"; fi

# Remove directories that are not needed in the export
if [ -d "${OUTDIR}/vendor/Twig/doc" ]; then rm -r "${OUTDIR}/vendor/Twig/doc"; fi
if [ -d "${OUTDIR}/vendor/Twig/ext" ]; then rm -r "${OUTDIR}/vendor/Twig/ext"; fi
if [ -d "${OUTDIR}/vendor/Twig/test" ]; then rm -r "${OUTDIR}/vendor/Twig/test"; fi

echo "Done"
