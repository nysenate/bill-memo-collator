#!/bin/sh
#
# collate_bill_memos.sh - Given an input PDF with XML placeholder tags,
#   transform the PDF by replacing the pages containing XML tags with
#   the support/opposition memo PDFs that those tags reference.
#
# Author: Ken Zalewski
# Organization: New York State Senate
# Date: 2019-03-01
# Revised: 2019-03-06
# Revised: 2020-07-22 - Validate MemoPDF files using pdfinfo before copying
#

prog=`basename $0`
memodir="/minjdriv/MEMOS ON FILE/Memo PDFs/2019 - 20 Memos on File"
tmpdir="/tmp/collate_bill_memos.tmp.d"
keep_tmpdir=0
srcfile=
outfile=

if [ "$OSTYPE" = "cygwin" ]; then
  tmpdir="C:/cygwin$tmpdir"
  memodir="Z:$memodir"
else
  memodir="/mnt$memodir"
fi

set -o pipefail

usage() {
  echo "Usage: $prog [--dir memodir] [--keep-tmpdir] [--tmpdir tempdir] [--output-file collated_file.pdf] lbdc_source_file.pdf" >&2
}

cleanup() {
  rm -f doc_data.txt
  [ $keep_tmpdir -eq 0 -a -d "$tmpdir" ] && rm -rf "$tmpdir"
}

cleanup_and_exit() {
  cleanup
  [ "$1" ] && exit $1 || exit -2
}


if [ $# -lt 1 ]; then
  usage
  exit 1
fi

while [ $# -gt 0 ]; do
  case "$1" in
    -d|--dir) shift; memodir="$1" ;;
    -h|--help) usage; exit 0 ;;
    -k|--keep*) keep_tmpdir=1 ;;
    -o|--out*) shift; outfile="$1" ;;
    -t|--tmp*) shift; tmpdir="$1" ;;
    -*) echo "$prog: $1: Unknown option" >&2; usage; exit 1 ;;
    *) srcfile="$1" ;;
  esac
  shift
done

if [ ! "$srcfile" ]; then
  echo "$prog: LBDC source file must be specified" >&2
  exit 1
elif [ ! -r "$srcfile" ]; then
  echo "$prog: $srcfile: LBDC source file not found" >&2
  exit 1
elif [ ! -d "$memodir" ]; then
  echo "$prog: $memodir: PDF memo directory not found" >&2
  exit 1
fi

# If no output file was specified, generate one based on the source filename.
if [ ! "$outfile" ]; then
  outfile=`basename "$srcfile" .pdf`.collated.pdf
fi

# Confirm that "pdfgrep", "pdfinfo", and "pdftk" are all available.
if ! type -p pdfgrep pdfinfo pdftk >/dev/null; then
  echo "$prog: "pdfgrep", "pdfinfo", and "pdftk" must all be available" >&2
  exit 1
fi

# Trap various signals to abort cleanly.
trap cleanup_and_exit 1 2 3 6 15

# Create the temporary work directory
mkdir -p "$tmpdir" || exit 1

# Split the source PDF document into multiple single-page documents.
echo "Splitting [$srcfile] into individual single-page PDF files"
pdftk "$srcfile" burst output "$tmpdir/page_%03d.pdf"

if [ $? -ne 0 ]; then
  echo "$prog: Unable to split source PDF [$srcfile] into single-page PDFs" >&2
  cleanup_and_exit -1
fi

# Iterate over the individual pages looking for LBDC placeholder tags.
# Whenever a placeholder tag is encountered, attempt to replace the entire
# page with the PDF memo that is referenced by the tag.

page_count=0
memo_count=0
err_count=0

for f in "$tmpdir"/page_???.pdf; do
  echo "Processing file [$f]"
  fname=`basename $f`
  let page_count++
  if pdfgrep '<(LBDC_PARM|EXTERNAL_BILL_MEMO) [^>]*/>' "$f" | tr '\n' ' ' > "$tmpdir/curtag.xml"; then
    echo "Found LBDC placeholder tag in [$fname]:"
    cat "$tmpdir/curtag.xml"; echo
    let memo_count++
    memofname=`sed -nr 's;.* (value|filename)="([^"]*)".*;\2;p' "$tmpdir/curtag.xml"`
    if [ "$memofname" ]; then
      echo "Attempting to replace [$fname] with memo file [$memofname]"
      memofile="$memodir/$memofname"
      # Verify that memo file is found and is readable.
      if [ -f "$memofile" -a -r "$memofile" ]; then
        # Verify that memo file is a valid PDF file.
        if pdfinfo "$memofile" >/dev/null; then
          cp -v "$memofile" "$f"
        else
          echo "$prog: File [$memofile] is not a valid PDF file; [$fname] will not be replaced" >&2
        fi
      else
        echo "$prog: Unable to find memo file [$memofile]; [$fname] will not be replaced" >&2
        let err_count++
      fi
    else
      echo "$prog: Unable to parse memo filename from LBDC placeholder tag" >&2
      let err_count++
    fi
  fi
done

if [ $err_count -gt 0 ]; then
  echo "$prog: WARNING: $err_count page(s) could not be replaced" >&2
fi

# Reassemble all of the individual PDF files into a single PDF file.
echo "Reassembling the individual PDF files into a single PDF output file"
pdftk "$tmpdir"/page_???.pdf cat output "$outfile"

if [ $? -eq 0 ]; then
  echo "Generated output file [$outfile]"
else
  echo "$prog: $outfile: Unable to generate output file" >&2
  cleanup_and_exit -1
fi

echo "Document summary:"
echo "Page count:  $page_count"
echo "Memo count:  $memo_count"
echo "Error count: $err_count"

cleanup_and_exit $err_count
