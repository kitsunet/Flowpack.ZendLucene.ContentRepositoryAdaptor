Flowpack.ZendLucene.ContentRepositoryAdaptor
============================================

Installation
------------
Please make sure you checked the Readme on
https://github.com/kitsunet/Flowpack.ZendLucene
about installation because it is a requirement for this.

Usage
-----
Currently this package is rather experimental. It CAN already create a searchable
index of all your nodes with the defaul configuration.

See the nodeindex:* commands on CLI for what you can do.

generally a:

  ./flow nodeindex:build

will generate the full index. After repeated builds you might want to run:

  ./flow nodeindex:optmize

Finally to search the index you can use

  ./flow nodeindex:find

Currently there is no Helper yet to access the index in Neos, but that will follow next.
Also I didn't find good settings for tokenizing some fields yet. So some things are not optimal yet.
