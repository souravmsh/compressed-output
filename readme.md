# Laravel Compressed Output Package

The `souravmsh/compressed-output` package minifies HTML, CSS, and JavaScript in Laravel applications to improve performance by reducing page load times.

## Features
- Minifies inline HTML, CSS, and JavaScript.
- Protects Blade directives, raw Blade content, and conditional comments.
- Preserves jQuery patterns and sensitive JS (e.g., regex/template literals).
- Configurable via `compressed-output.php`. 

## Installation

1. **Install via Composer**

   **Published Repository:**
   ```bash
   composer require souravmsh/laravel-widget
   ```

   **Local Development:**
   Add to `composer.json`:
   ```json
   "repositories": [
       {
           "type": "path",
           "url": "packages/souravmsh/laravel-widget"
       }
   ]
   ```
   Then:
   ```bash
   composer require souravmsh/laravel-widget:dev-main
   ```

2. **Publish Configuration**

   ```bash
   php artisan vendor:publish --tag=compressed-output-config
   ```

   Enable in `.env`:
   ```env
   COMPRESSED_OUTPUT_ENABLE=true
   ```

3. **Set Storage Permissions**

   ```bash
   chmod -R 775 storage
   php artisan storage:link
   ```

## Configuration

Edit `config/compressed-output.php`:
```php
<?php
return [
    'enable' => env('COMPRESSED_OUTPUT_ENABLE', false),
];
```

Set `COMPRESSED_OUTPUT_ENABLE=true` in `.env` to enable the middleware.

## How It Works
The `CompressedOutputMiddleware` processes `text/html` responses:
- **Protects**: Blade directives (`@...`, `{!! !!}`, `{{ }}`), inline CSS/JS, and conditional comments.
- **Minifies**:
  - **HTML**: Removes whitespace, non-conditional comments.
  - **CSS**: Removes comments, minimizes spaces, fixes values (e.g., `32pxauto` → `32px auto`).
  - **JS**: Removes comments, minimizes spaces, preserves jQuery patterns. Skips `application/json` or `text/template` scripts.
- **Headers**: Sets `Content-Length` and `X-Minified: true`.
- **Logs**: Tracks initial/minified content and skipped scripts.

## Example
**Original:**
```html
<script>
  $(document).ready(function() {
    $('.my-class').on('click', function() { alert('Clicked!'); });
  });
</script>
<style>
  .my-class { color: red; /* Comment */ margin: 10px auto; }
</style>
<div>  <p>  Hello, World!  </p>  </div>
```

**Minified:**
```html
<script>$(document).ready(function(){$('.my-class').on('click',function(){alert('Clicked!')})});</script>
<style>.my-class{color:red;margin:10px auto}</style>
<div><p>Hello, World!</p></div>
``` 

Verify `<script>` tags don’t use `type="application/json"` or `type="text/template"`.

## Limitations
- Regex-based minification may miss complex JS edge cases.
- Only processes inline content, not external files.
- Slight performance overhead; test in production.
  
## Contributing
Submit issues/pull requests to [GitHub](https://github.com/souravmsh/laravel-widget).

## License
[MIT License](https://opensource.org/licenses/MIT).
  
## Support
Open issues on [GitHub](https://github.com/souravmsh/laravel-widget) or contact the author.


