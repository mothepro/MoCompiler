# Mo's Web App Compiler
Move files from local machine to server

Neon file can hold options which uses long name of command line arg
The dashes are sub keys.

For example
The command line option ```--project-remote /var/www``` is the same at the neon config option
```
project:
	remote: /var/www
```

###Required Options
	-c						Configuration file to use
	-s	--host				Host name of server to compile to
	-p	--ppk				Location of PPK file to connect to server
	-d	--project-remote	Remote Directory to upload project


###Optional Options
	-h	--help				Print this help message.
	-l	--project-local		Local Directory to load project [Working Directory]
	
		--upload			Directories to move from local to remote
		--twig				Twig Template Directory
		--apigen			Documentation Configuration
		--compress			Compress static files
		--quiet				Silent mode
		--local-sass		Local SASS Directory
		--local-js			Local JS Directory
		--local-img			Local Image Directory
		--s3-key			Amazon S3 Access Key Activates compression
		--s3-secret			Amazon S3 Secret Key
		--remote-sass		Remote Directory to save sass on server or S3
		--remote-js			Remote Directory to save js on server or S3
		--remote-img		Remote Directory to save images on server or S3
		
####Composer Packages for Compression & Uploading
* tpyo/amazon-s3-php-class
* apigen/apigen
* nette/neon
