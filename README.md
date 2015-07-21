# MoCompiler
Move files from local machine to server

###Required Options
		-c		Configuration file to use
		-s		Host name of server to compile to
		-p		Location of PPK file to connect to server
		-d		Remote Directory to upload project


###Optional Options
		-h		--help	Print this help message.
	
		-l				Local Directory to load project [Working Directory]
		
		--upload		Directories to move from local to remote

		--twig			Twig Template Directory
		--apigen		Documentation Configuration

		--compress		Compress static files
		--quiet			Silent mode

		--local-sass	Local SASS Directory
		--local-js		Local JS Directory
		--local-img		Local Image Directory

		--s3-key		Amazon S3 Access Key Activates compression
		--s3-secret		Amazon S3 Secret Key

		--remote-sass	Remote Directory to save sass on server or S3
		--remote-js		Remote Directory to save js on server or S3
		--remote-img	Remote Directory to save images on server or S3
		
		
####Composer Packages for Compression & Uploading
* tpyo/amazon-s3-php-class
<<<<<<< HEAD
* ps/image-optimizer
* apigen/apigen
=======
* tedivm/jshrink
* ps/image-optimizer
* apigen/apigen
* packagist/closure
>>>>>>> origin/master
