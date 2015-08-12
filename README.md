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
	--host				Host name of server to compile to
	--ppk				Location of PPK file to connect to server
	--project-remote	Remote Directory to upload project


###Optional Options
	--project-local		Local Directory to load project [Working Directory]
	--project-files		Directories to move from local to remote

	--twig				Twig Template Directory
	--apigen			Documentation Configuration

	--compress			Compress static files
	--verbose			Level to output
						0 = Silent
						1 = Name of Job
						2 = Duration of Job
						3 = Commands run in command line

	--upload-sass		Local SASS Directory
	--upload-js			Local JS Directory
	--upload-img		Local Image Directory
	--upload-*			Local Static Directory

	--s3-key			Amazon S3 Access Key Activates compression
	--s3-secret			Amazon S3 Secret Key

	--download-sass		Remote Directory to save sass on server or S3
	--download-js		Remote Directory to save js on server or S3
	--download-img		Remote Directory to save images on server or 
	--download-*		Remote Static Directory to match --local-*

	--hooks-post		Remote Commands to run after uploading

	--constants			List of constants used throughout app
	--constantsOutput	Where to save new constants

Options are useful if you want to hide some config settings to your build process.
	```--s3-secret ImNotGoingOnTheVCS```