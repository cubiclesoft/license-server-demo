License Server Demo App
=======================

A functional [PHP App Server](https://github.com/cubiclesoft/php-app-server) and [Admin Pack](https://github.com/cubiclesoft/admin-pack) based application with [License Server](https://github.com/cubiclesoft/php-license-server) integration support.  Part of the [License Server demo](https://license-server-demo.cubiclesoft.com/).

This is intended for demonstration purposes only.  It is highly recommended to start with a fresh install of PHP App Server and Admin Pack when using those products and copy and paste relevant License Server integration code from this software for the new product.

[![Donate](https://cubiclesoft.com/res/donate-shield.png)](https://cubiclesoft.com/donate/) [![Discord](https://img.shields.io/discord/777282089980526602?label=chat&logo=discord)](https://cubiclesoft.com/product-support/github/)

Features
--------

* Functional app with License Server integration.
* Has a liberal open source license.  MIT or LGPL, your choice.  (NOTE:  The demo _binaries_ have a separate EULA.)
* Designed for relatively painless integration into your project.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

Building
--------

There are two helper files for building the application in the `proc` directory.  Running:

```
php proc\build_release.php
```

Will run Inno Setup, WiX, tar, etc. to prepare a release for publishing for Windows, Mac, and Linux in one go.  This requires Windows and you'll probably have to modify at least one path in that PHP file before it will successfully run.  It's generic enough though to be reusable for other projects.

Running:

```
php proc\publish.php
```

Will prepare the JSON file that is used by the Product Support Center to allow users to download files.  A file like this can be used as the basis of automating the entire publishing process - running scp/ssh commands to upload files, tagging a release in git, etc.

More Information
----------------

See the [License Server demo](https://license-server-demo.cubiclesoft.com/).
