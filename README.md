# bill-memo-collator
Given a master PDF file with placeholder tags, generate a new PDF where all placeholder tags have been replaced with their corresponding PDF memos.

Background:

The Senate receives a bill summary PDF file from LBDC whenever it is requested by Senate Counsel.  The file contains a list of bills, along with detailed information about each bill, including an impact analysis and references to support and opposition memos.

The support and opposition memo references are specified using an XML placeholder tag, an example of which might be:
   `<EXTERNAL_BILL_MEMO year="2019" billno="S4462" stance="support" filename="S4462_support.pdf" />`
   
When the bill summary PDF file is processed by the bill memo collator, all of the XML placeholder tags are replaced with the corresponding PDF memo files, as referenced by the year and the filename attributes within the tag.  Since there is only one tag per page, the entire page is replaced by the page or pages of the memo file.  If the tag cannot be replaced, the placeholder tag page will be left untouched.

The script that performs the search-and-replace process depends on the following utilites being available on the host system:
* pdfgrep, v2.1.2
* pdfinfo, v0.45
* pdftk, v2.02
