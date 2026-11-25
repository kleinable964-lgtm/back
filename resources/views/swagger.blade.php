<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRM API — Swagger</title>
    <link rel="stylesheet" href="{{ route('swagger.asset', ['asset' => 'swagger-ui.css'], false) }}">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="{{ route('swagger.asset', ['asset' => 'swagger-ui-bundle.js'], false) }}"></script>
    <script src="{{ route('swagger.asset', ['asset' => 'swagger-ui-standalone-preset.js'], false) }}"></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: @json(route('api.openapi', [], false)),
            dom_id: '#swagger-ui',
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
            layout: 'StandaloneLayout',
            deepLinking: true,
            filter: true,
            displayRequestDuration: true,
            persistAuthorization: false,
            validatorUrl: null,
            requestInterceptor: (request) => {
                request.headers.Accept = 'application/json';
                return request;
            }
        });
    </script>
</body>
</html>
