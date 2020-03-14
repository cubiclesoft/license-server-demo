License Server Demo
===================

A complete Stripe + PHP License Server integration + product support center + demo app ready to adjust and deploy.  Get back to writing software in minutes.

Try it out here:  https://license-server-demo.cubiclesoft.com/

[![Donate](https://cubiclesoft.com/res/donate-shield.png)](https://cubiclesoft.com/donate/)

Features
--------

* Boilerplate, ready-made [website](https://license-server-demo.cubiclesoft.com/) + [Demo App](https://github.com/cubiclesoft/license-server-demo/tree/master/support/demo-app) with working [PHP-based Software License Server](https://github.com/cubiclesoft/php-license-server) integration.
* Simple configuration.  Takes just a few minutes on most VPS hosts running Nginx.
* Has a liberal open source license.  MIT or LGPL, your choice.  (NOTE:  The demo _binaries_ have a separate EULA.)
* Designed for relatively painless integration into your project.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

Getting Started
---------------

First, install the [PHP-based Software License Server](https://github.com/cubiclesoft/php-license-server) on a server and set up your first product and version.

Next, download or clone this repository and upload the contents of the `license-server-demo.cubiclesoft.com/public_html` directory to the same server.  Note that this software assumes it will be hosted at the root of a domain or subdomain.

Next, [create a Stripe account](https://stripe.com/).  Test keys work fine for testing purposes.

Edit `base.php` on the server and make relevant adjustments.

In the same directory as `base.php`, create a file called `secrets.php` and fill in the bits of required information:

```php
<?php
	// Stripe keys.
	$stripe_publickey = "pk_...";
	$stripe_secretkey = "sk_...";

	// Generate a secret key:  https://www.random.org/integers/?num=20&min=0&max=255&col=10&base=16&format=plain&rnd=new
	$buy_form_secretkey = "...";
```

Adjust the web server configuration to have unknown paths in the `/product-support/` path map to `/product-support/api.php` and let the server handle delivering content from `protected_html`.  Here are the expanded rules for [Nginx](https://nginx.org/) that `license-server-demo.cubiclesoft.com` uses:

```
server {
	listen 80;
	listen [::]:80;
	listen 443 ssl;
	listen [::]:443 ssl;
	server_name license-server-demo.cubiclesoft.com;
	root /var/www/license-server-demo.cubiclesoft.com/public_html;

	ssl_certificate	      /etc/letsencrypt/live/license-server-demo.cubiclesoft.com/fullchain.pem;
	ssl_certificate_key   /etc/letsencrypt/live/license-server-demo.cubiclesoft.com/privkey.pem;

	ssl_stapling on;
	ssl_stapling_verify on;

	location = /favicon.ico {
		log_not_found off;
		access_log off;
	}

	location = /robots.txt {
		allow all;
		log_not_found off;
		access_log off;
	}

	location ~ /\. {
		deny all;
		access_log off;
		log_not_found off;
	}

	location ^~ /.well-known/ {
		allow all;
	}


	# Map product support center API paths.
	location /product-support/ {
		try_files $uri $uri/ /product-support/api.php$is_args$args;
	}

	# Internal redirect to download files.
	location /protected/ {
		internal;
		alias /var/www/license-server-demo.cubiclesoft.com/protected_html/;
	}

	# Map 404 errors to 404.php.
	error_page 404 /404.php;

	location / {
		try_files $uri $uri/ =404;
	}


	# Pass .php files onto a php-fpm/php-fcgi server.
	location ~ \.php$ {
		try_files $uri =404;

		fastcgi_split_path_info ^(.+\.php)(/.+)$;
		include fastcgi_params;
		fastcgi_index index.php;
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		fastcgi_pass php;
	}
}
```

Similar configurations can be set up for Apache and other web servers (e.g IIS).

Finally, create a `downloads` directory structure similar to:

```
/var/www/yourwebsite.com/protected_html/downloads/v1/
```

Once ready, upload your binaries and an [info.json](https://github.com/cubiclesoft/license-server-demo/blob/master/license-server-demo.cubiclesoft.com/protected_html/downloads/v1/info.json) file containing information about the binaries.  The [Demo App](https://github.com/cubiclesoft/license-server-demo/tree/master/support/demo-app) has a tool in `proc/publish.php` that generates an appropriate JSON file for [PHP App Server](https://github.com/cubiclesoft/php-app-server) based applications.

Note that this is just one possible implementation of an integration between a purchasing system and software licensing management.  The aim of this project is to be as simple as possible so that the software can be readily customized while also supporting the most important aspects of the purchasing lifecycle that both users and accountants expect.  As a software developer, you probably just want to get back to working on your software product.

More Information
----------------

* [PHP-based Software License Server](https://github.com/cubiclesoft/php-license-server) - High performance software licensing server.
* [PHP App Server](https://github.com/cubiclesoft/php-app-server) - Build native applications for Windows, Mac OSX, and Linux with PHP, HTML, CSS, and Javascript.
