crv_video_crawler
=================

A Symfony project created on June 16, 2017, 12:38 pm.

This repository has only learning purposes.
I have been learning Symfony 3 on https://codereviewvideos.com
It's a great resource with subscription cost only  24.97$.
I'm highly recommend https://codereviewvideos.com for all who wish to learn Symfony.

So, when I decided to learn how to crap some web resource I have used github.com and 
https://codereviewvideos.com as examples, just because I was using them in the moment.

I don't recommend to use this repository code in other purposes except learning.

Requirements
============

1. Redis (for requests cache)
2. sqlight
3. composer

Commands
========

Load links in db starting from page 4 and without download after.
```
bin/console crv:crawl -u seyfer -p <some passw> --from-page 4 --not-download 1
```

Start from some ID if download was interrupted without updating links
and with overwrite.
```
bin/console crv:crawl --not-update-db 1 --overwrite 1 --from-id 253
```
