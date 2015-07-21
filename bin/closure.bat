@ECHO ON
SET CLOSURE_JAR=%~dp0/closure/compiler.jar
java -jar "%CLOSURE_JAR%" %*
