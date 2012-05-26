echo "setup_xdebug: Starting xdebug installation"
pecl install xdebug
echo "setup_xdebug: xdebug [should have been] downloaded, modifying config files"
sed -i '$ a\[xdebug]' /etc/php5/apache2/php.ini
sed -i '$ a\zend_extension=usr/lib/php5/20090626/xdebug.so' /etc/php5/apache2/php.ini
sed -i '$ a\xdebug.remote_host=10.10.10.1' /etc/php5/apache2/php.ini
sed -i '$ a\xdebug.remote_port=9999' /etc/php5/apache2/php.ini
sed -i '$ a\xdebug.remote_enable=1' /etc/php5/apache2/php.ini
/etc/init.d/apache2 restart
echo "setup_xdebug: should now be setup"
echo "setup_xdebug: this is not permanent, clearing out the VM will require a re-run of this script"