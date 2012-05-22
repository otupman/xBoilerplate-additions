xBoilerplate-additions
======================

Additional components for xBoilerplate applications; these are "drag 'n drop" - you can take on of the many files
within the lib/CW directory and drop them into lib/CW in your xBoilerplate application.

Components include:
 - MySQL: a simplified way of accessing a MySQL database
 - Mail: a simpleified way of sending mail, with a few extra components thrown in

Getting Started - using the additions
----------------------------
You'll need an application deployed with xBoilerplate, so follow it's Getting Started and then come back here.

Back? Deployment of individual components is relatively painless

Getting Started - developing
----------------------------

 1. Fork this project
 2. Start up a working copy
 3. From the directory of your working copy, run command: vagrant up
 4. Once started, run command: vagrant ssh
 5. Within the VM, run command: cd /project
 6. To run the unit tests, run command: ant phpunit
