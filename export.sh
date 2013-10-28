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
    -s          Sync with output directory
    -c          Use current branch (do not switch to master)
EOF
}

## VARS INIT
OUTDIR=""
DOQUIET=""
TMPDIR="/tmp/myatu_export/"
SYNC=0
CURRENT_BRANCH=0

## CLI PARAMS
while getopts ":ho:qsc" option
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
    s)  # Sync to output
        SYNC=1
        ;;
    c)  # Current branch (do not switch to master)
        CURRENT_BRANCH=1
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

# Working directory is same as output directory at this stage
WORKDIR="${OUTDIR}"

# If the output directory does not exist, create it
if [ ! -d "${OUTDIR}" ]
then
    mkdir -p ${OUTDIR}
fi

# Sync prep
if [ ${SYNC} == 1 ]
then
    # Ensure we have a clean temp dir
    if [ -d "${TMPDIR}" ]
    then
        rm -rf ${TMPDIR}
    fi

    if [ ! -d "${TMPDIR}" ]
    then
        mkdir ${TMPDIR}
    fi

    # Change work dir
    WORKDIR="${TMPDIR}"
fi

# Start export
echo "Exporting GIT..."
if [ ${CURRENT_BRANCH} == 0 ]
then
    git checkout master ${DOQUIET}
fi
git checkout-index -a -f --prefix=${WORKDIR}/ ${DOQUIET}

# If there are submodules, check them out too
if [ -f "${WORKDIR}/.gitmodules" ]
then
    echo "Exporting Submodules..."

    GITSUBCMD='git checkout-index -a -f --prefix='${WORKDIR}'/$path/'
    git submodule foreach ${DOQUIET} --recursive ${GITSUBCMD}
fi

echo "Cleaning work directory"
# Remove files that are not needed in the export
if [ -f "${WORKDIR}/export.sh" ]; then rm "${WORKDIR}/export.sh"; fi
if [ -f "${WORKDIR}/.gitignore" ]; then rm "${WORKDIR}/.gitignore"; fi
if [ -f "${WORKDIR}/.gitmodules" ]; then rm "${WORKDIR}/.gitmodules"; fi
if [ -f "${WORKDIR}/README.md" ]; then rm "${WORKDIR}/README.md"; fi

# Remove directories that are not needed in the export
if [ -d "${WORKDIR}/vendor/Twig/doc" ]; then rm -r "${WORKDIR}/vendor/Twig/doc"; fi
if [ -d "${WORKDIR}/vendor/Twig/ext" ]; then rm -r "${WORKDIR}/vendor/Twig/ext"; fi
if [ -d "${WORKDIR}/vendor/Twig/test" ]; then rm -r "${WORKDIR}/vendor/Twig/test"; fi

if [ ${SYNC} == 1 ]
then
    echo "Syncing"
    rsync -aPrq --delete --exclude=.svn/ ${WORKDIR} ${OUTDIR}
    rm -rf ${WORKDIR}
fi

echo "Done"
