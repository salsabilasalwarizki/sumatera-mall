PS C:\laragon\www\sumateramall-app> composer require guzzlehttp/guzzle barryvdh/laravel-dompdf
./composer.json has been updated
Running composer update guzzlehttp/guzzle barryvdh/laravel-dompdf
Loading composer repositories with package information
Updating dependencies
Lock file operations: 6 installs, 0 updates, 0 removals
  - Locking barryvdh/laravel-dompdf (v3.1.2)
  - Locking dompdf/dompdf (v3.1.6)
  - Locking dompdf/php-font-lib (1.0.2)
  - Locking dompdf/php-svg-lib (1.0.2)
  - Locking masterminds/html5 (2.12.0)
  - Locking sabberworm/php-css-parser (v9.5.0)
Writing lock file
Installing dependencies from lock file (including require-dev)
Package operations: 6 installs, 0 updates, 0 removals
  - Downloading masterminds/html5 (2.12.0)
  - Downloading sabberworm/php-css-parser (v9.5.0)
  - Downloading dompdf/php-svg-lib (1.0.2)
  - Downloading dompdf/php-font-lib (1.0.2)
  - Downloading dompdf/dompdf (v3.1.6)
  - Downloading barryvdh/laravel-dompdf (v3.1.2)
  - Installing masterminds/html5 (2.12.0): Extracting archive
  - Installing sabberworm/php-css-parser (v9.5.0): Extracting archive
  - Installing dompdf/php-svg-lib (1.0.2): Extracting archive
  - Installing dompdf/php-font-lib (1.0.2): Extracting archive
  - Installing dompdf/dompdf (v3.1.6): Extracting archive
  - Installing barryvdh/laravel-dompdf (v3.1.2): Extracting archive
2 package suggestions were added by new dependencies, use `composer suggest` to see details.
Generating optimized autoload files
> Illuminate\Foundation\ComposerScripts::postAutoloadDump
> @php artisan package:discover --ansi

   INFO  Discovering packages.  

  barryvdh/laravel-dompdf .......................................................................................................... DONE
  laravel/boost .................................................................................................................... DONE
  laravel/mcp ...................................................................................................................... DONE
  laravel/pail ..................................................................................................................... DONE
  laravel/pao ...................................................................................................................... DONE
  laravel/roster ................................................................................................................... DONE
  laravel/tinker ................................................................................................................... DONE
  nesbot/carbon .................................................................................................................... DONE
  nunomaduro/collision ............................................................................................................. DONE
  nunomaduro/termwind .............................................................................................................. DONE
  pestphp/pest-plugin-laravel ...................................................................................................... DONE

93 packages you are using are looking for funding.
Use the `composer fund` command to find out more!
> @php artisan vendor:publish --tag=laravel-assets --ansi --force

   INFO  No publishable resources for tag [laravel-assets].  

> @php artisan boost:update --ansi
Boost guidelines and skills updated successfully.
No security vulnerability advisories found.
Using version ^8.3 for guzzlehttp/guzzle
Using version ^3.1 for barryvdh/laravel-dompdf

(venv) C:\laragon\www\sumateramall-app\scraper>python -c "import json; data = json.load(open('malls_sumatera.json')); print(type(data[0])); print(data[0])"
<class 'dict'>
{'name': 'Mal Aceh', 'city': 'Banda Aceh', 'province': 'Aceh', 'description': '', 'source': 'hardcoded', 'id': 'mal_aceh'}

(venv) C:\laragon\www\sumateramall-app\scraper>python to_rdf.py
Konversi JSON ke RDF...
Malls loaded: 39 items
Sample mall: {'name': 'Mal Aceh', 'city': 'Banda Aceh', 'province': 'Aceh', 'description': '', 'source': 'hardcoded', 'id': 'mal_aceh'}
Stores loaded: 80 items

Building ontology...
Adding provinces and cities...
Traceback (most recent call last):
  File "C:\laragon\www\sumateramall-app\scraper\to_rdf.py", line 225, in <module>
    main()
    ~~~~^^
  File "C:\laragon\www\sumateramall-app\scraper\to_rdf.py", line 202, in main
    add_provinces_and_cities(g, malls)
    ~~~~~~~~~~~~~~~~~~~~~~~~^^^^^^^^^^
  File "C:\laragon\www\sumateramall-app\scraper\to_rdf.py", line 72, in add_provinces_and_cities
    prov_uri = RES.Province[slugify(prov)]
               ~~~~~~~~~~~~^^^^^^^^^^^^^^^
TypeError: string indices must be integers, not 'str'

(venv) C:\laragon\www\sumateramall-app\scraper>                            