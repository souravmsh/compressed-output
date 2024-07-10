# Compressed Output
*A Laravel package for compressing the output of the application code*

## USER MANUAL 
**STEPS:-**
1. Create a `packages/souravmsh/` directory at the root of the Laravel application.
```json
	mkdir packages
	cd packages
	mkdir souravmsh
```
2. Clone the Repository from github.
```json
git clone https://github.com/souravmsh/compressed-output.git
```
3. Add package repositories to the application-root `composer.json` file

```json
    "repositories": {
        "local": {
            "type": "path",
            "url": "./packages/souravmsh/compressed-output",
            "options": {
                "symlink": true
            }
        }
    }
```

4. install package via comopser
```json
composer require souravmsh/compressed-output:dev-main
```
or delete the ```composer.lock``` file and run
```json
composer install
```
5. It will automatically add the package to your application
6. To enable/disable the package, add the below variable to the .env file

```json
COMPRESSED_OUTPUT_EANBLE=true/false
```
